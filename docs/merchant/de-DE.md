# Händlerhandbuch — Entwicklungsversion

Diese Ausgabe ist noch nicht für den produktiven Betrieb freigegeben. BestPrice entscheidet über Händleraufnahme und Feed-Annahme; eine lokale Prüfung ersetzt diese Freigabe nicht. Der aktuelle Funktionsstatus steht im Releasebericht. BestPrice ist ein Preisvergleich; Ihre Bestellungen, Zahlungen, Versand und Retouren bleiben in Shopware.

## Voraussetzungen und Installation

Beantragen Sie ein Händlerkonto direkt bei BestPrice. Klären Sie insbesondere Unternehmenssitz Zypern, CPC-Kosten, Pflichtunterlagen und zulässige Lieferländer. Die kostenlose Erweiterung befreit nicht von externen BestPrice-Kosten. Keine Testprodukte öffentlich einstellen. Die derzeitige Entwicklung wird mit Shopware6.7.16.1, PHP8.4, BCMath/DOM/XMLWriter/SQLite3 und MariaDB10.11 geprüft. Weitere Versionen sind nicht zugesagt.

Installieren Sie ausschließlich ein freigegebenes, geprüftes ZIP über Erweiterungen → Meine Erweiterungen. Bis zur vollständigen Archiv-Abnahme verwenden Sie nur einen Testshop. Starten Sie Queue-Worker und Scheduled-Task-Runner gemäß Ihrer Shopware-Hostingkonfiguration. Vergessen Sie die Administration-Assets nicht. Rollen benötigen mindestens stpi_gh_bestprice.viewer; Bearbeiten, Erzeugen, Lizenzen, Preise und Analytics sind getrennte Rechte.

## Einrichtung und Produktauswahl

Öffnen Sie Marketing → BestPrice Integration. Legen Sie einen Feednamen, Verkaufskanal, die zugehörige Domain und das Lieferland fest. Domain bestimmt Sprache und Währung. Der Export setzt EUR und Bruttopreise voraus. Wählen Sie ausdrücklich eine dynamische Produktgruppe, einzelne Produkte, Kategorien, Hersteller oder Tags. Einträge innerhalb eines Filters gelten alternativ; verschiedene Filter gelten gemeinsam. Ausschlüsse haben Vorrang. Die explizite Auswahl eines Elternprodukts umfasst seine Blattvarianten; dynamische Gruppen werden gegen Blattprodukte ausgewertet.

Prüfen Sie im BestPrice-Händlerportal die allgemeine Versandregel. Bestätigen Sie deren korrekte Einrichtung im Plugin. Aktivieren und speichern Sie die Konfiguration. Fordern Sie einen Build an. Die Verarbeitung erfolgt asynchron; bei ausbleibendem Build zuerst den Worker prüfen. Das Mindestintervall beträgt15Minuten, Standard60Minuten. Der Download selbst berechnet keine Produkte neu.

## Mapping, Varianten, Preise und Versand

IDs und Preise stammen fest aus Shopware. Texte, Hersteller, EAN und MPN lassen sich auf native Felder, customFields.FELD oder property.GRUPPEN_UUID abbilden. Preis-Manipulation allein im Feed ist ausgeschlossen. Fehlende MPN werden nicht aus der SKU erfunden. Produktbezogene skalare Custom Fields stpi_gh_bestprice_title, stpi_gh_bestprice_MPN, stpi_gh_bestprice_barcode, stpi_gh_bestprice_brand, stpi_gh_bestprice_color und stpi_gh_bestprice_size überschreiben zugeordnete Werte. Individuelle Versandkosten verwenden stpi_gh_bestprice_shipping als geprüften Brutto-EUR-Dezimalwert.

Standardmäßig werden aktive, sichtbare Blattprodukte mit Preis für die gewählte Kundensituation exportiert. Mindestmengen/Verkaufsschritte größer1 sind derzeit nicht unterstützt und führen zu Prüfhinweisen statt irreführenden Preisen. Die Mode-Regel „ein Eintrag pro Farbe, Größen zusammengefasst“ ist noch nicht fertig abgenommen. Verwenden Sie Mode-Sortimente erst nach dieser Umsetzung und offizieller Prüfung. Zusätzliche Bildfelder und spezielle Sortimentsattribute sind noch offen.

Die Versandkosten werden ohne produktbezogene Überschreibung bewusst ausgelassen; BestPrice soll seine bestätigte allgemeine Regel anwenden. Das ist keine Berechnung beliebiger Shopware-Rule-Builder-Versandkosten. Kostenlose Lieferung darf nur durch einen echten Nullwert angegeben werden. Prüfen Sie Gewichte und gegebenenfalls das frachtpflichtige Volumengewicht.

## Feed, Vorschau und Datenqualität

Nach erfolgreichem Build steht eine XML-URL bereit; ersetzen Sie .xml durch .csv für den Händlerexport. Die genaue BestPrice-CSV-Annahmekonfiguration ist noch zu bestätigen. Jede URL enthält einen geheimen Zugriffsschlüssel. Senden Sie sie nur an berechtigte Personen/BestPrice und redigieren Sie Proxylogs. Die lokale Vorschau ist der Abruf des zuletzt generierten Feeds. Kein gültiger Build bedeutet keine veröffentlichte Datei.

Datenqualität zeigt UUID, Feld, Schweregrad und Prüfcode mit Produktlink. Pflichtfelder, gültige Preise/URLs, Encoding, GTIN-Prüfziffern und doppelte Kennungen werden geprüft. Ungültige Datensätze werden ausgelassen. Sind alle Datensätze ungültig oder schlägt der Build fehl, bleibt der letzte gültige Feed erhalten. Der interne Score ist der prozentuale Anteil gültiger Datensätze; er ist kein BestPrice-Ranking. Warnungen zählen nicht als ungültig. Korrigieren Sie Probleme im Produkt und erzeugen Sie neu. Offizielle Annahme erfordert anschließend die BestPrice-Validierung.

## Tarife, Berechnungen und Tracking

Basic hat keine künstliche Produktzahlgrenze. Professional/Advanced sind vorgeschlagene Shopware-IAP-Tiers, noch keine aktivierten Angebote. Es wird kein eigener Kauf- oder Lizenzserver verwendet. Etwaige Käufe erfolgen ausschließlich über den offiziellen Shopware-Dialog, Kündigung/Wechsel im Shopware-Account. Die überlappenden Einzel-Add-ons sind zurückgestellt. Marktbeobachtung bleibt wegen fehlender Rechte gesperrt.

Die Preisvorschau berechnet aus vollständigen Nettokosten, Zusatzkosten, Zielmarge und Steuer einen Vorschlag. Sie schreibt keine Shopware-Preise. Profitabilität ist derzeit eine Berechnung aus Händlerangaben, keine BestPrice-Statistik. Geben Sie alle Nettokosten vollständig an; fehlende Angaben liefern keine scheinbar genaue Gewinnzahl. Umsatz/Klicks/Kampagnenimporte, automatische Preisänderungen und historische Reports sind noch nicht umgesetzt.

BestPrice360 ist recherchiert, aber externe Übertragung und offizielle Tests sind noch gesperrt. Keine produktive Tracking-Funktion oder Rechtskonformität wird zugesagt. Consent, Widerruf, Gastbestellungen, asynchrone/fehlgeschlagene Zahlungen und Deduplizierung müssen vor Freigabe im echten Ablauf geprüft werden. Headless-Tracking ist noch nicht bereit; keine geheimen Zugangsdaten an Clients weitergeben.

## Upgrade, Downgrade und Datenhaltung

Die Tarifprüfung läuft serverseitig. Basic bleibt nach Downgrade erhalten; zusätzliche Konfigurationen werden nicht gelöscht, aber ohne Advanced nicht neu aufgebaut. Bereits vorhandene Snapshots können veralten: deaktivieren Sie ungenutzte URLs bewusst. Aktivieren Sie keine nicht geprüften Automatisierungen. Deaktivierung entfernt die Pluginrouten und stoppt neue Pluginverarbeitung; Produktpreise/Bestellungen werden nicht verändert.

Bei Deinstallation mit Datenbeibehaltung bleiben eigene Tabellen und Feeddateien bestehen. Ohne Datenbeibehaltung werden nur eigene Tabellen, Plugin-Systemkonfiguration und stpi-gh-bestprice/ (Shopware private filesystem) entfernt. Händlerprodukte/Bestellungen anderer Erweiterungen bleiben bestehen. Prüfen Sie das Verhalten anhand der tatsächlich veröffentlichten Archivversion.

## Fehlerbehebung, Datenschutz und Support

Bei fehlenden Dateien: Aktivierung, Auswahl, Worker, Buildstatus, Domain/EUR/Bruttopreis, Bilder und Schreibrechte prüfen. Bei HTTP403: Rolle und Tarif prüfen. Bei HTTP404: aktivierter Feed, korrekter Schlüssel und erfolgreicher Build. Falsche Versand-/Preisangaben im Shop und Portal gemeinsam korrigieren.

Öffentliche Feeds enthalten keine Einkaufspreise/Kundendaten. Bewahren Sie Zugriffsschlüssel und Backups vertraulich auf. Erstellen Sie Ihre Datenschutzhinweise passend zu den tatsächlich aktivierten Diensten; rechtliche Prüfung für Griechenland/Zypern bleibt erforderlich. Support über https://stefanpilz.ltd mit Versionsangaben und redigiertem Diagnosecode, ohne Tokens, Kundenexporte oder komplette Mailarchive.
