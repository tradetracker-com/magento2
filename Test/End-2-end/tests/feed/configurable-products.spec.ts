/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Configurable Products', () => {
  test('configurable=simple should output child items', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
    });

    expect(items.length).toBeGreaterThan(0);
  });

  test('configurable=parent should output parent items', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;
    const { items } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'parent',
    });

    expect(items.length).toBeGreaterThan(0);
  });

  test('configurable=both should include more items than parent-only', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    const { items: parentOnly } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'parent',
    });

    const { items: both } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'both',
    });

    expect(parentOnly.length).toBeGreaterThan(0);
    expect(both.length).toBeGreaterThan(0);
    expect(both.length).toBeGreaterThanOrEqual(parentOnly.length);
  });

  test('configurable=simple should produce different count than parent', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    const { items: simpleItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
    });

    const { items: parentItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'parent',
    });

    expect(simpleItems.length).toBeGreaterThan(0);
    expect(parentItems.length).toBeGreaterThan(0);
    // Simple mode outputs children, parent mode outputs parents — counts should differ
    expect(simpleItems.length).not.toBe(parentItems.length);
  });
});
