/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect, Locator, Page } from '@playwright/test';
import { adminUrl } from '../../helpers/AdminUrl';
import { MAGENTO_ERROR } from '../../helpers/MagentoErrors';

/**
 * Stores > Configuration, driven by element id rather than by a typed group.
 *
 * The baseline specs work out which fields exist from Magento's own configuration structure, so
 * they cannot name them in advance the way ConfigPage's typed API does. Both page objects are
 * real: this one for the generic layer, ConfigPage for specs that assert on a known field.
 *
 * Works on the page the admin project is logged in to; the configure script disables admin secret
 * keys, otherwise opening a section by URL lands on the dashboard.
 */
export default class BaselineConfigPage {
  constructor(private readonly page: Page) {}

  async open(section: string): Promise<void> {
    await this.page.goto(adminUrl(`/admin/admin/system_config/edit/section/${section}`));
    await this.page.waitForLoadState('networkidle');
    await expect(this.page.locator('#config-edit-form')).toBeVisible();
    await expect(this.page.locator('body')).not.toContainText(MAGENTO_ERROR);
  }

  /** Expands a collapsed group (fieldset) by clicking its header, like the user does. */
  async openGroup(groupId: string): Promise<void> {
    const head = this.page.locator(`#${groupId}-head`);
    await expect(head).toBeVisible();
    if (!((await head.getAttribute('class')) ?? '').includes('open')) {
      await head.click();
    }
  }

  /** Expands every visible group header on the page and returns the ids it opened. */
  async expandAllGroups(): Promise<string[]> {
    const opened: string[] = [];
    const heads = this.page.locator('.section-config > .entry-edit-head > a[id$="-head"]');
    for (let i = 0; i < await heads.count(); i++) {
      const head = heads.nth(i);
      if (!await head.isVisible()) continue;
      if (!((await head.getAttribute('class')) ?? '').includes('open')) {
        await head.click();
      }
      opened.push(((await head.getAttribute('id')) ?? '').replace(/-head$/, ''));
    }
    return opened;
  }

  /** The rows of a dynamic-rows (array) field, by the field's HTML id. */
  rows(fieldId: string): Locator {
    return this.page.locator(`#${fieldId} tbody tr`);
  }

  /** Clicks "Add" under a dynamic-rows field and returns the new row. */
  async addRow(fieldId: string): Promise<Locator> {
    await this.page.locator(`#${fieldId} button.action-add`).first().click();
    const row = this.rows(fieldId).last();
    await expect(row).toBeVisible();
    return row;
  }

  /** Switches the configuration scope through the scope switcher (store view code or 'default'). */
  async openAtScope(section: string, scope: 'default' | 'website' | 'store', scopeId = 0): Promise<void> {
    const suffix = scope === 'default' ? '' : `/${scope}/${scopeId}`;
    await this.page.goto(adminUrl(`/admin/admin/system_config/edit/section/${section}${suffix}`));
    await this.page.waitForLoadState('networkidle');
    await expect(this.page.locator('#config-edit-form')).toBeVisible();
    await expect(this.page.locator('body')).not.toContainText(MAGENTO_ERROR);
  }

  /** Whether the field row is currently rendered (depends-hidden rows are not). */
  async isFieldVisible(elementId: string): Promise<boolean> {
    const row = this.page.locator(`#row_${elementId}`);
    return (await row.count()) > 0 && await row.isVisible();
  }

  /**
   * Sets one field through the form: unticks "Use Default/Website" first at a non-default scope,
   * then selects, multi-selects or fills. Returns false when the field is not on the page.
   */
  async setField(elementId: string, value: string | string[]): Promise<boolean> {
    if (!await this.isFieldVisible(elementId)) return false;
    const inherit = this.page.locator(`#${elementId}_inherit`);
    if (await inherit.count() && await inherit.isChecked()) {
      await inherit.uncheck();
    }
    const input = this.page.locator(`#${elementId}`);
    if (!await input.count()) return false;
    const tag = await input.evaluate((el) => el.tagName.toLowerCase());
    if (tag === 'select') {
      const multiple = await input.evaluate((el) => (el as HTMLSelectElement).multiple);
      await input.selectOption(multiple ? (Array.isArray(value) ? value : [value]) : (Array.isArray(value) ? value[0] : value));
    } else {
      await input.fill(Array.isArray(value) ? value.join(',') : value);
    }
    return true;
  }

  /**
   * Fills one row of a field-array renderer: clicks its Add button, then gives every select in the
   * new row its first non-empty option and every text input a value. Returns false when no Add
   * button exists for the field.
   */
  async fillRendererRow(elementId: string, text = 'e2e'): Promise<boolean> {
    const row = this.page.locator(`#row_${elementId}`);
    if (!await row.count() || !await row.isVisible()) return false;
    const add = row.locator('button.action-add, button[id$="_add"], .action-add').first();
    if (!await add.count()) return false;
    // At website/store scope the array is read-only until "Use Default" is unticked; at default scope some arrays disable Add while inheriting.
    const inherit = row.locator('input[type="checkbox"][id$="_inherit"]');
    if (await inherit.count() && await inherit.isChecked()) await inherit.uncheck();
    if (await add.isDisabled()) return false;
    const before = await row.locator('tbody tr').count();
    await add.click();
    const newRow = row.locator('tbody tr').nth(before);
    await expect(newRow).toBeVisible();
    // Log what went into the row: which option is "first" depends on the shop's attribute list, so a
    // row filled here can behave differently on two installations that are otherwise identical.
    const chosen: string[] = [];
    for (const select of await newRow.locator('select').all()) {
      const options = await select.locator('option').evaluateAll((els) => els.map((o) => (o as HTMLOptionElement).value).filter((v) => v !== ''));
      if (options.length) {
        await select.selectOption(options[0]);
        chosen.push(options[0]);
      }
    }
    for (const input of await newRow.locator('input[type="text"], input:not([type])').all()) {
      await input.fill(text);
      chosen.push(text);
    }
    console.log(`[row] ${elementId}: ${chosen.join(' | ')}`);
    return true;
  }

  async save(): Promise<void> {
    await this.page.locator('#save').click();
    await this.page.waitForLoadState('networkidle');
    const errors = await this.page.locator('.message-error').allInnerTexts();
    expect(errors, 'the save reported an error').toEqual([]);
    if (await this.page.locator('.message-success').count() === 0) {
      // Neither a success nor an error says nothing about why: a form that never submitted, a
      // redirect to another page and a validation error without a message all look the same here.
      // Report where the browser ended up and what it was shown.
      const messages = (await this.page.locator('.messages, .message').allInnerTexts()).join(' | ') || '(no messages)';
      const invalid = (await this.page.locator('.mage-error:visible, .admin__field-error:visible').allInnerTexts()).join(' | ')
        || '(no field errors)';
      throw new Error(
        `The configuration save produced no message.\nURL: ${this.page.url()}\nTitle: ${await this.page.title()}`
        + `\nMessages: ${messages}\nField errors: ${invalid}`
      );
    }
    await expect(this.page.locator('.message-success')).toBeVisible({ timeout: 15000 });
  }
}
