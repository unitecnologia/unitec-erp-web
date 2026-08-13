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
            <p class="erp-update-modal__lead" data-erp-update-lead>
                Baixe o pacote pelo ERP e depois execute a atualização manual.
                O Atualizador aplica os arquivos e encerra o sistema automaticamente.
            </p>
            <div class="erp-update-modal__package" data-erp-update-package-box>
                <p class="erp-update-modal__package-line">
                    <span>Versão instalada</span>
                    <strong data-erp-update-local-version>{{ \App\Support\Erp\ErpUpdateService::readInstalledVersion() }}</strong>
                </p>
                <p class="erp-update-modal__package-line">
                    <span>Pacote disponível</span>
                    <strong data-erp-update-remote-version>—</strong>
                </p>
                <p class="erp-update-modal__package-status" data-erp-update-package-status>
                    Verificando status do pacote...
                </p>
            </div>

            <div class="erp-update-modal__download-progress" data-erp-update-download-progress hidden>
                <p class="erp-update-modal__status" data-erp-update-status>Baixando pacote...</p>
                <div class="erp-update-modal__progress-track" aria-hidden="true">
                    <div class="erp-update-modal__progress-bar" data-erp-update-bar></div>
                </div>
                <p class="erp-update-modal__percent" data-erp-update-percent>0%</p>
            </div>

            <div class="erp-update-modal__success" data-erp-update-success hidden>
                <p class="erp-update-modal__success-title">Baixado com sucesso</p>
                <p class="erp-update-modal__success-body">
                    Clique em <strong>Executar atualização manual</strong>.
                    O Unitec Atualizador será aberto e o sistema será encerrado para aplicar o pacote.
                </p>
                <p class="erp-update-modal__success-path">
                    ZIP: <code>storage\app\private\updates\{{ config('unitec.update_zip_name', 'Unitec-ERP-Update.zip') }}</code>
                </p>
            </div>

            <ul class="erp-update-modal__list">
                <li>Download com verificação de tamanho e SHA256 (retomável se a rede cair).</li>
                <li>Instalação apenas pelo <code>bin\Unitec Atualizador.exe</code>.</li>
                <li>Banco, .env, storage e tools são preservados.</li>
            </ul>

            <div class="erp-update-modal__actions">
                <button type="button" class="erp-update-modal__btn erp-update-modal__btn--primary" data-erp-update-download>
                    Baixar atualização
                </button>
                <button type="button" class="erp-update-modal__btn erp-update-modal__btn--primary" data-erp-update-run-manual hidden>
                    Executar atualização manual
                </button>
                <button type="button" class="erp-update-modal__btn" data-erp-update-dismiss>
                    Fechar
                </button>
            </div>
            <p class="erp-update-modal__hint" data-erp-update-hint>
                Pode continuar usando o sistema durante o download.
            </p>
        </div>
    </div>
</div>
