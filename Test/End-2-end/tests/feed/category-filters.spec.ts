/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Category Filters', () => {
  let unfilteredItems: any[];

  test.beforeAll(async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const result = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_category': '0',
    });
    unfilteredItems = result.items;
  });

  test('category filter disabled should include all categories', () => {
    expect(unfilteredItems.length).toBeGreaterThan(0);
  });

  test('category filter enabled with include type should limit products', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    const gearCategoryId = api.getCategoryIdByName('Gear');
    if (!gearCategoryId) {
      test.skip(true, 'Gear category not found in this environment');
      return;
    }

    const { items: filtered } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_category': '1',
      'tradetracker/feed/filter_type_category': 'in',
      'tradetracker/feed/filter_category_ids': gearCategoryId,
    });

    expect(unfilteredItems.length).toBeGreaterThan(0);
    expect(filtered.length).toBeLessThan(unfilteredItems.length);
  });

  test('category filter with exclude type should produce a valid feed', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    const gearCategoryId = api.getCategoryIdByName('Gear');
    if (!gearCategoryId) {
      test.skip(true, 'Gear category not found in this environment');
      return;
    }

    const { items: filtered } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/filter_category': '1',
      'tradetracker/feed/filter_type_category': 'nin',
      'tradetracker/feed/filter_category_ids': gearCategoryId,
    });

    expect(filtered.length).toBeGreaterThan(0);
  });
});
