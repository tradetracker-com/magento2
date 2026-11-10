/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { type FullConfig } from '@playwright/test';
import TradeTrackerApi from './support/services/TradeTrackerApi';

async function globalSetup(config: FullConfig) {
  process.env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
  const { baseURL } = config.projects[0].use;
  if (!baseURL) {
    throw new Error('No baseURL configured: set BASE_URL');
  }

  await getAdminToken(baseURL);
  prepareStore();
}

const getAdminToken = async (baseURL: string) => {
  const username = process.env.MAGENTO_ADMIN_USER || 'exampleuser';
  const password = process.env.MAGENTO_ADMIN_PASS || 'examplepassword123';

  console.log('Requesting admin token from "' + baseURL + '"...');

  const response = await fetch(baseURL + 'rest/all/V1/integration/admin/token', {
    method: 'POST',
    headers: {
      'accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ username, password }),
  });

  process.env.admin_token = await response.json();
  console.log('Admin token acquired.');
};

/**
 * Everything about the shop that must be true before the first request. Secret keys in admin URLs
 * and the admin captcha break the admin specs; the configure script switches them off too, but a
 * box that was provisioned differently gets the same treatment here.
 */
const prepareStore = () => {
  if (!process.env.MAGENTO_CONTAINER) {
    return;
  }
  const api = new TradeTrackerApi();
  api.setBaseConfig({
    'admin/security/use_form_key': 0,
    'admin/captcha/enable': 0,
  });
  api.flushAllCaches();
};

export default globalSetup;
