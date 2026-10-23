<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#05070c">
    <title>Einrichtung – Vylaris Links</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/setup.css') }}">
</head>
<body>
<main id="setup" class="wrap" data-base="/setup/api">
    <header class="top">
        <img src="{{ asset('img/logo.svg') }}" alt="" width="36" height="36">
        <div>
            <p class="brand">Vylaris <span class="grad">Links</span></p>
            <p class="sub">Einrichtungsassistent</p>
        </div>
    </header>

    <ol class="steps" aria-label="Fortschritt">
        @foreach (['Start', 'System', 'Datenbank', 'Instanz', 'Admin', 'E-Mail', 'Installieren', 'Fertig'] as $i => $label)
            <li><span class="num">{{ $i + 1 }}</span><span class="lbl">{{ $label }}</span></li>
        @endforeach
    </ol>

    <noscript><div class="note bad">Der Assistent braucht JavaScript.</div></noscript>

    {{-- 0 Start --}}
    <section class="card" data-step="0">
        <h1 tabindex="-1">Willkommen</h1>
        <p>Dieser Assistent richtet deine Instanz in wenigen Schritten ein: Datenbank, Einstellungen, Administrator und E-Mail-Versand. Danach ist er automatisch gesperrt.</p>
        @if ($tokenReady)
            <p>Damit nicht jemand anderes die Einrichtung übernimmt, brauchst du ein <strong>Einrichtungs-Token</strong>. Du findest es auf dem Server in der Datei <code>{{ $tokenFile }}</code> – zum Beispiel per FTP oder mit <code>cat {{ $tokenFile }}</code>.</p>
            <form id="token-form" novalidate>
                <div class="fw">
                    <label for="token">Einrichtungs-Token</label>
                    <input id="token" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" required autofocus placeholder="xxxxx-xxxxx-xxxxx-xxxxx">
                    <p class="err" id="token-error" role="alert" hidden></p>
                </div>
                <div class="nav"><span></span><button class="btn primary" type="submit">Weiter</button></div>
            </form>
        @else
            <div class="note bad">
                Das Token konnte nicht angelegt werden, weil <code>storage/app</code> nicht beschreibbar ist. Mach den Ordner <code>storage</code> für den Webserver beschreibbar
                (<code>chmod -R ug+w storage bootstrap/cache</code>) und lade die Seite neu – oder lege die Datei <code>{{ $tokenFile }}</code> selbst mit einem geheimen Text an.
            </div>
        @endif
    </section>

    {{-- 1 System --}}
    <section class="card" data-step="1" hidden>
        <h2 tabindex="-1">Systemprüfung</h2>
        <p>Das braucht Vylaris Links auf diesem Server. Rote Punkte müssen behoben werden, gelbe sind Empfehlungen.</p>
        <ul class="checks" id="req-list"></ul>
        <p class="note" id="req-summary" role="status"></p>
        <div class="nav">
            <button class="btn" type="button" id="req-recheck">Erneut prüfen</button>
            <button class="btn primary" type="button" data-next id="req-next">Weiter</button>
        </div>
    </section>

    {{-- 2 Datenbank --}}
    <section class="card" data-step="2" hidden>
        <h2 tabindex="-1">Datenbank</h2>
        <p>Lege die Datenbank beim Hoster an (oder nutze einen Benutzer, der sie anlegen darf) und trage die Zugangsdaten ein.</p>
        <fieldset class="choice">
            <legend>Art</legend>
            <label><input type="radio" name="db.driver" value="mysql" checked> MySQL / MariaDB <small>(empfohlen)</small></label>
            <label><input type="radio" name="db.driver" value="sqlite"> SQLite <small>(nur für kleine Instanzen und Tests)</small></label>
        </fieldset>
        <div data-group="db-mysql">
            <div class="row">
                <div class="fw grow"><label for="db-host">Host</label><input id="db-host" name="db.host" type="text" required maxlength="120" autocomplete="off"><p class="err" hidden></p></div>
                <div class="fw port"><label for="db-port">Port</label><input id="db-port" name="db.port" type="number" required min="1" max="65535" inputmode="numeric"><p class="err" hidden></p></div>
            </div>
            <div class="fw"><label for="db-name">Datenbankname</label><input id="db-name" name="db.name" type="text" required maxlength="64" pattern="[A-Za-z0-9_]+" autocomplete="off"><p class="hint">Nur Buchstaben, Zahlen und Unterstrich.</p><p class="err" hidden></p></div>
            <div class="row">
                <div class="fw grow"><label for="db-user">Benutzer</label><input id="db-user" name="db.user" type="text" required maxlength="80" autocomplete="off"><p class="err" hidden></p></div>
                <div class="fw grow"><label for="db-pass">Passwort</label><input id="db-pass" name="db.pass" type="password" maxlength="200" autocomplete="new-password"><p class="err" hidden></p></div>
            </div>
        </div>
        <div data-group="db-sqlite" hidden>
            <p class="note">Die Daten landen in der Datei <code>database/database.sqlite</code>. Sie muss ausserhalb von <code>public/</code> bleiben (das ist hier der Fall).</p>
        </div>
        <div class="testrow">
            <button class="btn" type="button" id="db-test">Verbindung testen</button>
            <p id="db-result" role="status" class="note" hidden></p>
        </div>
        <div class="nav">
            <button class="btn" type="button" data-back>Zurück</button>
            <button class="btn primary" type="button" data-next id="db-next" disabled>Weiter</button>
        </div>
    </section>

    {{-- 3 Instanz --}}
    <section class="card" data-step="3" hidden>
        <h2 tabindex="-1">Deine Instanz</h2>
        <div class="fw"><label for="i-name">Name der Instanz</label><input id="i-name" name="instance.name" type="text" required maxlength="60"><p class="hint">Wird als Absendername in E-Mails verwendet.</p><p class="err" hidden></p></div>
        <div class="fw"><label for="i-url">Öffentliche Adresse</label><input id="i-url" name="instance.url" type="url" required maxlength="200" placeholder="https://links.example.ch"><p class="hint">So erreichen Besucher die Seite – mit <code>https://</code>, ohne Pfad am Ende.</p><p class="err" hidden></p></div>
        <fieldset class="choice">
            <legend>Betriebsart</legend>
            <label><input type="radio" name="instance.env" value="production" checked> Produktion <small>(Fehlerdetails versteckt, schnell – für den Live-Betrieb)</small></label>
            <label><input type="radio" name="instance.env" value="local"> Entwicklung <small>(Fehlerdetails sichtbar)</small></label>
        </fieldset>
        <div class="fw"><label for="i-max">Seiten pro Konto</label><input id="i-max" name="instance.max_pages" type="number" required min="1" max="50" inputmode="numeric"><p class="hint">Wie viele Link-Seiten ein Konto anlegen darf.</p><p class="err" hidden></p></div>
        <div class="fw check"><label><input type="checkbox" name="instance.registration" checked> Selbstregistrierung erlauben</label><p class="hint">Aus: Nur du als Administrator legst Konten an. Ein/Aus lässt sich später in der <code>.env</code> ändern (<code>LINKS_REGISTRATION</code>).</p></div>
        <div class="fw check"><label><input type="checkbox" name="instance.updates" checked> Automatische Update-Prüfung aktivieren</label><p class="hint">Prüft nachts auf neue Versionen. Installiert wird nie ohne deinen Klick im Admin-Bereich.</p></div>
        <div class="nav">
            <button class="btn" type="button" data-back>Zurück</button>
            <button class="btn primary" type="button" data-next>Weiter</button>
        </div>
    </section>

    {{-- 4 Admin --}}
    <section class="card" data-step="4" hidden>
        <h2 tabindex="-1">Administrator-Konto</h2>
        <p>Mit diesem Konto verwaltest du die Instanz (Meldungen, Sperren, Updates).</p>
        <div class="fw"><label for="a-name">Name</label><input id="a-name" name="admin.name" type="text" required maxlength="80" autocomplete="name"><p class="err" hidden></p></div>
        <div class="fw"><label for="a-email">E-Mail</label><input id="a-email" name="admin.email" type="email" required maxlength="120" autocomplete="email"><p class="err" hidden></p></div>
        <div class="fw"><label for="a-pass">Passwort</label><input id="a-pass" name="admin.password" type="password" required minlength="10" maxlength="200" autocomplete="new-password"><p class="hint">Mindestens 10 Zeichen mit Buchstaben und Zahlen.</p><p class="err" hidden></p></div>
        <div class="fw"><label for="a-pass2">Passwort wiederholen</label><input id="a-pass2" name="admin.password_confirmation" type="password" required maxlength="200" autocomplete="new-password"><p class="err" hidden></p></div>
        <div class="testrow">
            <button class="btn" type="button" id="pw-suggest">Passwort vorschlagen</button>
            <button class="btn" type="button" id="pw-toggle" aria-pressed="false">Anzeigen</button>
        </div>
        <div class="nav">
            <button class="btn" type="button" data-back>Zurück</button>
            <button class="btn primary" type="button" data-next>Weiter</button>
        </div>
    </section>

    {{-- 5 E-Mail --}}
    <section class="card" data-step="5" hidden>
        <h2 tabindex="-1">E-Mail-Versand</h2>
        <p>Für Passwort-Zurücksetzen und den Wochenbericht verschickt die Instanz E-Mails.</p>
        <fieldset class="choice">
            <legend>Versand</legend>
            <label><input type="radio" name="mail.mode" value="smtp" checked> SMTP-Zugang <small>(empfohlen)</small></label>
            <label><input type="radio" name="mail.mode" value="log"> Vorerst nicht <small>(E-Mails werden nur ins Log geschrieben)</small></label>
        </fieldset>
        <div data-group="mail-smtp">
            <div class="row">
                <div class="fw grow"><label for="m-host">SMTP-Host</label><input id="m-host" name="mail.host" type="text" required maxlength="120" placeholder="smtp.example.ch"><p class="err" hidden></p></div>
                <div class="fw port"><label for="m-port">Port</label><input id="m-port" name="mail.port" type="number" required min="1" max="65535" value="587" inputmode="numeric"><p class="err" hidden></p></div>
            </div>
            <div class="fw"><label for="m-sec">Verschlüsselung</label>
                <select id="m-sec" name="mail.security"><option value="starttls">STARTTLS (meist Port 587)</option><option value="ssl">SSL/TLS (meist Port 465)</option></select>
                <p class="err" hidden></p></div>
            <div class="row">
                <div class="fw grow"><label for="m-user">Benutzer</label><input id="m-user" name="mail.user" type="text" maxlength="120" autocomplete="off"><p class="err" hidden></p></div>
                <div class="fw grow"><label for="m-pass">Passwort</label><input id="m-pass" name="mail.pass" type="password" maxlength="200" autocomplete="new-password"><p class="err" hidden></p></div>
            </div>
            <div class="fw"><label for="m-from">Absender-Adresse</label><input id="m-from" name="mail.from" type="email" required maxlength="120"><p class="err" hidden></p></div>
            <div class="testrow">
                <button class="btn" type="button" id="mail-test">Testmail an den Administrator senden</button>
                <p id="mail-result" role="status" class="note" hidden></p>
            </div>
        </div>
        <div class="nav">
            <button class="btn" type="button" data-back>Zurück</button>
            <button class="btn primary" type="button" data-next>Weiter</button>
        </div>
    </section>

    {{-- 6 Installieren --}}
    <section class="card" data-step="6" hidden>
        <h2 tabindex="-1">Zusammenfassung</h2>
        <p>Prüfe die Angaben. Beim Installieren werden die <code>.env</code> geschrieben, die Datenbank eingerichtet und das Administrator-Konto angelegt.</p>
        <dl class="summary" id="summary"></dl>
        <p class="note bad" id="install-error" role="alert" hidden></p>
        <ul class="checks" id="run-log" aria-live="polite"></ul>
        <div class="nav">
            <button class="btn" type="button" data-back id="install-back">Zurück</button>
            <button class="btn primary" type="button" id="install-run">Jetzt installieren</button>
        </div>
    </section>

    {{-- 7 Fertig --}}
    <section class="card" data-step="7" hidden>
        <h2 tabindex="-1">Fertig – deine Instanz läuft</h2>
        <ul class="checks" id="done-log"></ul>
        <p>Der Assistent ist jetzt gesperrt, das Token gelöscht. Noch zwei Dinge auf dem Server:</p>
        <ol class="todo">
            <li>Stelle sicher, dass der Webserver auf den Ordner <code>public/</code> zeigt.</li>
            <li>Richte diesen Cron-Eintrag ein (Wochenbericht, nächtliche Update-Prüfung):<pre><code id="cron"></code></pre></li>
        </ol>
        <div class="nav"><span></span><a class="btn primary" id="login-link" href="/anmelden">Zur Anmeldung</a></div>
    </section>

    <p class="foot">Ein Dienst der VylarisGroup</p>
</main>
<script src="{{ asset('js/setup.js') }}" defer></script>
</body>
</html>
