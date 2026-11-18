/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { adminUrl } from 'Helpers/AdminUrl';
import { MAGENTO_ERROR } from 'Helpers/MagentoErrors';
import { SECTIONS } from 'Fixtures/baseline';

/**
 * Every configuration section of the module opens at every scope. The baseline saves and reads the
 * sections at Default Config only; the store view scope has its own code paths (the request hands
 * the store over as a string) and the DirectLinking comment died on one of them with a TypeError,
 * which took every [store view] field of the General section with it.
 */
test.describe('configuration sections at every scope', () => {
  const scopes: Array<{ label: string; path: string }> = [
    { label: 'website 1', path: 'website/1/' },
    { label: 'store view 1', path: 'store/1/' },
  ];

  for (const section of SECTIONS) {
    for (const scope of scopes) {
      test(`${section} opens at ${scope.label} without an exception`, async ({ page }) => {
        const response = await page.request.get(adminUrl(`/admin/admin/system_config/edit/section/${section}/${scope.path}`), { maxRedirects: 0 });
        const body = await response.text();

        expect(response.status(), `${section} at ${scope.label}: ${body.slice(0, 300)}`).toBe(200);
        expect(body, `${section} at ${scope.label}`).not.toMatch(MAGENTO_ERROR);
        expect(body, `${section} at ${scope.label} renders the section`).toContain('id="config-edit-form"');
      });
    }
  }
});
