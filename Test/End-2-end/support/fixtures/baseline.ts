/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import TradeTrackerApi from 'Services/TradeTrackerApi';

/**
 * Everything the four baseline specs need to know about this module, and the only file of the
 * baseline layer this module edits.
 *
 * The specs themselves are generic on purpose: they read the module's own ui_component XML, its
 * Controller/Adminhtml directory and Magento's configuration structure, so they keep working when
 * someone adds a column, a route or a field. What differs per module is which sections exist,
 * which CLI commands there are, and what "do the module's own work once" means. For TradeTracker
 * that is generating the product feed for store view 1.
 */

/** The module's configuration sections, in the order the admin shows them. */
export const SECTIONS = ['tradetracker_general', 'tradetracker_feed'];

/** Every console command the module registers. */
export const CLI_COMMANDS = ['tradetracker:feed:create'];

/**
 * Arguments a command cannot run without, by command name. The feed command takes an optional
 * store id and generates every enabled store view without one, so nothing is needed here.
 */
export const CLI_ARGUMENTS: Record<string, string> = {};

/** A command as the baseline runs it: the name, plus whatever argument it cannot do without. */
export function cliInvocation(command: string): string {
  const argument = CLI_ARGUMENTS[command];

  return argument === undefined ? command : `${command} ${argument}`;
}

/** The module's own error log in var/log; the routes baseline fails on anything written to it. */
export const ERROR_LOG = 'tradetracker-error.log';

/**
 * Admin pages that must render in the browser, not just answer a request. This module has no
 * listing of its own: its admin surface is the configuration, which the two section specs cover.
 */
export const ADMIN_PAGES: string[] = [];

/**
 * Bring the module to the state the baseline assumes: enabled, feed on for store view 1, default
 * file name. Idempotent; each spec calls it in `beforeAll`.
 */
export async function prepareBaseline(api: TradeTrackerApi): Promise<void> {
  await api.setMagentoConfig(process.env.BASE_URL || 'https://magento.test/', {
    'tradetracker/general/enable': '1',
    'tradetracker/feed/enable': '1',
    'tradetracker/feed/filename': 'tradetracker.xml',
  });
  api.flushAllCaches();
}

/** Undo whatever `prepareBaseline` created that another spec would trip over. */
export async function cleanupBaseline(api: TradeTrackerApi): Promise<void> {
  void api;
}

/**
 * Run the module's real work once and report how it went: the feed for store view 1. It has to
 * report a non-zero product count, because the fixture catalogue has products; a feed that writes
 * nothing is broken.
 */
export function runModuleWork(api: TradeTrackerApi): { label: string; exitCode: number; output: string; mustProduce: boolean }[] {
  const command = 'tradetracker:feed:create --store-id=1';

  return [{ label: command, ...api.runCliRaw(command), mustProduce: true }];
}

/**
 * The message a command is allowed to refuse with when the module is switched off: "Module is not
 * enabled." from the command itself, or "Product Feed for this StoreId not enabled." per store.
 */
export const DISABLED_MESSAGE = /not enabled/i;
