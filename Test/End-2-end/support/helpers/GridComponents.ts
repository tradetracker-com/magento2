/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import * as fs from 'fs';
import * as path from 'path';
import { adminFrontName } from './AdminRoutes';

/**
 * The module's admin grids as their ui_component files declare them.
 *
 * Deriving the coverage from the XML rather than listing columns in a test means a new column is
 * covered the moment someone adds it. Two layers use this:
 *
 * - `tests/admin/grid-filters.spec.ts` puts every filter of a grid into one request. An ambiguous
 *   column reference is ambiguous whatever else is in the WHERE clause, so one request per grid
 *   costs seconds and catches SQL that no page load, PHPStan run or unit test sees (two bol grids
 *   shipped broken this way).
 * - `tests/baseline/grids.spec.ts` drives the same grids through the browser: every filter hits and
 *   misses, every sortable column sorts both ways. That is what catches a filter mapped onto the
 *   wrong field, which is valid SQL and simply finds nothing (two order-grid filters in digitec-galaxus).
 */
export type FilterKind = 'text' | 'textRange' | 'select' | 'dateRange';

export interface GridColumn {
  name: string;
  label: string;
  /** Null when the column declares no filter, or when a <filterSelect> owns it (see below). */
  filter: FilterKind | null;
  sortable: boolean;
  dataType: string | null;
  /**
   * The module renders this cell itself, so its text is derived from the stored value and is not
   * what the filter searches: assert that the filter answers, not that the text matches.
   */
  rendered: boolean;
}

export interface GridMassAction {
  name: string;
  label: string;
  confirm: boolean;
}

export interface GridListing {
  /** UI component namespace, e.g. "<frontName>_<entity>_listing". */
  namespace: string;
  file: string;
  /** Admin route of the page that shows this grid. */
  route: string;
  columns: GridColumn[];
  massActions: GridMassAction[];
}

const UI_COMPONENT_DIR = path.resolve(__dirname, '../../../../view/adminhtml/ui_component');
// The admin front name comes from routes.xml, never a constant spelled out here: a module whose
// front name differs from its name (mm_meta, mm_tiktok) made every grid route a 404.
const ADMIN_ROUTE = adminFrontName();
const VENDOR = 'Magmodules';
const CONTROLLER_DIR = path.resolve(__dirname, '../../../../Controller/Adminhtml');
const LAYOUT_DIR = path.resolve(__dirname, '../../../../view/adminhtml/layout');

/**
 * The controller a grid namespace belongs to, as Magento resolves it.
 *
 * A namespace is not a route: `kiyoh_sr_product_review_listing` is served by
 * `Controller/Adminhtml/ProductReview`, `metatemplates_template_listing` by `Template` under the
 * front name `magmodules_metatemplates`, and `exception_monitor_listing` by `Exception`. Taking the
 * namespace apart by hand produced a 404 for each of those, which reads exactly like a grid that
 * failed to render - 30 seconds of waiting for a wrapper that was never going to come.
 *
 * So the module's own controller directories decide: the one whose name occurs in the namespace is
 * the route, longest name first, because a module with both `Feed` and `FeedType` must not match
 * the shorter one. Only when none of them occurs is the namespace used as it was.
 */
function controllerRoute(entity: string, namespace: string): string {
  const flattened = (text: string) => text.replace(/_/g, '').toLowerCase();
  const controllers = fs
    .readdirSync(CONTROLLER_DIR, { withFileTypes: true })
    .filter((item) => item.isDirectory())
    .map((item) => item.name)
    .sort((a, b) => b.length - a.length);

  const exact = controllers.find((name) => name.toLowerCase() === flattened(entity));
  if (exact) {
    return exact.toLowerCase();
  }

  const inNamespace = controllers.find((name) => flattened(namespace).includes(name.toLowerCase()));

  return inNamespace ? inNamespace.toLowerCase() : entity;
}

export class GridComponents {
  /**
   * Every listing the module ships that has an admin page of its own, in file name order.
   *
   * A ui_component file is not a page: `sales_order_grid.xml` adds a column to Magento's order grid
   * and `order_log_listing.xml` is a tab in the order view. Neither answers at a route derived from
   * its name, and the baseline would read both as a grid that fails to render. The module's own
   * layout files decide: a listing that a `<frontName>_<controller>_<action>.xml` layout declares
   * through `<uiComponent name="..."/>` is driven at that route; the others are left to the specs
   * that know where they live.
   */
  static load(): GridListing[] {
    const pages = GridComponents.listingPages();
    return fs
      .readdirSync(UI_COMPONENT_DIR)
      .filter((file) => /_(listing|grid)\.xml$/.test(file))
      .filter((file) => pages.has(file.replace(/\.xml$/, '')))
      .sort()
      .map((file) => GridComponents.parse(file, fs.readFileSync(path.join(UI_COMPONENT_DIR, file), 'utf-8'), pages.get(file.replace(/\.xml$/, ''))));
  }

  /** Listing namespace → admin route of the layout handle of this module that declares it. */
  private static listingPages(): Map<string, string> {
    const pages = new Map<string, string>();
    if (!fs.existsSync(LAYOUT_DIR)) {
      return pages;
    }
    for (const file of fs.readdirSync(LAYOUT_DIR).filter((f) => f.endsWith('.xml'))) {
      const handle = file.replace(/\.xml$/, '');
      const match = handle.match(new RegExp(`^${ADMIN_ROUTE}_([a-z0-9]+)_([a-z0-9]+)$`));
      if (!match) {
        continue;
      }
      const xml = fs.readFileSync(path.join(LAYOUT_DIR, file), 'utf-8');
      for (const component of xml.matchAll(/<uiComponent name="([^"]+)"/g)) {
        pages.set(component[1], `/admin/${ADMIN_ROUTE}/${match[1]}/${match[2]}/`);
      }
    }
    return pages;
  }

  static parse(file: string, xml: string, route?: string): GridListing {
    const namespace = file.replace(/\.xml$/, '');
    const entity = namespace.replace(new RegExp(`^${ADMIN_ROUTE}_`), '').replace(/_(listing|grid)$/, '');

    // A <filterSelect name="x"> in the filters block replaces whatever <filter> the column of that
    // name declares. It is usually a website > store > store view tree that the generic filler
    // cannot drive, so the column is listed without a filter and stays a manual check.
    const filterSelects = Array.from(xml.matchAll(/<filterSelect name="([^"]+)"/g)).map((m) => m[1]);

    const columns: GridColumn[] = [];
    const columnRe = /<column name="([^"]+)"([^>]*)>([\s\S]*?)<\/column>/g;
    let match: RegExpExecArray | null;
    while ((match = columnRe.exec(xml)) !== null) {
      const [, name, attributes, body] = match;
      const declared = body.match(/<filter>([^<]+)<\/filter>/)?.[1]?.trim() as FilterKind | undefined;
      columns.push({
        name,
        label: body.match(/<label[^>]*>([^<]+)<\/label>/)?.[1] ?? name,
        filter: filterSelects.includes(name) ? null : (declared ?? null),
        sortable: !/<sortable>false<\/sortable>/.test(body),
        dataType: body.match(/<dataType>([^<]+)<\/dataType>/)?.[1] ?? null,
        // Two ways a module renders a cell itself: a component class on the column, or a body
        // template. Either way the text on screen is derived from the stored value and is not
        // what the filter searches, so only the miss can be asserted.
        rendered: new RegExp(`class="${VENDOR}\\\\`).test(attributes) || /<bodyTmpl>/.test(body),
      });
    }

    const massActions: GridMassAction[] = [];
    const actionRe = /<action name="([^"]+)">([\s\S]*?)<\/action>/g;
    while ((match = actionRe.exec(xml)) !== null) {
      massActions.push({
        name: match[1],
        label: match[2].match(/<label[^>]*>([^<]+)<\/label>/)?.[1] ?? match[1],
        confirm: /<confirm>/.test(match[2]),
      });
    }

    return {
      namespace,
      file,
      route: route ?? `/admin/${ADMIN_ROUTE}/${controllerRoute(entity, namespace)}/index/`,
      columns,
      massActions,
    };
  }
}

/** Columns with a filter the generic filler can drive, one entry per name. */
export function filterableColumns(grid: GridListing): GridColumn[] {
  const seen = new Set<string>();
  return grid.columns.filter((column) => {
    if (!column.filter || seen.has(column.name)) {
      return false;
    }
    seen.add(column.name);
    return true;
  });
}

/**
 * Columns a listing declares more than once.
 *
 * Magento merges ui_component nodes by name, so a duplicate silently collapses into one column with
 * whichever settings happen to win. It is always a mistake and invisible until someone wonders why
 * a filter behaves differently than the XML says.
 */
export function duplicateColumns(grid: GridListing): string[] {
  const seen = new Set<string>();
  const duplicates = new Set<string>();
  for (const column of grid.columns) {
    if (seen.has(column.name)) {
      duplicates.add(column.name);
    }
    seen.add(column.name);
  }
  return Array.from(duplicates);
}

/**
 * One query parameter set that puts every filterable column of a grid into the WHERE clause.
 *
 * The values deliberately match nothing: this layer is about the query being valid SQL, not about
 * what comes back.
 */
export function allFilterParams(grid: GridListing): Record<string, string> {
  return filterableColumns(grid).reduce<Record<string, string>>(
    (params, column) => ({ ...params, ...filterParams(column) }),
    {}
  );
}

export function filterParams(column: GridColumn): Record<string, string> {
  switch (column.filter) {
    case 'dateRange':
      return {
        [`filters[${column.name}][from]`]: '2000-01-01',
        [`filters[${column.name}][to]`]: '2099-12-31',
      };
    case 'textRange':
      return {
        [`filters[${column.name}][from]`]: '0',
        [`filters[${column.name}][to]`]: '999999',
      };
    case 'select':
      return { [`filters[${column.name}]`]: '1' };
    default:
      return { [`filters[${column.name}]`]: 'e2e-no-match' };
  }
}
