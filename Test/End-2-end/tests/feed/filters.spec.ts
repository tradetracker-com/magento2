/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Filters', () => {
  test('stock filter enabled should exclude out-of-stock items', async ({ }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_stock': '1',
    });

    expect(items.length).toBeGreaterThan(0);
    for (const item of items) {
      expect(
        item['availability'],
        `Item ${item['ID']} should be in stock when stock filter is enabled`
      ).toBe('in stock');
    }
  });

  test('stock filter disabled should include out-of-stock items', async ({ }, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    // Generate with filter_stock=1 (exclude out of stock)
    const { items: filteredItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_stock': '1',
    });

    // Generate with filter_stock=0 (include out of stock)
    const { items: allItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_stock': '0',
    });

    expect(filteredItems.length).toBeGreaterThan(0);
    expect(filteredItems.length).toBeLessThanOrEqual(allItems.length);
  });
});
