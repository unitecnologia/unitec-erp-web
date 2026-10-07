@if ($this->nfseServicoPrestadoIndex !== null && isset($this->nfseServicos[$this->nfseServicoPrestadoIndex]))
    @php
        $prestadoIndex = $this->nfseServicoPrestadoIndex;
        $prestadoLinha = $this->nfseServicos[$prestadoIndex];
        $prestadoSomenteLeitura = $this->nfseServicoPrestadoSomenteLeitura($prestadoIndex);
        $prestadoDaOs = (int) ($prestadoLinha['os_id'] ?? 0) > 0 || str_starts_with((string) ($prestadoLinha['key'] ?? ''), 'nfse-os-');
    @endphp
    <div
        class="erp-lookup-modal erp-orc-equip-modal erp-nfse-servico-prestado-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-nfse-servico-prestado-title"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="fecharNfseServicoPrestado"></div>
        <div class="erp-lookup-modal__window erp-orc-equip-modal__window erp-nfse-servico-prestado-modal__window" role="document">
            <header class="erp-lookup-modal__titlebar">
                <span id="erp-nfse-servico-prestado-title">Serviço prestado</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="fecharNfseServicoPrestado" aria-label="Fechar">&times;</button>
            </header>
            <div class="erp-orc-equip-modal__body">
                <p class="erp-nfse-servico-prestado-modal__servico">{{ $prestadoLinha['descricao'] ?? '' }}</p>
                <p class="erp-nfse-servico-prestado-modal__hint">
                    @if ($prestadoDaOs && ! $this->nfseSomenteLeitura())
                        Veio da OS — para alterar, edite o serviço na OS e importe novamente.
                    @elseif ($prestadoSomenteLeitura)
                        Nota emitida — somente visualização.
                    @else
                        Vai na descrição do serviço da NFS-e (XML e impressão).
                    @endif
                </p>
                <textarea
                    id="erp-nfse-servico-prestado-texto"
                    wire:model="nfseServicoPrestadoTexto"
                    @if ($prestadoSomenteLeitura)
                        readonly
                        data-erp-preserve-case
                    @else
                        data-erp-uppercase
                        x-init="window.ErpUppercase?.bindInput($el); $nextTick(() => { $el.removeAttribute('readonly'); $el.focus(); })"
                    @endif
                    @class([
                        'erp-nfse-servico-prestado-modal__textarea',
                        'erp-nfse-servico-prestado-modal__textarea--readonly' => $prestadoSomenteLeitura,
                    ])
                    rows="8"
                    maxlength="1000"
                    placeholder="{{ $prestadoSomenteLeitura ? 'Sem descrição.' : 'Descreva o serviço prestado...' }}"
                ></textarea>
            </div>
            <footer class="erp-orc-equip-modal__footer erp-nfse-servico-prestado-modal__footer">
                @if ($prestadoSomenteLeitura)
                    <button type="button" class="erp-orc-equip-modal__ok" wire:click="fecharNfseServicoPrestado">Fechar</button>
                @else
                    <button type="button" class="erp-nfse-servico-prestado-modal__cancelar" wire:click="fecharNfseServicoPrestado">Cancelar</button>
                    <button type="button" class="erp-orc-equip-modal__ok" wire:click="confirmarNfseServicoPrestado">OK</button>
                @endif
            </footer>
        </div>
    </div>
@endif
