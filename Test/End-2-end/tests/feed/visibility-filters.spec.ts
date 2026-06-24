/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Visibility Filters - catalog+search', () => {
  let filteredItems: any[];

  test.beforeAll(async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    // Visibility 4 = Catalog, Search
    const result = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_visbility': '1',
      'tradetracker/feed/filter_visbility_options': '4',
    });
    filteredItems = result.items;
  });

  test('visibility filter enabled with catalog+search should include visible products', () => {
    expect(filteredItems.length).toBeGreaterThan(0);
  });

  test('visibility filter disabled should include more or equal products', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    const { items: unfiltered } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_visbility': '0',
    });

    expect(unfiltered.length).toBeGreaterThanOrEqual(filteredItems.length);
  });
});

test.describe('Visibility Filters - not visible individually', () => {
  test('visibility filter with not-visible-individually should produce a result', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    // Visibility 1 = Not Visible Individually
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_visbility': '1',
      'tradetracker/feed/filter_visbility_options': '1',
    });

    // Should still produce a feed (NVI products exist as configurable children)
    expect(items).toBeDefined();
  });
});
