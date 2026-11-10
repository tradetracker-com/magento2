/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import * as fs from 'fs';
import * as path from 'path';

/**
 * Every admin route the module exposes, derived from Controller/Adminhtml: a new controller is part of
 * the baseline the moment it is added. The route is `<frontName>/<controller>/<action>` with the first
 * letter of each part lower-cased, the way Magento resolves it.
 */
export interface AdminRoute { file: string; path: string; postOnly: boolean }

const MODULE_DIR = path.join(__dirname, '../../../..');
const CONTROLLER_DIR = path.join(MODULE_DIR, 'Controller/Adminhtml');
const ROUTES_XML = path.join(MODULE_DIR, 'etc/adminhtml/routes.xml');

/**
 * The frontName comes out of routes.xml, never out of a constant here: a module whose
 * frontName differs from its name used to make every route in this spec a 404.
 */
export function adminFrontName(): string {
  const match = fs.readFileSync(ROUTES_XML, 'utf-8').match(/frontName="([^"]+)"/);
  if (!match) throw new Error(`No frontName in ${ROUTES_XML}`);
  return match[1];
}

export function adminRoutes(): AdminRoute[] {
  const frontName = adminFrontName();
  const routes: AdminRoute[] = [];
  for (const controller of fs.readdirSync(CONTROLLER_DIR)) {
    const dir = path.join(CONTROLLER_DIR, controller);
    if (!fs.statSync(dir).isDirectory()) continue;
    for (const file of fs.readdirSync(dir).filter((f) => f.endsWith('.php'))) {
      const action = file.replace(/\.php$/, '');
      const source = fs.readFileSync(path.join(dir, file), 'utf-8');
      routes.push({
        file: `${controller}/${file}`,
        path: `/admin/${frontName}/${lcfirst(controller)}/${lcfirst(action)}/`,
        postOnly: /implements[^{]*HttpPostActionInterface/.test(source),
      });
    }
  }
  return routes.sort((a, b) => a.path.localeCompare(b.path));
}

function lcfirst(text: string): string {
  return text.charAt(0).toLowerCase() + text.slice(1);
}
