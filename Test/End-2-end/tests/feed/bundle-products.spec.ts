/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Bundle Products', () => {
  test('bundle=parent should include bundle products as parent items', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/bundle': 'parent',
    });

    expect(items.length).toBeGreaterThan(0);
  });

  test('bundle link setting should produce valid feeds', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items: parentLink } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/bundle': 'parent',
      'tradetracker/feed/bundle_link': '0',
    });

    const { items: childLink } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/bundle': 'parent',
      'tradetracker/feed/bundle_link': '1',
    });

    expect(parentLink.length).toBeGreaterThan(0);
    expect(childLink.length).toBeGreaterThan(0);
  });

  test('bundle image setting should produce valid feeds', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items: parentImage } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/bundle': 'parent',
      'tradetracker/feed/bundle_image': '0',
    });

    const { items: childImage } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/bundle': 'parent',
      'tradetracker/feed/bundle_image': '1',
    });

    expect(parentImage.length).toBeGreaterThan(0);
    expect(childImage.length).toBeGreaterThan(0);
  });
});
