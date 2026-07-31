@php
    $pageSizeOptions = [25, 50, 100];

    $filterFields = [
        'periodo_entrada' => 'Período (Data Entrada)',
        'data_emissao' => 'Data Emissão',
        'numero' => 'Número',
        'chave' => 'Chave',
        'cnpj' => 'CNPJ',
        'nome' => 'Fornecedor',
        'nsu' => 'NSU',
        'total' => 'Total',
    ];
@endphp

<div class="erp-nfe" wire:ignore.self>
    <div class="erp-nfe__filters">
        <div class="erp-nfe__filters-row">
            @include('filament.components.erp.empresa-badge', [
                'nome' => $this->empresaNome,
                'prefix' => 'erp-nfe',
            ])

            @include('filament.components.erp.notas-fornecedores.toolbar-filters', [
                'filterFields' => $filterFields,
            ])

            <div class="erp-nfe__page-size-group">
                <label class="erp-nfe__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="erp-nfe__select erp-nfe__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    @include('filament.components.erp.notas-fornecedores.tabs')

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')

    <script>
        (function () {
            function getNfFornListComponent() {
                const page = document.querySelector('.erp-notas-fornecedores-page');

                if (! page) {
                    return null;
                }

                const root = page.closest('[wire\\:id]');

                if (! root || ! window.Livewire?.find) {
                    return null;
                }

                return window.Livewire.find(root.getAttribute('wire:id'));
            }

            function hydrateNfFornPeriodFilters(payload = {}) {
                const page = document.querySelector('.erp-notas-fornecedores-page');

                if (! page || ! window.ErpDatepicker) {
                    return;
                }

                const component = getNfFornListComponent();
                const fields = {
                    periodoDe: String(payload.de ?? component?.get?.('periodoDe') ?? '').trim(),
                    periodoAte: String(payload.ate ?? component?.get?.('periodoAte') ?? '').trim(),
                    localSearchDe: String(payload.deEmissao ?? component?.get?.('localSearchDe') ?? '').trim(),
                    localSearchAte: String(payload.ateEmissao ?? component?.get?.('localSearchAte') ?? '').trim(),
                };

                Object.entries(fields).forEach(([field, isoValue]) => {
                    const input = page.querySelector(`input[data-wire-field="${field}"]`);

                    if (! input) {
                        return;
                    }

                    if (isoValue) {
                        input.dataset.erpDateInitial = isoValue;
                    } else {
                        delete input.dataset.erpDateInitial;
                    }

                    if (input.dataset.erpDateBound === '1' && input._flatpickr) {
                        if (isoValue && window.ErpDatepicker.isIsoDateString(isoValue)) {
                            const parsed = window.ErpDatepicker.parseValue(isoValue, 'iso');

                            if (parsed) {
                                window.ErpDatepicker.applyLocalDate(input, input._flatpickr, parsed);
                                input.dataset.erpDateSynced = isoValue;
                            }
                        } else if (! isoValue) {
                            window.ErpDatepicker.clearLocal?.(input, input._flatpickr);
                        }

                        window.ErpDatepicker.normalizeDisplay?.(
                            input,
                            input._flatpickr,
                            window.ErpDatepicker.getWireFormat(input),
                        );

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

            function scheduleNfFornPeriodHydration(payload) {
                requestAnimationFrame(() => {
                    hydrateNfFornPeriodFilters(payload);
                    window.setTimeout(() => hydrateNfFornPeriodFilters(payload), 120);
                });
            }

            if (! window.__erpNfFornPeriodHydrationBound) {
                window.__erpNfFornPeriodHydrationBound = true;

                const registerLivewireHooks = () => {
                    window.Livewire.on('erp-hydrate-nf-forn-dates', (payload) => {
                        scheduleNfFornPeriodHydration(payload ?? {});
                    });
                };

                const boot = () => {
                    if (! window.ErpDatepicker) {
                        window.setTimeout(boot, 30);

                        return;
                    }

                    scheduleNfFornPeriodHydration();
                };

                document.addEventListener('DOMContentLoaded', boot);
                document.addEventListener('livewire:navigated', boot);

                if (document.readyState !== 'loading') {
                    boot();
                }

                if (window.Livewire) {
                    registerLivewireHooks();
                } else {
                    document.addEventListener('livewire:init', registerLivewireHooks);
                }
            }
        })();
    </script>

    <script>
        window.ErpNfFornPrint = {
            openDanfe(url) {
                if (!url) {
                    return;
                }

                document.getElementById('erp-nf-forn-print-frame')?.remove();

                const iframe = document.createElement('iframe');
                iframe.id = 'erp-nf-forn-print-frame';
                iframe.src = url;
                iframe.title = 'Impressão DANFE';
                iframe.setAttribute('aria-hidden', 'true');
                iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;opacity:0;pointer-events:none;left:-9999px;top:-9999px;';

                let cleanedUp = false;
                const cleanup = () => {
                    if (cleanedUp) {
                        return;
                    }
                    cleanedUp = true;
                    iframe.remove();
                    window.removeEventListener('message', onMessage);
                };

                const onMessage = (event) => {
                    if (event.source !== iframe.contentWindow) {
                        return;
                    }
                    if (event.data?.type === 'erp-nf-forn-danfe-print-done') {
                        cleanup();
                    }
                };

                window.addEventListener('message', onMessage);
                iframe.addEventListener('load', () => {
                    window.setTimeout(cleanup, 120000);
                });
                document.body.appendChild(iframe);
            },
        };

        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'erp-nf-forn-overlay-close') {
                return;
            }

            const root = document.querySelector('.erp-notas-fornecedores-page');
            const componentEl = root?.closest('[wire\\:id]');
            const component = componentEl && window.Livewire
                ? window.Livewire.find(componentEl.getAttribute('wire:id'))
                : null;

            if (! component) {
                return;
            }

            const produtoId = Number.parseInt(String(event.data.produtoId ?? ''), 10);
            const itemIndex = Number.parseInt(String(event.data.itemIndex ?? ''), 10);

            if (! Number.isNaN(produtoId) && produtoId > 0 && ! Number.isNaN(itemIndex) && itemIndex >= 0) {
                component.call(
                    'applyOverlayProdutoXmlSaved',
                    itemIndex,
                    produtoId,
                    String(event.data.produtoCodigo ?? ''),
                    String(event.data.produtoDescricao ?? ''),
                    String(event.data.produtoGrupo ?? ''),
                    String(event.data.produtoPrecoVenda ?? ''),
                );

                return;
            }

            component.call('closeProductOverlay');
        });
    </script>
</div>
