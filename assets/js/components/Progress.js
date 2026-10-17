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
                    const scanned = Number(data.tables_scanned) || 0;
                    const total = Number(data.tables_total) || 0;
                    const progress = node.querySelector('.find-replace-progress');
                    const label = scanned + ' / ' + total;

                    if (progress) {
                        progress.textContent = label;
                    } else {
                        node.textContent = label;
                    }

                    if (data.status === 'COMPLETE' || data.status === 'FAILED') {
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
