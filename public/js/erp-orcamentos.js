(function () {
    function getOrcamentosListComponent() {
        const page = document.querySelector('.erp-orcamentos-page');

        if (! page) {
            return null;
        }

        const root = page.closest('[wire\\:id]');

        if (! root || ! window.Livewire?.find) {
            return null;
        }

        return window.Livewire.find(root.getAttribute('wire:id'));
    }

    function hydrateOrcamentosPeriodFilters(de, ate) {
        const page = document.querySelector('.erp-orcamentos-page');

        if (! page || ! window.ErpDatepicker) {
            return;
        }

        const component = getOrcamentosListComponent();
        const periodoDe = String(de ?? component?.get?.('periodoDe') ?? '').trim();
        const periodoAte = String(ate ?? component?.get?.('periodoAte') ?? '').trim();
        const fields = {
            periodoDe: periodoDe,
            periodoAte: periodoAte,
        };

        Object.entries(fields).forEach(([field, isoValue]) => {
            if (! isoValue) {
                return;
            }

            const input = page.querySelector(`input[data-wire-field="${field}"]`);

            if (! input) {
                return;
            }

            input.dataset.erpDateInitial = isoValue;

            if (input.dataset.erpDateBound === '1' && input._flatpickr) {
                window.ErpDatepicker.normalizeDisplay?.(input, input._flatpickr, window.ErpDatepicker.getWireFormat(input));

                return;
            }

            if (window.ErpDatepicker.destroy) {
                window.ErpDatepicker.destroy(input);
            }
        });

        if (typeof initErpDatepickers === 'function') {
            if (window.__erpDatepickerRetryCounts) {
                delete window.__erpDatepickerRetryCounts.document;
                delete window.__erpDatepickerRetryCounts[page];
            }

            initErpDatepickers(page);
        }
    }

    function scheduleOrcamentosPeriodHydration(de, ate) {
        requestAnimationFrame(() => {
            hydrateOrcamentosPeriodFilters(de, ate);
            window.setTimeout(() => hydrateOrcamentosPeriodFilters(de, ate), 120);
        });
    }

    function bindOrcamentosPeriodHydration() {
        if (window.__erpOrcamentosPeriodHydrationBound) {
            return;
        }

        window.__erpOrcamentosPeriodHydrationBound = true;

        const registerLivewireHooks = () => {
            window.Livewire.on('erp-hydrate-orcamentos-dates', ({ de, ate }) => {
                scheduleOrcamentosPeriodHydration(de, ate);
            });
        };

        const bootOrcamentosPeriodFilters = () => {
            if (! window.ErpDatepicker) {
                window.setTimeout(bootOrcamentosPeriodFilters, 30);

                return;
            }

            scheduleOrcamentosPeriodHydration();
        };

        document.addEventListener('DOMContentLoaded', bootOrcamentosPeriodFilters);

        document.addEventListener('livewire:navigated', bootOrcamentosPeriodFilters);

        if (document.readyState !== 'loading') {
            bootOrcamentosPeriodFilters();
        }

        if (window.Livewire) {
            registerLivewireHooks();
        } else {
            document.addEventListener('livewire:init', registerLivewireHooks);
        }
    }

    function reflowOrcamentosListGrid() {
        const page = document.querySelector('.erp-orcamentos-page');

        if (! page) {
            return;
        }

        const tableHost = page.querySelector('.fi-ta-content-ctn');

        if (! tableHost) {
            return;
        }

        void tableHost.offsetHeight;
        window.dispatchEvent(new Event('resize'));
    }

    function bindOrcamentosListLayout() {
        if (window.__erpOrcamentosListLayoutBound) {
            return;
        }

        window.__erpOrcamentosListLayoutBound = true;

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(reflowOrcamentosListGrid);
        });

        if (window.Livewire) {
            window.Livewire.hook('morph.updated', ({ el }) => {
                if (el?.querySelector?.('.erp-orcamentos-page') || el?.closest?.('.erp-orcamentos-page')) {
                    requestAnimationFrame(reflowOrcamentosListGrid);
                }
            });
        }

        window.addEventListener('resize', reflowOrcamentosListGrid);
    }

    if (! window.__erpOrcamentosPreviewCloseBound) {
        window.__erpOrcamentosPreviewCloseBound = true;

        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'erp-orcamento-preview-close') {
                return;
            }

            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('close-orcamento-preview');

                return;
            }

            const overlay = document.querySelector('.erp-orc-preview-overlay');
            const componentId = overlay?.dataset.livewireId;

            if (componentId && window.Livewire?.find) {
                window.Livewire.find(componentId)?.call('closePreviewOverlay');
            }
        });
    }

    if (window.__erpOrcamentosEmailKeysBound) {
        bindOrcamentosListLayout();
        bindOrcamentosPeriodHydration();
        requestAnimationFrame(reflowOrcamentosListGrid);

        return;
    }

    window.__erpOrcamentosEmailKeysBound = true;

    document.addEventListener('keydown', (event) => {
        const whatsAppModal = document.querySelector('.erp-orc-whatsapp-modal');

        if (whatsAppModal) {
            if (event.key === 'F5') {
                event.preventDefault();
                event.stopImmediatePropagation();

                const page = whatsAppModal.closest('.erp-orcamentos-page');
                const componentEl = page?.closest('[wire\\:id]');
                const componentId = componentEl?.getAttribute('wire:id');

                if (componentId && window.Livewire?.find) {
                    const wire = window.Livewire.find(componentId);
                    const messageInput = document.getElementById('erp-orc-whatsapp-message');
                    const toInput = document.getElementById('erp-orc-whatsapp-to');

                    if (wire && messageInput) {
                        wire.set('whatsAppMessage', messageInput.value);
                    }

                    if (wire && toInput) {
                        wire.set('whatsAppTo', toInput.value);
                    }

                    wire?.call('sendOrcamentoWhatsApp');
                }
            }

            return;
        }

        const emailModal = document.querySelector('.erp-orc-email-modal');

        if (! emailModal) {
            return;
        }

        if (event.key === 'F5') {
            event.preventDefault();
            event.stopImmediatePropagation();

            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('send-orcamento-email');

                return;
            }

            const page = emailModal.closest('.erp-orcamentos-page');
            const componentEl = page?.closest('[wire\\:id]');
            const componentId = componentEl?.getAttribute('wire:id');

            if (componentId && window.Livewire?.find) {
                window.Livewire.find(componentId)?.call('sendOrcamentoEmail');
            }
        }
    }, true);

    bindOrcamentosListLayout();
    bindOrcamentosPeriodHydration();
    requestAnimationFrame(reflowOrcamentosListGrid);
})();
