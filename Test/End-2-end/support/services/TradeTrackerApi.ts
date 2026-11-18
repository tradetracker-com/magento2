/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';
import { XMLParser } from 'fast-xml-parser';
import BaseApi from './BaseApi';

/** Default config for a working TradeTracker feed */
const DEFAULT_FEED_CONFIG: Record<string, string | number> = {
  'tradetracker/general/enable': 1,
  'tradetracker/feed/enable': 1,
  'tradetracker/feed/filename': 'tradetracker.xml',
};

export interface ParsedFeed {
  productFeed: {
    item: any | any[];
  };
}

/** An order placed by the suite through the REST checkout. */
export interface TestOrder {
  orderId: number;
  incrementId: string;
  grandTotal: number;
  /** order item ids, one per line */
  itemIds: number[];
}

/** One row of tradetracker_transaction_queue, as the admin or the cron would read it. */
export interface QueueRow {
  entityId: number;
  orderId: number;
  incrementId: string;
  storeId: number;
  action: string;
  reason: string | null;
  amount: string | null;
  status: string;
  attempts: number;
  message: string | null;
}


const ADDRESS = {
  firstname: 'E2E',
  lastname: 'TradeTracker',
  street: ['Teststraat 1'],
  city: 'Austin',
  region: 'Texas',
  region_id: 57,
  region_code: 'TX',
  country_id: 'US',
  postcode: '78701',
  telephone: '0612345678',
};

export default class TradeTrackerApi extends BaseApi {
  /** The configuration path prefix of this module, for the generic layer. */
  protected modulePrefix(): string {
    return 'tradetracker';
  }

  /**
   * Generate the TradeTracker feed via CLI.
   */
  generateFeed(storeId: number = 1): void {
    if (!this.container) {
      throw new Error('MAGENTO_CONTAINER env var is required for feed generation');
    }

    const root = this.getMagentoRoot();
    const cmd = `docker exec ${this.container} php ${root}/bin/magento tradetracker:feed:create --store-id=${storeId}`;
    console.log(`Generating feed: ${cmd}`);
    execSync(cmd, { stdio: 'pipe', timeout: 120000 });
    console.log('Feed generated.');
  }

  /**
   * Fetch the XML feed via HTTP.
   * TradeTracker uses plain XML format: media/tradetracker/tradetracker-{storeId}.xml
   */
  async fetchFeed(baseURL: string, storeId: number = 1, filename: string = 'tradetracker'): Promise<string> {
    const url = `${baseURL}media/tradetracker/${filename}-${storeId}.xml`;
    console.log(`Fetching feed: ${url}`);

    const response = await fetch(url, {
      headers: { 'Accept': 'application/xml' },
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch feed: ${response.status} ${response.statusText}`);
    }

    return response.text();
  }

  /**
   * Parse XML feed string into a typed object.
   * TradeTracker format: <products><item>...</item></products> (no namespace prefixes)
   */
  parseFeed(xml: string): ParsedFeed {
    const parser = new XMLParser({
      ignoreAttributes: false,
      attributeNamePrefix: '@_',
      parseTagValue: false,
      trimValues: true,
      processEntities: false,
    });

    return parser.parse(xml);
  }

  /**
   * Get items from a parsed feed, normalized to always be an array.
   */
  getItems(parsed: ParsedFeed): any[] {
    const items = parsed?.productFeed?.item;
    if (!items) {
      return [];
    }
    return Array.isArray(items) ? items : [items];
  }

  /**
   * Delete all tradetracker config overrides from core_config_data,
   * reverting to config.xml defaults.
   */
  resetTradeTrackerConfig(): void {
    this.resetConfig('tradetracker/%');
    console.log('Config reset to config.xml defaults');
  }

  /**
   * Full feed cycle: reset ALL config -> apply overrides -> generate -> fetch -> parse -> return items.
   * Each cycle starts from a clean config.xml baseline to prevent test pollution.
   */
  async runFeedCycle(
    baseURL: string,
    configs: Record<string, string | number> = {},
    storeId: number = 1
  ): Promise<{ feed: ParsedFeed; items: any[]; xml: string }> {
    this.resetTradeTrackerConfig();

    const fullConfig = { ...DEFAULT_FEED_CONFIG, ...configs };
    await this.setMagentoConfig(baseURL, fullConfig);

    this.generateFeed(storeId);
    const xml = await this.fetchFeed(baseURL, storeId);
    const feed = this.parseFeed(xml);
    const items = this.getItems(feed);

    return { feed, items, xml };
  }

  // ---------------------------------------------------------------------------------------------
  // Orders and the transaction queue
  // ---------------------------------------------------------------------------------------------

  /**
   * Place an order through the REST guest checkout (check/money order, flat rate) and return it.
   * The order is what the cancel and credit memo observers react to; nothing TradeTracker-specific
   * happens while it is placed.
   */
  async createOrder(baseURL: string, qty = 1): Promise<TestOrder> {
    const email = `e2e-tradetracker-${Date.now()}@example.com`;
    const sku = this.salableSimpleSku();
    // The default store view, not "all": the cart checks that the product is assigned to the
    // website of the store the request is made for, and "all" has no website.
    const cartId = await this.rest<string>(baseURL, 'POST', 'rest/default/V1/guest-carts');
    const cartPath = `rest/default/V1/guest-carts/${cartId}`;
    await this.rest(baseURL, 'POST', `${cartPath}/items`, { cartItem: { sku, qty, quote_id: cartId } });
    await this.rest(baseURL, 'POST', `${cartPath}/shipping-information`, {
      addressInformation: {
        shipping_address: { ...ADDRESS, email },
        billing_address: { ...ADDRESS, email },
        shipping_method_code: 'flatrate',
        shipping_carrier_code: 'flatrate',
      },
    });
    const orderId = Number(await this.rest(baseURL, 'POST', `${cartPath}/payment-information`, {
      email,
      paymentMethod: { method: 'checkmo' },
    }));

    const order = await this.rest<any>(baseURL, 'GET', `rest/all/V1/orders/${orderId}`);

    return {
      orderId,
      incrementId: String(order.increment_id),
      grandTotal: Number(order.grand_total),
      itemIds: (order.items as any[]).map((item) => Number(item.item_id)),
    };
  }

  /**
   * Refund part or all of an order offline through REST, which saves a credit memo and fires
   * sales_order_creditmemo_save_after exactly as the admin's Credit Memo button does.
   */
  async refundOrder(baseURL: string, order: TestOrder, qtyPerItem: number, shippingAmount: number): Promise<number> {
    // A credit memo refunds what was invoiced; a check/money order is not invoiced until someone does.
    const current = await this.rest<any>(baseURL, 'GET', `rest/all/V1/orders/${order.orderId}`);
    if (!Number(current.total_invoiced)) {
      await this.rest(baseURL, 'POST', `rest/all/V1/order/${order.orderId}/invoice`, { capture: false, notify: false });
    }

    return Number(await this.rest(baseURL, 'POST', `rest/all/V1/order/${order.orderId}/refund`, {
      items: order.itemIds.map((orderItemId) => ({ order_item_id: orderItemId, qty: qtyPerItem })),
      notify: false,
      arguments: { shipping_amount: shippingAmount, adjustment_positive: 0, adjustment_negative: 0 },
    }));
  }

  /** Add an order comment through REST: a plain order save, which fires sales_order_save_after. */
  async commentOrder(baseURL: string, orderId: number, comment: string): Promise<void> {
    await this.rest(baseURL, 'POST', `rest/all/V1/orders/${orderId}/comments`, {
      statusHistory: { comment, is_customer_notified: 0, is_visible_on_front: 0 },
    });
  }

  /**
   * A simple product the guest cart accepts right now: enabled, assigned to the main website and
   * in stock according to the stock index.
   *
   * Not a fixed SKU: the feed specs disable and re-enable the first product of the feed through
   * direct attribute writes, and a stock reindex in between leaves that product unsalable until
   * the next one. Picking from the index as it stands sidesteps whatever the earlier specs did.
   */
  salableSimpleSku(): string {
    const sku = this.requireDb().query(`
      $stmt = $pdo->query("
        SELECT e.sku
        FROM catalog_product_entity e
        JOIN catalog_product_website w ON w.product_id = e.entity_id AND w.website_id = 1
        JOIN cataloginventory_stock_status s ON s.product_id = e.entity_id AND s.stock_status = 1 AND s.qty > 10
        JOIN catalog_product_entity_int st ON st.entity_id = e.entity_id AND st.store_id = 0 AND st.value = 1
          AND st.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        WHERE e.type_id = 'simple' AND e.sku LIKE '24-%' AND e.sku NOT LIKE '%-%-%'
        ORDER BY e.entity_id ASC LIMIT 1
      ");
      echo (string)$stmt->fetchColumn();
    `).trim();
    if (!sku) {
      throw new Error('No enabled, in-stock simple product on website 1 to place an order with');
    }

    return sku;
  }

  /** The queue rows of one order, oldest first. */
  queueRows(orderId: number): QueueRow[] {
    const out = this.requireDb().query(`
      $stmt = $pdo->prepare("SELECT entity_id, order_id, increment_id, store_id, action, reason, amount, status, attempts, message FROM tradetracker_transaction_queue WHERE order_id = ? ORDER BY entity_id ASC");
      $stmt->execute([${orderId}]);
      echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    `).trim();
    const start = out.indexOf('[');
    const rows = start >= 0 ? (JSON.parse(out.slice(start)) as Record<string, string | null>[]) : [];

    return rows.map((row) => ({
      entityId: Number(row.entity_id),
      orderId: Number(row.order_id),
      incrementId: String(row.increment_id),
      storeId: Number(row.store_id),
      action: String(row.action),
      reason: row.reason,
      amount: row.amount,
      status: String(row.status),
      attempts: Number(row.attempts),
      message: row.message,
    }));
  }

  /** Remove the queue rows of the orders a spec placed. */
  deleteQueueRows(orderIds: number[]): void {
    if (orderIds.length === 0) return;
    this.requireDb().query(`
      $pdo->exec("DELETE FROM tradetracker_transaction_queue WHERE order_id IN (${orderIds.map(Number).join(',')})");
      echo 'ok';
    `);
  }

  /** The TradeTracker status column of an order, as the order grid shows it. */
  orderStatusColumn(orderId: number): string | null {
    const out = this.requireDb().query(`
      $stmt = $pdo->prepare("SELECT tt_status FROM sales_order_grid WHERE entity_id = ?");
      $stmt->execute([${orderId}]);
      $value = $stmt->fetchColumn();
      echo $value === false || $value === null ? '__NULL__' : $value;
    `).trim();

    return out === '__NULL__' ? null : out;
  }
}
