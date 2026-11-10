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

  test('a grouped member that is also a bundle component stays with grouped=simple', async ({}, testInfo) => {
    // The yoga straps 24-WG085/086/087 are linked to the grouped 24-WG085_Group and packed into
    // the bundle 24-WG080. "Only Bundle Product" used to drop them whatever the grouped setting said.
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/grouped': 'simple',
      'tradetracker/feed/bundle': 'parent',
    });

    const skus = items.map((item: any) => String(item.sku));
    for (const sku of ['24-WG085', '24-WG086', '24-WG087']) {
      expect(skus, `${sku} is in the feed`).toContain(sku);
    }
    expect(skus, 'the grouped parent is not').not.toContain('24-WG085_Group');
    expect(skus, 'the bundle itself still is').toContain('24-WG080');
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
