@if ($this->detalheModalOpen && is_array($this->detalheRegistro))
    @php($d = $this->detalheRegistro)
    <div
        class="erp-lookup-modal erp-operacao-logs-detail-modal"
        wire:keydown.escape.window="fecharDetalhe"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="fecharDetalhe"></div>

        <div
            class="erp-lookup-modal__window erp-operacao-logs-detail-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-operacao-log-detail-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-operacao-log-detail-title">Detalhes da operação</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="fecharDetalhe"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-operacao-logs-detail-modal__body">
                <dl class="erp-operacao-logs-detail-modal__grid">
                    <div>
                        <dt>Data/Hora</dt>
                        <dd>{{ $d['ocorrido_em'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Usuário</dt>
                        <dd>{{ $d['usuario'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Operação</dt>
                        <dd>{{ $d['operacao'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Código</dt>
                        <dd class="erp-operacao-logs-detail-modal__mono">{{ $d['operacao_codigo'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Documento</dt>
                        <dd>{{ $d['documento'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Resultado</dt>
                        <dd>{{ $d['resultado'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Origem</dt>
                        <dd class="erp-operacao-logs-detail-modal__mono">{{ $d['origem'] ?? '—' }}</dd>
                    </div>
                    <div class="erp-operacao-logs-detail-modal__full">
                        <dt>Resumo</dt>
                        <dd>{{ $d['resumo'] ?? '—' }}</dd>
                    </div>
                </dl>

                @if (! empty($d['detalhes_json']))
                    <div class="erp-operacao-logs-detail-modal__json-wrap">
                        <div class="erp-operacao-logs-detail-modal__json-label">Detalhes técnicos</div>
                        <pre class="erp-operacao-logs-detail-modal__json">{{ $d['detalhes_json'] }}</pre>
                    </div>
                @else
                    <p class="erp-operacao-logs-detail-modal__empty">Sem detalhes técnicos adicionais.</p>
                @endif
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button type="button" wire:click="fecharDetalhe" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label">Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
