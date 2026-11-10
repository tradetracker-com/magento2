# Changelog

## [1.9.0]

**Features:**
* The product feed is now built on a shared ProductData service. Products, stock, prices, images and categories come out of one collector. The feed fields, the shipping price per price band, the delivery time per stock state and the "Category Name for Feed" per category are unchanged.
* The main image for "All Images" now follows the Main Image setting (first, last or an image type); it was always the first image.
* Added a unit test suite (`Test/Unit`, 146 tests) covering the ProductData collectors and a set of architecture gates. It runs in CI on PHP 8.2 to 8.5.
* Added `docs/CLI_COMMANDS.md` for `bin/magento tradetracker:feed:create`.
* The Generate action on the feed overview now runs the feed without leaving the configuration page and shows how far it is: the row reports the products written against the total while the run goes, holds the finished bar for a moment, and the page then reloads with the result. A browser without JavaScript keeps the old link.

**Improvements:**
* PHP 8.2 or newer is required. PHP 8.1 and older receive no fix for CVE-2026-109618 and CVE-2026-109619 (remote code execution in PHP core, fixed in 8.2.35, 8.3.36, 8.4.27 and 8.5.12). The compile and PHPStan job runs PHP 8.2 on Magento 2.4.7-p10, 8.3 and 8.4 on 2.4.8-p5 and 8.5 on 2.4.9.
* The passphrase is stored encrypted with the Magento encryption key. Existing plain-text values are encrypted automatically during `setup:upgrade`; the Check Credentials button tests with the stored passphrase of the selected scope.
* The feed cron runs in its own cron group (`tradetracker`, separate process), so a long feed run no longer holds up Magento's default group.
* PHPStan runs at level 8 without a baseline.
* Every configuration field offers "Use system value", except the Extra Fields, Shipping Settings and Advanced Filters tables. A select whose first option means something (Yes, Static Values, Include) now has that choice declared as a default, so what the admin shows with the checkbox ticked is what the module reads.

**Bugfixes:**
* Fixed the feed cron never generating a feed. The job read its frequency from `tradetracker/feeds/cron_frequency` while the setting lives under `tradetracker/feed/cron_frequency`, so the check "is a frequency set" always failed and the job returned before generating anything. The schedule is now read from the Frequency field itself, "Custom" resolves to the custom expression on save, and an expression the select does not offer shows as "Custom" instead of falling back to the first option. A stored "Custom" from an earlier release is replaced by its custom expression during `setup:upgrade`, because the scheduler rejects the word itself.
* Fixed "Use Parent URL for Simples" under Grouped Products never applying. The field stored its value under a path the feed did not read; a value saved there by an earlier release is moved during `setup:upgrade`.
* Fixed the pixel never sending a custom currency. The Currency Code field wrote to the path of the "Overwrite Currency" toggle, and the check that reads them returned nothing exactly when the toggle was on. The code has its own path, the check is the right way round, and a code saved into the toggle by an earlier release is moved to its own path during `setup:upgrade`.
* Fixed the configuration sections being behind ACL resources that no acl.xml declares (`TradeTracker_Connect::general` and `::feed`), which denied them to every admin role without full access. Both sections are now behind `TradeTracker_Connect::config`.
* Fixed a product without a price of its own (an unsalable configurable, for one) being published with the price of the product that came before it in the batch.
* Fixed the extra fields reading the wrong key: an extra field was looked up under its attribute code while the collector keys values by field name, so it was empty unless the two happened to match.
* Fixed HTML entities in catalogue values reaching the feed as text. The Luma sample data has "LumaTech&trade; V-Tee"; the writer escaped the entity a second time and TradeTracker showed "&trade;" instead of "™". Named and numeric entities now become the character they stand for, and characters XML does not allow are dropped.
* Fixed DirectLinking giving a 404 when the Url Key is left on "Use system value". Saving the section on Default Config wrote one URL rewrite for store 0, which no request matches; it now writes a rewrite for every store view, with the key that store view inherits.
* Fixed a simple that is both a grouped member and a bundle component never reaching the feed. The bundle setting "Only Bundle Product" dropped it whatever the grouped setting said; a child now stays as soon as one of its parent relations asks for simples, and goes only when every parent in the feed says parent only.
* Fixed the URL rewrites of a previous DirectLinking Url Key staying behind after the key changes. The rewrites this module wrote for the same store view under another key are removed when the new one is saved.
* Fixed `fromPrice` equalling `price` for a discounted product. The feed published the lowest price twice; `fromPrice` is now the regular price and `price` the selling price.
* Removed three defaults from `etc/config.xml` that no field reads (`gtin_attribute`, `condition_type`, `condition_default`).

**Tests:**
* `Test/Unit/Architecture` pins the ACL, CLI wiring, config defaults and paths, cron wiring, data patches, changelog format and generated E2E documentation.
* `Test/Unit/Service/ProductData` covers the collectors: price carry-over, rule prices, MSI reservations, configurable keys, category paths and the visibility filter.
* `Service\Feed\CreateTest` pins the entity decoding and the XML escaping of the writer.
* `PixelRepositoryTest` pins that a custom currency is sent exactly when the toggle is on and a code is set.
* `ProgressTest` pins the progress record of a run: start, advance, finish, failure, and that store views do not share state.
* `feed-progress.spec.ts` generates the feed from the configuration page and checks that the row reports the run, the page stays, and the progress endpoint answers for the store it ran for.
* `ChildSelectionTest` pins which parents and children leave the feed when a child has parents of more than one type.
* `grouped-products.spec.ts` checks that the yoga straps, grouped members and bundle components at once, are in the feed with "Only Linked Simple Products" and "Only Bundle Product".
* `SaveRewriteTest` pins the DirectLinking rewrite per store view for an inherited and an explicit key, on Default Config and on a store view.
* `Model\ProductData\RepositoryTest` pins the from price, the selling price and the static delivery time of a feed row.
* `CanRestoreSelectDefaultTest` pins that every select whose first option means something has a default in `config.xml`.
* `MigrateConfigPathsTest` pins the three configuration migrations of this release and that a store already on the new shape is left alone.
* `advanced-filters.spec.ts` now removes the filters it set, so the feed of the next spec is not filtered by its leftovers.
* `E2E-TESTS.md` is generated from the Playwright test list.

**Compatibility:**
* Magento 2.4.x and up
* PHP 8.2 and up
