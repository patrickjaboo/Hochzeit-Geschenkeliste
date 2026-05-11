=== Hochzeit Geschenkeliste ===
Contributors: patrickjanssen
Tags: wedding, wishlist, gifts, reservation, shortcode
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ein WordPress-Plugin für eine Hochzeits-Geschenkeliste mit E-Mail-verifizierten Reservierungen.

== Description ==

Hochzeit Geschenkeliste ermöglicht es, eine Geschenkeliste auf einer WordPress-Seite anzuzeigen und Geschenke für Gäste reservierbar zu machen.

Funktionen:

* Frontend-Shortcode `[geschenkeliste]`
* Verwaltung der Geschenke im Adminbereich
* Reservierung mit E-Mail-Bestätigung
* Selbstständige Stornierung per E-Mail-Link
* Automatische Bereinigung unbestätigter Reservierungen

== Installation ==

1. Plugin-Ordner `hochzeit-geschenkeliste` nach `/wp-content/plugins/` hochladen.
2. Plugin in WordPress unter "Plugins" aktivieren.
3. Seite erstellen und den Shortcode `[geschenkeliste]` einfügen.

== Frequently Asked Questions ==

= Welche personenbezogenen Daten werden gespeichert? =

Bei einer Reservierung speichert das Plugin die E-Mail-Adresse, optional einen Namen, einen Verifizierungs-Token und den Reservierungszeitpunkt.

= Kann ich Reservierungsdaten exportieren und löschen? =

Ja. Das Plugin integriert sich in die WordPress-Datenschutz-Tools (Export/Löschen personenbezogener Daten).

== Screenshots ==

1. Frontend-Ansicht der Geschenkeliste
2. Admin-Verwaltung der Geschenke
3. Reservierungsdialog für Gäste

== Changelog ==

= 1.1.0 =
* Veröffentlichungs-Metadaten ergänzt (Lizenz, Requires, Domain Path)
* WordPress.org-kompatibles `readme.txt` ergänzt
* Datenschutzintegration für Export/Löschen personenbezogener Daten ergänzt
* `uninstall.php` ergänzt
* Fehlenden AJAX-Endpoint `get_geschenk` implementiert
* Sanitizing-Härtung für Input-Werte

= 1.0.0 =
* Erste Veröffentlichung

== Upgrade Notice ==

= 1.1.0 =
Empfohlenes Update für bessere Datenschutzintegration und Veröffentlichungs-Compliance.
