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
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then((response) => response.json())
            .then((data) => {
                const payload = data && data.data && !Array.isArray(data.data) ? data.data : data;
                const scanned = Number(payload.tables_scanned) || 0;
                const total = Number(payload.tables_total) || 0;
                const progress = node.querySelector('.find-replace-progress');
                const label = scanned + ' / ' + total;

                if (progress) {
                    progress.textContent = label;
                } else {
                    node.textContent = label;
                }

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
}

export default Progress;
