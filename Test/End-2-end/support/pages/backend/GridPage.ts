/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect, Locator, Page } from '@playwright/test';
import { adminUrl } from 'Helpers/AdminUrl';
import { GridColumn } from 'Helpers/GridComponents';
import { MAGENTO_ERROR } from 'Helpers/MagentoErrors';

/**
 * A Magento UI listing driven like the admin does: the Filters panel, the sortable header, the Columns
 * control. Only the visible header is used; the grid keeps a hidden sticky clone of it.
 */
export default class GridPage {
  constructor(private readonly page: Page) {}

  async open(route: string): Promise<void> {
    await this.page.goto(adminUrl(route));
    await this.page.waitForLoadState('networkidle');
    // The grid arrives with its own data request, long after the admin's pollers have settled.
    await expect(this.page.locator('[data-role="grid-wrapper"]').first()).toBeVisible({ timeout: 30_000 });
    await expect(this.page.locator('body')).not.toContainText(MAGENTO_ERROR);
    await this.settled();
    await this.clearFilters();
  }

  /**
   * A bounded wait that says so when it runs out, rather than swallowing it silently.
   *
   * Bounded is right: a stalled grid must fail its own step, not the whole run. But a wait that
   * always runs out costs its full timeout and looks like work - 39 of them per run cost 19
   * minutes here, which is what put the baseline over the CI budget. Reported, so the next one is
   * visible in the log instead of hiding in the clock.
   */
  private async timed<T>(what: string, promise: Promise<T>): Promise<T | undefined> {
    const started = Date.now();
    try {
      return await promise;
    } catch {
      console.log(`[grid] ${what} ran out after ${Math.round((Date.now() - started) / 1000)}s`);

      return undefined;
    }
  }

  async settled(): Promise<void> {
    await this.timed('networkidle', this.page.waitForLoadState('networkidle', { timeout: 30_000 }));
    await expect(this.page.locator('.admin__data-grid-loading-mask:visible')).toHaveCount(0, { timeout: 30_000 });
  }

  /**
   * Runs an action that makes the grid reload and waits for the reload itself, not for a side
   * effect of it. The loading mask has not appeared yet the moment a click returns and the sort
   * classes are set before the data arrives, so both are read too early; the grid's own render
   * request is the one signal that means the rows on screen belong to the new state.
   */
  private async reloading(what: string, action: () => Promise<void>): Promise<void> {
    // Wait for the request to start before waiting for its response. An action that reloads the
    // grid fires its XHR straight from the click handler, so if nothing has started shortly after
    // the action there is nothing coming: the action was a no-op (clearing filters that are
    // already clear, a header that does not sort). Waiting the full 30 seconds for a response that
    // will never arrive is what made one grid take nine minutes - 39 of these in a run, 19 minutes
    // of nothing, which is why the baseline could not finish inside the CI budget.
    const started = this.page
      .waitForRequest((r) => /mui\/index\/render/.test(r.url()), { timeout: 5_000 })
      .catch(() => null);
    const response = this.timed(
      `render response (${what})`,
      this.page.waitForResponse((r) => /mui\/index\/render/.test(r.url()), { timeout: 30_000 })
    );
    await action();
    if (await started) {
      await response;
    }
    await this.settled();
  }

  async clearFilters(): Promise<void> {
    const clearAll = this.page.locator('.admin__data-grid-filters-current:visible .action-clear');
    if (await clearAll.count()) {
      await this.reloading('clear filters', () => clearAll.first().click());
    }
  }

  /**
   * The real grid table. Magento keeps a sticky clone of the header in a second wrapper, and that
   * clone carries the same admin__data-grid-wrap class without a .sticky-header one to tell them
   * apart; only data-role separates them, and picking the wrong one reads an empty grid.
   */
  private table(): Locator {
    return this.page.locator('[data-role="grid-wrapper"] table.data-grid').first();
  }

  /** Visible column headers in table order (the first cell is the multicheck). */
  async headers(): Promise<string[]> {
    const texts = await this.table().locator('thead th').allInnerTexts();
    return texts.map((t) => t.trim());
  }

  rows(): Locator {
    return this.table().locator('tbody tr.data-row');
  }

  async rowCount(): Promise<number> {
    await this.settled();
    return this.rows().count();
  }

  /** Cell texts of one column for every row on the page, matched by the column label. */
  async columnValues(label: string): Promise<string[]> {
    const index = (await this.headers()).indexOf(label);
    expect(index, `column "${label}" visible in the grid`).toBeGreaterThanOrEqual(0);
    // Direct children only: the order grid's Customer, Products and Price renderers nest tables with their own cells.
    const cells = this.rows().locator(`:scope > td:nth-child(${index + 1})`);
    return (await cells.allInnerTexts()).map((t) => t.trim());
  }

  header(label: string): Locator {
    return this.table().locator('thead th').filter({ hasText: new RegExp(`^\\s*${escape(label)}\\s*$`) }).first();
  }

  /** Clicks the header until it sorts in the wanted direction. */
  async sort(label: string, direction: 'asc' | 'desc'): Promise<void> {
    const th = this.header(label);
    await expect(th).toHaveClass(/_sortable/);
    const wanted = direction === 'asc' ? /_ascend/ : /_descend/;
    for (let i = 0; i < 3 && !wanted.test((await th.getAttribute('class')) ?? ''); i++) {
      await this.reloading(`sort ${label}`, () => th.click());
    }
    await expect(th).toHaveClass(wanted);
  }

  async openFilters(): Promise<void> {
    const panel = this.page.locator('.admin__data-grid-filters-wrap:visible');
    if (!(await panel.count()) || !(await panel.isVisible())) {
      await this.page.locator('.admin__data-grid-header:visible button[data-action="grid-filter-expand"]').first().click();
    }
    await expect(this.page.locator('.admin__data-grid-filters-wrap:visible')).toBeVisible();
  }

  /** Applies one column filter: text, textRange (from/to), dateRange (from/to) or select (ui-select or native). */
  async filter(column: GridColumn, value: string | { from?: string; to?: string }): Promise<void> {
    await this.openFilters();
    const filters = this.page.locator('.admin__data-grid-filters:visible');
    if (typeof value === 'object') {
      if (value.from !== undefined) await filters.locator(`input[name="${column.name}[from]"]`).fill(value.from);
      if (value.to !== undefined) await filters.locator(`input[name="${column.name}[to]"]`).fill(value.to);
    } else if (column.filter === 'select') {
      const native = filters.locator(`select[name="${column.name}"]`);
      if (await native.count()) {
        await native.selectOption({ label: value });
      } else {
        const field = filters.locator('.admin__form-field').filter({ has: this.page.locator('label', { hasText: new RegExp(`^\\s*${escape(column.label)}\\s*$`) }) }).first();
        await field.locator('.admin__action-multiselect').click();
        await field.locator('.admin__action-multiselect-menu-inner-item', { hasText: new RegExp(`^\\s*${escape(value)}\\s*$`) }).first().click();
      }
    } else {
      await filters.locator(`input[name="${column.name}"]`).fill(value);
    }
    await this.reloading(`apply ${column.name}`, () =>
      this.page.locator('.admin__data-grid-filters-wrap:visible button[data-action="grid-filter-apply"]').click()
    );
    // Waiting only for the loading mask to disappear is a race: it has not appeared yet the moment
    // the click returns, so the wait passes against the unfiltered grid and the rows read back are
    // the ones from before the filter. Magento lists an applied filter as a chip; wait for that
    // first, then let the grid settle.
    await expect(
      this.page.locator('.admin__data-grid-filters-current:visible .admin__current-filters-list li')
    ).not.toHaveCount(0, { timeout: 30_000 });
  }

  /**
   * Opens a select filter, picks its first option and applies it. Returns the option label and whether
   * a cell can be expected to show exactly that label (a tree such as website > store view selects a
   * group whose cells show the leaf names); null when the filter is not on the page (a store filter is
   * hidden in single-store mode). Every wait is bounded: a stalled select must not eat the test's budget.
   */
  async filterFirstOption(column: GridColumn): Promise<{ label: string; exact: boolean } | null> {
    await this.openFilters();
    const filters = this.page.locator('.admin__data-grid-filters:visible');
    const native = filters.locator(`select[name="${column.name}"]`);
    let label: string;
    let exact = true;
    if (await native.count()) {
      const options = await native.locator('option').evaluateAll((els) => els.map((o) => ({ value: (o as HTMLOptionElement).value, label: (o as HTMLOptionElement).text.trim() })));
      label = options.find((o) => o.value !== '')!.label;
      await native.selectOption({ label });
    } else {
      const field = filters.locator('.admin__form-field').filter({ has: this.page.locator('label', { hasText: new RegExp(`^\\s*${escape(column.label)}\\s*$`) }) }).first();
      if (!(await field.count()) || !(await field.isVisible())) return null;
      await field.locator('.admin__action-multiselect').click({ timeout: 10_000 });
      const all = field.locator('.admin__action-multiselect-menu-inner-item:visible');
      if (!(await all.count())) return null;
      const leaves = all.filter({ hasNot: this.page.locator('.admin__action-multiselect-menu-inner-item') });
      exact = (await leaves.count()) > 0;
      const item = exact ? leaves.first() : all.first();
      const labelEl = item.locator('.admin__action-multiselect-label');
      label = ((await labelEl.count()) ? await labelEl.first().innerText() : await item.innerText()).split('\n')[0].trim();
      await item.click({ timeout: 10_000 });
      const done = field.locator('button:visible', { hasText: /^\s*Done\s*$/ });
      if (await done.count()) await done.first().click({ timeout: 10_000 });
    }
    await this.page.locator('.admin__data-grid-filters-wrap:visible button[data-action="grid-filter-apply"]').click({ timeout: 10_000 });
    await this.settled();
    return { label, exact };
  }

  /** Ticks or unticks a column in the Columns control and returns whether its header is shown. */
  async toggleColumn(label: string): Promise<boolean> {
    const control = this.page.locator('.admin__data-grid-action-columns:visible').first();
    await control.locator('button.admin__action-dropdown').click();
    await control.locator('.admin__field-option label', { hasText: new RegExp(`^\\s*${escape(label)}\\s*$`) }).first().click();
    await control.locator('button.admin__action-dropdown').click();
    await this.settled();
    return (await this.header(label).count()) > 0;
  }

  noRecordsMessage(): Locator {
    return this.page.locator('.admin__data-grid-wrap .data-grid-tr-no-data');
  }
}

function escape(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
