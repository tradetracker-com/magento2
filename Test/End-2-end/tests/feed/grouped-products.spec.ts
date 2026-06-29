/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Grouped Products', () => {
  test('grouped=parent should include grouped products as parent items', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/grouped': 'parent',
    });

    expect(items.length).toBeGreaterThan(0);
  });

  test('grouped=simple should output child items of grouped products', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/grouped': 'simple',
    });

    expect(items.length).toBeGreaterThan(0);
  });

  test('grouped link setting should produce valid feeds', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items: parentLink } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/grouped': 'parent',
      'tradetracker/feed/grouped_link': '0',
    });

    const { items: childLink } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/grouped': 'parent',
      'tradetracker/feed/grouped_link': '1',
    });

    expect(parentLink.length).toBeGreaterThan(0);
    expect(childLink.length).toBeGreaterThan(0);
  });

  test('grouped image setting should produce valid feeds', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items: parentImage } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/grouped': 'parent',
      'tradetracker/feed/grouped_image': '0',
    });

    const { items: childImage } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/grouped': 'parent',
      'tradetracker/feed/grouped_image': '1',
    });

    expect(parentImage.length).toBeGreaterThan(0);
    expect(childImage.length).toBeGreaterThan(0);
  });
});
