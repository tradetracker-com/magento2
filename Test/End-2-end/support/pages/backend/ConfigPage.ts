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
    const form = this.page.locator('#config-edit-form');
    await form.waitFor({ state: 'visible', timeout: 30_000 }).catch(() => undefined);
    if (await form.count() === 0) {
      // "element not found" says nothing about why: the dashboard after a secret-key redirect, the
      // login page after a lost session and an error page all look the same. Report where the
      // browser ended up and what it was shown.
      const body = (await this.page.locator('body').innerText().catch(() => '')).slice(0, 400).replace(/\s+/g, ' ');
      throw new Error(`No configuration form for section ${section}.\nURL: ${this.page.url()}\nTitle: ${await this.page.title()}\nBody: ${body}`);
    }
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
