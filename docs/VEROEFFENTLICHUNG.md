# Veröffentlichung auf WordPress.org (Schritt-für-Schritt)

## 1) Voraussetzungen

- WordPress.org Konto erstellen: https://login.wordpress.org/register
- Einen eindeutigen Plugin-Slug prüfen (z. B. `hochzeit-geschenkeliste`)
- Sicherstellen, dass dein Plugin unter GPL-kompatibler Lizenz steht (ist jetzt umgesetzt)

## 2) Antrag stellen

1. Gehe auf: https://wordpress.org/plugins/developers/add/
2. Plugin-Name und kurze Beschreibung eintragen
3. ZIP-Datei des Plugin-Ordners hochladen
4. Auf Freigabe-E-Mail vom Plugin Review Team warten

## 3) Nach Freigabe: SVN-Zugang

Nach Annahme bekommst du ein eigenes SVN-Repository, z. B.:

`https://plugins.svn.wordpress.org/hochzeit-geschenkeliste/`

Ordnerstruktur:

- `trunk/` (aktuelle Version)
- `tags/1.1.0/` (Versionstags)
- `assets/` (Banner/Icons/Screenshots für Plugin-Seite)

## 4) Erstes Release

1. Inhalt deines Plugins in `trunk/` hochladen
2. Dieselben Dateien in `tags/1.1.0/` kopieren
3. Commit mit sinnvoller Nachricht durchführen
4. Nach einigen Minuten erscheint die Plugin-Seite öffentlich

## 5) Empfohlene Assets

- Plugin-Icon: 256x256 und 128x128 PNG
- Banner: 1544x500 PNG/JPG
- Screenshots: passend zu `readme.txt` Screenshots-Sektion

## 6) Künftige Updates

- `Version` im Plugin-Header erhöhen
- `Stable tag` in `readme.txt` aktualisieren
- Changelog ergänzen
- Neue Version in `tags/<version>/` anlegen und committen

## 7) Wichtiger rechtlicher Hinweis

Du **kannst auch als Privatperson ohne Firma/Gewerbe** auf WordPress.org veröffentlichen.

Typisch ist:

- Ein Impressum ist für die WordPress.org-Plugin-Seite selbst nicht als Unternehmenspflicht gebunden wie bei einem eigenen Shop.
- Wenn du eine eigene Website/Landingpage betreibst, gelten dort die landesspezifischen Rechtspflichten (z. B. in Deutschland Impressum/Datenschutz je nach Einzelfall).
- Für das Plugin selbst solltest du eine Kontaktmöglichkeit und Datenschutzhinweise bereitstellen.

## 8) GitHub und WordPress.org sauber trennen

- **GitHub** ist dein Entwicklungs-Repository (Branches, Issues, PRs).
- **WordPress.org (SVN)** ist dein Release-Repository (nur veröffentlichte, lauffähige Versionen).
- Die Datei `.distignore` stellt sicher, dass Entwicklungsdateien nicht in das Release-Archiv kommen.

Typischer Ablauf:

1. Entwicklung und Tests in GitHub abschließen.
2. Version in `hochzeit-geschenkeliste.php` und `readme.txt` erhöhen.
3. Release-Archiv aus dem Projekt erzeugen (ohne Dev-Dateien).
4. Archiv an WordPress.org einreichen (erste Einreichung) oder neue Version nach SVN pushen.

## 9) Release-Archiv erstellen

Empfohlen mit WP-CLI (nutzt `.distignore`):

```bash
wp package install wp-cli/dist-archive-command
wp dist-archive . ./dist --create-target-dir --plugin-dirname=hochzeit-geschenkeliste
```

Alternative ohne WP-CLI:

```bash
PLUGIN_VERSION="$(awk -F': ' '/^[[:space:]]*\\* Version:/ {print $2; exit}' hochzeit-geschenkeliste.php | xargs)"
mkdir -p /private/tmp/plugin-release
rsync -av --delete \
  --exclude-from='.distignore' \
  ./ /private/tmp/plugin-release/hochzeit-geschenkeliste
cd /private/tmp/plugin-release && zip -r "hochzeit-geschenkeliste.${PLUGIN_VERSION}.zip" hochzeit-geschenkeliste
```
