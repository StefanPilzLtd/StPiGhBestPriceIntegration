# Merchant guide — development version

This version is not approved for production. BestPrice approves merchants and feeds separately; local validation is not official acceptance. Check the release report for actual feature and test status. BestPrice compares prices; checkout, payments, delivery and returns remain in your Shopware shop.

## Prerequisites and installation

Apply directly to BestPrice. Confirm company eligibility, Cyprus registration requirements, CPC costs, documents and delivery countries. A free plugin does not make BestPrice traffic free. Never publish synthetic test products. The development candidate is restricted to Shopware6.7.16.1/PHP8.4 with BCMath, DOM, XMLWriter, SQLite3 and the other declared extensions. Other combinations require explicit tests.

Install only an approved archive through Extensions → My extensions. Until archive acceptance is complete, use a disposable shop. Run Shopware's queue worker and scheduled task runner. Build/install Administration assets. Grant viewer, editor, generator, license, pricing and analytics privileges according to the intended role.

## Setup and product selection

Open Marketing → BestPrice Integration. Choose a name, storefront sales channel, its domain and delivery country. Domain determines language/currency. EUR and gross prices are required. Explicitly select a dynamic group, products, categories, manufacturers or tags. Different filters combine with AND; entries within each filter combine with OR. Exclusions win. Explicit parent selection includes leaf variants; dynamic groups are evaluated on leaves.

Confirm the real general shipping rule in the BestPrice portal. Save/enable the configuration and request generation. The worker performs the build asynchronously. Standard refresh is60minutes; minimum15minutes. Download requests only serve generated files.

## Mapping, variants, prices and shipping

Product IDs and actual sales-channel prices cannot be remapped. Text fields can use native fields, customFields.FIELD or property.GROUP_UUID. MPN is not invented from SKU. Scalar per-product custom fields stpi_gh_bestprice_title, stpi_gh_bestprice_MPN, stpi_gh_bestprice_barcode, stpi_gh_bestprice_brand, stpi_gh_bestprice_color and stpi_gh_bestprice_size override mapped values. stpi_gh_bestprice_shipping may contain a verified gross EUR amount.

Active, visible leaf products are considered. Minimum quantities or purchase steps above1 are rejected rather than publishing misleading unit prices. Fashion's colour-level grouping with combined sizes has not passed implementation/acceptance; do not submit those ranges yet. Additional images and category-specific attributes are still open.

Without a verified product override, Shipping is omitted so the confirmed BestPrice merchant rule applies. Arbitrary Shopware shipping conditions are not approximated with a flat fee. Zero must mean genuinely free delivery. Validate physical/volumetric carrier weight and regional conditions.

## Generated files and validation

A successful build provides the XML capability URL. Replace .xml with .csv for the merchant export; official CSV dialect/acceptance still needs confirmation. Treat the URL key as confidential and redact it from proxy/support logs. Preview downloads the last generated snapshot. A failed or all-invalid build preserves the last valid feed.

Data quality lists product UUID, field, severity and code with a product link. Checks cover required fields, prices, URLs, encoding, GTIN checksum and duplicate identity. Invalid rows are skipped. The internal quality score is the percentage of valid rows, rounded down; it is not a BestPrice ranking. Warnings do not reduce that percentage. Correct product data, rebuild and then use BestPrice's official validation.

## Plans, calculations and tracking

Basic has no artificial product-count cap. Professional/Advanced are proposed native Shopware IAP tiers, with no paid catalogue activated. There is no separate billing or license server. Purchases, if registered later, use Shopware's official dialog; manage cancellation/downgrade in the Shopware account. Overlapping separate add-ons are deferred. Market monitoring is disabled without commercial rights.

Pricing preview uses net cost, overhead, target margin and tax; it does not write prices. Profitability currently calculates merchant-entered net revenue/costs, not official BestPrice analytics. Missing costs produce no precise profit claim. Click/campaign ingestion, historical reporting and automated repricing are not implemented.

360 is researched, while external transmission approval and official tests remain open. Production tracking/consent compliance is not claimed. Before activation, test consent and withdrawal, guests, payment failures/redirects/asynchronous flows and deduplication. Headless tracking is not delivered; never expose secret merchant credentials to clients.

## Lifecycle and troubleshooting

Server guards enforce features. Downgrade preserves Basic and configuration; extra feeds cannot be regenerated without Advanced. Existing enabled snapshots remain available but may become stale. Disable unused URLs deliberately. Deactivation removes plugin routes and stops new plugin processing without changing orders or product prices.

Uninstall with keep-data retains plugin tables/files; remove-data drops only plugin tables, its system-config prefix and stpi-gh-bestprice/ (Shopware private filesystem). Verify lifecycle behavior on the exact released archive. For missing files, check activation, selection, worker, status, domain, currency/tax, images and file permissions. A403 usually requires checking ACL/entitlement; a404 requires checking enabled feed/key/valid snapshot.

Feeds contain product data, not customer or purchasing costs. Secure URL keys and backups. Adapt your privacy notice to actual services and obtain relevant Greek/Cypriot legal review. Contact https://stefanpilz.ltd with versions and redacted diagnostic codes, never credentials/customer exports/full mail archives.
