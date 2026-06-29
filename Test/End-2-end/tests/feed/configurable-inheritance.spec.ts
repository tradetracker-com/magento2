/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Configurable Inheritance', () => {
  test('configurable_link settings should produce valid feeds', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    // 0 = use parent link
    const { items: parentLink } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
      'tradetracker/feed/configurable_link': '0',
    });

    // 1 = use child link
    const { items: childLink } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
      'tradetracker/feed/configurable_link': '1',
    });

    expect(parentLink.length).toBeGreaterThan(0);
    expect(childLink.length).toBeGreaterThan(0);

    // Find a matching item and verify URLs exist
    const parentItem = parentLink[0];
    const childItem = childLink.find(i => i['ID'] === parentItem?.['ID']);
    if (parentItem && childItem) {
      expect(parentItem['productURL']).toBeTruthy();
      expect(childItem['productURL']).toBeTruthy();
    }
  });

  test('configurable_image settings should produce valid feeds', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    // 0 = use parent image
    const { items: parentImage } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
      'tradetracker/feed/configurable_image': '0',
    });

    // 1 = use child image if available
    const { items: childImage } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
      'tradetracker/feed/configurable_image': '1',
    });

    expect(parentImage.length).toBeGreaterThan(0);
    expect(childImage.length).toBeGreaterThan(0);

    const parentWithImage = parentImage.filter(i => i['imageURL']);
    const childWithImage = childImage.filter(i => i['imageURL']);
    expect(parentWithImage.length, 'At least some parent-image items should have imageURL').toBeGreaterThan(0);
    expect(childWithImage.length, 'At least some child-image items should have imageURL').toBeGreaterThan(0);
  });

  test('configurable_parent_atts should inherit specified attributes from parent', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
      'tradetracker/feed/configurable_parent_atts': 'name',
    });

    expect(items.length).toBeGreaterThan(0);
    // Children should have a name (inherited from parent if child has none)
    const withName = items.filter(i => i['name']);
    expect(withName.length, 'Items inheriting parent name').toBeGreaterThan(0);
  });

  test('configurable_nonvisible should control NVI product inclusion', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    const { items: withNvi } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
      'tradetracker/feed/configurable_nonvisible': '1',
    });

    const { items: withoutNvi } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
      'tradetracker/feed/configurable_nonvisible': '0',
    });

    expect(withNvi.length).toBeGreaterThan(0);
    // Including NVI should produce equal or more items
    expect(withNvi.length).toBeGreaterThanOrEqual(withoutNvi.length);
  });
});
