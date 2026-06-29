/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

let xml: string;
let feed: any;
let items: any[];

test.beforeAll(async ({}, testInfo) => {
  const baseURL = testInfo.project.use.baseURL!;
  const result = await api.runFeedCycle(baseURL);
  xml = result.xml;
  feed = result.feed;
  items = result.items;
});

test.describe('Feed Structure', () => {
  test('should produce valid parseable XML', () => {
    expect(feed).toBeTruthy();
    expect(feed.productFeed).toBeTruthy();
  });

  test('should have productFeed root element with items', () => {
    expect(feed.productFeed.item).toBeTruthy();
  });

  test('should contain at least one item', () => {
    expect(items.length).toBeGreaterThanOrEqual(1);
  });

  test('every item should have an ID field', () => {
    for (const item of items) {
      expect(item['ID'], 'Every item must have an ID').toBeTruthy();
    }
  });

  test('every item should have a name field', () => {
    for (const item of items) {
      expect(item['name'], `Item ${item['ID']} must have a name`).toBeTruthy();
    }
  });

  test('most items should have a productURL field', () => {
    const withUrl = items.filter(i => i['productURL']);
    // Some configurable children (NVI) may not have their own URL
    expect(withUrl.length, 'Most items should have a productURL').toBeGreaterThan(items.length * 0.5);
  });

  test('every item should have a valid price', () => {
    for (const item of items) {
      const price = parseFloat(item['price']);
      expect(price, `Item ${item['ID']} should have numeric price > 0`).toBeGreaterThan(0);
    }
  });

  test('every item should have availability field', () => {
    const validValues = ['in stock', 'out of stock', 'preorder'];
    for (const item of items) {
      expect(
        validValues,
        `Item ${item['ID']} availability "${item['availability']}" should be valid`
      ).toContain(item['availability']);
    }
  });

  test('item IDs should be unique', () => {
    const ids = items.map(i => i['ID']);
    const uniqueIds = new Set(ids);
    expect(uniqueIds.size, 'All item IDs should be unique').toBe(ids.length);
  });
});
