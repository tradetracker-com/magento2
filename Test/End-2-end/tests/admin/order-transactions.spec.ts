/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect, Page } from '@playwright/test';
import { adminUrl } from 'Helpers/AdminUrl';
import TradeTrackerApi, { TestOrder } from 'Services/TradeTrackerApi';

const api = new TradeTrackerApi();

/** The tab link on the order page; the li and the a share the id. */
const TAB_LINK = 'a#sales_order_view_tabs_tradetracker_order_log';
/**
 * The tab's content once loaded. jQuery UI puts a remote tab's answer in a panel it creates itself,
 * not in the empty `_content` div the tabs template renders, so the content is found by its own class.
 */
const TAB_CONTENT = '.tradetracker-transaction';
const TAB_ENDPOINT = /\/tradetracker\/transaction\/tab\//;

/** What the tab endpoint says with credentials TradeTracker does not know, or with no TradeTracker at all. */
const TAB_OUTCOME = /Failed to authenticate|No answer from|HTTP \d+ without a SOAP document|Could not fetch the TradeTracker WSDL/;

/**
 * The order page used to fetch the order's transaction from TradeTracker while it loaded, for
 * every admin, with no timeout: a slow ws.tradetracker.com meant a 60-second order page. The tab
 * now loads its content only when it is opened, within the configured timeout, and can be switched
 * off. Cancelling an order or saving a credit memo queues the assessment instead of sending it.
 */
test.describe('TradeTracker Conversion on the order page', () => {
  let baseURL: string;
  const placed: TestOrder[] = [];

  test.beforeAll(async ({}, testInfo) => {
    baseURL = testInfo.project.use.baseURL!;
    // The feed specs toggle product status with direct writes; bring the stock index in line with
    // the catalogue as it stands before an order is placed against it.
    api.runCli('indexer:reindex cataloginventory_stock');
    api.resetTradeTrackerConfig();
    await configure({});
  });

  test.afterAll(async () => {
    api.deleteQueueRows(placed.map((order) => order.orderId));
    api.resetTradeTrackerConfig();
    api.flushAllCaches();
  });

  async function configure(overrides: Record<string, string | number>): Promise<void> {
    await api.setMagentoConfig(baseURL, {
      'tradetracker/general/enable': 1,
      'tradetracker/general/customer_id': 999999,
      'tradetracker/general/passphrase': 'e2e-not-a-passphrase',
      'tradetracker/pixel/enable': 1,
      'tradetracker/pixel/campaign_id': 1,
      'tradetracker/pixel/order_tab': 1,
      'tradetracker/advanced/api_timeout': 3,
      ...overrides,
    });
    api.flushAllCaches();
  }

  async function placeOrder(qty = 1): Promise<TestOrder> {
    const order = await api.createOrder(baseURL, qty);
    placed.push(order);
    return order;
  }

  async function openOrder(page: Page, order: TestOrder): Promise<void> {
    await page.goto(adminUrl(`/admin/sales/order/view/order_id/${order.orderId}/`));
    await expect(page.locator('#sales_order_view_tabs')).toBeVisible();
  }

  test('the order page shows the tab without asking TradeTracker, and fills it when it is opened', async ({ page }) => {
    const order = await placeOrder();
    const tabRequests: string[] = [];
    page.on('request', (request) => {
      if (TAB_ENDPOINT.test(request.url())) tabRequests.push(request.url());
    });

    await openOrder(page, order);

    const tab = page.locator(TAB_LINK);
    await expect(tab, 'the tab is listed').toBeVisible();
    expect(tabRequests, 'the order page did not load the tab content').toEqual([]);
    await expect(page.locator(TAB_CONTENT), 'no content until the tab is opened').toHaveCount(0);

    const exceptionsBefore = api.countLogLines('exception.log');
    const started = Date.now();
    const [response] = await Promise.all([
      page.waitForResponse((r) => TAB_ENDPOINT.test(r.url())),
      tab.click(),
    ]);
    const elapsed = Date.now() - started;

    expect(response.status(), 'the tab endpoint answers').toBe(200);
    expect(tabRequests, 'the tab content was requested once, on the click').toHaveLength(1);
    // With the API timeout at 3 seconds the answer is a refusal or a timeout, never a 60-second wait.
    expect(elapsed, `the tab answered in ${elapsed} ms`).toBeLessThan(15_000);
    // A block that throws renders nothing and logs nowhere but exception.log; say what came back.
    const body = await response.text();
    const exceptions = api.readLogLinesSince('exception.log', exceptionsBefore).join('\n');
    expect(body, `the tab endpoint rendered the panel\nbody: ${body.slice(0, 600)}\nexception.log: ${exceptions.slice(0, 1500)}`)
      .toContain('tradetracker-transaction');
    const content = page.locator(TAB_CONTENT);
    await expect(content).toBeVisible();
    await expect(content).toContainText('TradeTracker Conversion');
    await expect(content.locator('.tradetracker-transaction-message')).toHaveText(TAB_OUTCOME);
  });

  test('the tab stays off the order page when the setting is off', async ({ page }) => {
    const order = await placeOrder();
    await configure({ 'tradetracker/pixel/order_tab': 0 });

    await openOrder(page, order);

    await expect(page.locator(TAB_LINK)).toHaveCount(0);
    await configure({});
  });

  test('the tab stays off the order page when the pixel is off', async ({ page }) => {
    const order = await placeOrder();
    await configure({ 'tradetracker/pixel/enable': 0 });

    await openOrder(page, order);

    await expect(page.locator(TAB_LINK)).toHaveCount(0);
    await configure({});
  });

  test('cancelling an order queues one rejection and sends nothing', async ({ page }) => {
    const order = await placeOrder();
    await openOrder(page, order);

    await page.locator('#order-view-cancel-button').click();
    await page.locator('.modal-popup.confirm._show button.action-accept').click();
    await expect(page.locator('.message-success')).toContainText('canceled');

    let rows = api.queueRows(order.orderId);
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({
      incrementId: order.incrementId,
      action: 'rejected',
      reason: 'order_canceled',
      amount: null,
      status: 'pending',
      attempts: 0,
      message: null,
    });

    // Every later save of the cancelled order fires the same observer; the row stays one row.
    await api.commentOrder(baseURL, order.orderId, 'E2E: a save after the cancellation');
    rows = api.queueRows(order.orderId);
    expect(rows, 'a second save adds no second row').toHaveLength(1);
  });

  test('a partial credit memo queues an acceptance for the amount that stands', async ({}) => {
    const order = await placeOrder(2);

    await api.refundOrder(baseURL, order, 1, 0);

    const rows = api.queueRows(order.orderId);
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({ action: 'accepted', reason: null, status: 'pending' });
    const refunded = order.grandTotal - Number(rows[0].amount);
    expect(refunded, 'the amount that stands is the order total minus one item').toBeGreaterThan(0);
    expect(Number(rows[0].amount)).toBeLessThan(order.grandTotal);
  });

  test('a full credit memo queues a rejection', async ({}) => {
    const order = await placeOrder(1);
    const shipping = Number((await api.rest<any>(baseURL, 'GET', `rest/all/V1/orders/${order.orderId}`)).shipping_amount);

    await api.refundOrder(baseURL, order, 1, shipping);

    const rows = api.queueRows(order.orderId);
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({ action: 'rejected', reason: 'order_canceled', amount: null, status: 'pending' });
  });

  test('the queue command sends the rows within the timeout and keeps a refused row for a retry', async ({}) => {
    // Refused credentials end a run at the first row, so the rows the earlier tests queued would
    // stand in front of this one; the run has to meet this order's row first.
    api.deleteQueueRows(placed.map((placedOrder) => placedOrder.orderId));
    const order = await placeOrder();
    await api.refundOrder(baseURL, order, 1, 0);
    expect(api.queueRows(order.orderId)).toHaveLength(1);

    const started = Date.now();
    const run = api.runCliRaw('tradetracker:transaction:process-queue');
    const elapsed = Date.now() - started;

    expect(run.exitCode, run.output).toBe(0);
    expect(run.output).toMatch(/^Processed \d+: \d+ done, \d+ refused, \d+ failed for good, \d+ postponed\./m);
    // A refusal from TradeTracker or no TradeTracker at all: either way the run is over within a
    // few timeouts, not within a few minutes.
    expect(elapsed, `the run took ${elapsed} ms`).toBeLessThan(60_000);

    const [row] = api.queueRows(order.orderId);
    expect(row.status, 'the row waits for the next run').toBe('pending');
    expect(row.message ?? '').toMatch(TAB_OUTCOME);
    // Refused credentials and no answer at all leave the attempts alone; only TradeTracker's own
    // refusal of the assessment counts one.
    expect(row.attempts, 'a refusal counts, a login failure or an outage does not').toBeLessThanOrEqual(1);
  });

  test('with the module off a cancellation queues nothing', async ({ page }) => {
    const order = await placeOrder();
    await configure({ 'tradetracker/general/enable': 0 });

    await openOrder(page, order);
    await page.locator('#order-view-cancel-button').click();
    await page.locator('.modal-popup.confirm._show button.action-accept').click();
    await expect(page.locator('.message-success')).toContainText('canceled');

    expect(api.queueRows(order.orderId)).toEqual([]);
    await configure({});
  });
});
