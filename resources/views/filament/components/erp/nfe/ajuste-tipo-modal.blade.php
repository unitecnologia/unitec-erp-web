@if ($this->nfeAjusteTipoOpen)
    <div
        class="erp-lookup-modal erp-nfe-import-menu-modal erp-nfe-ajuste-tipo-modal"
        wire:keydown.escape="closeNfeAjusteTipoModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNfeAjusteTipoModal"></div>

        <div class="erp-lookup-modal__window erp-nfe-import-menu-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfe-ajuste-tipo-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nfe-ajuste-tipo-title">Nota Fiscal — Ajuste/Complementar</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNfeAjusteTipoModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-nfe-import-menu-modal__body">
                <section class="erp-nfe-import-menu-modal__section">
                    <h3 class="erp-nfe-import-menu-modal__section-title">Tipo de operação</h3>
                    <ul class="erp-nfe-import-menu-modal__list">
                        <li>
                            <button
                                type="button"
                                wire:click="selecionarNfeAjusteTipoEstorno"
                                class="erp-nfe-import-menu-modal__item"
                            >
                                <span class="erp-nfe-import-menu-modal__icon" aria-hidden="true">↺</span>
                                <span class="erp-nfe-import-menu-modal__text">
                                    Estorno de NF-e não cancelada no prazo legal
                                </span>
                            </button>
                        </li>
                        <li>
                            <button type="button" disabled class="erp-nfe-import-menu-modal__item erp-nfe-import-menu-modal__item--pending">
                                <span class="erp-nfe-import-menu-modal__icon" aria-hidden="true">▦</span>
                                <span class="erp-nfe-import-menu-modal__text">NF-e Complementar</span>
                                <span class="erp-nfe-import-menu-modal__badge">Em implementação</span>
                            </button>
                        </li>
                        <li>
                            <button type="button" disabled class="erp-nfe-import-menu-modal__item erp-nfe-import-menu-modal__item--pending">
                                <span class="erp-nfe-import-menu-modal__icon" aria-hidden="true">▦</span>
                                <span class="erp-nfe-import-menu-modal__text">Ajuste Crédito ICMS</span>
                                <span class="erp-nfe-import-menu-modal__badge">Em implementação</span>
                            </button>
                        </li>
                        <li>
                            <button type="button" disabled class="erp-nfe-import-menu-modal__item erp-nfe-import-menu-modal__item--pending">
                                <span class="erp-nfe-import-menu-modal__icon" aria-hidden="true">▦</span>
                                <span class="erp-nfe-import-menu-modal__text">Ajuste Débito ICMS</span>
                                <span class="erp-nfe-import-menu-modal__badge">Em implementação</span>
                            </button>
                        </li>
                        <li>
                            <button type="button" disabled class="erp-nfe-import-menu-modal__item erp-nfe-import-menu-modal__item--pending">
                                <span class="erp-nfe-import-menu-modal__icon" aria-hidden="true">▦</span>
                                <span class="erp-nfe-import-menu-modal__text">Outros Ajustes</span>
                                <span class="erp-nfe-import-menu-modal__badge">Em implementação</span>
                            </button>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfe-import-menu-modal__actions">
                <button type="button" wire:click="closeNfeAjusteTipoModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
