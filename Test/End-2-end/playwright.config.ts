/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  globalSetup: require.resolve('./global-setup.ts'),

  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  maxFailures: process.env.CI ? 3 : undefined,
  reporter: process.env.CI
    ? [['list'], ['html']]
    : [['html', { open: 'never' }]],
  use: {
    baseURL: process.env.BASE_URL || 'https://magento.test/',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: true,
  },

  timeout: 120000,

  projects: [
    { name: 'setup', testMatch: /.*\.setup\.ts/ },

    {
      name: 'admin',
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.auth/backend.json',
      },
      dependencies: ['setup'],
      testMatch: /\/admin\/.*\.spec\.ts/,
    },

    {
      name: 'api',
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.auth/backend.json',
      },
      dependencies: ['setup'],
      testMatch: /\/feed\/.*\.spec\.ts/,
    },

    // Last on purpose: the baseline rewrites the whole configuration and resets it afterwards.
    {
      name: 'baseline',
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.auth/backend.json',
      },
      dependencies: ['admin', 'api'],
      testMatch: /\/baseline\/.*\.spec\.ts/,
    },
  ],
});
