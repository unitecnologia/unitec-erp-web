@if ($this->loteModalOpen)
    @php
        $terminais = $this->loteTerminalOptions();
        $resumo = $this->resumoSelecao();
        $perPage = 25;
        $pages = max(1, (int) ceil($this->loteCandidatasTotal / $perPage));
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal erp-tef-liq-lote-form-modal"
        wire:keydown.escape.window="closeLoteModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeLoteModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window erp-tef-liq-lote-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-tef-liq-lote-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-tef-liq-lote-form-title">Novo lote de liquidação SuperTEF</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeLoteModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form" data-erp-form autocomplete="off">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Filtros (somente controle — não liquida)</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="lote-terminal">Terminal *</label>
                                <select id="lote-terminal" wire:model.live="loteForm.terminal_id" class="erp-pcad-form__select erp-pcad-form__input--grow" autofocus>
                                    <option value="">Selecione…</option>
                                    @foreach ($terminais as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="lote-pago-de">Pago de</label>
                                <input id="lote-pago-de" type="date" wire:model="loteForm.pago_de" class="erp-pcad-form__input erp-pcad-form__input--date">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="lote-pago-ate">até</label>
                                <input id="lote-pago-ate" type="date" wire:model="loteForm.pago_ate" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="lote-cnpj">CNPJ adquirente</label>
                                <input id="lote-cnpj" type="text" wire:model.blur="loteForm.acquirer_cnpj" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="18" inputmode="numeric" placeholder="Opcional">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline">
                                    <input type="checkbox" wire:model="loteForm.somente_sem_acquirer" class="erp-pcad-form__checkbox"> Sem CNPJ
                                </label>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label">Destino BANCO</label>
                                <span class="erp-tef-liq-lote-form-modal__destino {{ $this->loteDestinoStatus === 'ok' ? 'is-ok' : 'is-warn' }}">
                                    {{ $this->loteDestinoLabel !== '' ? $this->loteDestinoLabel : '—' }}
                                </span>
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <button type="button" class="erp-contador-form-modal__btn" wire:click="buscarCandidatas(1)">Buscar transações</button>
                            </div>
                        </div>
                    </section>

                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">
                            Candidatas ({{ $this->loteCandidatasTotal }}) — selecionadas: {{ $resumo['qtd'] }}
                        </h3>
                        <div class="erp-tef-liq-lote-form-modal__table-wrap">
                            <table class="erp-tef-liq-lote-form-modal__table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>ID</th>
                                        <th>Pago em</th>
                                        <th>NSU</th>
                                        <th>Bandeira</th>
                                        <th>Parc.</th>
                                        <th>Bruto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->loteCandidatas as $row)
                                        @php $checked = in_array((int) $row['id'], $this->loteSelectedIds, true); @endphp
                                        <tr>
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    @checked($checked)
                                                    wire:click="toggleCandidata({{ (int) $row['id'] }})"
                                                >
                                            </td>
                                            <td>{{ $row['id'] }}</td>
                                            <td>{{ $row['pago_em'] }}</td>
                                            <td>{{ $row['nsu'] }}</td>
                                            <td>{{ $row['bandeira'] }}</td>
                                            <td>{{ $row['parcelas'] }}</td>
                                            <td class="is-right">{{ $row['amount'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7">Nenhuma candidata. Ajuste filtros e busque.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($pages > 1)
                            <div class="erp-tef-liq-lote-form-modal__pager">
                                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="buscarCandidatas({{ max(1, $this->loteCandidatasPage - 1) }})" @disabled($this->loteCandidatasPage <= 1)>Anterior</button>
                                <span>Pág. {{ $this->loteCandidatasPage }}/{{ $pages }}</span>
                                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="buscarCandidatas({{ min($pages, $this->loteCandidatasPage + 1) }})" @disabled($this->loteCandidatasPage >= $pages)>Próxima</button>
                            </div>
                        @endif
                    </section>

                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Resumo / taxas</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="lote-taxas">Taxa informada</label>
                                <input id="lote-taxas" type="text" wire:model.blur="loteForm.valor_taxas" class="erp-pcad-form__input" inputmode="decimal">
                                <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="lote-prevista">Prevista</label>
                                <input id="lote-prevista" type="date" wire:model="loteForm.data_prevista_liquidacao" class="erp-pcad-form__input erp-pcad-form__input--date">
                            </div>
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="lote-ext">ID externo</label>
                                <input id="lote-ext" type="text" wire:model="loteForm.identificador_externo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="80">
                            </div>
                            <p class="erp-tef-liq-lote-form-modal__hint">
                                Selecionadas: {{ $resumo['qtd'] }} · Bruto (página visível): R$ {{ number_format($resumo['bruto_pagina'], 2, ',', '.') }} ·
                                Taxas: R$ {{ number_format($resumo['taxas'], 2, ',', '.') }}.
                                Totais finais são recalculados na gravação. Sem taxa confiável = origem “não informada”.
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="closeLoteModal">Cancelar</button>
                <button type="button" class="erp-contador-form-modal__btn" wire:click="saveLote">Criar lote</button>
            </div>
        </div>
    </div>
@endif
