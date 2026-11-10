/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { adminUrl } from 'Helpers/AdminUrl';
import { adminRoutes } from 'Helpers/AdminRoutes';
import { PageErrors } from 'Helpers/PageErrors';
import { MAGENTO_ERROR, OUTPUT_ERROR, expectNoMagentoError } from 'Helpers/MagentoErrors';
import TradeTrackerApi from 'Services/TradeTrackerApi';
import { ADMIN_PAGES, ERROR_LOG, prepareBaseline } from 'Fixtures/baseline';

const api = new TradeTrackerApi();
const routes = adminRoutes();

/**
 * Baseline for every admin route the module has: a bare request (no parameters, no selection, no XHR)
 * is answered with a page or a redirect, never with an exception page or a 500, and nothing lands in
 * the module's error log. The grid pages also load in the browser without a JavaScript error of ours.
 */
test.describe('admin routes', () => {

  test.beforeAll(async () => {
    await prepareBaseline(api);
  });

  test('every controller under Controller/Adminhtml is listed', () => {
    expect(routes.length, 'controllers found under Controller/Adminhtml').toBeGreaterThan(0);
  });

  for (const route of routes) {
    test(`${route.path} answers a bare request without an exception`, async ({ page }) => {
      const baseline = api.countLogLines(ERROR_LOG);
      const response = await page.request.get(adminUrl(route.path), { maxRedirects: 0 });
      // A controller that implements HttpPostActionInterface is refused with Magento's 404 on GET; that is the contract.
      const allowed = route.postOnly ? [404] : [200, 302];
      expect(allowed, `${route.file}: status ${response.status()}\n${(await response.text()).slice(0, 300)}`).toContain(response.status());
      if (response.status() === 302) {
        // Only Magento's own dead end counts: a module whose routes are called
        // /exception_monitor/exception/index redirects to its own name all day.
        expect(response.headers()['location'], `${route.file} redirect target`).not.toMatch(/noroute/i);
      } else {
        const type = response.headers()['content-type'] ?? '';
        if (/html/.test(type)) {
          expect(await response.text(), `${route.file} body`).not.toMatch(MAGENTO_ERROR);
        }
      }
      const logged = api.readLogLinesSince(ERROR_LOG, baseline).filter((l) => /Fatal|Uncaught|must be of type|Call to a member/.test(l));
      expect(logged, `${route.file} wrote a PHP error to error.log`).toEqual([]);
    });
  }

  for (const path of ADMIN_PAGES) {
    test(`${path} renders in the browser without a JavaScript error of ours`, async ({ page }) => {
      const errors = new PageErrors(page);
      await page.goto(adminUrl(path));
      await page.waitForLoadState('networkidle');
      // "element not found" says nothing about why: an access-denied page, a 404 handle and a grid
      // that failed to bootstrap all look the same. Report what the page actually shows.
      await expectNoMagentoError(page, path);
      expect(errors.own, `${path} JavaScript errors`).toEqual([]);
      // The listing is rendered by KnockoutJS after its own data request, which is well after
      // networkidle in an admin that never stops polling; deciding on the first look reads an
      // empty page as a broken one.
      const wrapper = page.locator('[data-role="grid-wrapper"]').first();
      await wrapper.waitFor({ state: 'attached', timeout: 30_000 }).catch(() => undefined);
      if (await wrapper.count() === 0) {
        const heading = (await page.locator('h1, .page-title').first().textContent().catch(() => null)) ?? '(no heading)';
        const body = (await page.locator('body').innerText()).slice(0, 400).replace(/\s+/g, ' ');
        throw new Error(`${path} has no grid wrapper.\nTitle: ${await page.title()}\nHeading: ${heading}\nBody: ${body}`);
      }
      await expect(wrapper).toBeVisible();
    });
  }
});
