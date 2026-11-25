# StPiGhBestPriceIntegration – BestPrice.gr Integration for Shopware 6

**Open-source Shopware 6 plugin for BestPrice.gr: XML product feeds, CSV export, product mapping and local feed validation.**

StPiGhBestPriceIntegration exports selected products from a Shopware sales channel as a product data feed. It helps Shopware merchants, agencies and developers prepare, test and customize an integration with the Greek price comparison platform BestPrice.gr. Orders and payments remain in your Shopware store; the plugin does not import orders from BestPrice.

**Version:** v0.9 · **License:** [MIT](LICENSE) · **Download:** [Release with installable plugin ZIP](https://github.com/StefanPilzLtd/StPiGhBestPriceIntegration/releases/tag/v0.9)

## Shopware BestPrice integration features

| Feature | Current implementation |
| --- | --- |
| **XML product feeds and CSV export** | Export product data as XML and merchant CSV, with no artificial product-count limit in the basic feed. Official BestPrice acceptance remains unverified. |
| **Product selection** | Select individual products, dynamic product groups, categories, manufacturers and tags, and exclude products. |
| **Sales channels and product prices** | Use the selected domain, language and delivery country, with actual EUR gross prices from the Shopware sales channel. |
| **Product mapping** | Map product text, manufacturer, EAN/GTIN and MPN fields; use custom fields and property groups as data sources. |
| **Variant export** | Export individual leaf variants with inherited product data. BestPrice fashion grouping by color and size is still outstanding. |
| **Local feed validation** | Check required fields, URLs, prices, encoding, GTIN check digits and duplicate identifiers; display diagnostics for individual products. |
| **Scheduled feed generation** | Update feeds through the queue and scheduled tasks, or request a build manually. A failed build preserves the last valid feed. |
| **Native Shopware Administration** | Manage feeds, mappings and diagnostics in the Administration, with interface text in German, English and Greek. |

Shipping costs can be overridden per product. Otherwise, the export relies on a confirmed general shipping rule in the BestPrice merchant account. It does not reproduce every possible Shopware shipping rule.

### Additional features and development status

Price previews, a profitability calculator and additional feed configurations are implemented in the code. These features are subject to the existing Shopware In-App Purchase (IAP) checks; no IAP offers are active for this GitHub release.

BestPrice 360, consent and conversion processing, historical analytics and automated repricing are not available in v0.9. Tracking and market intelligence are disabled. See [implementation status and limitations](docs/status.md) for details.

## Why this plugin is public on GitHub

I could not fully test the plugin because BestPrice did not provide me with a test account. I originally planned to publish it in the Shopware Store. Without the access needed to complete those tests, I am making the code publicly available on GitHub instead.

Local checks include 56 passing unit tests, plus installation, activation, reinstallation and uninstallation of the release ZIP in an isolated test shop. These checks do not establish official BestPrice feed acceptance or Shopware Store approval. See the [v0.9 verification record](docs/verification-v0.9.json).

## Requirements

The current release targets **Shopware 6.7.16.1**, **PHP 8.4** and **MariaDB 10.11**. Composer dependencies restrict installation to this Shopware version. Other versions require separate adaptation and testing.

The Shopware queue worker and scheduled-task runner must be running for automatic feed generation. Connecting to BestPrice also requires authorized merchant access and validation of your product data by BestPrice.

## Installation and getting started

1. Download the [installable plugin ZIP from release v0.9](https://github.com/StefanPilzLtd/StPiGhBestPriceIntegration/releases/download/v0.9/StPiGhBestPriceIntegration-0.9.0.zip).
2. In a compatible test shop, upload the ZIP under **Extensions → My extensions**, then install and activate the plugin.
3. Open **Marketing → BestPrice Integration** and select the sales channel, domain, delivery country and products to export.
4. Review the mapping and shipping rule, enable the configuration and request a feed build.
5. Review the diagnostics and generated XML/CSV feed before submitting it to BestPrice for validation through an authorized merchant account.

Feed URLs contain an access key and should be treated as confidential.

[English merchant guide](docs/merchant/en-GB.md) · [German merchant guide](docs/merchant/de-DE.md) · [Greek merchant guide](docs/merchant/el-GR.md) · [Development and testing](docs/development.md)

## Contributing: use, test and improve the code

You are welcome to use, modify, test and run the code under the [MIT license](LICENSE). [Issues](https://github.com/StefanPilzLtd/StPiGhBestPriceIntegration/issues), [pull requests](https://github.com/StefanPilzLtd/StPiGhBestPriceIntegration/pulls) and feedback are welcome. Experience with real BestPrice merchant accounts, reproducible bug reports and product-feed improvements are particularly helpful.

Please keep credentials, feed keys and customer data out of public issues. See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

## Freelance Shopware development, integrations and support

I am **Stefan Pilz, a freelance Shopware developer**. Feel free to contact me with questions about the plugin or if you need help with your Shopware project. I work on plugin development, integrations, customizations and technical support for Shopware stores.

**Contact:** [gh-plugins@stefanpilz.ltd](mailto:gh-plugins@stefanpilz.ltd) · **Website:** [stefanpilz.ltd](https://stefanpilz.ltd)
