/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { Page } from '@playwright/test';

/**
 * Collects the uncaught JS errors of a page that this module can be blamed for.
 *
 * Every admin configuration page also runs the JS of every other installed module, and some of that
 * is broken on its own: PayPal_Braintree 4.6.1-p3 (Magento 2.4.7-p3) calls the prototype.js
 * `Array#each` from `js/system.js` before prototype is loaded and throws
 * `locations.each is not a function` on sections that have nothing to do with payments.
 * An error whose top frame sits in another vendor's static JS is therefore recorded here, but not
 * counted; everything else - our own files, inline scripts, core files we may have fed bad data - is.
 */
export class PageErrors {
  readonly own: string[] = [];
  readonly foreign: string[] = [];

  constructor(page: Page) {
    page.on('pageerror', (error) => {
      const frame = topFrame(error.stack ?? '');
      const vendor = frame.match(/\/static\/(?:version\d+\/)?[^/]+\/[^/]+\/[^/]+\/[^/]+\/([A-Z][A-Za-z0-9]*)_[A-Z][A-Za-z0-9]*\/js\//)?.[1];
      if (vendor !== undefined && vendor !== 'TradeTracker') {
        this.foreign.push(`${error.message} (${frame})`);
        return;
      }
      this.own.push(error.message);
    });
  }
}

function topFrame(stack: string): string {
  const line = stack.split('\n').find((entry) => /^\s*at\s/.test(entry)) ?? '';
  return line.trim().replace(/^at\s+/, '');
}
