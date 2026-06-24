/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Build an absolute admin URL from a relative admin path.
 *
 * IMPORTANT — Admin URL patterns:
 * - Custom module routes (grids, controllers): /admin/{frontName}/{controller}/{action}
 *   Example: adminUrl('/admin/accounting/product/index')
 *
 * - Core Magento routes (system config): /admin/admin/{route}/{action}
 *   Example: adminConfigUrl('accounting_base')
 *
 * Check etc/adminhtml/routes.xml for the module's frontName.
 */
export function adminUrl(path: string): string {
  const baseURL = process.env.BASE_URL || 'https://magento.test/';
  return new URL(path, baseURL).toString();
}

/**
 * Build an admin system config URL for a given section.
 *
 * @example adminConfigUrl('katanapim_general')
 * // => https://magento.test/admin/admin/system_config/edit/section/katanapim_general
 */
export function adminConfigUrl(section: string): string {
  return adminUrl(`/admin/admin/system_config/edit/section/${section}`);
}
