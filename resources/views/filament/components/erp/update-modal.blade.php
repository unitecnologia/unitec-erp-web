<div
    id="erp-system-update-modal"
    class="erp-update-modal"
    hidden
    aria-hidden="true"
    data-zip-name="{{ config('unitec.update_zip_name', 'Unitec-ERP-Update.zip') }}"
>
    <div class="erp-update-modal__backdrop" data-erp-update-dismiss></div>

    <div class="erp-update-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-update-modal-title">
        <div class="erp-update-modal__titlebar">
            <span id="erp-update-modal-title">Atualizar Sistema</span>
            <button type="button" class="erp-update-modal__close" data-erp-update-dismiss aria-label="Fechar">✕</button>
        </div>

        <div class="erp-update-modal__panel" data-erp-update-panel="confirm">
            <div class="erp-update-modal__icon" aria-hidden="true">⬇</div>
            <p class="erp-update-modal__lead">
                O Unitec ERP será atualizado com o pacote
                <strong>{{ config('unitec.update_zip_name', 'Unitec-ERP-Update.zip') }}</strong>
                disponível na nuvem.
            </p>
            <ul class="erp-update-modal__list">
                <li>Seus dados (.env, storage e banco) serão preservados.</li>
                <li>O download e a instalação rodam em segundo plano.</li>
                <li>A página recarrega sozinha ao concluir.</li>
            </ul>
            <div class="erp-update-modal__actions">
                <button type="button" class="erp-update-modal__btn erp-update-modal__btn--primary" data-erp-update-start>
                    Atualizar agora
                </button>
                <button type="button" class="erp-update-modal__btn" data-erp-update-dismiss>
                    Cancelar
                </button>
            </div>
        </div>

        <div class="erp-update-modal__panel" data-erp-update-panel="progress" hidden>
            <p class="erp-update-modal__status" data-erp-update-status>
                Iniciando atualização...
            </p>
            <div class="erp-update-modal__progress-track" aria-hidden="true">
                <div class="erp-update-modal__progress-bar" data-erp-update-bar></div>
            </div>
            <p class="erp-update-modal__percent" data-erp-update-percent>0%</p>

            <ol class="erp-update-modal__steps" data-erp-update-steps>
                @foreach ([
                    'starting' => 'Preparar processo',
                    'downloading' => 'Baixar pacote',
                    'extracting' => 'Extrair ZIP',
                    'applying' => 'Aplicar arquivos',
                    'migrating' => 'Atualizar banco',
                    'finalizing' => 'Finalizar caches',
                    'completed' => 'Concluir',
                ] as $stepKey => $stepLabel)
                    <li data-step="{{ $stepKey }}">
                        <span class="erp-update-step__index">{{ $loop->iteration }}</span>
                        <div class="erp-update-step__body">
                            <div class="erp-update-step__header">
                                <span class="erp-update-step__label">{{ $stepLabel }}</span>
                                <span class="erp-update-step__pct" data-step-pct="{{ $stepKey }}">0%</span>
                            </div>
                            <div class="erp-update-step__track" aria-hidden="true">
                                <div class="erp-update-step__bar" data-step-bar="{{ $stepKey }}"></div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>

            <div class="erp-update-modal__info">
                <p class="erp-update-modal__detail" data-erp-update-detail hidden></p>
                <p class="erp-update-modal__command" data-erp-update-command hidden></p>
                <p class="erp-update-modal__elapsed" data-erp-update-elapsed hidden></p>
            </div>

            <p class="erp-update-modal__hint" data-erp-update-hint>
                Não feche o navegador até a atualização terminar.
            </p>
            <div class="erp-update-modal__actions erp-update-modal__actions--progress">
                <button type="button" class="erp-update-modal__btn" data-erp-update-reset hidden>
                    Limpar e tentar de novo
                </button>
                <button type="button" class="erp-update-modal__btn" data-erp-update-dismiss>
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>
