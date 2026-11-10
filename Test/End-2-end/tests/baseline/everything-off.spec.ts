/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from 'Services/TradeTrackerApi';
import BaselineConfigPage from 'Pages/backend/BaselineConfigPage';
import { ConfigSurface, SurfaceField } from 'Helpers/ConfigSurface';
import { PageErrors } from 'Helpers/PageErrors';
import { MAGENTO_ERROR, OUTPUT_ERROR, expectNoMagentoError } from 'Helpers/MagentoErrors';
import { MagentoDb } from 'Helpers/MagentoDb';
import { adminUrl } from 'Helpers/AdminUrl';
import { ADMIN_PAGES, CLI_COMMANDS, cliInvocation, DISABLED_MESSAGE, prepareBaseline, runModuleWork, SECTIONS } from 'Fixtures/baseline';

const api = new TradeTrackerApi();

/**
 * The counterpart of "everything on": every Yes/No switch of the module off, saved through the admin
 * form and read back, and then the CLI commands, cron jobs, the module's own work and its admin pages still run
 * without an exception. A disabled module has to be as quiet as an enabled one.
 */
test.describe('everything off', () => {
  // Serial on purpose: the CLI, cron and grid checks below read the configuration the section tests saved.
  test.describe.configure({ mode: 'serial' });

  let surface: ConfigSurface;
  const db = new MagentoDb(process.env.MAGENTO_CONTAINER || '');

  test.beforeAll(async () => {
    await prepareBaseline(api);
    surface = ConfigSurface.load(api, SECTIONS);
  });

  for (const sectionId of SECTIONS) {
    test(`section ${sectionId} saves with every switch off and every value lands in core_config_data`, async ({ page }) => {
      test.setTimeout(180_000);
      const errors = new PageErrors(page);
      const section = surface.sections.find((s) => s.id === sectionId)!;
      const config = new BaselineConfigPage(page);
      await config.open(sectionId);
      await config.expandAllGroups();

      const switches = section.groups.flatMap((g) => g.fields).filter(isYesNo);
      const set: SurfaceField[] = [];
      for (const field of switches) {
        if (await config.setField(field.elementId, '0')) set.push(field);
      }
      expect(set.length, `${sectionId}: Yes/No switches reachable with everything off`).toBeGreaterThan(0);
      await config.save();
      expect(errors.own, 'JS errors while filling and saving').toEqual([]);

      const stored = db.readConfig(set.map((f) => f.path));
      const wrong = set.filter((f) => stored[f.path] !== '0').map((f) => `${f.path}=${stored[f.path]}`);
      expect(wrong, 'every switch reads back as 0').toEqual([]);
      test.info().annotations.push({ type: 'set', description: `${set.length} switches off` });
    });
  }

  test('the module\'s own work, every CLI command and every cron job run with everything off', () => {
    // With the module switched off a command may refuse, but only with the one sentence meant for it.
    const refusedCleanly = (run: { exitCode: number; output: string }) => run.exitCode === 0 || DISABLED_MESSAGE.test(run.output);
    for (const run of runModuleWork(api)) {
      expect(refusedCleanly(run), `${run.label}\n${run.output}`).toBe(true);
      expect(run.output, run.label).not.toMatch(OUTPUT_ERROR);
    }

    for (const command of CLI_COMMANDS) {
      const run = api.runCliRaw(cliInvocation(command));
      expect(refusedCleanly(run), `${command}\n${run.output}`).toBe(true);
      expect(run.output, command).not.toMatch(OUTPUT_ERROR);
    }
    for (const job of api.cronJobs()) {
      expect(api.runCronJob(job.instance, job.method), job.name).not.toMatch(OUTPUT_ERROR);
    }
  });

  for (const path of ADMIN_PAGES) {
    test(`${path} loads with everything off`, async ({ page }) => {
      const errors = new PageErrors(page);
      await page.goto(adminUrl(path));
      await page.waitForLoadState('networkidle');
      await expect(page.locator('[data-role="grid-wrapper"]').first()).toBeVisible();
      await expectNoMagentoError(page, path);
      expect(errors.own).toEqual([]);
    });
  }

  test.afterAll(async () => {
    api.resetModuleConfig();
    await prepareBaseline(api);
  });
});

function isYesNo(field: SurfaceField): boolean {
  if (field.type !== 'select' || !ConfigSurface.isInput(field)) return false;
  const options = field.options.filter((o) => o.value !== '');
  return options.length === 2 && options.every((o) => ['0', '1'].includes(o.value));
}
