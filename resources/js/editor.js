// Editor-Logik für Vylaris Links (Alpine.js-Komponente)
export function editor() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    return {
        tab: 'inhalt',
        page: {},
        items: [],
        socials: [],
        openId: null,
        saving: false,
        saved: false,
        message: '',
        errors: {},
        newSocial: { platform: 'instagram', url: '' },
        newSlug: '',
        passwords: {},
        previewTimer: null,
        saveTimers: {},
        copied: false,

        init() {
            const s = JSON.parse(document.getElementById('editor-state').textContent);
            this.page = s.page;
            this.items = s.items.map((i) => this.prepare(i));
            this.socials = s.socials;
            this.newSlug = s.page.slug;
        },

        base() {
            return `/editor/${this.page.id}`;
        },

        prepare(item) {
            item.schedule = Object.assign({ enabled: false, days: [], from: '', to: '' }, item.schedule || {});
            item.data = item.data || {};
            item._images = (item.data.images || []).join('\n');
            item._open = false;
            return item;
        },

        async api(method, url, body, isForm = false) {
            this.saving = true;
            this.message = '';
            try {
                const res = await fetch(url, {
                    method,
                    headers: Object.assign(
                        { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        isForm || body === undefined ? {} : { 'Content-Type': 'application/json' }
                    ),
                    body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
                    credentials: 'same-origin',
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const first = json.errors ? Object.values(json.errors)[0][0] : json.message || 'Das hat nicht geklappt.';
                    const err = new Error(first);
                    err.errors = json.errors || {};
                    throw err;
                }
                this.saved = true;
                setTimeout(() => (this.saved = false), 1500);
                return json;
            } catch (e) {
                this.message = e.message;
                throw e;
            } finally {
                this.saving = false;
            }
        },

        reloadPreview() {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => {
                const f = this.$refs.preview;
                if (f && f.contentWindow) f.contentWindow.location.reload();
            }, 250);
        },

        // ---------- Seite ----------
        async savePage(fields) {
            try {
                const json = await this.api('PATCH', this.base(), fields);
                this.page = json.page;
                this.reloadPreview();
            } catch (e) {}
        },

        field(name) {
            return this.savePage({ [name]: this.page[name] });
        },

        async setColor(key, value) {
            const colors = Object.assign({}, this.page.colors, { [key]: value || null });
            Object.keys(colors).forEach((k) => (colors[k] === null || colors[k] === '') && delete colors[k]);
            this.page.colors = colors;
            await this.savePage({ colors: Object.keys(colors).length ? colors : [] });
        },

        async saveSlug() {
            try {
                const json = await this.api('PATCH', this.base() + '/adresse', { slug: this.newSlug });
                this.page = json.page;
                this.reloadPreview();
            } catch (e) {}
        },

        async upload(kind, event) {
            const file = event.target.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('file', file);
            try {
                const json = await this.api('POST', `${this.base()}/upload/${kind}`, fd, true);
                this.page = json.page;
                this.reloadPreview();
            } catch (e) {}
            event.target.value = '';
        },

        async removeUpload(kind) {
            try {
                const json = await this.api('DELETE', `${this.base()}/upload/${kind}`);
                this.page = json.page;
                this.reloadPreview();
            } catch (e) {}
        },

        async copyUrl() {
            try {
                await navigator.clipboard.writeText(this.page.public_url);
                this.copied = true;
                setTimeout(() => (this.copied = false), 1500);
            } catch (e) {}
        },

        // ---------- Einträge ----------
        async addItem(type) {
            try {
                const json = await this.api('POST', this.base() + '/eintraege', { type });
                const item = this.prepare(json.item);
                item._open = true;
                this.items.push(item);
                this.reloadPreview();
            } catch (e) {}
        },

        payload(item) {
            const sch = item.schedule;
            const data = Object.assign({}, item.data);
            if (item.type === 'gallery') data.images = item._images.split('\n').map((s) => s.trim()).filter(Boolean);
            const p = {
                title: item.title,
                url: item.url,
                icon: item.icon || null,
                body: item.body,
                effect: item.effect,
                is_visible: item.is_visible,
                age_gate: !!item.age_gate,
                data,
                schedule: {
                    enabled: !!sch.enabled,
                    days: sch.days || [],
                    from: sch.from || null,
                    to: sch.to || null,
                },
            };
            if (!['countdown', 'gallery'].includes(item.type)) delete p.data;
            return p;
        },

        queueSave(item, extra = {}) {
            clearTimeout(this.saveTimers[item.id]);
            this.saveTimers[item.id] = setTimeout(() => this.saveItem(item, extra), 450);
        },

        async saveItem(item, extra = {}) {
            this.errors[item.id] = '';
            try {
                const json = await this.api('PATCH', `${this.base()}/eintraege/${item.id}`, Object.assign(this.payload(item), extra));
                const open = item._open;
                Object.assign(item, this.prepare(json.item));
                item._open = open;
                this.reloadPreview();
            } catch (e) {
                this.errors[item.id] = e.message;
            }
        },

        async setPassword(item) {
            const pw = (this.passwords[item.id] || '').trim();
            if (pw.length < 4) {
                this.errors[item.id] = 'Das Passwort braucht mindestens 4 Zeichen.';
                return;
            }
            await this.saveItem(item, { password: pw });
            this.passwords[item.id] = '';
        },

        removePassword(item) {
            return this.saveItem(item, { remove_password: true });
        },

        toggleDay(item, d) {
            const i = item.schedule.days.indexOf(d);
            i === -1 ? item.schedule.days.push(d) : item.schedule.days.splice(i, 1);
            this.queueSave(item);
        },

        async removeItem(item) {
            if (!confirm('Diesen Eintrag löschen?')) return;
            try {
                await this.api('DELETE', `${this.base()}/eintraege/${item.id}`);
                this.items = this.items.filter((i) => i.id !== item.id);
                this.reloadPreview();
            } catch (e) {}
        },

        async move(item, dir) {
            const i = this.items.indexOf(item);
            const j = i + dir;
            if (j < 0 || j >= this.items.length) return;
            this.items.splice(i, 1);
            this.items.splice(j, 0, item);
            try {
                await this.api('POST', this.base() + '/eintraege/reihenfolge', { ids: this.items.map((x) => x.id) });
                this.reloadPreview();
            } catch (e) {}
        },

        // ---------- Social-Icons ----------
        async addSocial() {
            if (!this.newSocial.url.trim()) return;
            try {
                const json = await this.api('POST', this.base() + '/socials', this.newSocial);
                this.socials.push(json.social);
                this.newSocial.url = '';
                this.reloadPreview();
            } catch (e) {}
        },

        async removeSocial(s) {
            try {
                await this.api('DELETE', `${this.base()}/socials/${s.id}`);
                this.socials = this.socials.filter((x) => x.id !== s.id);
                this.reloadPreview();
            } catch (e) {}
        },

        typeLabel(t) {
            return { link: 'Link', heading: 'Überschrift', text: 'Text', image: 'Bild', youtube: 'YouTube', twitch: 'Twitch', spotify: 'Spotify', countdown: 'Countdown', gallery: 'Galerie' }[t] || t;
        },
    };
}
