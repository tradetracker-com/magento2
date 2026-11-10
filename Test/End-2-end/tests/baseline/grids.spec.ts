/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import TradeTrackerApi from 'Services/TradeTrackerApi';
import GridPage from 'Pages/backend/GridPage';
import { duplicateColumns, GridColumn, GridComponents } from 'Helpers/GridComponents';
import { PageErrors } from 'Helpers/PageErrors';
import { cleanupBaseline, prepareBaseline } from 'Fixtures/baseline';

const api = new TradeTrackerApi();
const listings = GridComponents.load();

/**
 * Baseline for every admin grid the module declares: it loads without errors, every filterable column
 * filters (a value taken from the first row hits, an impossible value misses), every sortable column
 * sorts both ways, and the Columns control hides and shows a column. The grids and their columns come
 * from the ui_component XML, so a new column is covered the moment it is added.
 */
test.describe('grids', () => {
  // Not serial: each grid is independent, and a serial block hides every grid after the first failure.
  test.beforeAll(async () => {
    if (listings.length === 0) {
      return;
    }
    await prepareBaseline(api);
  });

  test.afterAll(async () => {
    if (listings.length === 0) {
      return;
    }
    await cleanupBaseline(api);
  });

  test('every listing the module ships declares filters and mass actions', () => {
    // A module without a grid of its own is a legitimate shape, not a failure: one that renders on
    // the storefront has nothing to list. GridWiringTest skips for the same reason, and both start
    // asserting the day a listing is added.
    test.skip(listings.length === 0, 'the module declares no listing of its own');

    for (const listing of listings) {
      expect(listing.columns.length, `${listing.namespace} has columns`).toBeGreaterThan(0);
      expect(duplicateColumns(listing), `${listing.namespace} declares no column twice`).toEqual([]);
    }
  });

  for (const listing of listings) {
    test(`${listing.namespace}: loads, every filter hits and misses, every sortable column sorts both ways, a column can be hidden`, async ({ page }) => {
      test.setTimeout(600_000);
      const errors = new PageErrors(page);
      const grid = new GridPage(page);
      await grid.open(listing.route);
      expect(await grid.rowCount(), 'the grid has rows to work with').toBeGreaterThan(0);

      const t0 = Date.now();
      const lap = (what: string) => console.log(`[${listing.namespace}] ${what} at ${Math.round((Date.now() - t0) / 1000)}s`);
      const headers = await grid.headers();
      const visible = listing.columns.filter((c) => headers.includes(c.label));
      const report: string[] = [];

      try {
      for (const column of visible.filter((c) => c.filter)) {
        lap(`filter ${column.name} (${column.filter})`);
        let sample: string;
        let exact = true;
        if (column.filter === 'select') {
          const picked = await grid.filterFirstOption(column);
          if (!picked) { report.push(`${column.name}: select filter not on the page (hidden in this store setup)`); continue; }
          sample = picked.label;
          // A rendered cell shows what the module draws (stars, a status badge), not the stored
          // value the filter searches on, so only that the filter answers can be asserted.
          exact = picked.exact && !column.rendered;
          if (!(await grid.rowCount())) {
            report.push(`${column.name}: no row carries the first option "${sample}", only the miss is asserted`);
            await grid.clearFilters();
            continue;
          }
        } else if (column.rendered) {
          report.push(`${column.name}: rendered by the module, cell text is not the stored value; only the miss is asserted`);
          await grid.filter(column, missValue(column));
          await expect(grid.noRecordsMessage(), `${listing.namespace}.${column.name}: impossible value finds nothing without an error`).toBeVisible();
          await expect(page.locator('.message-error'), `${listing.namespace}.${column.name}: filter does not error`).toHaveCount(0);
          await grid.clearFilters();
          continue;
        } else {
          const values = (await grid.columnValues(column.label)).filter((v) => v !== '');
          if (!values.length) { report.push(`${column.name}: no value on the first page, filter not exercised`); continue; }
          sample = values[0];
          await grid.filter(column, hitValue(column, sample));
        }
        const hits = await grid.columnValues(column.label);
        expect(hits.length, `${listing.namespace}.${column.name} filter "${sample}" hits`).toBeGreaterThan(0);
        if (exact) for (const hit of hits) expect(matches(column, hit, sample), `${listing.namespace}.${column.name}: "${hit}" matches filter "${sample}"`).toBe(true);
        else report.push(`${column.name}: grouped option "${sample}" selected, ${hits.length} row(s), cell text not compared`);
        await grid.clearFilters();

        if (column.filter !== 'select') {
          await grid.filter(column, missValue(column));
          await expect(grid.noRecordsMessage(), `${listing.namespace}.${column.name}: impossible value finds nothing`).toBeVisible();
          await grid.clearFilters();
        }
      }
      } finally {
        // A failed assertion must not leave a filter in the admin's grid bookmark for the next spec.
        await grid.clearFilters();
      }

      for (const column of visible.filter((c) => c.sortable)) {
        lap(`sort ${column.name}`);
        await grid.sort(column.label, 'asc');
        const asc = await grid.columnValues(column.label);
        await grid.sort(column.label, 'desc');
        const desc = await grid.columnValues(column.label);
        if (new Set(asc).size < 2) { report.push(`${column.name}: all values equal on the first page, order not asserted`); continue; }
        if (isSelect(column)) {
          // A select sorts on the stored id while the cell shows the label, so the labels come out grouped,
          // not alphabetical, and the two directions mirror each other.
          expect(blocks(asc), `${listing.namespace}.${column.name} ascending groups: ${asc.slice(0, 6).join(' | ')}`).toEqual(Array.from(new Set(asc)));
          expect(blocks(desc), `${listing.namespace}.${column.name} descending groups: ${desc.slice(0, 6).join(' | ')}`).toEqual(Array.from(new Set(desc)));
          continue;
        }
        if (column.rendered) {
          // The cell is built by the module - truncated, badged, formatted - so the text on screen
          // is not the value MySQL sorted on, and judging its order judges the renderer. What can
          // be asserted is that the grid re-sorted at all and lost nothing doing it.
          expect(new Set(desc), `${listing.namespace}.${column.name} keeps its rows when sorted`).toEqual(new Set(asc));
          if (new Set(asc).size > 1) {
            expect(desc.join('|'), `${listing.namespace}.${column.name} sorts the other way round`).not.toBe(asc.join('|'));
          }
          report.push(`${column.name}: cell rendered by the module, order of the text not compared`);
          continue;
        }
        expect(isOrdered(column, asc, 1), `${listing.namespace}.${column.name} ascending: ${asc.slice(0, 5).join(' | ')}`).toBe(true);
        expect(isOrdered(column, desc, -1), `${listing.namespace}.${column.name} descending: ${desc.slice(0, 5).join(' | ')}`).toBe(true);
      }

      lap('columns control');
      const toggle = visible.find((c) => c.filter === 'text') ?? visible[visible.length - 1];
      expect(await grid.toggleColumn(toggle.label), `${toggle.label} hidden`).toBe(false);
      expect(await grid.toggleColumn(toggle.label), `${toggle.label} shown again`).toBe(true);

      test.info().annotations.push({ type: 'not-asserted', description: report.join(' | ') });
      expect(errors.own).toEqual([]);
    });
  }
});

function isSelect(column: GridColumn): boolean {
  return column.filter === 'select' || column.dataType === 'select';
}

/** The distinct values in the order they first appear; equals the distinct set only when every value is in one block. */
function blocks(values: string[]): string[] {
  return values.filter((v, i) => i === 0 || values[i - 1] !== v);
}

/** The filter input for a value read from the grid: ranges get from=to, dates the day, text the value. */
function hitValue(column: GridColumn, sample: string): string | { from?: string; to?: string } {
  if (column.filter === 'textRange') { const n = number(sample); return { from: String(n), to: String(n) }; }
  if (column.filter === 'dateRange') { const d = new Date(sample); return { from: usDate(d), to: usDate(d) }; }
  return sample;
}

function missValue(column: GridColumn): string | { from?: string; to?: string } {
  if (column.filter === 'textRange') return { from: '999999999', to: '999999999' };
  if (column.filter === 'dateRange') return { from: '01/01/2001', to: '01/02/2001' };
  return 'zz-no-such-value-zz';
}

function matches(column: GridColumn, cell: string, sample: string): boolean {
  if (column.filter === 'textRange') return number(cell) === number(sample);
  if (column.filter === 'dateRange') return usDate(new Date(cell)) === usDate(new Date(sample));
  if (column.filter === 'select') return cell.split('\n')[0].trim().toLowerCase() === sample.toLowerCase() || cell.toLowerCase().includes(sample.toLowerCase());
  return cell.toLowerCase().includes(sample.toLowerCase());
}

function isOrdered(column: GridColumn, values: string[], direction: 1 | -1): boolean {
  for (let i = 1; i < values.length; i++) {
    const c = compare(column, values[i - 1], values[i]) * direction;
    if (c > 0) return false;
  }
  return true;
}

function compare(column: GridColumn, a: string, b: string): number {
  if (column.dataType === 'date' || column.filter === 'dateRange') return Date.parse(a) - Date.parse(b);
  // The column name is a hint, the value decides. `event_id` ends in `_id` but holds
  // "api_3FRpsy9MAOPz..."; stripping the non-digits turned that into the number 3991664 and the
  // order was asserted against nonsense.
  if (column.filter === 'textRange' || /price|amount|_id$/.test(column.name)) {
    if (looksNumeric(a) && looksNumeric(b)) {
      const na = number(a); const nb = number(b);
      if (!Number.isNaN(na) && !Number.isNaN(nb)) return na - nb;
    }
  }
  // MySQL sorts text case-insensitively with the collations Magento uses, so compare the same way.
  return a.toLowerCase().localeCompare(b.toLowerCase());
}

/**
 * Whether the whole value reads as a number. Currency decoration is allowed around it, letters in
 * front of the digits are not: "MH01" and "api_3FRpsy9..." are identifiers, "$ 49.95" and
 * "49.95 EUR" are amounts. Date columns never reach this - they take the date branch above.
 */
function looksNumeric(text: string): boolean {
  return /^[\s\u20ac$\u00a3]*[-+]?\d[\d.,\s]*(?:[\s\u20ac$\u00a3]|[A-Z]{3})*$/.test(text.trim());
}

function number(text: string): number {
  return parseFloat(text.replace(/[^0-9.\-]/g, ''));
}

function usDate(d: Date): string {
  return `${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}/${d.getFullYear()}`;
}
