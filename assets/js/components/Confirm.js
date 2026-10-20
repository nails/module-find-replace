class Confirm {
    constructor(adminController) {
        this.adminController = adminController;
        this.pendingScope = 'selected';
        this.modal = null;

        adminController.onRefreshUi(() => {
            this.bind();
        });

        this.bind();
    }

    dialog() {
        if (this.modal) {
            return this.modal;
        }

        this.modal = this.adminController.getInstance('Modal').create();
        this.modal
            .setTitle('Replace text?')
            .addAction('Replace', ['btn-danger'], () => {
                this.apply();
            })
            .addAction('Cancel', ['btn-default'], () => {
                this.modal.hide();
            });

        return this.modal;
    }

    show(message) {
        const body = document.createElement('p');
        body.textContent = message;
        this.dialog().setBody(body).show();
    }

    apply() {
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
                this.show(this.message(form, this.pendingScope));
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
