/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi, { ParsedFeed } from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Feed File Output', () => {
  test('custom filename should be reflected in feed URL', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    await api.setMagentoConfig(baseURL, {
      'tradetracker/general/enable': 1,
      'tradetracker/feed/enable': 1,
      'tradetracker/feed/filename': 'custom-feed.xml',
    });

    api.generateFeed(1);

    const xml = await api.fetchFeed(baseURL, 1, 'custom-feed');
    expect(xml).toContain('<productFeed');

    // Restore default filename
    await api.setMagentoConfig(baseURL, {
      'tradetracker/feed/filename': 'tradetracker.xml',
    });
  });
});

test.describe('Feed File - default config', () => {
  let xml: string;
  let feed: ParsedFeed;
  let items: any[];

  test.beforeAll(async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const result = await api.runFeedCycle(baseURL);
    xml = result.xml;
    feed = result.feed;
    items = result.items;
  });

  test('feed should be valid XML with correct encoding declaration', () => {
    expect(xml).toMatch(/^<\?xml version="1\.0" encoding="utf-8"\?>/i);
  });

  test('feed should contain productFeed root element', () => {
    expect(feed.productFeed).toBeTruthy();
  });

  test('feed should contain at least one item', () => {
    expect(items.length).toBeGreaterThanOrEqual(1);
  });

  test('every item should have required fields', () => {
    for (const item of items) {
      expect(item['ID'], 'Item should have ID').toBeTruthy();
      expect(item['name'], 'Item should have name').toBeTruthy();
      expect(item['price'], 'Item should have price').toBeTruthy();
    }
  });

  test('every item price should be a valid number', () => {
    for (const item of items) {
      const price = parseFloat(item['price']);
      expect(price, `Item ${item['ID']} should have numeric price`).toBeGreaterThan(0);
    }
  });
});
