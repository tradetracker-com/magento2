/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect, Page } from '@playwright/test';

/**
 * What an admin page looks like when Magento failed, rather than what it looks like when it merely
 * says the word "exception".
 *
 * The baseline used to search a rendered page for /Exception/. That is the admin's own vocabulary:
 * a grid column called Exception Message, a menu item, a route named /exception/index - and for
 * magento2-exception-monitor every single page of the module matched. These are the strings Magento
 * itself puts on an error page or in a stack trace, and nothing else.
 */
export const MAGENTO_ERROR =
  /Fatal error|Stack trace|has been thrown|An error has happened during application run|There has been an error processing your request|Report ID: webapi|Uncaught \w*(Error|Exception)|exception '[^']+' with message/;

/** The rendered page shows no sign of Magento having failed. */
export async function expectNoMagentoError(page: Page, what: string): Promise<void> {
  await expect(page.locator('body'), `${what} shows a Magento error page`).not.toContainText(MAGENTO_ERROR);
}

/**
 * What a failure looks like in the output of a command, a cron job or a snippet.
 *
 * Deliberately not the bare word "exception": a module about exceptions prints it in its own
 * success messages, and the baseline then fails on the module doing exactly what it should.
 */
export const OUTPUT_ERROR = /Uncaught|Fatal error|Stack trace|Error:|must be of type|Call to a member|PHP (Warning|Notice)/;
