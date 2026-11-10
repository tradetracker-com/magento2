/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { Page, expect } from '@playwright/test';

/**
 * Run a mass action on the grid the way the admin does, without losing its success message.
 *
 * A UI grid writes every state change - a page render, a ticked row - through
 * `POST mui/bookmark/save`, debounced by two seconds. That request reads the admin session and
 * writes it back as it read it, so when it lands on top of the mass-action POST it takes the
 * `addSuccessMessage()` the controller just added with it: the rows are gone and the page shows no
 * message at all. Proven on digitec-galaxus (GH Actions, 2.4.9): bookmark/save 107524-107743 ms,
 * massDelete 107548-107694 ms.
 *
 * So the save is given its chance to land before the menu is opened. A test that filters or
 * searches first escapes this by accident, which is why the wait belongs here rather than in a
 * single spec.
 */
export async function runMassAction(page: Page, label: string, confirm = true): Promise<void> {
  await page
    .waitForResponse((r) => r.url().includes('mui/bookmark/save'), { timeout: 3000 })
    .catch(() => undefined);
  await page.waitForLoadState('networkidle');

  const toolbar = page.locator('.admin__data-grid-header');
  await toolbar.locator('button').filter({ hasText: 'Actions' }).first().click();
  await toolbar.locator('.action-menu-item').filter({ hasText: label }).first().click();

  if (confirm) {
    const accept = page.locator('.modal-popup.confirm .action-accept');
    if (await accept.isVisible({ timeout: 5000 }).catch(() => false)) {
      await accept.click();
    }
  }

  await page.waitForLoadState('networkidle');
  await expect(page.locator('.admin__data-grid-loading-mask:visible')).toHaveCount(0, { timeout: 30_000 });
}
