/* Einrichtungsassistent – bewusst ohne Build, Framework, Cookies und Session. */
(function () {
    'use strict';

    const root = document.getElementById('setup');
    if (!root) return;

    const base = root.dataset.base;
    const $ = (sel, ctx) => (ctx || root).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || root).querySelectorAll(sel));
    const panels = $$('[data-step]');
    const crumbs = $$('.steps li');
    const LAST_FORM_STEP = 6;

    let token = '';
    let current = 0;
    let dbOk = false;
    let blocking = 0;

    // ---------------------------------------------------------------- Hilfen

    function el(tag, cls, text) {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
    }

    async function api(path, body) {
        let res;
        try {
            res = await fetch(base + path, {
                method: 'POST',
                credentials: 'omit',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Setup-Token': token },
                body: JSON.stringify(body || {}),
            });
        } catch (e) {
            return { status: 0, data: { message: 'Der Server ist nicht erreichbar.' } };
        }
        let data = {};
        try { data = await res.json(); } catch (e) {
            data = { message: 'Unerwartete Antwort des Servers (Status ' + res.status + '). Details stehen in storage/logs/laravel.log.' };
        }
        return { status: res.status, data: data };
    }

    function setPath(obj, path, value) {
        const parts = path.split('.');
        let o = obj;
        parts.slice(0, -1).forEach(function (p) { o = o[p] = o[p] || {}; });
        o[parts[parts.length - 1]] = value;
    }

    function collect() {
        const out = {};
        $$('[name]').forEach(function (f) {
            if (f.type === 'radio' && !f.checked) return;
            setPath(out, f.name, f.type === 'checkbox' ? f.checked : f.value);
        });
        return out;
    }

    function fill(defaults) {
        Object.keys(defaults).forEach(function (section) {
            Object.keys(defaults[section]).forEach(function (key) {
                const name = section + '.' + key;
                const value = defaults[section][key];
                $$('[name="' + name + '"]').forEach(function (f) {
                    if (f.type === 'radio') f.checked = f.value === String(value);
                    else if (f.type === 'checkbox') f.checked = !!value;
                    else f.value = value == null ? '' : value;
                });
            });
        });
        toggleGroups();
    }

    function clearErrors() {
        $$('.err').forEach(function (p) { p.hidden = true; p.textContent = ''; });
        $$('[aria-invalid]').forEach(function (i) { i.removeAttribute('aria-invalid'); });
    }

    /** Zeigt Serverfehler am Feld an und gibt den frühesten betroffenen Schritt zurück. */
    function showErrors(errors) {
        let first = null;
        Object.keys(errors).forEach(function (name) {
            const field = $('[name="' + name + '"]');
            if (!field) return;
            const wrap = field.closest('.fw');
            const p = wrap && $('.err', wrap);
            if (p) { p.textContent = errors[name][0]; p.hidden = false; }
            field.setAttribute('aria-invalid', 'true');
            const step = Number(field.closest('[data-step]').dataset.step);
            if (first === null || step < first) first = step;
        });
        return first;
    }

    // ---------------------------------------------------------------- Navigation

    function show(i) {
        current = i;
        panels.forEach(function (p) { p.hidden = Number(p.dataset.step) !== i; });
        crumbs.forEach(function (c, n) {
            c.classList.toggle('done', n < i);
            if (n === i) c.setAttribute('aria-current', 'step'); else c.removeAttribute('aria-current');
        });
        if (i === LAST_FORM_STEP) renderSummary();
        const heading = $('[data-step="' + i + '"] h1, [data-step="' + i + '"] h2');
        if (heading) heading.focus({ preventScroll: true });
        window.scrollTo({ top: 0 });
    }

    function toggleGroups() {
        const driver = ($('[name="db.driver"]:checked') || {}).value;
        const mail = ($('[name="mail.mode"]:checked') || {}).value;
        [['db-mysql', driver === 'mysql'], ['db-sqlite', driver === 'sqlite'], ['mail-smtp', mail === 'smtp']].forEach(function (g) {
            const box = $('[data-group="' + g[0] + '"]');
            if (!box) return;
            box.hidden = !g[1];
            $$('input, select', box).forEach(function (f) { f.disabled = !g[1]; });
        });
    }

    function validateStep(i) {
        const panel = $('[data-step="' + i + '"]');
        if (i === 4) {
            const pw = $('#a-pass'), pw2 = $('#a-pass2');
            pw.setCustomValidity(pw.value && !(/[A-Za-z]/.test(pw.value) && /\d/.test(pw.value)) ? 'Das Passwort braucht Buchstaben und Zahlen.' : '');
            pw2.setCustomValidity(pw2.value !== pw.value ? 'Die Passwörter stimmen nicht überein.' : '');
        }
        const fields = $$('input, select', panel).filter(function (f) { return !f.disabled; });
        for (let n = 0; n < fields.length; n++) {
            if (!fields[n].checkValidity()) { fields[n].reportValidity(); return false; }
        }
        return true;
    }

    $$('[data-next]').forEach(function (b) {
        b.addEventListener('click', function () { if (validateStep(current)) show(current + 1); });
    });
    $$('[data-back]').forEach(function (b) {
        b.addEventListener('click', function () { show(current - 1); });
    });
    $$('[name="db.driver"], [name="mail.mode"]').forEach(function (r) {
        r.addEventListener('change', function () { toggleGroups(); if (r.name === 'db.driver') resetDb(); });
    });
    ['#a-pass', '#a-pass2'].forEach(function (s) {
        $(s).addEventListener('input', function () { $('#a-pass').setCustomValidity(''); $('#a-pass2').setCustomValidity(''); });
    });
    $('#m-sec').addEventListener('change', function (e) {
        const port = $('#m-port');
        if (port.value === '587' || port.value === '465') port.value = e.target.value === 'ssl' ? '465' : '587';
    });

    // ---------------------------------------------------------------- Schritt 0: Token

    const tokenForm = $('#token-form');
    if (tokenForm) {
        tokenForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const err = $('#token-error');
            const btn = $('button[type=submit]', tokenForm);
            token = $('#token').value.trim();
            err.hidden = true;
            if (!token) { err.textContent = 'Bitte gib das Token ein.'; err.hidden = false; return; }
            btn.disabled = true;
            const r = await api('/start');
            btn.disabled = false;
            if (r.status !== 200) { err.textContent = r.data.message || 'Das hat nicht geklappt.'; err.hidden = false; return; }
            fill(r.data.defaults);
            renderRequirements(r.data);
            show(1);
        });
    }

    // ---------------------------------------------------------------- Schritt 1: System

    function renderRequirements(data) {
        const list = $('#req-list');
        list.replaceChildren();
        const marks = { ok: '✓', warn: '!', fail: '✕' };
        const words = { ok: 'In Ordnung', warn: 'Hinweis', fail: 'Fehler' };
        data.requirements.forEach(function (r) {
            const li = el('li', r.state);
            const ic = el('span', 'ic', marks[r.state]);
            ic.setAttribute('aria-hidden', 'true');
            const tx = el('span', 'tx', r.label);
            tx.prepend(el('span', 'sr', words[r.state] + ': '));
            if (r.state !== 'ok' && r.hint) tx.append(el('small', '', r.hint));
            li.append(ic, tx);
            list.append(li);
        });
        blocking = data.blocking;
        const sum = $('#req-summary');
        sum.className = 'note ' + (blocking ? 'bad' : 'ok');
        sum.textContent = blocking
            ? blocking + ' Punkt(e) müssen zuerst behoben werden. Danach „Erneut prüfen“.'
            : 'Alles Nötige ist vorhanden.';
        $('#req-next').disabled = blocking > 0;
    }

    $('#req-recheck').addEventListener('click', async function () {
        const btn = this;
        btn.disabled = true;
        const r = await api('/start');
        btn.disabled = false;
        if (r.status === 200) renderRequirements(r.data);
    });

    // ---------------------------------------------------------------- Schritt 2: Datenbank

    function resetDb() {
        dbOk = false;
        $('#db-next').disabled = true;
        $('#db-result').hidden = true;
    }
    $('[data-step="2"]').addEventListener('input', resetDb);

    $('#db-test').addEventListener('click', async function () {
        const btn = this, out = $('#db-result');
        if (!validateStep(2)) return;
        btn.disabled = true;
        out.className = 'note';
        out.textContent = 'Teste die Verbindung …';
        out.hidden = false;
        const r = await api('/datenbank', { db: collect().db });
        btn.disabled = false;
        clearErrors();
        if (r.status === 422) { showErrors(r.data.errors || {}); out.hidden = true; return; }
        dbOk = r.status === 200 && r.data.ok === true;
        out.className = 'note ' + (dbOk ? 'ok' : 'bad');
        out.textContent = r.data.message || 'Das hat nicht geklappt.';
        $('#db-next').disabled = !dbOk;
    });

    // ---------------------------------------------------------------- Schritt 4: Passwort

    $('#pw-suggest').addEventListener('click', function () {
        const chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let pw;
        do {
            const bytes = new Uint32Array(16);
            crypto.getRandomValues(bytes);
            pw = Array.from(bytes, function (b) { return chars[b % chars.length]; }).join('');
        } while (!(/[a-z]/.test(pw) && /[A-Z]/.test(pw) && /\d/.test(pw)));
        $('#a-pass').value = pw;
        $('#a-pass2').value = pw;
        setPasswordVisible(true);
    });
    function setPasswordVisible(on) {
        ['#a-pass', '#a-pass2'].forEach(function (s) { $(s).type = on ? 'text' : 'password'; });
        const t = $('#pw-toggle');
        t.setAttribute('aria-pressed', String(on));
        t.textContent = on ? 'Verbergen' : 'Anzeigen';
    }
    $('#pw-toggle').addEventListener('click', function () { setPasswordVisible(this.getAttribute('aria-pressed') !== 'true'); });

    // ---------------------------------------------------------------- Schritt 5: E-Mail

    $('#mail-test').addEventListener('click', async function () {
        const btn = this, out = $('#mail-result');
        if (!validateStep(5)) return;
        const data = collect();
        btn.disabled = true;
        out.className = 'note';
        out.textContent = 'Sende Testnachricht …';
        out.hidden = false;
        const r = await api('/mail', { mail: data.mail, to: data.admin.email });
        btn.disabled = false;
        if (r.status === 422) { out.className = 'note bad'; out.textContent = Object.values(r.data.errors || {}).map(function (e) { return e[0]; }).join(' ') || r.data.message; return; }
        out.className = 'note ' + (r.data.ok ? 'ok' : 'bad');
        out.textContent = r.data.message || 'Das hat nicht geklappt.';
    });

    // ---------------------------------------------------------------- Schritt 6: Installieren

    function renderSummary() {
        const d = collect();
        const dl = $('#summary');
        dl.replaceChildren();
        const rows = [
            ['Name', d.instance.name],
            ['Adresse', d.instance.url],
            ['Betriebsart', d.instance.env === 'production' ? 'Produktion' : 'Entwicklung'],
            ['Registrierung', d.instance.registration ? 'Offen' : 'Geschlossen (nur Administrator)'],
            ['Seiten pro Konto', d.instance.max_pages],
            ['Update-Prüfung', d.instance.updates ? 'An' : 'Aus'],
            ['Datenbank', d.db.driver === 'sqlite' ? 'SQLite (database/database.sqlite)' : d.db.user + '@' + d.db.host + ':' + d.db.port + '/' + d.db.name],
            ['Administrator', d.admin.name + ' <' + d.admin.email + '>'],
            ['E-Mail', d.mail.mode === 'smtp' ? 'SMTP ' + d.mail.host + ':' + d.mail.port : 'Nur ins Log'],
        ];
        rows.forEach(function (r) { dl.append(el('dt', '', r[0]), el('dd', '', String(r[1]))); });
    }

    function renderSteps(list, steps) {
        list.replaceChildren();
        const marks = { ok: '✓', warn: '!', fail: '✕' };
        steps.forEach(function (s) {
            const li = el('li', s.state);
            const ic = el('span', 'ic', marks[s.state]);
            ic.setAttribute('aria-hidden', 'true');
            const tx = el('span', 'tx', s.label);
            if (s.detail) tx.append(el('small', '', s.detail));
            li.append(ic, tx);
            list.append(li);
        });
    }

    $('#install-run').addEventListener('click', async function () {
        const btn = this, err = $('#install-error'), log = $('#run-log'), back = $('#install-back');
        btn.disabled = true;
        back.disabled = true;
        err.hidden = true;
        clearErrors();
        log.replaceChildren(el('li', 'ok', 'Installation läuft … das kann einen Moment dauern.'));

        const r = await api('/installieren', collect());
        log.replaceChildren();

        if (r.status === 422 || r.status === 403 || r.status === 409 || r.status === 0 || !r.data.steps) {
            back.disabled = false;
            btn.disabled = false;
            const errors = r.data.errors || {};
            const step = showErrors(errors);
            err.textContent = Object.keys(errors).length ? 'Einige Angaben sind ungültig – bitte korrigieren.' : (r.data.message || 'Das hat nicht geklappt.');
            err.hidden = false;
            if (step !== null) show(step);
            return;
        }

        renderSteps(log, r.data.steps);
        if (r.data.ok) {
            renderSteps($('#done-log'), r.data.steps);
            $('#cron').textContent = r.data.cron;
            if (/^https?:\/\//.test(r.data.login)) $('#login-link').href = r.data.login;
            show(7);
            return;
        }
        back.disabled = false;
        btn.disabled = false;
        btn.textContent = 'Erneut versuchen';
        err.textContent = 'Ein Schritt ist fehlgeschlagen. Behebe die Ursache (Details oben) und versuche es erneut – bereits Erledigtes bleibt erhalten.';
        err.hidden = false;
    });

    toggleGroups();
    show(0);
})();
