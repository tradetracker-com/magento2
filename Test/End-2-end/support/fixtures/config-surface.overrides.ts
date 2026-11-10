/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import type { SurfaceOverrides } from 'Helpers/ConfigSurface';

/**
 * Fields the generic "everything on" chooser must not set blind. Every entry carries its reason;
 * this file is reviewed like code, it is not a place to hide a field that fails.
 */
export const CONFIG_SURFACE_OVERRIDES: SurfaceOverrides = {
  'tradetracker/general/enable': {
    value: '1',
    reason: 'the module stays enabled for the rest of the run',
  },
  'tradetracker/feed/enable': {
    value: '1',
    reason: 'the feed stays enabled so the work check has something to generate',
  },
  'tradetracker_general/general/integration_token': {
    value: null,
    reason: 'rendered by a block that shows the Magento integration token; there is nothing to set',
  },
  'tradetracker/feed/filter_category': {
    value: '0',
    reason: 'an arbitrary category restriction empties the feed; covered by category-filters.spec.ts',
  },
  'tradetracker/feed/custom_filters_data': {
    value: null,
    reason: 'a dynamic-rows condition table the generic filler cannot build; covered by advanced-filters.spec.ts',
  },
  'tradetracker/feed/shipping': {
    value: null,
    reason: 'a dynamic-rows price band table; a text value in a price band makes the band meaningless',
  },
};
