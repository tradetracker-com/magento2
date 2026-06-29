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

export default class TradeTrackerApi extends BaseApi {
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
}
