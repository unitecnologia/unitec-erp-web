(() => {
    const granted = new Set();
    let fullAccess = false;
    let locked = false;
    let booted = false;
    let pending = null;
    let directoryQuery = '';

    function normalize(payload) {
        const data = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});

        return {
            keys: Array.isArray(data.keys) ? data.keys : [],
            fullAccess: !!data.fullAccess,
            locked: !!data.locked,
        };
    }

    function collectKeys(group) {
        const keys = [];

        for (const item of group.items || []) {
            if (item.access) {
                keys.push(item.access);
            }

            for (const action of item.actions || []) {
                keys.push(action.key);
            }
        }

        for (const child of group.children || []) {
            keys.push(...collectKeys(child));
        }

        return keys;
    }

    function syncChecks(scope) {
        scope.querySelectorAll('[data-perm-key]').forEach((input) => {
            input.checked = fullAccess || granted.has(input.dataset.permKey);
            input.disabled = locked || fullAccess;
        });

        scope.querySelectorAll('[data-perm-grant]').forEach((button) => {
            button.disabled = locked || fullAccess;
        });
    }

    function checkboxLabel(key, text, row) {
        const label = document.createElement('label');
        label.className = row ? 'erp-permissoes__row-check' : '';
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.dataset.permKey = key;
        const span = document.createElement('span');
        span.textContent = text;
        label.append(input, span);

        return label;
    }

    function appendItem(body, item) {
        const mod = document.createElement('div');
        mod.className = 'erp-permissoes__tree-module';
        const head = document.createElement('div');

        if (item.access) {
            head.appendChild(checkboxLabel(item.access, item.label, true));
        } else {
            const title = document.createElement('span');
            title.className = 'erp-permissoes__tree-title';
            title.textContent = item.label;
            head.appendChild(title);
        }

        mod.appendChild(head);
        const actions = item.actions || [];

        if (actions.length) {
            const box = document.createElement('div');
            box.className = 'erp-permissoes__tree-actions';

            for (const action of actions) {
                box.appendChild(checkboxLabel(action.key, action.label, false));
            }

            mod.appendChild(box);
        }

        body.appendChild(mod);
    }

    function buildGroup(parent, group, nested) {
        const section = document.createElement('section');
        section.className = 'erp-permissoes__tree-group' + (nested ? ' is-nested' : '');
        section._permGroup = group;
        section._permKeys = collectKeys(group);

        const header = document.createElement('header');
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'erp-permissoes__tree-toggle';
        toggle.dataset.permToggle = '1';
        toggle.textContent = '› ' + group.label;

        const actions = document.createElement('span');
        const allow = document.createElement('button');
        allow.type = 'button';
        allow.dataset.permGrant = '1';
        allow.textContent = 'Liberar tudo';
        const block = document.createElement('button');
        block.type = 'button';
        block.dataset.permGrant = '0';
        block.textContent = 'Bloquear';
        actions.append(allow, block);
        header.append(toggle, actions);

        const body = document.createElement('div');
        body.className = 'erp-permissoes__tree-body';
        body.hidden = true;

        section.append(header, body);
        parent.appendChild(section);
    }

    function toggleSection(section) {
        const body = section.querySelector(':scope > .erp-permissoes__tree-body');
        const group = section._permGroup;
        const opening = body.hidden;

        if (opening && body.dataset.built !== '1') {
            for (const item of group.items || []) {
                appendItem(body, item);
            }

            for (const child of group.children || []) {
                buildGroup(body, child, true);
            }

            body.dataset.built = '1';
            syncChecks(body);
        }

        body.hidden = !opening;
        const toggle = section.querySelector(':scope > header > .erp-permissoes__tree-toggle');
        toggle.textContent = (body.hidden ? '› ' : '⌄ ') + group.label;
    }

    function setKeys(keys, on) {
        if (locked || fullAccess) {
            return;
        }

        for (const key of keys) {
            if (on) {
                granted.add(key);
            } else {
                granted.delete(key);
            }
        }

        const root = document.getElementById('erp-perm-root');

        if (root) {
            syncChecks(root);
        }
    }

    function apply(payload) {
        const data = normalize(payload);

        if (!booted) {
            pending = data;

            return;
        }

        granted.clear();
        fullAccess = data.fullAccess;
        locked = data.locked || data.fullAccess;

        if (!fullAccess) {
            for (const key of data.keys) {
                granted.add(key);
            }
        }

        const root = document.getElementById('erp-perm-root');

        if (root) {
            syncChecks(root);
        }
    }

    function filterDirectory() {
        const query = directoryQuery.trim().toLocaleLowerCase();

        document.querySelectorAll('[data-perm-row]').forEach((row) => {
            const hay = row.getAttribute('data-perm-row') || '';
            row.hidden = query !== '' && !hay.includes(query);
        });
    }

    function boot() {
        const root = document.getElementById('erp-perm-root');

        if (!root || root.dataset.booted === '1') {
            return;
        }

        const catalogNode = document.getElementById('erp-perm-catalog');
        const bootNode = document.getElementById('erp-perm-boot');
        const host = document.getElementById('erp-perm-groups');

        if (!catalogNode || !bootNode || !host) {
            return;
        }

        let catalog = [];

        try {
            catalog = JSON.parse(catalogNode.textContent || '[]');
        } catch (error) {
            catalog = [];
        }

        for (const group of catalog) {
            buildGroup(host, group, false);
        }

        root.dataset.booted = '1';
        booted = true;

        root.addEventListener('change', (event) => {
            const input = event.target;

            if (!(input instanceof HTMLInputElement) || !input.matches('[data-perm-key]')) {
                return;
            }

            if (locked || fullAccess) {
                input.checked = fullAccess;

                return;
            }

            if (input.checked) {
                granted.add(input.dataset.permKey);
            } else {
                granted.delete(input.dataset.permKey);
            }
        });

        root.addEventListener('click', (event) => {
            const grant = event.target.closest('[data-perm-grant]');

            if (grant) {
                const section = grant.closest('.erp-permissoes__tree-group');
                setKeys(section?._permKeys || [], grant.dataset.permGrant === '1');

                return;
            }

            const toggle = event.target.closest('[data-perm-toggle]');

            if (toggle) {
                toggleSection(toggle.closest('.erp-permissoes__tree-group'));
            }
        });

        let initial = { keys: [], fullAccess: false, locked: false };

        try {
            initial = normalize(JSON.parse(bootNode.textContent || '{}'));
        } catch (error) {
            initial = { keys: [], fullAccess: false, locked: false };
        }

        apply(pending || initial);
        pending = null;
        syncChecks(root);
    }

    document.addEventListener('input', (event) => {
        if (!event.target.matches('[data-perm-search]')) {
            return;
        }

        directoryQuery = event.target.value;
        filterDirectory();
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-perm-save]');

        if (!button || button.disabled || locked || fullAccess) {
            return;
        }

        const wire = button.closest('[wire\\:id]');
        const component = wire && window.Livewire ? window.Livewire.find(wire.getAttribute('wire:id')) : null;
        component?.savePermissionKeys([...granted]);
    });

    function bindLivewire() {
        if (!window.Livewire || bindLivewire.done) {
            return;
        }

        bindLivewire.done = true;

        window.Livewire.on('erp-permissoes-sync', (payload) => {
            apply(payload);
        });

        window.Livewire.hook('morph.updated', () => {
            const input = document.querySelector('[data-perm-search]');

            if (input && document.activeElement !== input) {
                input.value = directoryQuery;
            }

            filterDirectory();
        });
    }

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('livewire:navigated', boot);
    document.addEventListener('livewire:init', bindLivewire);
    bindLivewire();

    if (document.readyState !== 'loading') {
        boot();
    }
})();
