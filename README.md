# Hochzeit Geschenkeliste - WordPress Plugin

Ein einfaches WordPress-Plugin zur Verwaltung einer Hochzeits-Geschenkeliste.

## Features

### Frontend
- Anzeige aller Geschenke in einem ansprechenden Grid-Layout
- Geschenke mit Titel, Beschreibung, Bild und Link zum Shop
- Automatischer Platzhalter mit Geschenk-Icon, wenn kein Bild hochgeladen wurde
- Statusanzeige: "Verfügbar" oder "Vergeben"
- Reservierungsfunktion mit E-Mail-Verifizierung
- E-Mail-Bestätigung mit Verifizierungslink
- Selbstständige Stornierung durch Gäste per E-Mail-Link
- Responsive Design für mobile Geräte

### Backend (WordPress Admin)
- Geschenke hinzufügen, bearbeiten und löschen
- Bildupload über WordPress Media Library
- Übersicht aller Geschenke mit Reservierungsstatus
- Anzeige, wer welches Geschenk reserviert hat (Name, E-Mail, Datum)
- Status-Anzeige: "Bestätigt" oder "Warte auf Bestätigung"
- Reservierungen manuell aufheben

## Installation

1. **Plugin hochladen**
   - Lade den Ordner `hochzeit-geschenkeliste` in das Verzeichnis `/wp-content/plugins/` hoch
   - Oder: ZIP-Datei über WordPress Admin > Plugins > Installieren > Plugin hochladen

2. **Plugin aktivieren**
   - Gehe zu WordPress Admin > Plugins
   - Aktiviere "Hochzeit Geschenkeliste"
   - Das Plugin erstellt automatisch die benötigten Datenbanktabellen

3. **Geschenke hinzufügen**
   - Im WordPress Admin findest du nun den Menüpunkt "Geschenkeliste" (mit Herz-Icon)
   - Füge deine Geschenkwünsche hinzu mit:
     - Titel (Pflichtfeld)
     - Beschreibung (optional)
     - Link zum Shop (optional)
     - Bild (optional)

4. **Frontend einbinden**
   - Erstelle eine neue Seite (z.B. "Geschenkeliste")
   - Füge den Shortcode `[geschenkeliste]` in den Seiteninhalt ein
   - Veröffentliche die Seite
   - Die Geschenkeliste wird nun auf dieser Seite angezeigt

## Verwendung

### Für Administratoren (Backend)

1. **Geschenk hinzufügen:**
   - Titel eingeben (erforderlich)
   - Optional: Beschreibung, Link und Bild hinzufügen
   - "Geschenk hinzufügen" klicken

2. **Geschenk bearbeiten:**
   - In der Übersicht auf "Bearbeiten" klicken
   - Änderungen vornehmen
   - "Speichern" klicken

3. **Geschenk löschen:**
   - In der Übersicht auf "Löschen" klicken
   - Bestätigung erforderlich

4. **Reservierungen einsehen:**
   - In der Übersicht siehst du bei jedem Geschenk:
     - Status: Verfügbar, "Warte auf Bestätigung" oder "Bestätigt"
     - Name und E-Mail des Reservierenden
     - Reservierungsdatum und -uhrzeit
     - Button "Reservierung aufheben" bei bestätigten Reservierungen

### Für Gäste (Frontend)

1. Geschenkeliste auf der Website aufrufen
2. Gewünschtes Geschenk aussuchen
3. "Geschenk reservieren" klicken
4. Name (optional) und E-Mail-Adresse eingeben
5. "Jetzt reservieren" klicken
6. Bestätigungs-E-Mail wird verschickt
7. E-Mail öffnen und auf "Reservierung bestätigen" klicken
8. Nach der Bestätigung wird das Geschenk als "Vergeben" markiert
9. Optional: Reservierung über den Stornierungslink in der E-Mail selbst aufheben

## Datenbank

Das Plugin erstellt zwei Tabellen:

- `wp_geschenkeliste` - Speichert die Geschenke
- `wp_geschenkeliste_reservierungen` - Speichert die Reservierungen mit Verifizierungsstatus

## E-Mail-Verifizierung

Das Plugin verwendet ein Zwei-Schritt-Verifizierungssystem:

1. **Reservierung:** Gast gibt E-Mail-Adresse ein
2. **Bestätigungs-E-Mail:** Automatische E-Mail mit Bestätigungs- und Stornierungslink
3. **Verifizierung:** Gast klickt auf Bestätigungslink
4. **Aktivierung:** Reservierung wird aktiviert und als "Vergeben" markiert

**Vorteile:**
- Schutz vor Spam und Missbrauch
- Sicherstellung gültiger E-Mail-Adressen
- Gäste können Reservierungen selbst stornieren
- Automatische Bereinigung nicht bestätigter Reservierungen

## Shortcode

```
[geschenkeliste]
```

Dieser Shortcode zeigt die komplette Geschenkeliste im Frontend an.

## Customization

### CSS anpassen
Die Styles findest du in:
- `/css/frontend-style.css` - Frontend-Styling
- `/css/admin-style.css` - Backend-Styling

### Farben ändern
In der `frontend-style.css` kannst du die Hauptfarbe anpassen:
```css
.button-reserve {
    background: #0073aa; /* Deine Farbe */
}
```

## Support

Bei Fragen oder Problemen kannst du:
- Den Code in den Plugin-Dateien anpassen
- Die CSS-Dateien für individuelles Styling bearbeiten

## Technische Details

- **Kompatibilität:** WordPress 6.0+
- **PHP Version:** 7.4+
- **Datenbank:** MySQL 5.6+

## Sicherheit

- Alle AJAX-Requests sind mit Nonces geschützt
- E-Mail-Adressen werden validiert
- SQL-Injection-Schutz durch prepared statements
- XSS-Schutz durch Sanitization und Escaping

## Lizenz

Dieses Plugin steht unter der Lizenz **GPLv2 oder neuer**.
