# Implementation status

Version 0.9.0 provides an implemented feed core with XML/merchant CSV, selected product export, local validation, atomic feed publication, queue/scheduled generation, a price preview, a merchant-input profitability calculator and native Shopware Administration.

BestPrice declined developer test accounts, sandboxes and developer API/360 test keys. Official BestPrice feed acceptance and live-provider tests are unavailable to this project. Local tests cannot establish provider acceptance.

BestPrice 360 and consent/conversion processing are not implemented. Fashion grouping, additional category attributes, historical analytics, automated repricing and assortment optimization remain incomplete. Tracking and market intelligence are disabled. The native IAP catalogue is not registered; no offers are active. The complete coverage, role/locale, checkout and performance matrix remains open.

The public release is source for community use, testing and contributions. It is not a Shopware Store approval or a BestPrice certification. Shopware 6.7.16.1 / PHP 8.4 is the current compatibility target. It is a separate plugin identity from the earlier private development candidate; no automatic migration from that identity is provided.
