/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';
import { MagentoDb } from '../helpers/MagentoDb';

/**
 * Shared base class for module-specific API services.
 * Provides config management, cache flushing, REST, CLI and DB helpers.
 */
export default class BaseApi {

  /**
   * Decrypt stored config values, by path.
   *
   * A field declared `type="obscure"` with Magento's Encrypted backend model stores ciphertext
   * ("0:3:..."), so reading the row back and comparing it with what was typed never matches.
   *
   * @param paths config paths whose stored value should be decrypted
   */
  decryptConfig(paths: string[]): Record<string, string> {
    if (!paths.length) return {};
    const b64 = Buffer.from(JSON.stringify(paths)).toString('base64');
    const json = this.runMagentoPhp(`
      $paths = json_decode(base64_decode('${b64}'), true);
      $encryptor = $om->get(\\Magento\\Framework\\Encryption\\EncryptorInterface::class);
      // Magento's own connection: this snippet runs inside the application, so there are no
      // database credentials to hand around.
      $connection = $om->get(\\Magento\\Framework\\App\\ResourceConnection::class)->getConnection();
      $table = $om->get(\\Magento\\Framework\\App\\ResourceConnection::class)->getTableName('core_config_data');
      $out = [];
      foreach ($paths as $path) {
        $value = $connection->fetchOne(
          $connection->select()->from($table, ['value'])->where('path = ?', $path)->order('scope_id ASC')->limit(1)
        );
        $out[$path] = $value === false ? null : $encryptor->decrypt((string)$value);
      }
      echo json_encode($out);
    `).trim();
    const start = json.indexOf('{');

    return start === -1 ? {} : JSON.parse(json.slice(start));
  }
  protected db: MagentoDb | null;
  protected container: string | undefined;
  private magentoRoot: string | null = null;

  constructor() {
    this.container = process.env.MAGENTO_CONTAINER;
    this.db = this.container ? new MagentoDb(this.container) : null;
  }

  /**
   * The DB helper, or a clear error when the suite runs without a container.
   */
  requireDb(): MagentoDb {
    if (!this.db) {
      throw new Error('MAGENTO_CONTAINER env var is required');
    }
    return this.db;
  }

  /**
   * Set Magento configuration values at default scope and clean the config cache.
   * Uses docker exec if MAGENTO_CONTAINER is set, otherwise config-setter.php.
   *
   * cache:clean, not cache:flush: cleaning the config type runs through Magento's WarmConfigCache
   * plugin, which takes the config lock, waits for a request that is still generating the config
   * cache, and rewrites the cache from the database itself. cache:flush wipes the backend without
   * that lock, and a request that outlived the previous test then saved the config it had read
   * before our write on top of the flush (Rich Snippets on the GitHub runner, 2026-10-26/27).
   */
  async setMagentoConfig(baseURL: string, configs: Record<string, string | number>): Promise<void> {
    if (this.container && this.db) {
      this.setBaseConfig(configs);
      execSync(`docker exec ${this.container} bin/magento cache:clean config`, { stdio: 'pipe' });
    } else {
      await this.setConfigViaHelper(baseURL, configs);
    }
  }

  /**
   * Write config rows at default scope without touching any cache.
   */
  setBaseConfig(configs: Record<string, string | number>): void {
    const db = this.requireDb();
    for (const [configPath, value] of Object.entries(configs)) {
      db.setConfig(configPath, String(value));
      console.log(`Config set: ${configPath} = ${value}`);
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
   * Flush every cache backend, then rebuild the config cache under Magento's config lock.
   *
   * cache:flush is the only command that also empties the page cache and a module cache when the
   * web tier still has those types enabled (cache:clean skips a disabled type, and a php-fpm with
   * opcache.revalidate_freq=60, as on the VPS, keeps using the env.php it started with for a
   * minute). The lock-guarded cache:clean config afterwards waits for a request that is still
   * generating the config cache and writes the current database state over whatever it saved.
   */
  flushAllCaches(): void {
    if (!this.container) return;
    execSync(`docker exec ${this.container} bin/magento cache:flush`, { stdio: 'pipe', timeout: 60000 });
    execSync(`docker exec ${this.container} bin/magento cache:clean config`, {
      stdio: 'pipe',
      timeout: 60000,
    });
    console.log('All caches flushed.');
    this.warmStoreResolution();
  }

  /**
   * Compute the allowed-store list from the CLI so the web tier reads it instead of building it.
   *
   * StoreResolver silently serves the default store view when the requested store is missing from
   * this list, and the list is a derived entry in the config cache that whichever request comes
   * first after a flush fills. Asking for it here, from a process whose store list is current,
   * leaves a correct value behind. Called after every flush, because each flush drops it again.
   *
   * This is not enough on its own for a store view created while the suite is running - see the
   * note on store fixtures in global-setup.ts. It is worth doing regardless: it keeps the derived
   * list consistent with the database for every other spec.
   *
   * Returns the store ids the resolver will accept, or null when it could not be determined.
   */
  warmStoreResolution(): string[] | null {
    if (!this.container) return null;

    try {
      const out = this.runMagentoPhp(`
        // Drop the derived entry first: StoresData::getStoresData() reads its cache before it
        // computes, so asking without this hands back the value it is meant to replace.
        $om->get(\\Magento\\Framework\\App\\Cache\\TypeListInterface::class)->cleanType('config');

        $resolver = $om->get(\\Magento\\Store\\Model\\StoreResolver::class);
        $reflection = new \\ReflectionObject($resolver);
        // No setAccessible(): a no-op since PHP 8.1 and a deprecation on 8.5, which developer mode
        // turns into a fatal.
        $readProperty = static function (string $name) use ($reflection, $resolver) {
            return $reflection->getProperty($name)->getValue($resolver);
        };
        $runMode = (string)$readProperty('runMode');
        $scopeCode = $readProperty('scopeCode');

        [$allowed, $default] = $om->get(\\Magento\\Store\\Model\\StoresData::class)
            ->getStoresData($runMode, $scopeCode === null ? null : (string)$scopeCode);

        echo json_encode(['allowed' => array_values($allowed), 'default' => $default]);
      `);

      const parsed = JSON.parse(out.trim().split('\n').pop() || '{}');
      const allowed = (parsed.allowed ?? []).map(String);
      console.log(`Store resolution warmed: stores ${allowed.join(',')}, default ${parsed.default}`);

      return allowed;
    } catch (error) {
      // Named rather than swallowed: a suite that reports a clean flush while this failed would
      // make every later store assertion untrustworthy.
      console.log('Store resolution warm-up failed: ' + String(error));

      return null;
    }
  }

  /**
   * Clean only the named cache types by their tags (to prove what the module refreshes by
   * itself). `cache:flush` would wipe the whole storage the types share.
   */
  cleanCacheTypes(types: string[]): void {
    if (!this.container) return;
    execSync(`docker exec ${this.container} bin/magento cache:clean ${types.join(' ')}`, {
      stdio: 'pipe',
      timeout: 60000,
    });
    console.log(`Caches cleaned: ${types.join(', ')}`);
  }

  /**
   * A new cache type ships disabled until the admin enables it; the per-type commands only act
   * on enabled types.
   */
  enableCacheType(type: string): void {
    if (!this.container) return;
    execSync(`docker exec ${this.container} bin/magento cache:enable ${type}`, { stdio: 'pipe', timeout: 60000 });
  }

  /**
   * Set a product EAV attribute value (store 0) by SKU.
   */
  setProductAttribute(sku: string, attributeCode: string, value: string): void {
    this.requireDb().setProductAttribute(sku, attributeCode, value);
    console.log(`Product attribute set: ${sku}.${attributeCode} = ${value}`);
    this.flushAllCaches();
  }

  /**
   * Remove a product EAV attribute value (store 0) by SKU.
   */
  clearProductAttribute(sku: string, attributeCode: string): void {
    this.requireDb().clearProductAttribute(sku, attributeCode);
    console.log(`Product attribute cleared: ${sku}.${attributeCode}`);
    this.flushAllCaches();
  }

  /**
   * Run a Magento CLI command inside the container and return its output.
   */
  runCli(command: string): string {
    if (!this.container) {
      throw new Error('MAGENTO_CONTAINER env var is required');
    }

    try {
      return execSync(`docker exec ${this.container} bin/magento ${command} 2>&1`, {
        encoding: 'utf-8',
        timeout: 300000,
      });
    } catch (error: any) {
      // execSync throws on a non-zero exit and hides the command's own output, which turns every
      // CLI failure into "Command failed" with no reason. Put the output in the message.
      const output = [error?.stdout, error?.stderr].filter(Boolean).join('\n').trim();
      throw new Error(`bin/magento ${command} exited ${error?.status}\n${output}`);
    }
  }

  /**
   * Run a PHP snippet with Magento bootstrapped. $om is the object manager, area code is set to
   * adminhtml so admin-only services resolve.
   */
  runMagentoPhp(snippet: string): string {
    if (!this.container) {
      throw new Error('MAGENTO_CONTAINER env var is required');
    }

    const php = `
      foreach (['/var/www/html/app/bootstrap.php', '/data/app/bootstrap.php'] as $b) {
        if (file_exists($b)) { require $b; break; }
      }
      $bootstrap = \\Magento\\Framework\\App\\Bootstrap::create(BP, $_SERVER);
      $om = $bootstrap->getObjectManager();
      try { $om->get(\\Magento\\Framework\\App\\State::class)->setAreaCode('adminhtml'); } catch (\\Throwable $e) {}
      $om->get(\\Magento\\Framework\\Registry::class)->register('isSecureArea', true, true);
      ${snippet}
    `;

    const escaped = php.replace(/'/g, "'\\''");

    try {
      return execSync(`docker exec ${this.container} php -r '${escaped}'`, {
        encoding: 'utf-8',
        timeout: 120000,
        maxBuffer: 64 * 1024 * 1024,
      });
    } catch (error: any) {
      const output = [error?.stdout, error?.stderr].filter(Boolean).join('\n').trim();
      throw new Error(`Magento PHP snippet exited ${error?.status}\n${output}`);
    }
  }

  /**
   * Call the Magento REST API as the E2E admin (token acquired in global-setup).
   *
   * Throws with the response body on a non-2xx status, so a failing fixture names its cause.
   */
  async rest<T = any>(baseURL: string, method: 'GET' | 'POST' | 'PUT' | 'DELETE', path: string, body?: unknown): Promise<T> {
    const token = process.env.admin_token;
    if (!token) {
      throw new Error('No admin token: global-setup did not run or the token request failed');
    }

    const url = baseURL + path.replace(/^\//, '');
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });

    const text = await response.text();
    if (!response.ok) {
      throw new Error(`${method} ${url} -> HTTP ${response.status}: ${text.slice(0, 1000)}`);
    }

    return (text ? JSON.parse(text) : null) as T;
  }

  /**
   * Run a PHP snippet inside the container (for custom queries).
   */
  /**
   * The configuration path prefix and cron job prefix of this module, e.g. "digitec_galaxus".
   *
   * The baseline layer is identical in every module, so everything it needs from the API lives
   * here; only this one string differs. Override it in the module's own service.
   */
  protected modulePrefix(): string {
    throw new Error('modulePrefix() must be implemented by the module API');
  }

  /**
   * Run a console command and report how it went, rather than throwing.
   *
   * The baseline asserts on the exit code of commands that are expected to refuse, so a non-zero
   * exit is an answer here, not a failure.
   */
  runCliRaw(command: string): { exitCode: number; output: string } {
    try {
      const output = execSync(
        `docker exec ${this.container} bin/magento ${command} 2>&1`,
        { stdio: 'pipe', timeout: 300000 }
      ).toString();
      return { exitCode: 0, output };
    } catch (error) {
      const e = error as { status?: number; stdout?: Buffer; stderr?: Buffer };
      return { exitCode: e.status ?? 1, output: `${e.stdout?.toString() ?? ''}${e.stderr?.toString() ?? ''}` };
    }
  }

  /** Every cron job this module registers, read from Magento's own cron configuration. */
  /** Magento lists an entry with a schedule and no class too; `instance` is null for those. */
  cronJobs(): { name: string; instance: string | null; method: string | null; group: string }[] {
    const prefix = this.modulePrefix();
    const json = this.runMagentoPhp(`
      $config = $om->get(\\Magento\\Cron\\Model\\ConfigInterface::class)->getJobs();
      $jobs = [];
      foreach ($config as $group => $groupJobs) {
        foreach ($groupJobs as $name => $job) {
          if (strpos($name, '${prefix}') !== 0) { continue; }
          $jobs[] = ['name' => $name, 'instance' => $job['instance'] ?? null, 'method' => $job['method'] ?? null, 'group' => $group];
        }
      }
      echo json_encode($jobs);
    `).trim();
    const start = json.indexOf('[');
    return start >= 0 ? JSON.parse(json.slice(start)) : [];
  }

  /** Call one cron job the way Magento would, and return whatever it printed or threw. */
  runCronJob(instance: string | null, method: string | null): string {
    if (!instance || !method) {
      throw new Error('runCronJob needs a class and a method; this entry has none');
    }

    return this.runMagentoPhp(`
      try {
        $job = $om->get('${instance}');
        $schedule = $om->create(\\Magento\\Cron\\Model\\Schedule::class);
        $job->${method}($schedule);
        echo "ok";
      } catch (\\Throwable $e) {
        echo "Exception: " . get_class($e) . ": " . $e->getMessage();
      }
    `);
  }

  /** Remove every stored value of this module, so a spec starts from the shipped defaults. */
  resetModuleConfig(): void {
    const prefix = this.modulePrefix();
    this.execInContainer(`
      $pdo->exec("DELETE FROM core_config_data WHERE path LIKE '${prefix}%'");
      echo 'reset';
    `);
    this.flushAllCaches();
  }

  /** How many lines a log file holds right now, so a spec can read only what it adds. */
  countLogLines(file: string): number {
    try {
      const out = execSync(
        `docker exec ${this.container} sh -c "wc -l < var/log/${file} 2>/dev/null || echo 0"`,
        { stdio: 'pipe' }
      ).toString().trim();
      return Number(out) || 0;
    } catch {
      return 0;
    }
  }

  /** The lines a log file gained since countLogLines() was called. */
  readLogLinesSince(file: string, baseline: number): string[] {
    try {
      const out = execSync(
        `docker exec ${this.container} sh -c "tail -n +${baseline + 1} var/log/${file} 2>/dev/null || true"`,
        { stdio: 'pipe' }
      ).toString();
      return out.split('\n').filter((line) => line.trim() !== '');
    } catch {
      return [];
    }
  }

  protected execInContainer(phpCode: string): string {
    return this.requireDb().query(phpCode);
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
}
