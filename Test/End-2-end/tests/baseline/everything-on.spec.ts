/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from 'Services/TradeTrackerApi';
import BaselineConfigPage from 'Pages/backend/BaselineConfigPage';
import { ConfigSurface, SurfaceField, ValueChoice } from 'Helpers/ConfigSurface';
import { PageErrors } from 'Helpers/PageErrors';
import { MAGENTO_ERROR, OUTPUT_ERROR, expectNoMagentoError } from 'Helpers/MagentoErrors';
import { MagentoDb } from 'Helpers/MagentoDb';
import { CONFIG_SURFACE_OVERRIDES } from 'Fixtures/config-surface.overrides';
import { CLI_COMMANDS, cliInvocation, DISABLED_MESSAGE, prepareBaseline, runModuleWork, SECTIONS } from 'Fixtures/baseline';
/** Whether a config path belongs to this module, by its own section ids. */
const ownsPath = (path: string): boolean => SECTIONS.some((section) => path.startsWith(`${section}/`));


const api = new TradeTrackerApi();

/** Attributes that exist on the fixture catalog with a value, by what the field is for. */
function attributeFor(field: SurfaceField): string | null {
  const p = field.path;
  if (/weight/.test(p)) return 'weight';
  if (/retail_price|msrp/.test(p)) return 'msrp';
  if (/purchase_price|cost/.test(p)) return 'cost';
  if (/country|origin/.test(p)) return 'country_of_manufacture';
  if (/gtin|ean/.test(p)) return 'sku';
  if (/brand|manufacturer/.test(p)) return 'manufacturer';
  if (/description/.test(p)) return 'description';
  if (/name_tag|short/.test(p)) return 'short_description';
  if (/name|title/.test(p)) return 'name';
  if (/image/.test(p)) return 'image';
  if (/quantity|qty/.test(p)) return 'min_sale_qty';
  return null;
}

/**
 * The baseline: every configuration field of the module set to its "everything on" value through
 * the admin form, section by section, with the success message, no JS errors of our own and a
 * read-back of every value from core_config_data. Three fields used to be set in CI; this sets all.
 */
test.describe('everything on', () => {
  // Serial on purpose: the CLI, cron and work checks below read the configuration the section tests saved.
  test.describe.configure({ mode: 'serial' });

  let surface: ConfigSurface;
  const db = new MagentoDb(process.env.MAGENTO_CONTAINER || '');
  const chosen: ValueChoice[] = [];

  test.beforeAll(async () => {
    await prepareBaseline(api);
    surface = ConfigSurface.load(api, SECTIONS);
    expect(surface.allFields().length, 'configuration surface dumped').toBeGreaterThan(0);
  });

  for (const sectionId of SECTIONS) {
    test(`section ${sectionId} saves with every option on and every value lands in core_config_data`, async ({ page }) => {
      test.setTimeout(300_000);
      const errors = new PageErrors(page);
      const section = surface.sections.find((s) => s.id === sectionId);
      expect(section, `section ${sectionId} in the dump`).toBeDefined();

      const config = new BaselineConfigPage(page);
      await config.open(sectionId);
      const opened = await config.expandAllGroups();
      expect(opened.length).toBeGreaterThan(0);

      const set: ValueChoice[] = [];
      const skipped: string[] = [];
      const fields = section!.groups.flatMap((g) => g.fields);
      const groupOf = new Map(section!.groups.flatMap((g) => g.fields.map((f) => [f.elementId, g] as const)));
      /** A depends id names a field of the same group first ("enable" exists in general and ftp alike). */
      // Magento stores a depends id as the full path (section/group/field); a bare field id is resolved in the same group first.
      const resolveDep = (field: SurfaceField, depId: string): SurfaceField | undefined =>
        fields.find((f) => f.path === depId || f.elementId === depId.replace(/\//g, '_'))
        ?? groupOf.get(field.elementId)?.fields.find((f) => f.id === depId)
        ?? fields.find((f) => f.id === depId)
        ?? fields.find((f) => f.elementId.endsWith(`_${depId}`));
      const setIds = new Set<string>();
      /**
       * Sets the fields this one depends on (recursively, to the value the dependency asks for), then
       * the field itself. A field that is wanted at a specific value by a dependant takes that value
       * instead of its own "everything on" choice; a later dependant asking for another value loses.
       */
      const setWithDependencies = async (field: SurfaceField, depth = 0, wanted?: string): Promise<'set' | 'hidden' | 'skip'> => {
        if (setIds.has(field.elementId)) return 'set';
        if (depth > 6) return 'hidden';
        for (const dep of field.depends) {
          const depField = resolveDep(field, dep.id);
          if (!depField || setIds.has(depField.elementId)) continue;
          await setWithDependencies(depField, depth + 1, dep.value.split(',')[0]);
        }
        if (!ownsPath(field.path)) {
          // A field the structure lists under our section but whose value lives elsewhere (core path) is not
          // ours to set. Decided by the module's own section ids, never by a prefix spelled out here: every
          // copy of this spec had one that matched no path at all, so no field was ever set and the
          // assertions below ran on an empty list.
          console.log(`[${sectionId}] foreign field in structure: ${JSON.stringify(field)}`);
          skipped.push(`${field.path} (outside the module)`);
          return 'skip';
        }
        const choice = wanted !== undefined
          ? { field, value: wanted, reason: 'dependency' }
          : ConfigSurface.everythingOn(field, CONFIG_SURFACE_OVERRIDES, attributeFor, fields);
        if (choice.value === null) { skipped.push(`${field.path} (${choice.reason})`); return 'skip'; }
        const done = choice.value === 'rows'
          ? await config.fillRendererRow(field.elementId)
          : await config.setField(field.elementId, choice.value);
        if (!done) {
          const inDom = await page.locator(`#row_${field.elementId}`).count();
          skipped.push(`${field.path} (${inDom ? 'hidden' : 'no row #row_' + field.elementId}; depends ${JSON.stringify(field.depends)})`);
          return 'hidden';
        }
        setIds.add(field.elementId); set.push(choice);
        return 'set';
      };
      for (const field of fields) {
        await setWithDependencies(field);
      }
      test.info().annotations.push({ type: 'skipped', description: skipped.slice(0, 40).join(' | ') });
      const byReason = new Map<string, string[]>();
      for (const entry of skipped) {
        const reason = (entry.match(/\(([^;()]+)/)?.[1] ?? 'other').trim();
        byReason.set(reason, [...(byReason.get(reason) ?? []), entry.replace(/^.*?\//, '').replace(/ \(.*$/, '')]);
      }
      console.log(`[${sectionId}] set ${set.length}, skipped ${skipped.length}:\n  ` + Array.from(byReason.entries()).map(([reason, paths]) => `${reason} (${paths.length}): ${paths.join(', ')}`).join('\n  '));
      chosen.push(...set);
      test.info().annotations.push({ type: 'set', description: `${set.length} fields set, ${skipped.length} skipped` });
      await test.info().attach(`${sectionId}-values.json`, { body: JSON.stringify({ set: set.map((c) => ({ path: c.field.path, value: c.value, reason: c.reason })), skipped }, null, 2), contentType: 'application/json' });

      await config.save();
      expect(errors.own, 'JS errors while filling and saving').toEqual([]);

      const scalar = set.filter((c) => c.value !== 'rows' && ownsPath(c.field.path) && !Array.isArray(c.value));
      const stored = db.readConfig(scalar.map((c) => c.field.path));
      // A field declared type="obscure" with Magento's Encrypted backend model stores ciphertext,
      // so the row never equals what was typed. Decrypt those and compare the plaintext, rather
      // than skipping them: whether the value survives the round trip is the thing worth knowing.
      const isEncrypted = (f: SurfaceField) => f.type === 'obscure' || /Backend\\Encrypted/.test(f.backendModel ?? '');
      const decrypted = api.decryptConfig(scalar.filter((c) => isEncrypted(c.field)).map((c) => c.field.path));
      const mismatches = scalar
        .map((c) => ({
          path: c.field.path,
          expected: Array.isArray(c.value) ? c.value.join(',') : String(c.value),
          stored: isEncrypted(c.field) ? decrypted[c.field.path] : stored[c.field.path],
        }))
        // A browser submits a textarea with CRLF line endings, so a multiline value never comes
        // back byte for byte. The line ending is the browser's, not the module's.
        .filter((m) => normalizeNewlines(m.stored) !== normalizeNewlines(m.expected));
      expect(mismatches, 'every saved value read back from core_config_data').toEqual([]);
      const rows = set.filter((c) => c.value === 'rows');
      const storedRows = db.readConfig(rows.map((c) => c.field.path));
      expect(rows.filter((c) => !storedRows[c.field.path]).map((c) => c.field.path), 'every field array stored a row').toEqual([]);
      // An override that leaves a field alone, or pins a dependency to a value that hides it, takes it out of reach.
      const chosenFor = new Map(set.map((c) => [c.field.elementId, c.value] as const));
      /**
       * Out of reach in this pass: an override leaves the field alone, or a field it depends on
       * ended up at a value that hides it. Two options of the same select cannot both be on in one
       * save, so a dependant of the branch that lost is not a gap in coverage.
       */
      const blockedByOverride = (f: SurfaceField): boolean =>
        CONFIG_SURFACE_OVERRIDES[f.path]?.value === null
        || f.depends.some((d) => {
          const dep = resolveDep(f, d.id);
          if (!dep) return false;
          const override = CONFIG_SURFACE_OVERRIDES[dep.path];
          if (override !== undefined && override.value !== null && !d.value.split(',').includes(String(override.value))) {
            return true;
          }
          const chosen = chosenFor.get(dep.elementId);
          return chosen !== undefined && !Array.isArray(chosen) && !d.value.split(',').includes(String(chosen));
        });
      const reachable = ConfigSurface.reachableInputs(section!.groups.flatMap((g) => g.fields))
        .filter((f) => !blockedByOverride(f) && f.path.startsWith(`${sectionId}/`));
      const unset = reachable.filter((f) => !setIds.has(f.elementId)).map((f) => f.path);
      expect(unset, 'every reachable input was set').toEqual([]);
    });
  }

  test('the module still does its own work with everything on', () => {
    for (const run of runModuleWork(api)) {
      expect(run.exitCode, `${run.label}\n${run.output}`).toBe(0);
      expect(run.output, run.label).not.toMatch(OUTPUT_ERROR);
      // Exit code 0 on an empty result proves nothing. Assert what the work produced: digitec passed
      // this test for three rounds while quietly writing four empty feed files.
      //
      // What counts as "it said what it did" differs per command, and the fixture decides: a feed
      // generation has to report a non-zero count because the fixture catalog has products, while a
      // queue run or a cleanup with nothing to do reports zero. Demanding a non-zero count
      // everywhere failed a queue run that answered "Processed Queue 0 items" perfectly correctly.
      expect(run.output, `${run.label} reports what it did\n${run.output}`)
        .toMatch(run.mustProduce ? /[1-9]\d*/ : /\d/);
    }
  });

  test('every CLI command runs with everything on and refuses with an unknown option', () => {
    for (const command of CLI_COMMANDS) {
      const run = api.runCliRaw(cliInvocation(command));
      expect(run.exitCode, `${command}\n${run.output}`).toBe(0);
      expect(run.output, command).not.toMatch(OUTPUT_ERROR);
      const bad = api.runCliRaw(`${cliInvocation(command)} --nope`);
      expect(bad.exitCode, `${command} --nope`).not.toBe(0);
    }
  });

  test('every cron job runs with everything on', () => {
    const jobs = api.cronJobs();
    // A module without a crontab.xml is a legitimate shape, not a failure: this module renders on
    // the storefront and has nothing to schedule.
    test.skip(jobs.length === 0, 'the module registers no cron jobs');

    // An entry with a schedule and no class is a schedule written into a group where no job is
    // declared - a backend model copying the frequency into crontab/<group>/jobs/... next to the
    // field the administrator edits. Magento lists it as a job and then cannot instantiate it.
    const phantom = jobs.filter((job) => !job.instance).map((job) => `${job.group}/${job.name}`);
    expect(phantom, 'cron entries with a schedule but no class').toEqual([]);

    for (const job of jobs) {
      const result = api.runCronJob(job.instance, job.method);
      expect(result, `${job.name}`).not.toMatch(OUTPUT_ERROR);
    }
  });

  test.afterAll(async () => {
    api.resetModuleConfig();
    await prepareBaseline(api);
  });
});

/** Line endings as the value was typed, so a textarea's CRLF does not read as a changed value. */
function normalizeNewlines(value: string | undefined): string | undefined {
  return value === undefined ? value : value.replace(/\r\n/g, '\n');
}
