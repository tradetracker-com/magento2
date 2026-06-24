/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect } from '@playwright/test';
import { adminUrl } from '../../helpers/AdminUrl';

export default class BackendLogin {
  async login(page, maxRetries = 3) {
    const username = process.env.MAGENTO_ADMIN_USER || 'exampleuser';
    const password = process.env.MAGENTO_ADMIN_PASS || 'examplepassword123';

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      await page.goto(adminUrl('/admin'));
      await page.getByLabel('Username').fill(username);
      await page.getByLabel('Password').fill(password);
      await page.getByRole('button', { name: 'Sign in' }).click();

      try {
        await page.waitForURL('**/admin/dashboard/**', { timeout: 30000, waitUntil: 'domcontentloaded' });
        return;
      } catch {
        if (attempt === maxRetries) {
          throw new Error(`Admin login failed after ${maxRetries} attempts`);
        }
        await page.waitForTimeout(2000);
      }
    }
  }
}
