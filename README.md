# Vylaris Links

Link-in-Bio-Dienst der VylarisGroup Schweiz, gebaut mit **Laravel 13** (PHP 8.3), **Blade + Alpine.js + Tailwind CSS 4** und **MySQL/MariaDB**.
Kostenlos, ohne Werbung, ohne Tracking-Cookies auf den öffentlichen Profilseiten.

## Funktionen

- Eigene Registrierung, Login und Passwort-Reset (nur Deutsch)
- Bis zu 3 Seiten pro Konto, Adresse `deine-domain/name`
- Einträge: Link, Überschrift, Text, Bild, YouTube (nocookie), Twitch, Spotify, Countdown, Galerie
- 24 Social-Icons (Kürzel-Badges), Live-Vorschau im Editor
- 12 Designs, 10 Anordnungen, eigene Farben/Schrift/Knopfform, Hintergrundbild
- Effekte: Avatar-Ring, Glanz/Wackeln pro Link, Partikel (Schnee, Herzen, Konfetti, Glühwürmchen), Maus-Funken, Eingang mit Musik
- Zeitsteuerung, Passwort- und „Ab 18“-Schutz pro Link, Kontaktformular (Honeypot, Link-Filter, Rate-Limit)
- Cookielose Statistik ohne IP-Adressen (Tageszähler, Gerät, Herkunft, optional Land per `CF-IPCountry`)
- Wochenbericht per E-Mail (`links:weekly-report`, montags 07:30 Europe/Zurich)
- Melden-Funktion für Besucher, Admin-Bereich zum Sperren von Seiten und Konten
- Automatisches Update-System aus der `source-code.zip` eines GitHub-Releases (siehe unten)

## Datenschutz-Konzept

Die öffentlichen Routen (`/{name}`, `/go/{id}`, Kontakt, Melden) laufen **ohne Session und ohne Cookies**
(siehe `routes/web.php`). Rückmeldungen laufen über Query-Parameter, geschützte Links werden ohne Session
direkt nach der Prüfung weitergeleitet. Die Statistik speichert keine IP-Adressen. Editor, Dashboard und
Landingpage nutzen normale Laravel-Sessions.

## Einrichtung

### Web-Assistent (empfohlen)

1. Das `source-code.zip` des neuesten Releases entpacken (enthält `vendor/` und `public/build`) und den Webserver auf den Ordner `public/` zeigen lassen. Der Ordner `storage/` und `bootstrap/cache/` müssen für den Webserver beschreibbar sein.
2. Die Seite im Browser öffnen – sie führt automatisch zum Assistenten unter `/setup`.
3. Das **Einrichtungs-Token** eingeben. Es steht auf dem Server in der Datei `storage/app/setup-token` (Schutz davor, dass ein Fremder die Einrichtung übernimmt). Alternativ lässt sich ein festes Token über `LINKS_SETUP_TOKEN` in der `.env` setzen.
4. Der Assistent führt durch: Systemprüfung → Datenbank (mit Verbindungstest) → Instanz (Name, Adresse, Betriebsart, Registrierung offen/geschlossen, Seiten pro Konto, Update-Prüfung) → Administrator → E-Mail/SMTP (mit Testmail) → Zusammenfassung → Installation.
5. Danach ist der Assistent gesperrt (`storage/app/installed.json`, Token gelöscht). Noch den Cron-Eintrag aus dem Abschlussbildschirm einrichten.

Eine bereits laufende Instanz mit Administrator wird beim ersten Aufruf automatisch als eingerichtet erkannt – der Assistent erscheint dort nicht. Läuft die Instanz ohne Administrator, fängt er an dieser Stelle an. Der Assistent arbeitet ohne Cookies, Sessions und Datenbank; geschrieben wird nur die `.env`. Installationen aus Git brauchen vorher `composer install --no-dev` und `npm ci && npm run build` (oder `./setup.sh`).

### Auf dem Server per Skript

1. Das `source-code.zip` des neuesten Releases entpacken (enthält `vendor/` und `public/build`) – oder per `git clone` holen.
2. Den Webserver auf den Ordner `public/` zeigen lassen.
3. Im Projektordner ausführen:

```bash
./setup.sh
```

Das Skript installiert bei Bedarf Composer-Pakete und baut die Assets (nur bei einer Git-Installation), setzt die Verzeichnisrechte und startet `php artisan links:install`. Dieser Befehl fragt Adresse, Datenbank, optional SMTP und das Admin-Konto ab und erledigt `.env`, `APP_KEY`, Datenbank anlegen (falls erlaubt), Migrationen, `storage:link` und die Produktions-Caches. Er lässt sich gefahrlos erneut ausführen; vorhandene Werte bleiben erhalten.

Ohne Rückfragen (z. B. für Skripte):

```bash
LINKS_DB_PASSWORD='…' LINKS_ADMIN_PASSWORD='…' ./setup.sh --url=https://links.example.ch \
  --db-name=vylaris_links --db-user=links --admin-email=du@example.ch --no-interaction
```

Mit `php artisan links:install --check` wird nur geprüft (PHP, Erweiterungen, `.env`, Schlüssel, Datenbank, Migrationen, Rechte, Assets) – hilfreich bei einem Fehler 500.

### Manuell

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env        # DB_*, MAIL_*, APP_URL anpassen
php artisan key:generate
php artisan migrate --force
php artisan storage:link
npm ci && npm run build
php artisan links:make-admin deine@mail.ch   # nach der Registrierung
```

Für den Wochenbericht muss der Scheduler laufen:

```cron
* * * * * cd /pfad/zum/projekt && php artisan schedule:run >> /dev/null 2>&1
```

## Entwicklung und Tests

```bash
composer run dev        # oder: php artisan serve + npm run dev
php artisan test
```

## Update-System

Der Admin-Bereich (**Admin → Updates**) und der Befehl `php artisan links:update` aktualisieren die Anwendung
aus der Datei `source-code.zip`, die am neuesten Release von `UPDATER_REPO` hängt (Standard: `VylarisGroup/vylaris-links`).

**Ablauf:** Release prüfen → Zip herunterladen (nur von GitHub-Hosts) → Grösse und SHA-256 prüfen → Paket auf unsichere
Pfade, Symlinks und Zip-Bomben prüfen → entpacken → Datenbank sichern → Wartungsmodus → Dateien austauschen →
`migrate` und `optimize:clear` → Wartungsmodus aus. Schlägt ein Schritt fehl, werden Migrationen und Dateien
automatisch zurückgesetzt. Die Sicherungen (Dateien und Datenbank, MySQL/MariaDB als `.sql.gz`, SQLite als Kopie)
liegen in `storage/app/updater/backups`, die letzten 3 bleiben erhalten.

**Wann:** Der Scheduler prüft jede Nacht um 03:15 auf neue Releases. Installiert wird nur per Klick im Admin-Bereich
oder per `php artisan links:update`. Der Admin-Link zeigt einen grünen Punkt, wenn ein Update bereitsteht.

**Nie angefasst werden:** `.env`, `storage/`, `node_modules/`, `.git`, `public/storage`, `bootstrap/cache`
und SQLite-Dateien in `database/`.

**Release veröffentlichen:** Release mit Tag (z. B. `v1.1.0`) auf GitHub veröffentlichen. Der Workflow
`.github/workflows/release.yml` baut dann den fertigen Stand (mit `vendor/` und `public/build`, `VERSION` aus dem Tag)
und hängt ihn als `source-code.zip` an. Die installierte Version steht in der Datei `VERSION`.

Einstellungen in `.env`: `UPDATER_ENABLED`, `UPDATER_REPO`, `UPDATER_KEEP_BACKUPS`, optional `UPDATER_GITHUB_TOKEN`.
Voraussetzungen: PHP-Erweiterung `zip`, vom Webserver beschreibbarer Projektordner, rund 400 MB freier Platz.
Ein manueller Rollback ist möglich, indem der Inhalt von `backups/<Datum>/files` zurückkopiert und die Datenbank-Sicherung eingespielt wird.

## Hinweise

- `LINKS_MAX_PAGES` und `LINKS_RESERVED_SLUGS` (kommagetrennt) in `.env` anpassbar.
- Hochgeladene Bilder liegen auf der `public`-Disk (`storage/app/public/pages/{id}`).
- Das Logo (`public/img/logo.svg`) ist ein Platzhalter und kann durch das offizielle Vylaris-Logo ersetzt werden.
