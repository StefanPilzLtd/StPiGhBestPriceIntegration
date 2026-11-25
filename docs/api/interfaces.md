# Verified interface register

Reviewed 2026-11-24. Availability is separate from authorization and successful testing.

| Interface | Official evidence | Access / contract | Implementation and testing status |
|---|---|---|---|
| XML | [Developer Hub](https://merchants.bestprice.gr/xml-specs/), [referenced PDF](https://merchants.bestprice.gr/assets/bestprice-xml-specification.pdf) | Merchant submits public URL; PDF returned HTTP 403 in this environment | Writer and local validator implemented; full field/limit contract and official acceptance **not confirmed** |
| CSV | [Merchant site](https://merchants.bestprice.gr/) says matching uses XML or CSV | Exact dialect, headers, encoding and account configuration undocumented in retrieved content | Semicolon UTF-8 merchant export implemented; do not claim accepted official CSV dialect |
| Shipping | [Official shipping FAQ](https://www.bestprice.gr/faq/2641/pos-tha-prostheso-to-kostos-apostolis-enos-proiontos-sto-xml-archeio-mou.html) | Per-product gross EUR cost or merchant general rule | Shipping omitted unless explicit product override; merchant confirms fallback. Zero means genuinely free shipping |
| Weight | [Official weight FAQ](https://www.bestprice.gr/faq/2642/pos-tha-prostheso-to-varos-enos-proiontos-sto-xml-archeio-mou.html) | Carrier weight must reflect physical/volumetric rules | Shopware kg converted to grams; volumetric-weight mapping requires merchant validation |
| 360 | [Official repository](https://github.com/TheBestCo/bestprice-360) | Merchant 360 key; custom browser integration | Contract researched; transmission permission/test/consent gates are recorded separately. No live events sent |
| Merchant analytics / reports | Merchant portal is documented, no public API contract retrieved | Account/partner confirmation needed; scopes, limits, schemas, errors, costs unknown | No invented analytics API or portal scraping; local merchant-input profitability only |
| Merchant products/status/feed errors | Official portal/validator linked on merchant homepage | Public access failed; no machine-readable API retrieved | Local diagnostics only; no official validator result claimed |
| Orders | [Official terms](https://www.bestprice.gr/policies/terms) explain retailer transaction | Checkout stays in retailer's store | No BestPrice Orders API/import implemented |
| MCP | [Official MCP page](https://www.bestprice.gr/mcp), [repository](https://github.com/TheBestCo/bestprice-mcp) | Consumer use; commercial/systematic observation expressly restricted | Module unconditionally denied. No product queries or market-data storage |
| Certification | [Official certification page](https://www.bestprice.gr/certification) is merchant-facing; exact eligibility needs follow-up | Not a plugin developer certificate | No certification/partner badge |
| Brands / campaigns / coupons / reviews | Public merchant/brand links establish services, not a permitted plugin API | Request scopes/contracts and test facilities | Not implemented; no guessed endpoints |

For unavailable private APIs, request/response schemas, versioning, rate limits, retry semantics, rights, fees and error codes remain **unknown**. An HTTP 403 retrieving documentation is not a successful authentication test. `not verified` never means `does not exist`.

## Feed contract and limitations

The current local contract uses store/date/products/product with productId, title, productURL, image, price, category_name, availability, brand and optional MPN, barcode, description, size, color, weight and Shipping. A complete official field specification could not be retrieved; this contract must be reconciled before production. Do not treat unit tests against our own contract as vendor contract validation. Source names are deliberately centralized in FeedWriter::FIELDS.

The Developer Hub distinguishes fashion (record by colour) from other variant categories (record per variant). Current catalog processing exports leaf variants. Fashion grouping is not release-complete. Extra images and category-specific attributes also require exact official fields before expanding the serializer. Do not invent category IDs, VAT elements or inventory fields.

The locally chosen availability list and title length limit are defensive preflight values pending full official confirmation. GTIN checksum validation is local. MPN is not fabricated from SKU. The internal quality score is valid products / total seen × 100, rounded down; warnings do not reduce it.

## 360 data contract

The custom guide documents connect, addOrder and addProduct. Order revenue includes tax/shipping; product price is gross per unit. Product IDs must match feed IDs. The guide requests loader installation before cookie decisions; this project's consent requirement takes precedence. That difference and revocation behavior need BestPrice confirmation. The documented trigger is an order placed, regardless of payment method/status. It must not be labelled paid revenue; failed/cancelled transactions need explicit exclusion and tests. Refund/cancellation events are not documented in retrieved custom-installation material and must not be invented. Shopify-specific direct transport is not permission for a Shopware backend transport.
