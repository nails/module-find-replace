class Searches {
    constructor() {
        this.root = document.getElementById('find-replace');
        this.form = document.getElementById('find-replace-form');
        this.rows = document.getElementById('find-replace-searches');
        this.alert = document.getElementById('find-replace-alert');
        this.items = [];
        this.timer = null;

        if (!this.root || !this.form || !this.rows) {
            return;
        }

        this.api = this.root.getAttribute('data-api');
        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.create();
        });
        this.load();
    }

    create() {
        const formData = new FormData(this.form);
        const button = this.form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = true;
        }

        this.request(this.api, 'POST', {
            term: formData.get('term') || '',
            replacement: formData.get('replacement') || '',
            case_sensitive: formData.getAll('case_sensitive'),
            include_tables: formData.get('include_tables') || '',
            exclude_tables: formData.get('exclude_tables') || '',
        })
            .then(() => {
                this.message('success', 'Search queued.');
                this.form.reset();
                this.restoreCaseSensitive();
                return this.load();
            })
            .catch((error) => {
                this.message('danger', error.message);
            })
            .finally(() => {
                if (button) {
                    button.disabled = false;
                }
            });
    }

    restoreCaseSensitive() {
        const box = this.form.querySelector('input[type="checkbox"][name="case_sensitive"]');

        if (box) {
            box.checked = true;
        }
    }

    load() {
        window.clearTimeout(this.timer);

        return this.request(this.api, 'GET')
            .then((payload) => {
                const searches = Array.isArray(payload.data) ? payload.data : [];
                this.render(searches);
                const open = searches.some((item) => item.status === 'PENDING' || item.status === 'RUNNING');
                this.timer = window.setTimeout(() => this.load(), open ? 2500 : 60000);
            })
            .catch((error) => {
                this.message('danger', error.message);
                this.timer = window.setTimeout(() => this.load(), 60000);
            });
    }

    render(searches) {
        const seen = searches.map((item) => item.id);

        searches.forEach((item) => {
            const existing = this.items.find((row) => row.id === item.id);

            if (existing) {
                this.update(existing.node, item);
                return;
            }

            this.items.push({
                id: item.id,
                node: this.add(item),
            });
        });

        for (let index = this.items.length - 1; index >= 0; index--) {
            if (seen.includes(this.items[index].id)) {
                continue;
            }

            this.items[index].node.remove();
            this.items.splice(index, 1);
        }

        if (this.items.length === 0) {
            this.rows.textContent = '';
            this.rows.append(this.empty('No searches yet'));
        }
    }

    add(item) {
        const placeholder = this.rows.querySelector('.no-data');

        if (placeholder) {
            placeholder.closest('tr').remove();
        }

        const row = document.createElement('tr');
        row.append(
            this.cell('term', this.link(item)),
            this.cell('status'),
            this.cell('progress'),
            this.cell('results'),
            this.cell('last-run'),
            this.actions(item),
        );
        this.update(row, item);
        this.rows.append(row);

        return row;
    }

    update(row, item) {
        const term = row.querySelector('.find-replace-term');
        const link = term.querySelector('a');

        if (link) {
            link.href = item.results_url;
            link.textContent = item.term;
        }

        const status = row.querySelector('.find-replace-status');
        status.textContent = '';
        status.append(document.createTextNode(item.status));

        if (item.error) {
            const error = document.createElement('div');
            error.className = 'find-replace-error';
            error.textContent = item.error;
            status.append(error);
        }

        row.querySelector('.find-replace-progress').textContent = item.tables_scanned + ' / ' + item.tables_total;
        row.querySelector('.find-replace-results').textContent = String(item.result_count);
        row.querySelector('.find-replace-last-run').textContent = item.last_run || '';

        const keep = row.querySelector('[data-action="persistent"]');
        keep.textContent = item.persistent ? 'Stop keeping' : 'Keep';
        keep.dataset.persistent = item.persistent ? '0' : '1';
    }

    actions(item) {
        const cell = document.createElement('td');
        cell.className = 'actions';
        cell.append(
            this.button('Keep', 'persistent', () => this.persist(item.id, cell)),
            this.button('Run again', 'rerun', () => this.rerun(item.id)),
            this.button('Delete', 'delete', () => this.remove(item.id), 'btn-danger'),
        );

        return cell;
    }

    persist(id, cell) {
        const button = cell.querySelector('[data-action="persistent"]');
        this.request(this.api + '/persistent', 'POST', {
            id: id,
            persistent: button.dataset.persistent === '1',
        })
            .then(() => this.load())
            .catch((error) => this.message('danger', error.message));
    }

    rerun(id) {
        this.request(this.api + '/rerun', 'POST', {id: id})
            .then(() => this.load())
            .catch((error) => this.message('danger', error.message));
    }

    remove(id) {
        if (!window.confirm('Delete this search and its results?')) {
            return;
        }

        this.request(this.api + '/delete', 'POST', {id: id})
            .then(() => this.load())
            .catch((error) => this.message('danger', error.message));
    }

    button(label, action, onClick, style) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm ' + (style || 'btn-default');
        button.dataset.action = action;
        button.textContent = label;
        button.addEventListener('click', onClick);

        return button;
    }

    cell(name, child) {
        const cell = document.createElement('td');
        cell.className = 'find-replace-' + name;

        if (child) {
            cell.append(child);
        }

        return cell;
    }

    link(item) {
        const link = document.createElement('a');
        link.href = item.results_url;
        link.textContent = item.term;

        return link;
    }

    empty(text) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 6;
        cell.className = 'no-data';
        cell.textContent = text;
        row.append(cell);

        return row;
    }

    message(kind, text) {
        if (!this.alert) {
            return;
        }

        this.alert.hidden = false;
        this.alert.className = 'alert alert-' + kind;
        this.alert.textContent = text;
    }

    request(url, method, body) {
        const options = {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        return fetch(url, options)
            .then((response) => response.json().then((payload) => {
                if (!response.ok) {
                    throw new Error(payload.error || 'The request failed.');
                }

                return payload;
            }));
    }
}

export default Searches;
