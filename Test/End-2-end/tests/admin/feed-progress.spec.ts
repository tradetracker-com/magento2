/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect, Page } from '@playwright/test';
import ConfigPage from 'Pages/backend/ConfigPage';
import TradeTrackerApi from 'Services/TradeTrackerApi';

const SECTION = 'tradetracker_feed';

const api = new TradeTrackerApi();

/**
 * Generating a feed from the configuration page used to navigate to the Generate action, so the
 * merchant stared at a spinner for as long as the catalogue took. The run now happens over AJAX
 * while the row polls the progress endpoint: the page stays, the bar fills, and the result lands
 * in a page message after the reload.
 */
test.describe('Feed generation from the configuration page', () => {
  test.beforeAll(async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    api.resetTradeTrackerConfig();
    await api.setMagentoConfig(baseURL, {
      'tradetracker/general/enable': 1,
      'tradetracker/feed/enable': 1,
      'tradetracker/feed/filename': 'tradetracker.xml',
    });
  });

  test('generating keeps the page and reports the run in the row', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    const config = new ConfigPage(page);
    await config.open(SECTION);
    await config.expandAllGroups();

    const url = page.url();
    const row = await startGenerate(page);

    // The row reports the result itself and holds it for a moment; the reload with the page
    // message follows. Without credentials the module refuses the run with an error message
    // instead, which the row then shows in place.
    const progress = row.locator('[data-role="tt-feed-progress"]');
    await expect(
      progress.or(page.locator('.message-error').first()),
      'the row reports the run or the page reports why it did not start',
    ).toBeVisible({ timeout: 180000 });

    expect(page.url(), 'the page did not navigate').toBe(url);
    expect(errors, 'JS errors while generating').toEqual([]);
  });

  test('the progress endpoint answers for the store it is asked about', async ({ page }) => {
    const config = new ConfigPage(page);
    await config.open(SECTION);
    await config.expandAllGroups();

    const row = page.locator('[data-role="tt-feed-row"]').first();
    const progressUrl = await row.getAttribute('data-progress-url');
    expect(progressUrl, 'the row carries its progress url').toBeTruthy();

    const state = await page.evaluate(async (endpoint) => {
      const formKey = (document.querySelector('input[name="form_key"]') as HTMLInputElement)?.value ?? '';
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `form_key=${encodeURIComponent(formKey)}`,
      });
      return response.json();
    }, progressUrl as string);

    expect(state.success).toBe(true);
    expect(state.running, 'no run is in flight').toBe(false);
  });

  test('a CLI run leaves a finished record the endpoint reports', async ({ page }) => {
    api.generateFeed(1);

    const config = new ConfigPage(page);
    await config.open(SECTION);
    await config.expandAllGroups();

    const row = page.locator('[data-role="tt-feed-row"]').first();
    const progressUrl = await row.getAttribute('data-progress-url');

    const state = await page.evaluate(async (endpoint) => {
      const formKey = (document.querySelector('input[name="form_key"]') as HTMLInputElement)?.value ?? '';
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `form_key=${encodeURIComponent(formKey)}`,
      });
      return response.json();
    }, progressUrl as string);

    expect(state.running).toBe(false);
    expect(state.done, 'the CLI run recorded itself').toBe(true);
    // The total is the number of ids the filters passed; rows the collector drops (excluded,
    // no price, out of stock) are counted out of the written number, never into it.
    expect(state.total, 'the run knew its total').toBeGreaterThan(0);
    expect(state.written, 'the run wrote products').toBeGreaterThan(0);
    expect(state.written, 'no more written than selected').toBeLessThanOrEqual(state.total);
    expect(state.message).toContain('Products in feed');
  });
});

/**
 * Clicks Generate on the first store row.
 */
async function startGenerate(page: Page) {
  const row = page.locator('[data-role="tt-feed-row"]').first();
  const generate = row.locator('a.generate[data-ajax-url]');
  await expect(generate, 'the row offers Generate over AJAX').toBeVisible();
  await generate.click();
  return row;
}
