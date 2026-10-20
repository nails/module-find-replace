import {paintBadge} from './Status.js';

const MESSAGES = {
    PENDING: 'Waiting for a worker to start this search.',
    RUNNING: 'Scanning tables.',
};

class Progress {
    constructor() {
        document.querySelectorAll('[data-find-replace-status]').forEach((node) => {
            this.watch(node);
        });
    }

    watch(node) {
        const url = node.getAttribute('data-url');

        if (!url) {
            return;
        }

        window.setTimeout(() => {
            fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.json())
                .then((data) => {
                    const payload = data && data.data && !Array.isArray(data.data) ? data.data : data;
                    this.paint(node, payload);

                    if (payload.status === 'COMPLETE' || payload.status === 'FAILED') {
                        window.location.reload();
                        return;
                    }

                    this.watch(node);
                })
                .catch(() => {
                    this.watch(node);
                });
        }, 2000);
    }

    paint(node, payload) {
        const status = payload.status || '';
        const badgeNode = node.querySelector('[data-find-replace-badge]');
        const message = node.querySelector('[data-find-replace-message]');
        const meter = node.querySelector('[data-find-replace-meter]');
        const bar = node.querySelector('[data-find-replace-bar]');
        const progress = node.querySelector('.find-replace-progress');
        const scanned = Number(payload.tables_scanned) || 0;
        const total = Number(payload.tables_total) || 0;
        const percent = total > 0 ? Math.min(100, Math.floor((scanned / total) * 100)) : 0;

        if (badgeNode) {
            paintBadge(badgeNode, status);
        }

        if (message && MESSAGES[status]) {
            message.textContent = MESSAGES[status];
        }

        if (meter) {
            meter.hidden = status !== 'RUNNING';
        }

        if (bar) {
            bar.style.width = percent + '%';
        }

        if (progress) {
            progress.textContent = scanned + ' / ' + total;
        }

        const track = node.querySelector('.find-replace-meter');

        if (track) {
            track.setAttribute('aria-valuemax', String(total));
            track.setAttribute('aria-valuenow', String(scanned));
        }
    }
}

export default Progress;
