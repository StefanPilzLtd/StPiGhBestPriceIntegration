# StPiGhBestPriceIntegration – BestPrice.gr-Integration für Shopware 6

**Open-Source-Shopware-6-Plugin für BestPrice.gr: XML-Produktfeed, CSV-Export, Produkt-Mapping und lokale Feed-Validierung.**

StPiGhBestPriceIntegration exportiert ausgewählte Produkte aus einem Shopware-Verkaufskanal als Produktdatenfeed. Damit können Shopware-Händler, Agenturen und Entwickler eine Anbindung an den griechischen Preisvergleich BestPrice.gr vorbereiten, testen und an ihre Anforderungen anpassen. Bestellungen und Zahlungen bleiben im Shopware-Shop; das Plugin importiert keine BestPrice-Bestellungen.

**Version:** v0.9 · **Lizenz:** [MIT](LICENSE) · **Download:** [Release mit installierbarem Plugin-ZIP](https://github.com/StefanPilzLtd/StPiGhBestPriceIntegration/releases/tag/v0.9)

## Funktionen des Shopware-BestPrice-Plugins

| Funktion | Was bereits implementiert ist |
| --- | --- |
| **XML-Produktfeed und CSV-Export** | Produktdaten als XML und Händler-CSV bereitstellen; der Basis-Feed hat keine künstliche Produktzahlbegrenzung. Die offizielle BestPrice-Annahme ist noch nicht geprüft. |
| **Gezielte Produktauswahl** | Einzelne Produkte, dynamische Produktgruppen, Kategorien, Hersteller und Tags auswählen sowie Produkte ausschließen. |
| **Verkaufskanal und Produktpreise** | Domain, Sprache und Lieferland berücksichtigen; tatsächliche EUR-Bruttopreise aus dem gewählten Shopware-Verkaufskanal verwenden. |
| **Produkt-Mapping** | Produkttexte, Hersteller, EAN/GTIN und MPN zuordnen; Custom Fields und Eigenschaftsgruppen als Datenquellen verwenden. |
| **Variantenexport** | Blattvarianten mit vererbten Produktdaten exportieren. Die BestPrice-Modegruppierung nach Farbe und Größe ist noch offen. |
| **Lokale Feed-Validierung** | Pflichtfelder, URLs, Preise, Encoding, GTIN-Prüfziffern und doppelte Kennungen prüfen; Diagnosemeldungen pro Produkt anzeigen. |
| **Automatische Feed-Erstellung** | Feeds über Queue und Scheduled Tasks aktualisieren oder einen Build manuell anstoßen. Bei einem fehlgeschlagenen Build bleibt der letzte gültige Feed erhalten. |
| **Native Shopware-Administration** | Feeds, Mapping und Diagnose direkt in der Administration verwalten; Oberflächentexte auf Deutsch, Englisch und Griechisch. |

Versandkosten lassen sich produktbezogen überschreiben. Andernfalls setzt der Export eine bestätigte allgemeine Versandregel im BestPrice-Händlerkonto voraus; eine vollständige Nachbildung beliebiger Shopware-Versandregeln ist nicht enthalten.

### Weitere Funktionen und aktueller Entwicklungsstand

Preisvorschau, Profitabilitätsrechner und zusätzliche Feed-Konfigurationen sind im Code vorhanden. Diese Funktionen unterliegen den bestehenden Shopware-IAP-Prüfungen; für dieses GitHub-Release ist kein IAP-Angebot freigeschaltet.

BestPrice 360, Consent- und Conversion-Verarbeitung, historische Analytics und automatisches Repricing stehen in v0.9 noch nicht bereit. Tracking und Market Intelligence sind deaktiviert. Den vollständigen Stand findest du unter [Funktionen und Einschränkungen](docs/status.md).

## Warum der Code öffentlich auf GitHub steht

Ich konnte das Plugin nicht vollständig testen, weil BestPrice mir keinen Testaccount zur Verfügung gestellt hat. Ursprünglich wollte ich es im Shopware Store veröffentlichen. Da mir der notwendige Zugang für die vollständigen Tests fehlt, stelle ich den Code stattdessen öffentlich auf GitHub bereit.

Lokale Prüfungen sind vorhanden: 56 Unit-Tests sowie Installation, Aktivierung, Neuinstallation und Deinstallation des Release-ZIPs wurden in einem isolierten Testshop geprüft. Eine offizielle BestPrice-Feed-Abnahme oder Shopware-Store-Freigabe liegt damit nicht vor. Details stehen im [Prüfnachweis für v0.9](docs/verification-v0.9.json).

## Voraussetzungen

Der aktuelle Release ist auf **Shopware 6.7.16.1**, **PHP 8.4** und **MariaDB 10.11** ausgerichtet. Die Composer-Abhängigkeiten beschränken die Installation auf diese Shopware-Version. Weitere Versionen müssen gesondert angepasst und getestet werden.

Für die automatische Feed-Erstellung müssen die Shopware-Queue und der Scheduled-Task-Runner laufen. Für die tatsächliche Anbindung an BestPrice brauchst du außerdem einen berechtigten Händlerzugang und die Prüfung deiner Produktdaten durch BestPrice.

## Installation und erste Schritte

1. Lade das [installierbare Plugin-ZIP von Release v0.9](https://github.com/StefanPilzLtd/StPiGhBestPriceIntegration/releases/download/v0.9/StPiGhBestPriceIntegration-0.9.0.zip) herunter.
2. Lade das ZIP in einem passenden Testshop unter **Erweiterungen → Meine Erweiterungen** hoch, installiere und aktiviere das Plugin.
3. Öffne **Marketing → BestPrice Integration** und wähle Verkaufskanal, Domain, Lieferland und die zu exportierenden Produkte.
4. Prüfe Mapping und Versandregel, aktiviere die Konfiguration und stoße die Feed-Erstellung an.
5. Kontrolliere die Diagnose und den erzeugten XML-/CSV-Feed, bevor du ihn mit einem berechtigten Händlerkonto bei BestPrice prüfen lässt.

Feed-URLs enthalten einen Zugriffsschlüssel und sollten vertraulich behandelt werden.

[Händlerhandbuch auf Deutsch](docs/merchant/de-DE.md) · [English merchant guide](docs/merchant/en-GB.md) · [Εγχειρίδιο εμπόρου](docs/merchant/el-GR.md) · [Entwicklung und Tests](docs/development.md)

## Mitmachen: Code nutzen, testen und weiterentwickeln

Du kannst den Code unter der [MIT-Lizenz](LICENSE) nutzen, anpassen, testen und verwenden. [Issues](https://github.com/StefanPilzLtd/StPiGhBestPriceIntegration/issues), [Pull Requests](https://github.com/StefanPilzLtd/StPiGhBestPriceIntegration/pulls) und Feedback sind herzlich willkommen. Besonders hilfreich sind Erfahrungen mit echten BestPrice-Händlerkonten, nachvollziehbare Fehlerberichte und Verbesserungen am Produktfeed.

Bitte teile keine Zugangsdaten, Feed-Schlüssel oder Kundendaten in öffentlichen Issues. Hinweise für Beiträge stehen in [CONTRIBUTING.md](CONTRIBUTING.md).

## Shopware-Freelancer: Entwicklung, Schnittstellen und Support

Ich bin **Stefan Pilz, Shopware-Freelancer**. Bei Fragen zum Plugin oder wenn du Unterstützung rund um Shopware brauchst, kannst du mich gerne kontaktieren. Ich helfe bei Plugin-Entwicklung, Schnittstellen, individuellen Anpassungen und technischer Betreuung deines Shopware-Shops.

**Kontakt:** [gh-plugins@stefanpilz.ltd](mailto:gh-plugins@stefanpilz.ltd) · **Website:** [stefanpilz.ltd](https://stefanpilz.ltd)

## English summary

StPiGhBestPriceIntegration is an **open-source Shopware 6 integration for BestPrice.gr**, providing XML product feeds, merchant CSV export, product selection, field mapping, local feed validation and scheduled generation. It includes a native Administration interface in German, English and Greek. BestPrice did not provide a developer test account, so official provider acceptance remains unverified. The MIT-licensed source is available for use, customization, testing and contributions.
