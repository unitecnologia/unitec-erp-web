@if ($this->conciliarModalOpen)
    @php
        $podeConfirmar = $this->podeConfirmarConciliacaoManual();
        $matchSel = null;
        foreach ($this->conciliarCandidatos as $cand) {
            if ((int) ($cand['id'] ?? 0) === (int) ($this->conciliarCandidatoId ?? 0)) {
                $matchSel = $cand['match'] ?? null;
                break;
            }
        }
    @endphp
    <div
        class="erp-lookup-modal erp-conciliacao-c3-modal"
        wire:keydown.escape.window="closeConciliarModal"
        @if ($podeConfirmar)
            wire:keydown.f5.window.prevent="confirmarConciliacaoManual"
        @endif
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeConciliarModal"></div>
        <div class="erp-lookup-modal__window erp-conciliacao-c3-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Conciliar Lançamento</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeConciliarModal">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-conciliacao-c3-modal__body">
                @php $r = $this->conciliarExtratoResumo ?? []; @endphp
                <div class="erp-conciliacao-c3-modal__resumo">
                    <div class="erp-conciliacao-c3-modal__meta">
                        <div><span>Data</span><strong>{{ $r['data'] ?? '—' }}</strong></div>
                        <div><span>Tipo</span><strong>{{ $r['tipo'] ?? '—' }}</strong></div>
                        <div><span>Valor</span><strong>R$ {{ $r['valor'] ?? '—' }}</strong></div>
                        <div><span>Situação</span><strong>{{ $r['situacao'] ?? '—' }}</strong></div>
                    </div>
                    <div class="erp-conciliacao-c3-modal__fields">
                        <div class="erp-conciliacao-c3-modal__field">
                            <span>Conta</span>
                            <strong>{{ $r['conta'] ?? '—' }}</strong>
                        </div>
                        <div class="erp-conciliacao-c3-modal__field">
                            <span>Histórico</span>
                            <strong>{{ $r['historico'] ?? '—' }}</strong>
                        </div>
                    </div>
                    <div class="erp-conciliacao-c3-modal__fitid">FITID {{ $r['fitid'] ?? '—' }}</div>
                </div>

                <section class="erp-conciliacao-c3-modal__matches" aria-label="Correspondências no Livro Caixa">
                    <h2 class="erp-conciliacao-c3-modal__section-title">Correspondências no Livro Caixa</h2>

                    @if (filled($this->conciliarAvisoPix))
                        <p class="erp-conciliacao-c3-modal__warn" role="status">{{ $this->conciliarAvisoPix }}</p>
                    @endif

                    @if ($this->conciliarCandidatosTotal === 0)
                        <p class="erp-conciliacao-c3-modal__empty" role="status">
                            Nenhum lançamento correspondente encontrado no Livro Caixa.
                        </p>
                    @else
                        <div class="erp-conciliacao-c3-modal__table-wrap">
                            <table class="erp-conciliacao-c3-modal__table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Emissão</th>
                                        <th>Cód.</th>
                                        <th>Doc.</th>
                                        <th>Sentido</th>
                                        <th>Valor</th>
                                        <th>Histórico</th>
                                        <th>ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->conciliarCandidatos as $c)
                                        <tr
                                            wire:key="c3-cand-{{ $c['id'] }}"
                                            class="{{ (int) $this->conciliarCandidatoId === (int) $c['id'] ? 'is-selected' : '' }}"
                                            wire:click="$set('conciliarCandidatoId', {{ (int) $c['id'] }})"
                                        >
                                            <td>
                                                <input
                                                    type="radio"
                                                    name="c3-candidato"
                                                    value="{{ (int) $c['id'] }}"
                                                    wire:model.live="conciliarCandidatoId"
                                                >
                                            </td>
                                            <td>{{ $c['emissao'] }}</td>
                                            <td>{{ $c['codigo'] ?: '—' }}</td>
                                            <td>{{ $c['documento'] }}</td>
                                            <td>{{ $c['sentido'] }}</td>
                                            <td class="erp-conciliacao-c3-modal__num">{{ $c['valor'] }}</td>
                                            <td class="erp-conciliacao-c3-modal__hist">{{ $c['historico'] }}</td>
                                            <td>
                                                <span class="erp-conciliacao-c3-modal__match erp-conciliacao-c3-modal__match--{{ $c['match'] ?? 'compativel' }}">{{ $c['match_label'] ?? '' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($this->conciliarCandidatosLastPage > 1)
                            <div class="erp-conciliacao-c3-modal__pager">
                                <button
                                    type="button"
                                    class="erp-rh-treinamentos__btn erp-rh-treinamentos__btn--secondary"
                                    wire:click="conciliarCandidatosIrPara({{ max(1, $this->conciliarCandidatosPage - 1) }})"
                                    @disabled($this->conciliarCandidatosPage <= 1)
                                >Anterior</button>
                                <span>{{ $this->conciliarCandidatosPage }} / {{ $this->conciliarCandidatosLastPage }}</span>
                                <button
                                    type="button"
                                    class="erp-rh-treinamentos__btn erp-rh-treinamentos__btn--secondary"
                                    wire:click="conciliarCandidatosIrPara({{ min($this->conciliarCandidatosLastPage, $this->conciliarCandidatosPage + 1) }})"
                                    @disabled($this->conciliarCandidatosPage >= $this->conciliarCandidatosLastPage)
                                >Próxima</button>
                            </div>
                        @endif
                    @endif
                </section>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-codigo-nome-modal__actions erp-conciliacao-c3-modal__actions">
                <button
                    type="button"
                    @if ($podeConfirmar)
                        wire:click="confirmarConciliacaoManual"
                    @endif
                    data-erp-key="F5"
                    class="erp-pcad-actions__btn"
                    @disabled(! $podeConfirmar)
                    title="{{ $podeConfirmar
                        ? ($matchSel === 'fraca' ? 'Confirmar exigirá aviso de identificação não comprovada' : 'Confirmar vínculo 1:1')
                        : 'Selecione um candidato correspondente para confirmar' }}"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Confirmar</span>
                </button>
                <button type="button" wire:click="closeConciliarModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>
@endif
