/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

// Advanced filter format:
// {"_1":{"attribute":"price","condition":"gt","value":"10","type":"and"}}
const PRICE_FILTER = JSON.stringify({
  _1: {
    attribute: 'price',
    condition: 'gt',
    value: '10',
    type: 'and',
  },
});

const SKU_FILTER = JSON.stringify({
  _1: {
    attribute: 'sku',
    condition: 'like',
    value: '24-MB%',
    type: 'and',
  },
});

test.describe('Advanced Filters', () => {
  test('price filter should only include products above threshold', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/custom_filters': '1',
      'tradetracker/feed/custom_filters_data': PRICE_FILTER,
    });

    expect(items.length).toBeGreaterThan(0);
    for (const item of items) {
      const price = parseFloat(item['price']);
      expect(price, `Item ${item['ID']} price ${price} should be > 10`).toBeGreaterThan(10);
    }
  });
});

test.describe('Advanced Filters - unfiltered baseline', () => {
  let baselineItems: any[];

  test.beforeAll(async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const result = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/custom_filters': '0',
    });
    baselineItems = result.items;
  });

  test('disabled advanced filters should include all products', () => {
    expect(baselineItems.length).toBeGreaterThan(0);
  });

  test('SKU filter should limit feed to matching products', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    const { items: filtered } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/custom_filters': '1',
      'tradetracker/feed/custom_filters_data': SKU_FILTER,
    });

    expect(filtered.length).toBeGreaterThan(0);
    expect(filtered.length).toBeLessThan(baselineItems.length);
  });
});
