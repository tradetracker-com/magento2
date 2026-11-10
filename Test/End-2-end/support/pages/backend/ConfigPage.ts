/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect, Page } from '@playwright/test';
import { adminConfigUrl } from '../../helpers/AdminUrl';

/**
 * Stores > Configuration page of one section, on the page the chromium project is logged in to.
 */
export default class ConfigPage {
  constructor(private readonly page: Page) {}

  async open(section: string): Promise<void> {
    await this.page.goto(adminConfigUrl(section));
    await this.page.waitForLoadState('networkidle');
    await expect(this.page.locator('#config-edit-form')).toBeVisible();
    await expect(this.page.locator('body')).not.toContainText('Exception');
  }

  /** Expands every collapsed group header on the page, like the user does. */
  async expandAllGroups(): Promise<void> {
    const heads = this.page.locator('.section-config > .entry-edit-head > a[id$="-head"]');
    for (let i = 0; i < await heads.count(); i++) {
      const head = heads.nth(i);
      if (!await head.isVisible()) continue;
      if (!((await head.getAttribute('class')) ?? '').includes('open')) {
        await head.click();
      }
    }
  }
}
