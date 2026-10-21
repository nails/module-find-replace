import {paintBadge} from './Status.js';

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
        const scanning = node.querySelector('[data-find-replace-scanning]');
        const meter = node.querySelector('.js-admin-progress');
        const scanned = Number(payload.tables_scanned) || 0;
        const total = Number(payload.tables_total) || 0;

        if (badgeNode) {
            paintBadge(badgeNode, status);
        }

        if (message) {
            message.hidden = status !== 'PENDING';
        }

        if (scanning) {
            scanning.hidden = status !== 'RUNNING';
        }

        if (meter) {
            meter.hidden = status !== 'RUNNING';
            meter.dataset.max = String(total);
            meter.dataset.value = String(scanned);
        }
    }
}

export default Progress;
