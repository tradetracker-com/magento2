/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from '../../support/services/TradeTrackerApi';

const api = new TradeTrackerApi();

test.describe('Disabled Products', () => {
  let testSku: string;
  let testProductId: string;

  test.beforeAll(() => {
    // Pick the first simple product SKU from the sample data.
    // We'll resolve it in the first test that needs it.
    testSku = '';
    testProductId = '';
  });

  test.afterAll(() => {
    // Re-enable the product to leave clean state
    if (testSku) {
      try {
        api.setProductAttribute(testSku, 'status', '1');
      } catch {
        // best-effort cleanup
      }
    }
  });

  test('disabled simple products should not appear in the feed', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    // 1. Generate a baseline feed with all products enabled
    const { items: baselineItems } = await api.runFeedCycle(baseURL);
    expect(baselineItems.length).toBeGreaterThan(0);

    // Pick a simple product from the feed to disable
    const simpleItem = baselineItems.find((i: any) => i['sku']);
    expect(simpleItem, 'Need at least one product with SKU in the feed').toBeTruthy();
    testSku = simpleItem['sku'];
    testProductId = simpleItem['ID'];

    // 2. Disable the product
    api.setProductAttribute(testSku, 'status', '2');

    // 3. Generate the feed again
    const { items: filteredItems } = await api.runFeedCycle(baseURL);

    // 4. The disabled product should NOT be in the feed
    const disabledInFeed = filteredItems.find((i: any) => i['sku'] === testSku);
    expect(
      disabledInFeed,
      `Disabled product ${testSku} should not appear in the feed`
    ).toBeUndefined();

    // 5. Feed should have fewer items
    expect(filteredItems.length).toBeLessThan(baselineItems.length);
  });

  test('disabled configurable parent should not leak into feed (parent mode)', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    // Generate feed in parent mode to get configurable parents
    const { items: baselineItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'parent',
    });
    expect(baselineItems.length).toBeGreaterThan(0);

    // Find a configurable product (they tend to have higher entity IDs or specific SKU patterns)
    // We'll identify one by generating in simple mode and comparing
    const { items: simpleItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
    });

    // Find an SKU that appears in parent mode but not in simple mode (= configurable parent)
    const simpleSkus = new Set(simpleItems.map((i: any) => i['sku']));
    const configurableItem = baselineItems.find((i: any) => !simpleSkus.has(i['sku']));

    if (!configurableItem) {
      test.skip();
      return;
    }

    const configSku = configurableItem['sku'];

    // Disable the configurable parent
    api.setProductAttribute(configSku, 'status', '2');

    // Generate feed in parent mode again
    const { items: filteredItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'parent',
    });

    const disabledInFeed = filteredItems.find((i: any) => i['sku'] === configSku);
    expect(
      disabledInFeed,
      `Disabled configurable parent ${configSku} should not appear in feed`
    ).toBeUndefined();

    // Re-enable
    api.setProductAttribute(configSku, 'status', '1');
  });

  test('disabled configurable parent should not leak into feed (simple mode)', async ({}, testInfo) => {
    const baseURL = testInfo.project.use.baseURL!;

    // In simple mode, configurable parents should never appear, even if loaded for attribute inheritance.
    // Generate baseline
    const { items: baselineItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
    });
    expect(baselineItems.length).toBeGreaterThan(0);

    // Find a configurable parent SKU (not in simple feed)
    const { items: parentItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'parent',
    });

    const simpleSkus = new Set(baselineItems.map((i: any) => i['sku']));
    const configurableItem = parentItems.find((i: any) => !simpleSkus.has(i['sku']));

    if (!configurableItem) {
      test.skip();
      return;
    }

    const configSku = configurableItem['sku'];

    // Disable the configurable parent
    api.setProductAttribute(configSku, 'status', '2');

    // Generate in simple mode — the disabled parent must not leak through
    const { items: filteredItems } = await api.runFeedCycle(baseURL, {
      'tradetracker/feed/configurable': 'simple',
    });

    const disabledInFeed = filteredItems.find((i: any) => i['sku'] === configSku);
    expect(
      disabledInFeed,
      `Disabled configurable parent ${configSku} should not leak into simple mode feed`
    ).toBeUndefined();

    // Children of the disabled parent should still appear (they are enabled)
    expect(filteredItems.length).toBeGreaterThan(0);

    // Re-enable
    api.setProductAttribute(configSku, 'status', '1');
  });
});
