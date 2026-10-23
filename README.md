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

## Datenschutz-Konzept

Die öffentlichen Routen (`/{name}`, `/go/{id}`, Kontakt, Melden) laufen **ohne Session und ohne Cookies**
(siehe `routes/web.php`). Rückmeldungen laufen über Query-Parameter, geschützte Links werden ohne Session
direkt nach der Prüfung weitergeleitet. Die Statistik speichert keine IP-Adressen. Editor, Dashboard und
Landingpage nutzen normale Laravel-Sessions.

## Einrichtung

```bash
composer install
cp .env.example .env        # DB_*, MAIL_*, APP_URL anpassen
php artisan key:generate
php artisan migrate
php artisan storage:link
npm install && npm run build
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

## Hinweise

- `LINKS_MAX_PAGES` und `LINKS_RESERVED_SLUGS` (kommagetrennt) in `.env` anpassbar.
- Hochgeladene Bilder liegen auf der `public`-Disk (`storage/app/public/pages/{id}`).
- Das Logo (`public/img/logo.svg`) ist ein Platzhalter und kann durch das offizielle Vylaris-Logo ersetzt werden.
