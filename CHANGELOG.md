# Changelog

## [1.10.0]

**Features:**
* Added "Conversion Tab on Orders" under Conversion Pixel. It switches the "TradeTracker Conversion" tab on the order page off for shops that do not use it; the status column in the order grid is still kept up to date by the nightly sync.
* Added "API Timeout" under Advanced Setting: the seconds every request to the TradeTracker API gets, 10 by default. It covers the credentials check, the conversion tab, the transaction queue and the nightly status sync.
* Added `bin/magento tradetracker:transaction:process-queue` and the cron job `tradetracker_process_transaction_queue`, which send the queued cancellations and credit memos to TradeTracker every five minutes.

**Improvements:**
* The "TradeTracker Conversion" tab loads its content when it is opened, over AJAX from `tradetracker/transaction/tab`. Up to 1.9.0 the order page authenticated with TradeTracker and fetched the transaction while it loaded, for every admin, whether the tab was opened or not.
* Every request to the TradeTracker API is capped by the new API Timeout. The SOAP client had no limit on how long it waited for an answer; the module now waits no longer than configured and reports a slow or missing answer instead. The WSDL is kept in `var/tradetracker/merchant.wsdl`, refreshed once a day, and a refresh that does not come through keeps the copy it has.
* Cancelling an order or saving a credit memo no longer calls TradeTracker inside the save. The assessment is written to `tradetracker_transaction_queue` and sent by the cron job; a refusal is retried on the next run and given up on after five, while no answer at all, refused credentials or a campaign the account may not use postpone the row without counting against it, so the rows go out once the connection or the configuration is right. The same assessment is queued once, however often the cancelled order is saved afterwards.
* The tab and the observers check that the module is enabled, that the pixel is enabled for the order's store view and that a Campaign ID is set. Up to 1.9.0 they called TradeTracker for every shop that had the module installed.
* The conversion tab is shown to admins who may view orders (`Magento_Sales::actions_view`); it used to require the configuration resource `TradeTracker_Connect::config`, which admins who handle orders rarely hold.
* The Debug Log and Error Log buttons carry short labels and sit side by side, as in every other Magmodules module.
* Only a pending transaction is assessed; a transaction TradeTracker already accepted or rejected is reported and left as it is.
* The configuration page scripts (log viewer, show more) load with the configuration page only. They used to be pulled into every admin page through `requirejs-config.js`.
* Removed the "reject transaction" modal, its JavaScript and the `tradetracker/transaction/status` endpoint. No template ever rendered the button that opened the modal, so the endpoint had no caller since the module shipped, while the modal and its script were loaded on every order page.

**Bugfixes:**
* Fixed a configurable product being published with a price nothing sells for. Without an explicit price choice the parent now takes the cheapest final price among its buyable variants, read from the price index in one query per batch. The index's own columns were wrong either way: with a catalog price rule on the configurable `min_price` sat below every variant, with the rule on the variants `final_price` sat above the cheapest one.
* Fixed a child that belongs to several parents inheriting title, URL, images and categories from whichever parent the database listed first. It now follows the configurable when it has one, then the grouped, then the bundle.
* Fixed the parent lookups on Adobe Commerce with content staging: `catalog_product_relation` holds the parent's `row_id`, which staging moves away from its `entity_id`; the parent's `entity_id` is now read from the entity table.
* Fixed the configuration section TradeTracker - General answering with a TypeError at store view scope: the DirectLinking comment handed the store from the request to the configuration as a string. Every store view field of the section was out of reach because of it.
* Fixed a DirectLinking redirect without the `r` parameter answering with a 500. The controller passed `null` to `urlencode()`, which has been a TypeError since the controller declared strict types in 1.9.0; a missing parameter now behaves like an empty one, with the tracking cookies still set.
* Fixed a second partial credit memo accepting the transaction for the wrong amount. The amount sent was the order total minus that one credit memo; it is now the order total minus everything refunded so far, and refunds that add up to the order total reject the transaction.

**Tests:**
* `Test/Unit/Service/Api` covers the transport (timeout, connect timeout, SOAP fault on HTTP 400, gateway page), the WSDL cache and the adapter; `Test/Unit/Service/Transaction` the lookup, the assessment, the queue and its processing; `Test/Unit/Observer/Transaction` the cancel and credit memo observers; `Test/Unit/Block` the tab's visibility.
* `Test/Unit/Architecture/ObserversNeverCallTheApiTest` fails any observer or order-page tab block that depends on the API client.
* `Test/End-2-end/tests/admin/order-transactions.spec.ts` proves on a running shop that the order page does not request the tab content, that the tab fills on a click within the timeout, that the two settings hide the tab, that a cancellation, a partial and a full credit memo queue the right row once, and that the queue command finishes within the timeout and keeps a refused row for a retry.

**Compatibility:**
* Magento 2.4.x and up
* PHP 8.2 and up

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
* PHPStan runs at level 8 without a baseline, and every PHP file declares strict types.
* The code passes the Magento coding standard at severity 6: the DirectLinking cookies are set through Magento's cookie manager, the log viewer reads through the filesystem driver, the feed writer escapes without a discouraged function, and the templates escape every output. CI runs the sniffer and a PHP 8.2 to 8.6 compatibility check on every pull request.
* The end-to-end suite runs on Magento 2.4.7 and 2.4.9 in CI.
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
* Fixed product links missing the store code when "Add Store Code to Urls" is on. Every link of a non-default store view opened the default store, with and without a URL rewrite.
* Fixed bundle and grouped components sharing an item group with their parent. Only a configurable's children are variants of one another.
* Fixed a simple that is both a grouped member and a bundle component never reaching the feed. The bundle setting "Only Bundle Product" dropped it whatever the grouped setting said; a child now stays as soon as one of its parent relations asks for simples, and goes only when every parent in the feed says parent only.
* Fixed the URL rewrites of a previous DirectLinking Url Key staying behind after the key changes. The rewrites this module wrote for the same store view under another key are removed when the new one is saved.
* Fixed `fromPrice` equalling `price` for a discounted product. The feed published the lowest price twice; `fromPrice` is now the regular price and `price` the selling price.
* Fixed the transaction status action answering a request without an order id with an exception page; it now returns the error as a result.
* Removed three defaults from `etc/config.xml` that no field reads (`gtin_attribute`, `condition_type`, `condition_default`).

**Tests:**
* `Test/Unit/Architecture` pins the ACL, CLI wiring, config defaults and paths, cron wiring, data patches, changelog format and generated E2E documentation.
* `Test/Unit/Service/ProductData` covers the collectors: price carry-over, rule prices, MSI reservations, configurable keys, category paths and the visibility filter.
* `Service\Feed\CreateTest` pins the entity decoding and the XML escaping of the writer.
* `PixelRepositoryTest` pins that a custom currency is sent exactly when the toggle is on and a code is set.
* `ProgressTest` pins the progress record of a run: start, advance, finish, failure, and that store views do not share state.
* `feed-progress.spec.ts` generates the feed from the configuration page and checks that the row reports the run, the page stays, and the progress endpoint answers for the store it ran for.
* `UrlTest` pins the store code in the product link, with and without a URL rewrite.
* `ChildSelectionTest` pins which parents and children leave the feed when a child has parents of more than one type.
* `grouped-products.spec.ts` checks that the yoga straps, grouped members and bundle components at once, are in the feed with "Only Linked Simple Products" and "Only Bundle Product".
* `SaveRewriteTest` pins the DirectLinking rewrite per store view for an inherited and an explicit key, on Default Config and on a store view.
* `Model\ProductData\RepositoryTest` pins the from price, the selling price and the static delivery time of a feed row.
* `CanRestoreSelectDefaultTest` pins that every select whose first option means something has a default in `config.xml`.
* `MigrateConfigPathsTest` pins the three configuration migrations of this release and that a store already on the new shape is left alone.
* `advanced-filters.spec.ts` now removes the filters it set, so the feed of the next spec is not filtered by its leftovers.
* Added the baseline end-to-end layer: every configuration field of both sections set through the admin form and read back, the same with every switch off, every admin controller answering a bare request without an exception, and the feed still generating in both states.
* `E2E-TESTS.md` is generated from the Playwright test list.

**Compatibility:**
* Magento 2.4.x and up
* PHP 8.2 and up
