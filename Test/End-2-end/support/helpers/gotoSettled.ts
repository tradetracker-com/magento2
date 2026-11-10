/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { Page } from '@playwright/test';

/**
 * Navigate and wait until the page has stopped making requests.
 *
 * Every test writes config straight into the database and cleans the cache from the CLI. A
 * request that is still running from the previous page can rebuild a cache from what it read
 * before that write and save it after the clean, and the storefront then renders the previous
 * test's config. The GitHub runner hit that at the same tests in every run (Rich Snippets,
 * 2026-10-26/27); the faster VPS never did. Leaving no request open when a test ends removes
 * the browser as a source of such stragglers; cache:clean (lock-guarded for the config type)
 * and the disabled page and module caches in global-setup cover the rest.
 */
export async function gotoSettled(
  page: Page,
  url: string,
  options: Parameters<Page['goto']>[1] = {}
): Promise<void> {
  await page.goto(url, { waitUntil: 'domcontentloaded', ...options });
  await settle(page);
}

/**
 * Wait for the open requests of a page to finish; a page that keeps polling does not block a test.
 */
export async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => undefined);
}
