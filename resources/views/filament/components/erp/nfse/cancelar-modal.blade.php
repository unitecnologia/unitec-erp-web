@if ($this->nfseCancelarModalOpen)
    @php
        $cancelarResumo = $this->nfseCancelarResumo();
        $mostrarJustificativa = $this->nfseCancelarProvedor === 'nacional' && $this->nfseCancelarMotivo === '9';
        $justificativaLen = mb_strlen(trim($this->nfseCancelarJustificativa), 'UTF-8');
        $justificativaMin = \App\Support\Erp\Nfse\NfseSefinEventoXmlGerador::XMOTIVO_MIN;
        $justificativaMax = \App\Support\Erp\Nfse\NfseSefinEventoXmlGerador::XMOTIVO_MAX;
    @endphp
    <div
        class="erp-lookup-modal erp-orc-equip-modal erp-nfse-cancelar-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-nfse-cancelar-title"
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
                    @foreach ($this->nfseCancelarMotivos() as $codigo => $rotulo)
                        <label @class([
                            'erp-nfse-cancelar-modal__motivo',
                            'is-selected' => $this->nfseCancelarMotivo === (string) $codigo,
                        ])>
                            <input type="radio" wire:model.live="nfseCancelarMotivo" value="{{ $codigo }}">
                            <span>{{ $rotulo }}</span>
                        </label>
                    @endforeach
                </div>

                @if ($mostrarJustificativa)
                    {{-- Estilos inline: public/css não entra em hotfix. --}}
                    <style>
                        .erp-nfse-cancelar-modal__label--justificativa{margin-top:.65rem}
                        .erp-nfse-cancelar-modal__justificativa{display:block;width:100%;min-height:4.2rem;margin:0;padding:.45rem .55rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;font-size:.82rem;line-height:1.35;resize:vertical}
                        .erp-nfse-cancelar-modal__justificativa:focus{outline:2px solid #fecaca;border-color:#dc2626}
                        .erp-nfse-cancelar-modal__contador{margin:.3rem 0 0;font-size:.72rem;color:#64748b}
                        .erp-nfse-cancelar-modal__contador.is-warn{color:#b45309}
                        .erp-nfse-cancelar-modal__contador.is-ok{color:#047857}
                    </style>
                    <label class="erp-nfse-cancelar-modal__label erp-nfse-cancelar-modal__label--justificativa" for="erp-nfse-cancelar-justificativa">
                        Justificativa (obrigatória)
                    </label>
                    <textarea
                        id="erp-nfse-cancelar-justificativa"
                        class="erp-nfse-cancelar-modal__justificativa"
                        wire:model.live.debounce.150ms="nfseCancelarJustificativa"
                        rows="3"
                        maxlength="{{ $justificativaMax }}"
                        placeholder="Descreva o motivo do cancelamento ({{ $justificativaMin }} a {{ $justificativaMax }} caracteres)"
                        autocomplete="off"
                    ></textarea>
                    <p @class([
                        'erp-nfse-cancelar-modal__contador',
                        'is-ok' => $justificativaLen >= $justificativaMin,
                        'is-warn' => $justificativaLen > 0 && $justificativaLen < $justificativaMin,
                    ])>
                        {{ $justificativaLen }}/{{ $justificativaMax }}
                        @if ($justificativaLen < $justificativaMin)
                            — faltam {{ $justificativaMin - $justificativaLen }} caracteres
                        @endif
                    </p>
                @endif

                <p class="erp-nfse-cancelar-modal__aviso">
                    @if ($this->nfseCancelarProvedor === 'nacional')
                        O pedido vai para a SEFIN Nacional (evento e101101). Depois de cancelada, a NFS-e não pode ser reativada e a OS fica liberada para uma nova nota.
                    @else
                        O pedido vai para a prefeitura. Depois de cancelada, a NFS-e não pode ser reativada e a OS fica liberada para uma nova nota.
                    @endif
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
                    <span wire:loading wire:target="confirmarNfseCancelar">
                        {{ $this->nfseCancelarProvedor === 'nacional' ? 'Enviando à SEFIN…' : 'Enviando à prefeitura…' }}
                    </span>
                </button>
            </footer>
        </div>
    </div>
@endif
