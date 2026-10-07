@if ($this->nfseCancelarModalOpen)
    @php
        $cancelarResumo = $this->nfseCancelarResumo();
    @endphp
    <div
        class="erp-lookup-modal erp-orc-equip-modal erp-nfse-cancelar-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-nfse-cancelar-title"
        wire:keydown.escape.window="fecharNfseCancelar"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="fecharNfseCancelar"></div>
        <div class="erp-lookup-modal__window erp-orc-equip-modal__window erp-nfse-cancelar-modal__window" role="document">
            <header class="erp-lookup-modal__titlebar">
                <span id="erp-nfse-cancelar-title">Cancelar NFS-e</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="fecharNfseCancelar" aria-label="Fechar">&times;</button>
            </header>
            <div class="erp-orc-equip-modal__body">
                @if ($cancelarResumo !== null)
                    <dl class="erp-nfse-cancelar-modal__resumo">
                        <div><dt>NFS-e nº</dt><dd>{{ $cancelarResumo['numero'] }}</dd></div>
                        <div><dt>Emissão</dt><dd>{{ $cancelarResumo['emissao'] }}</dd></div>
                        <div class="erp-nfse-cancelar-modal__resumo-tomador"><dt>Tomador</dt><dd>{{ $cancelarResumo['tomador'] }}</dd></div>
                        <div><dt>Total</dt><dd>R$ {{ $cancelarResumo['total'] }}</dd></div>
                    </dl>
                @endif

                <p class="erp-nfse-cancelar-modal__label">Motivo do cancelamento</p>
                <div class="erp-nfse-cancelar-modal__motivos" role="radiogroup">
                    @foreach (\App\Models\Nfse::motivosCancelamento() as $codigo => $rotulo)
                        <label @class([
                            'erp-nfse-cancelar-modal__motivo',
                            'is-selected' => $this->nfseCancelarMotivo === (string) $codigo,
                        ])>
                            <input type="radio" wire:model.live="nfseCancelarMotivo" value="{{ $codigo }}">
                            <span>{{ $rotulo }}</span>
                        </label>
                    @endforeach
                </div>

                <p class="erp-nfse-cancelar-modal__aviso">
                    O pedido vai para a prefeitura. Depois de cancelada, a NFS-e não pode ser reativada e a OS fica liberada para uma nova nota.
                </p>
            </div>
            <footer class="erp-orc-equip-modal__footer erp-nfse-cancelar-modal__footer">
                <button type="button" class="erp-nfse-servico-prestado-modal__cancelar" wire:click="fecharNfseCancelar">Voltar</button>
                <button
                    type="button"
                    class="erp-nfse-cancelar-modal__confirmar"
                    wire:click="confirmarNfseCancelar"
                    wire:loading.attr="disabled"
                    wire:target="confirmarNfseCancelar"
                >
                    <span wire:loading.remove wire:target="confirmarNfseCancelar">Cancelar NFS-e</span>
                    <span wire:loading wire:target="confirmarNfseCancelar">Enviando à prefeitura…</span>
                </button>
            </footer>
        </div>
    </div>
@endif
