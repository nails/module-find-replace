class Confirm {
    constructor(adminController) {
        this.pendingScope = 'selected';
        this.dialog = this.createDialog();

        adminController.onRefreshUi(() => {
            this.bind();
        });

        this.bind();
    }

    createDialog() {
        const dialog = document.createElement('div');
        dialog.className = 'find-replace-confirm';
        dialog.hidden = true;

        const panel = document.createElement('div');
        panel.className = 'find-replace-confirm__panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'find-replace-confirm-title');

        const title = document.createElement('h2');
        title.id = 'find-replace-confirm-title';
        title.className = 'find-replace-confirm__title';
        title.textContent = 'Replace text?';

        this.body = document.createElement('p');
        this.body.className = 'find-replace-confirm__body';

        const actions = document.createElement('div');
        actions.className = 'find-replace-confirm__actions';

        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'btn btn-default';
        cancel.textContent = 'Cancel';
        cancel.addEventListener('click', () => {
            this.hide();
        });

        const replace = document.createElement('button');
        replace.type = 'button';
        replace.className = 'btn btn-danger';
        replace.textContent = 'Replace';
        replace.addEventListener('click', () => {
            this.apply();
        });

        actions.append(cancel, replace);
        panel.append(title, this.body, actions);
        dialog.append(panel);
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                this.hide();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !dialog.hidden) {
                this.hide();
            }
        });
        document.body.append(dialog);

        return dialog;
    }

    show(message) {
        this.body.textContent = message;
        this.dialog.hidden = false;
    }

    hide() {
        this.dialog.hidden = true;
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
