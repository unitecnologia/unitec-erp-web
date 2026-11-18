(() => {
    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    function bindLookup(config) {
        const root = document.getElementById(config.rootId);
        const input = document.getElementById(config.inputId);
        if (!root || !input) return;

        const url = root.getAttribute('data-lookup-url') || '';
        let timer = null;
        let abort = null;
        let seq = 0;
        let selected = 0;
        let rows = [];
        let panelEl = null;

        const close = () => {
            if (panelEl) {
                panelEl.remove();
                panelEl = null;
            }
            rows = [];
            selected = 0;
            input.setAttribute('aria-expanded', 'false');
        };

        const placePanel = () => {
            if (!panelEl) return;
            const rect = input.getBoundingClientRect();
            const width = Math.max(rect.width, config.minWidth || 320);
            let left = rect.left;
            if (left + width > window.innerWidth - 8) {
                left = Math.max(8, window.innerWidth - width - 8);
            }
            panelEl.style.top = `${Math.round(rect.bottom + 2)}px`;
            panelEl.style.left = `${Math.round(left)}px`;
            panelEl.style.width = `${Math.round(width)}px`;
        };

        const highlight = (index) => {
            if (!panelEl || rows.length === 0) return;
            selected = Math.max(0, Math.min(rows.length - 1, index));
            panelEl.querySelectorAll('[data-rvg-lookup-index]').forEach((el) => {
                const on = Number(el.getAttribute('data-rvg-lookup-index')) === selected;
                el.classList.toggle('erp-cliente-filter-lookup__row--active', on);
            });
            panelEl.querySelector(`[data-rvg-lookup-index="${selected}"]`)
                ?.scrollIntoView({ block: 'nearest' });
        };

        const pick = (index) => {
            const row = rows[index];
            if (!row) return;
            const value = String(config.valueFromRow(row) || '').trim();
            input.value = value;
            input.setAttribute('data-committed', value);
            close();
            input.focus();
        };

        const render = (list) => {
            const next = Array.isArray(list) ? list : [];
            if (panelEl) {
                panelEl.remove();
                panelEl = null;
            }
            rows = next;
            selected = 0;
            if (rows.length === 0) {
                input.setAttribute('aria-expanded', 'false');
                return;
            }

            panelEl = document.createElement('div');
            panelEl.id = config.panelId;
            panelEl.className = 'erp-cliente-filter-lookup erp-rvg-cliente-lookup-panel';
            panelEl.setAttribute('role', 'listbox');
            panelEl.setAttribute('aria-label', config.ariaLabel);

            const body = rows.map((row, index) => (
                `<tr class="erp-cliente-filter-lookup__row${index === 0 ? ' erp-cliente-filter-lookup__row--active' : ''}"`
                + ` data-rvg-lookup-index="${index}" role="option" aria-selected="${index === 0 ? 'true' : 'false'}">`
                + config.rowHtml(row, escapeHtml)
                + `</tr>`
            )).join('');

            panelEl.innerHTML = (
                `<table class="erp-cliente-filter-lookup__table">`
                + `<thead><tr>${config.headerHtml}</tr></thead>`
                + `<tbody>${body}</tbody></table>`
            );

            document.body.appendChild(panelEl);
            placePanel();
            input.setAttribute('aria-expanded', 'true');

            panelEl.querySelectorAll('[data-rvg-lookup-index]').forEach((el) => {
                el.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    pick(Number(el.getAttribute('data-rvg-lookup-index')));
                });
                el.addEventListener('mouseenter', () => {
                    highlight(Number(el.getAttribute('data-rvg-lookup-index')));
                });
            });
        };

        const fetchRows = (term) => {
            if (!url) return;
            const mySeq = ++seq;
            if (abort) abort.abort();
            abort = new AbortController();

            const endpoint = new URL(url, window.location.origin);
            endpoint.searchParams.set('q', term);

            fetch(endpoint.toString(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: abort.signal,
            })
                .then((res) => (res.ok ? res.json() : Promise.reject(res)))
                .then((payload) => {
                    if (mySeq !== seq) return;
                    render(payload?.results || []);
                })
                .catch((err) => {
                    if (err?.name === 'AbortError') return;
                    if (mySeq === seq) close();
                });
        };

        const schedule = () => {
            const term = String(input.value || '').trim();
            const committed = String(input.getAttribute('data-committed') || '').trim();

            window.clearTimeout(timer);
            if (abort) {
                abort.abort();
                abort = null;
            }

            if (term === '' || term.toUpperCase() === committed.toUpperCase()) {
                close();
                return;
            }

            timer = window.setTimeout(() => fetchRows(term), 180);
        };

        input.addEventListener('input', () => {
            input.removeAttribute('data-committed');
            schedule();
        });

        input.addEventListener('focus', () => {
            const term = String(input.value || '').trim();
            const committed = String(input.getAttribute('data-committed') || '').trim();
            if (term !== '' && term.toUpperCase() !== committed.toUpperCase()) {
                schedule();
            }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
                return;
            }

            if (!panelEl || rows.length === 0) return;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(selected + 1);
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(selected - 1);
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                pick(selected);
            }
        });

        document.addEventListener('mousedown', (event) => {
            if (!panelEl) return;
            if (event.target === input || panelEl.contains(event.target)) return;
            close();
        });

        window.addEventListener('resize', placePanel);
        window.addEventListener('scroll', placePanel, true);

        const initial = String(input.value || '').trim();
        if (initial !== '') {
            input.setAttribute('data-committed', initial);
        }
    }

    bindLookup({
        rootId: 'erp-rvg-cliente-lookup',
        inputId: 'erp-rvg-cliente-q',
        panelId: 'erp-rvg-cliente-lookup-panel',
        ariaLabel: 'Clientes encontrados',
        minWidth: 420,
        headerHtml: '<th>Razão Social</th><th>Fantasia</th><th>CPF/CNPJ</th>',
        rowHtml: (row, esc) => (
            `<td>${esc(row.nome || '')}</td>`
            + `<td>${esc(row.fantasia || '—')}</td>`
            + `<td>${esc(row.cpf_cnpj || '—')}</td>`
        ),
        valueFromRow: (row) => row.nome || '',
    });

    bindLookup({
        rootId: 'erp-rvg-cidade-lookup',
        inputId: 'erp-rvg-cidade-q',
        panelId: 'erp-rvg-cidade-lookup-panel',
        ariaLabel: 'Cidades de clientes',
        minWidth: 280,
        headerHtml: '<th>Cidade</th><th>UF</th>',
        rowHtml: (row, esc) => (
            `<td>${esc(row.cidade || '')}</td>`
            + `<td>${esc(row.uf || '—')}</td>`
        ),
        valueFromRow: (row) => row.cidade || '',
    });
})();
