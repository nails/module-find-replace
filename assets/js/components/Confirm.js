class Confirm {
    constructor(adminController) {
        this.modal = adminController.getInstance('Modal').create();
        this.pendingScope = 'selected';
        this.modal
            .setTitle('Replace text?')
            .addAction('Cancel', ['btn-default'], (event, modal) => {
                modal.hide();
            })
            .addAction('Replace', ['btn-danger'], () => {
                const form = document.getElementById('find-replace-results');

                if (!form) {
                    return;
                }

                let scope = form.querySelector('input[type="hidden"][name="scope"]');

                if (!scope) {
                    scope = document.createElement('input');
                    scope.type = 'hidden';
                    scope.name = 'scope';
                    form.appendChild(scope);
                }

                scope.value = this.pendingScope;
                form.submit();
            });

        adminController.onRefreshUi(() => {
            this.bind();
        });

        this.bind();
    }

    bind() {
        const form = document.getElementById('find-replace-results');

        if (form && !form.classList.contains('js-find-replace-bound')) {
            form.classList.add('js-find-replace-bound');
            form.addEventListener('change', (event) => {
                if (event.target.matches('.js-toggle-all')) {
                    form.querySelectorAll('input[name="result_ids[]"]').forEach((input) => {
                        input.checked = event.target.checked;
                    });
                }

                if (event.target.matches('.js-toggle-all, input[name="result_ids[]"]')) {
                    this.sync(form);
                }
            });
        }

        document.querySelectorAll('.js-find-replace-apply:not(.processed)').forEach((button) => {
            button.classList.add('processed');
            button.addEventListener('click', (event) => {
                event.preventDefault();
                this.pendingScope = button.getAttribute('data-scope') || 'selected';
                this.modal.setBody(this.message(form, this.pendingScope)).show();
            });
        });

        this.sync(form);
    }

    message(form, scope) {
        if (scope === 'all') {
            if (form && form.getAttribute('data-filtered') === '1') {
                return 'This updates every saved result that matches the current filters.';
            }

            return 'This updates every saved result for this search.';
        }

        const count = this.selectedCount(form);
        const records = count === 1 ? 'record' : 'records';

        return `This updates ${count} selected ${records}.`;
    }

    selectedCount(form) {
        if (!form) {
            return 0;
        }

        return form.querySelectorAll('input[name="result_ids[]"]:checked').length;
    }

    sync(form) {
        if (!form) {
            return;
        }

        const button = form.querySelector('.js-find-replace-apply[data-scope="selected"]');

        if (!button) {
            return;
        }

        button.disabled = this.selectedCount(form) === 0;
    }
}

export default Confirm;
