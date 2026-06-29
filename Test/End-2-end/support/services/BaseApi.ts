/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';
import { MagentoDb } from '../helpers/MagentoDb';

/**
 * Shared base class for module-specific API services.
 * Provides config management, cache flushing, and DB helpers.
 *
 * Extend this class in your module and add module-specific methods.
 */
export default class BaseApi {
  protected db: MagentoDb | null;
  protected container: string | undefined;
  private magentoRoot: string | null = null;

  constructor() {
    this.container = process.env.MAGENTO_CONTAINER;
    this.db = this.container ? new MagentoDb(this.container) : null;
  }

  /**
   * Detect the Magento root directory inside the container.
   * Checks /var/www/html and /data (CI image uses /data).
   */
  getMagentoRoot(): string {
    if (this.magentoRoot) return this.magentoRoot;

    const result = this.execInContainer(`
      foreach (['/var/www/html', '/data'] as $root) {
        if (file_exists($root . '/app/etc/env.php')) { echo $root; exit; }
      }
      echo '/var/www/html';
    `).trim();

    this.magentoRoot = result;
    console.log(`Magento root detected: ${this.magentoRoot}`);
    return this.magentoRoot;
  }

  /**
   * Set Magento configuration values and flush config cache.
   * Uses docker exec if MAGENTO_CONTAINER is set, otherwise config-setter.php.
   */
  async setMagentoConfig(baseURL: string, configs: Record<string, string | number>): Promise<void> {
    if (this.container && this.db) {
      for (const [configPath, value] of Object.entries(configs)) {
        this.db.setConfig(configPath, String(value));
        console.log(`Config set: ${configPath} = ${value}`);
      }
      const root = this.getMagentoRoot();
      execSync(`docker exec ${this.container} php ${root}/bin/magento cache:flush config`, { stdio: 'pipe' });
    } else {
      await this.setConfigViaHelper(baseURL, configs);
    }
  }

  /**
   * Delete all config rows matching a LIKE pattern, reverting to config.xml defaults.
   */
  resetConfig(pathPattern: string): void {
    if (!this.db) return;
    this.db.resetConfig(pathPattern);
    console.log(`Config reset: ${pathPattern}`);
  }

  /**
   * Flush full page cache and related cache types.
   */
  flushPageCache(): void {
    if (!this.container) return;
    const root = this.getMagentoRoot();
    execSync(`docker exec ${this.container} php ${root}/bin/magento cache:flush full_page block_html layout config`, {
      stdio: 'pipe',
      timeout: 30000,
    });
    console.log('Page cache flushed.');
  }

  /**
   * Get the active frontend theme_id from core_config_data.
   */
  getActiveThemeId(): string | null {
    if (!this.db) return null;
    const result = this.db.query(`
      $stmt = $pdo->query("SELECT value FROM core_config_data WHERE path = 'design/theme/theme_id' AND scope = 'default' AND scope_id = 0");
      $val = $stmt->fetchColumn();
      echo $val ?: "";
    `);
    return result.trim() || null;
  }

  /**
   * Flush all caches.
   */
  flushAllCaches(): void {
    if (!this.container) return;
    const root = this.getMagentoRoot();
    execSync(`docker exec ${this.container} php ${root}/bin/magento cache:flush`, {
      stdio: 'pipe',
      timeout: 30000,
    });
    console.log('All caches flushed.');
  }

  /**
   * Set a product EAV attribute value by SKU.
   */
  setProductAttribute(sku: string, attributeCode: string, value: string): void {
    if (!this.db) {
      throw new Error('MAGENTO_CONTAINER env var is required');
    }
    this.db.setProductAttribute(sku, attributeCode, value);
    console.log(`Product attribute set: ${sku}.${attributeCode} = ${value}`);
    this.flushAllCaches();
  }

  /**
   * Get a category entity_id by its name.
   */
  getCategoryIdByName(name: string): string | null {
    if (!this.db) return null;
    return this.db.getCategoryIdByName(name);
  }

  /**
   * Get the effective tax rate (%) for a product based on its tax class and the store's origin country.
   * Queries the tax_calculation_rate table for the matching country.
   */
  getProductTaxRate(productId: string): number {
    const result = this.execInContainer(`
      $taxClassId = $pdo->query("
        SELECT value FROM catalog_product_entity_int
        WHERE entity_id = ${productId}
          AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'tax_class_id' AND entity_type_id = 4)
          AND store_id = 0
      ")->fetchColumn();
      if (!$taxClassId) { echo '0'; return; }

      $originCountry = $pdo->query("
        SELECT value FROM core_config_data WHERE path = 'shipping/origin/country_id' ORDER BY scope_id ASC LIMIT 1
      ")->fetchColumn();
      if (!$originCountry) { $originCountry = 'US'; }

      $rate = $pdo->prepare("
        SELECT tcr.rate FROM tax_calculation_rate tcr
        JOIN tax_calculation tc ON tc.tax_calculation_rate_id = tcr.tax_calculation_rate_id
        JOIN tax_calculation_rule tcrl ON tcrl.tax_calculation_rule_id = tc.tax_calculation_rule_id
        WHERE tcr.tax_country_id = ?
          AND tc.product_tax_class_id = ?
        LIMIT 1
      ");
      $rate->execute([$originCountry, $taxClassId]);
      $rateValue = $rate->fetchColumn();
      echo $rateValue ? number_format((float)$rateValue, 4, '.', '') : '0';
    `);
    return parseFloat(result) || 0;
  }

  /**
   * Run a PHP snippet inside the container (for custom queries).
   */
  protected execInContainer(phpCode: string): string {
    if (!this.db) {
      throw new Error('MAGENTO_CONTAINER env var is required');
    }
    return this.db.query(phpCode);
  }

  private async setConfigViaHelper(baseURL: string, configs: Record<string, string | number>): Promise<void> {
    const token = process.env.admin_token;
    const configArray = Object.entries(configs).map(([path, value]) => ({
      path,
      value: String(value),
    }));

    const response = await fetch(`${baseURL}opt/config-setter.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token, configs: configArray }),
    });

    if (!response.ok) {
      const text = await response.text();
      throw new Error(`Failed to set config: ${response.status} - ${text}`);
    }

    const result = await response.json() as any;
    console.log('Config set:', result.configs_set);
  }
}
