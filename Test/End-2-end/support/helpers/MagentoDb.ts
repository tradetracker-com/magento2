/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';
import { shellQuote } from './Shell';

/** A `core_config_data` scope. `default` is the global fallback every other scope inherits from. */
export type ConfigScope = 'default' | 'websites' | 'stores';

/**
 * Database helper that wraps Docker exec + PDO boilerplate.
 * Eliminates repeated env.php → PDO connection setup across modules.
 */
export class MagentoDb {
  constructor(private container: string) {}

  /**
   * Run arbitrary PHP with $pdo pre-initialized as a PDO connection.
   * The PHP code should use $pdo and echo its result.
   */
  query(phpBody: string): string {
    const fullPhp = `
      foreach (['/var/www/html/app/etc/env.php', '/data/app/etc/env.php'] as $p) { if (file_exists($p)) { $env = include $p; break; } }
      $db = $env['db']['connection']['default'];
      $pdo = new PDO("mysql:host={$db['host']};dbname={$db['dbname']}", $db['username'], $db['password']);
      ${phpBody}
    `;

    // Single-quoted, not `bash -lc`: the PHP is full of `$pdo`, `$env` and `$db`, and every layer
    // of shell in between would expand them into nothing. `shellQuote` is the only quoting here.
    return execSync(`docker exec ${this.container} php -r ${shellQuote(fullPhp)}`, {
      stdio: 'pipe',
      timeout: 30000,
      // The default 1 MB buffer turns a large result into a bare "spawnSync /bin/sh ENOBUFS",
      // which says nothing about the query that produced it.
      maxBuffer: 64 * 1024 * 1024,
    }).toString();
  }

  /**
   * Set a config value in core_config_data (or delete the row if value is empty, which brings back
   * the config.xml default — it does not store an admin "No").
   */
  setConfig(path: string, value: string): void {
    this.setScopedConfig(path, value, 'default', 0);
  }

  /**
   * The stored value of each of these paths in one scope, as the rows read right now.
   *
   * A path with no row is absent from the result rather than present as an empty string: at store
   * scope that is the difference between "Use Default" and a stored empty value.
   */
  readConfig(paths: string[], scope: ConfigScope = 'default', scopeId = 0): Record<string, string> {
    if (paths.length === 0) {
      return {};
    }
    const b64 = Buffer.from(JSON.stringify(paths)).toString('base64');
    const out = this.query(`
      $paths = json_decode(base64_decode('${b64}'), true);
      $in = implode(',', array_map([$pdo, 'quote'], $paths));
      $stmt = $pdo->prepare("SELECT path, value FROM core_config_data WHERE scope = ? AND scope_id = ? AND path IN ($in)");
      $stmt->execute(['${scope}', ${scopeId}]);
      echo json_encode($stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    `).trim();
    const start = out.indexOf('{');

    return start >= 0 ? (JSON.parse(out.slice(start)) as Record<string, string>) : {};
  }

  /**
   * Set a config value in a specific scope.
   *
   * Worth reaching for deliberately: a value that exists only at website or store-view scope is
   * invisible to anything reading it from the admin, where the resolved store is the admin store
   * (id 0) and therefore falls back to the `default` row. Several modules have shipped an admin
   * controller that refused to run for exactly that reason while the storefront worked fine.
   */
  setScopedConfig(path: string, value: string, scope: ConfigScope, scopeId: number): void {
    if (value === '') {
      const b64Path = Buffer.from(path).toString('base64');
      this.query(`
        $stmt = $pdo->prepare("DELETE FROM core_config_data WHERE path = ? AND scope = ? AND scope_id = ?");
        $stmt->execute([base64_decode('${b64Path}'), '${scope}', ${scopeId}]);
        echo 'deleted';
      `);
      return;
    }

    const b64Path = Buffer.from(path).toString('base64');
    const b64Value = Buffer.from(value).toString('base64');
    this.query(`
      $path = base64_decode('${b64Path}');
      $value = base64_decode('${b64Value}');
      $stmt = $pdo->prepare("INSERT INTO core_config_data (scope, scope_id, path, value) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = ?");
      $stmt->execute(['${scope}', ${scopeId}, $path, $value, $value]);
      echo 'ok';
    `);
  }

  /**
   * Read a config value back out of a specific scope. Returns null when no row exists there —
   * which is not the same as the effective value, since Magento would fall back to `default`.
   */
  getScopedConfig(path: string, scope: ConfigScope = 'default', scopeId = 0): string | null {
    const b64Path = Buffer.from(path).toString('base64');
    const result = this.query(`
      $stmt = $pdo->prepare("SELECT value FROM core_config_data WHERE path = ? AND scope = ? AND scope_id = ?");
      $stmt->execute([base64_decode('${b64Path}'), '${scope}', ${scopeId}]);
      $row = $stmt->fetchColumn();
      echo $row === false ? '__NULL__' : $row;
    `).trim();

    return result === '__NULL__' ? null : result;
  }

  /**
   * Delete all config rows matching a LIKE pattern, in every scope, reverting to config.xml
   * defaults. Use between tests that write scoped config: a leftover website-scope row silently
   * changes the next test's answer.
   */
  resetConfig(pathPattern: string): void {
    const b64Pattern = Buffer.from(pathPattern).toString('base64');
    this.query(`
      $pattern = base64_decode('${b64Pattern}');
      $deleted = $pdo->exec("DELETE FROM core_config_data WHERE path LIKE " . $pdo->quote($pattern));
      echo "reset:$deleted";
    `);
  }

  /**
   * Set a product EAV attribute value by SKU.
   * For select/dropdown attributes, pass the option label -- it will be resolved to the option ID.
   */
  setProductAttribute(sku: string, attributeCode: string, value: string): void {
    const b64Value = Buffer.from(value).toString('base64');
    this.query(`
      $sku = '${sku}';
      $attrCode = '${attributeCode}';
      $value = base64_decode('${b64Value}');

      $stmt = $pdo->prepare("SELECT entity_id FROM catalog_product_entity WHERE sku = ?");
      $stmt->execute([$sku]);
      $entityId = $stmt->fetchColumn();
      if (!$entityId) { echo "SKU not found: $sku"; exit; }

      $stmt = $pdo->prepare("SELECT attribute_id, backend_type, frontend_input FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 4");
      $stmt->execute([$attrCode]);
      $attr = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$attr) { echo "Attribute not found: $attrCode"; exit; }

      $attrId = $attr['attribute_id'];
      $backendType = $attr['backend_type'];
      $frontendInput = $attr['frontend_input'];

      if (in_array($frontendInput, ['select', 'multiselect'], true)) {
        $stmt = $pdo->prepare("SELECT eaov.option_id FROM eav_attribute_option eao JOIN eav_attribute_option_value eaov ON eao.option_id = eaov.option_id WHERE eao.attribute_id = ? AND eaov.value = ? AND eaov.store_id = 0 LIMIT 1");
        $stmt->execute([$attrId, $value]);
        $optionId = $stmt->fetchColumn();
        if (!$optionId) {
          $pdo->prepare("INSERT INTO eav_attribute_option (attribute_id, sort_order) VALUES (?, 0)")->execute([$attrId]);
          $optionId = $pdo->lastInsertId();
          $pdo->prepare("INSERT INTO eav_attribute_option_value (option_id, store_id, value) VALUES (?, 0, ?)")->execute([$optionId, $value]);
        }
        $value = $optionId;
      }

      $table = "catalog_product_entity_$backendType";
      $stmt = $pdo->prepare("INSERT INTO $table (attribute_id, store_id, entity_id, value) VALUES (?, 0, ?, ?) ON DUPLICATE KEY UPDATE value = ?");
      $stmt->execute([$attrId, $entityId, $value, $value]);
      echo "ok";
    `);
  }

  /**
   * Get a category entity_id by its name. Returns the ID string or null.
   */
  getCategoryIdByName(name: string): string | null {
    const b64Name = Buffer.from(name).toString('base64');
    const result = this.query(`
      $name = base64_decode('${b64Name}');
      $stmt = $pdo->prepare("SELECT e.entity_id FROM catalog_category_entity e JOIN catalog_category_entity_varchar v ON e.entity_id = v.entity_id JOIN eav_attribute a ON v.attribute_id = a.attribute_id WHERE a.attribute_code = 'name' AND a.entity_type_id = 3 AND v.value = ? AND v.store_id = 0 LIMIT 1");
      $stmt->execute([$name]);
      $id = $stmt->fetchColumn();
      echo $id ?: '';
    `).trim();

    return result || null;
  }

  /**
   * Create a category under a given parent. Returns the new entity_id.
   * If a category with the same name already exists under that parent, returns the existing ID.
   */
  createCategory(name: string, parentId: number = 2, isActive: boolean = true): string {
    const b64Name = Buffer.from(name).toString('base64');
    const result = this.query(`
      $name = base64_decode('${b64Name}');
      $parentId = ${parentId};
      $isActive = ${isActive ? '1' : '0'};

      // Check if already exists under this parent
      $stmt = $pdo->prepare("
        SELECT e.entity_id FROM catalog_category_entity e
        JOIN catalog_category_entity_varchar v ON e.entity_id = v.entity_id
        JOIN eav_attribute a ON v.attribute_id = a.attribute_id
        WHERE a.attribute_code = 'name' AND a.entity_type_id = 3
        AND v.value = ? AND v.store_id = 0 AND e.parent_id = ?
        LIMIT 1
      ");
      $stmt->execute([$name, $parentId]);
      $existing = $stmt->fetchColumn();
      if ($existing) { echo $existing; exit; }

      // Get parent path and level
      $stmt = $pdo->prepare("SELECT path, level FROM catalog_category_entity WHERE entity_id = ?");
      $stmt->execute([$parentId]);
      $parent = $stmt->fetch(PDO::FETCH_ASSOC);
      $parentPath = $parent['path'];
      $level = (int)$parent['level'] + 1;

      // Get next children_count position
      $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM catalog_category_entity WHERE parent_id = ?");
      $stmt->execute([$parentId]);
      $position = (int)$stmt->fetchColumn();

      // Insert entity
      $pdo->prepare("INSERT INTO catalog_category_entity (parent_id, path, level, position, children_count) VALUES (?, '', ?, ?, 0)")->execute([$parentId, $level, $position]);
      $entityId = $pdo->lastInsertId();

      // Update path
      $path = $parentPath . '/' . $entityId;
      $pdo->prepare("UPDATE catalog_category_entity SET path = ? WHERE entity_id = ?")->execute([$path, $entityId]);

      // Set name attribute
      $stmt = $pdo->prepare("SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 3");
      $stmt->execute();
      $nameAttrId = $stmt->fetchColumn();
      $pdo->prepare("INSERT INTO catalog_category_entity_varchar (attribute_id, store_id, entity_id, value) VALUES (?, 0, ?, ?)")->execute([$nameAttrId, $entityId, $name]);

      // Set is_active attribute
      $stmt = $pdo->prepare("SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'is_active' AND entity_type_id = 3");
      $stmt->execute();
      $activeAttrId = $stmt->fetchColumn();
      $pdo->prepare("INSERT INTO catalog_category_entity_int (attribute_id, store_id, entity_id, value) VALUES (?, 0, ?, ?)")->execute([$activeAttrId, $entityId, $isActive]);

      // Set url_key attribute
      $stmt = $pdo->prepare("SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'url_key' AND entity_type_id = 3");
      $stmt->execute();
      $urlKeyAttrId = $stmt->fetchColumn();
      $urlKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
      $pdo->prepare("INSERT INTO catalog_category_entity_varchar (attribute_id, store_id, entity_id, value) VALUES (?, 0, ?, ?)")->execute([$urlKeyAttrId, $entityId, $urlKey]);

      // Update parent children_count
      $pdo->prepare("UPDATE catalog_category_entity SET children_count = children_count + 1 WHERE entity_id = ?")->execute([$parentId]);

      echo $entityId;
    `).trim();

    return result;
  }

  /**
   * Assign a product to a category by SKU.
   */
  assignProductToCategory(sku: string, categoryId: string): void {
    const result = this.query(`
      $sku = '${sku}';
      $categoryId = ${categoryId};

      $stmt = $pdo->prepare("SELECT entity_id FROM catalog_product_entity WHERE sku = ?");
      $stmt->execute([$sku]);
      $productId = $stmt->fetchColumn();
      if (!$productId) { echo "SKU not found: $sku"; exit; }

      $pdo->prepare("INSERT IGNORE INTO catalog_category_product (category_id, product_id, position) VALUES (?, ?, 0)")->execute([$categoryId, $productId]);
      echo 'ok';
    `).trim();

    if (result !== 'ok') {
      throw new Error(`Failed to assign product to category: ${result}`);
    }
  }

  /**
   * Remove a product from a category by SKU.
   */
  removeProductFromCategory(sku: string, categoryId: string): void {
    this.query(`
      $sku = '${sku}';
      $categoryId = ${categoryId};

      $stmt = $pdo->prepare("SELECT entity_id FROM catalog_product_entity WHERE sku = ?");
      $stmt->execute([$sku]);
      $productId = $stmt->fetchColumn();
      if (!$productId) { echo "done"; exit; }

      $pdo->prepare("DELETE FROM catalog_category_product WHERE category_id = ? AND product_id = ?")->execute([$categoryId, $productId]);
      echo 'ok';
    `);
  }

  /**
   * Delete a category by entity_id (cleanup helper).
   */
  deleteCategory(categoryId: string): void {
    this.query(`
      $id = ${categoryId};
      $pdo->prepare("DELETE FROM catalog_category_entity_varchar WHERE entity_id = ?")->execute([$id]);
      $pdo->prepare("DELETE FROM catalog_category_entity_int WHERE entity_id = ?")->execute([$id]);
      $pdo->prepare("DELETE FROM catalog_category_entity_text WHERE entity_id = ?")->execute([$id]);
      $pdo->prepare("DELETE FROM catalog_category_entity_decimal WHERE entity_id = ?")->execute([$id]);
      $pdo->prepare("DELETE FROM catalog_category_entity_datetime WHERE entity_id = ?")->execute([$id]);
      $pdo->prepare("DELETE FROM catalog_category_product WHERE category_id = ?")->execute([$id]);
      $pdo->prepare("DELETE FROM catalog_category_entity WHERE entity_id = ?")->execute([$id]);
      echo 'ok';
    `);
  }

  /**
   * Remove a product EAV attribute value (store 0) by SKU, so the product reads as having none.
   */
  clearProductAttribute(sku: string, attributeCode: string): void {
    const b64Sku = Buffer.from(sku).toString('base64');
    const b64Code = Buffer.from(attributeCode).toString('base64');
    this.query(`
      $sku = base64_decode('${b64Sku}');
      $attrCode = base64_decode('${b64Code}');
      $stmt = $pdo->prepare("SELECT entity_id FROM catalog_product_entity WHERE sku = ?");
      $stmt->execute([$sku]);
      $entityId = $stmt->fetchColumn();
      if (!$entityId) { echo "SKU not found: $sku"; exit; }
      $stmt = $pdo->prepare("SELECT attribute_id, backend_type FROM eav_attribute WHERE attribute_code = ? AND entity_type_id = 4");
      $stmt->execute([$attrCode]);
      $attr = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$attr) { echo "Attribute not found: $attrCode"; exit; }
      $table = 'catalog_product_entity_' . $attr['backend_type'];
      $pdo->prepare("DELETE FROM $table WHERE attribute_id = ? AND entity_id = ?")->execute([$attr['attribute_id'], $entityId]);
      echo 'cleared';
    `);
  }
}
