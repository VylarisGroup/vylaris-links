// Fortschrittsanzeige für das Update im Admin-Bereich
export function updater() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    return {
        status: {},
        busy: false,
        error: '',
        timer: null,
        installUrl: '',
        statusUrl: '',

        init() {
            const d = this.$el.dataset;
            this.status = JSON.parse(d.status || '{}');
            this.installUrl = d.installUrl;
            this.statusUrl = d.statusUrl;
            if (this.status.state === 'running') {
                this.busy = true;
                this.poll();
            }
        },

        async install() {
            if (!confirm('Update jetzt installieren? Die Seite ist dabei kurz im Wartungsmodus. Eine Sicherung wird automatisch angelegt.')) return;
            this.busy = true;
            this.error = '';
            this.status = { state: 'running', message: 'Update wird gestartet …', percent: 1, log: [] };
            try {
                const res = await fetch(this.installUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || json.error) {
                    this.error = json.error || 'Das Update konnte nicht gestartet werden.';
                    if (res.status !== 409) this.busy = false;
                }
            } catch (e) {
                // Bei einem direkten Lauf kann die Verbindung während der Wartung abbrechen – der Status zeigt das Ergebnis.
            }
            this.poll();
        },

        poll() {
            clearInterval(this.timer);
            this.timer = setInterval(async () => {
                try {
                    const r = await fetch(this.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!r.ok) return;
                    this.status = await r.json();
                    if (['done', 'failed'].includes(this.status.state)) {
                        clearInterval(this.timer);
                        this.busy = false;
                        if (this.status.state === 'done') setTimeout(() => location.reload(), 2500);
                    }
                } catch (e) {}
            }, 1500);
        },
    };
}
