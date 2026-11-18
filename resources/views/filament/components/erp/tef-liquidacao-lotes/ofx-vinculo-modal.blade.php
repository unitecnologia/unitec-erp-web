@if ($this->ofxVinculoModalOpen)
    @php $r = $this->ofxVinculoLoteResumo; @endphp
    <div class="erp-lookup-modal erp-contador-form-modal erp-tef-liq-lote-form-modal" wire:keydown.escape.window="closeOfxVinculoModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeOfxVinculoModal"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window erp-tef-liq-lote-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Vincular OFX — Lote #{{ $r['id'] ?? '' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeOfxVinculoModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <p class="erp-tef-liq-lote-form-modal__hint">
                    {{ $r['situacao'] ?? '' }} · {{ $r['ofx_situacao'] ?? '' }} · Conta: {{ $r['conta'] ?? '—' }}<br>
                    Líquido R$ {{ $r['liquido'] ?? '0,00' }} · Vinculado R$ {{ $r['vinculado'] ?? '0,00' }} ·
                    Restante R$ {{ $r['restante'] ?? '0,00' }} — somente controle, sem movimento financeiro.
                </p>

                <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                    <label class="erp-pcad-form__label">De</label>
                    <input type="date" wire:model="ofxFiltroDe" class="erp-pcad-form__input erp-pcad-form__input--date">
                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">Até</label>
                    <input type="date" wire:model="ofxFiltroAte" class="erp-pcad-form__input erp-pcad-form__input--date">
                    <input type="text" wire:model="ofxFiltroQ" class="erp-pcad-form__input erp-pcad-form__input--grow" placeholder="Histórico / FITID" maxlength="80">
                    <button type="button" class="erp-contador-form-modal__btn" wire:click="buscarOfxCandidatos">Buscar</button>
                </div>

                <div class="erp-tef-liq-lote-form-modal__table-wrap">
                    <table class="erp-tef-liq-lote-form-modal__table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Restante</th>
                                <th>TEF</th>
                                <th>Histórico</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->ofxCandidatos as $row)
                                <tr>
                                    <td>
                                        <input
                                            type="radio"
                                            name="ofx-sel"
                                            @checked((int) $this->ofxSelectedExtratoId === (int) $row['id'])
                                            wire:click="selecionarOfxExtrato({{ (int) $row['id'] }})"
                                        >
                                    </td>
                                    <td>{{ $row['id'] }}</td>
                                    <td>{{ $row['data'] }}</td>
                                    <td class="is-right">{{ $row['valor'] }}</td>
                                    <td class="is-right">{{ $row['restante'] }}</td>
                                    <td>{{ $row['tef_sit'] }}</td>
                                    <td>{{ $row['historico'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7">Nenhum crédito elegível. Busque sob demanda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="erp-pcad-form__row erp-codigo-nome-form__row" style="margin-top:0.5rem;">
                    <label class="erp-pcad-form__label" for="ofx-valor">Valor vínculo</label>
                    <input id="ofx-valor" type="text" wire:model="ofxValorVinculo" class="erp-pcad-form__input" inputmode="decimal">
                    <label class="erp-pcad-form__label erp-pcad-form__label--inline">
                        <input type="checkbox" wire:model="ofxEncerrarCreditoComSobra" class="erp-pcad-form__checkbox"> Encerrar crédito com sobra
                    </label>
                </div>
                <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                    <label class="erp-pcad-form__label" for="ofx-motivo">Justificativa</label>
                    <input id="ofx-motivo" type="text" wire:model="ofxDiferencaMotivo" class="erp-pcad-form__input erp-pcad-form__input--grow" maxlength="255" placeholder="Obrigatória se encerrar crédito com sobra (taxa/antecipação)">
                </div>
            </div>
            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="closeOfxVinculoModal">Fechar</button>
                <button type="button" class="erp-contador-form-modal__btn" wire:click="confirmarOfxVinculo">Confirmar vínculo</button>
            </div>
        </div>
    </div>
@endif
