<x-filament-panels::page>
    <div class="erp-os-window erp-operacoes-fiscais">
        <header class="erp-os-window__titlebar">
            <span>CFOP — Operações fiscais</span>
            <a href="{{ url('/admin') }}" class="erp-os-window__close" title="ESC | Sair" aria-label="Fechar">&times;</a>
        </header>

        <div class="erp-os-window__body erp-operacoes-fiscais__body">
            <header class="erp-operacoes-fiscais__header">
                <div>
                    <h1>CFOP — Operações fiscais</h1>
                    <p>Configure os CFOPs utilizados como padrão pelo sistema. Regras fiscais específicas podem substituir estes códigos automaticamente.</p>
                </div>
            </header>

            @if ($this->alert !== '')
                <div @class(['erp-operacoes-fiscais__alert', 'is-ok' => str_starts_with($this->alert, 'OK:')]) role="alert">
                    <strong>{{ str_starts_with($this->alert, 'OK:') ? 'Pronto' : 'Atenção' }}</strong>
                    <span>{{ str_starts_with($this->alert, 'OK:') ? substr($this->alert, 3) : $this->alert }}</span>
                    <button type="button" wire:click="$set('alert', '')">×</button>
                </div>
            @endif

            <section class="erp-operacoes-fiscais__card">
                <div class="erp-operacoes-fiscais__table-wrap">
                    <table class="erp-operacoes-fiscais__table">
                        <thead>
                            <tr>
                                <th>Operação</th>
                                <th>Dentro do Estado</th>
                                <th>Fora do Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->operacoes() as $key => $meta)
                                <tr wire:key="operacao-fiscal-linha-{{ $key }}">
                                    <td>
                                        <div class="erp-operacoes-fiscais__op-label">
                                            <span>{{ $meta['label'] }}</span>
                                            @if (
                                                $this->isPadraoUnitec($key, 'estadual')
                                                && (! $meta['interestadual_aplicavel'] || $this->isPadraoUnitec($key, 'interestadual'))
                                            )
                                                <span class="erp-operacoes-fiscais__badge" title="Valor padrão oficial do Unitec ERP">Padrão Unitec</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="erp-operacoes-fiscais__cfop-cell">
                                        <input
                                            wire:key="operacao-fiscal-input-{{ $key }}-estadual"
                                            type="text"
                                            class="erp-operacoes-fiscais__cfop-input"
                                            wire:model.live.debounce.200ms="form.{{ $key }}_estadual"
                                            wire:focus="abrirBuscaCfop('{{ $key }}_estadual')"
                                            wire:blur="reformatarCampoCfop('{{ $key }}_estadual')"
                                            wire:input="atualizarBuscaCfop('{{ $key }}_estadual', $event.target.value)"
                                            autocomplete="off"
                                            maxlength="120"
                                            placeholder="Ex.: 5102"
                                        >
                                        @if ($this->cfopLookupCampo === $key.'_estadual')
                                            @include('filament.pages.partials.operacoes-fiscais-cfop-lookup')
                                        @endif
                                    </td>
                                    <td class="erp-operacoes-fiscais__cfop-cell">
                                        @if ($meta['interestadual_aplicavel'])
                                            <input
                                                wire:key="operacao-fiscal-input-{{ $key }}-interestadual"
                                                type="text"
                                                class="erp-operacoes-fiscais__cfop-input"
                                                wire:model.live.debounce.200ms="form.{{ $key }}_interestadual"
                                                wire:focus="abrirBuscaCfop('{{ $key }}_interestadual')"
                                                wire:blur="reformatarCampoCfop('{{ $key }}_interestadual')"
                                                wire:input="atualizarBuscaCfop('{{ $key }}_interestadual', $event.target.value)"
                                                autocomplete="off"
                                                maxlength="120"
                                                placeholder="Ex.: 6102"
                                            >
                                            @if ($this->cfopLookupCampo === $key.'_interestadual')
                                                @include('filament.pages.partials.operacoes-fiscais-cfop-lookup')
                                            @endif
                                        @else
                                            <div class="erp-operacoes-fiscais__na" title="Esta operação não possui CFOP interestadual">
                                                Não se aplica
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="erp-operacoes-fiscais__mensagem-box">
                    <label class="erp-operacoes-fiscais__mensagem" for="operacoes-fiscais-mensagem">
                        <span>Mensagem padrão das operações fiscais</span>
                        <textarea
                            id="operacoes-fiscais-mensagem"
                            wire:model="form.mensagem"
                            rows="2"
                            placeholder="Informação adicional opcional que poderá ser utilizada nas operações fiscais."
                        ></textarea>
                    </label>
                </div>
            </section>

            <footer class="erp-operacoes-fiscais__actions">
                <button
                    type="button"
                    class="erp-operacoes-fiscais__btn erp-operacoes-fiscais__btn--ghost"
                    wire:click="restaurarPadroes"
                    wire:confirm="Deseja restaurar os CFOPs padrão do Unitec ERP? As configurações atuais desta empresa serão substituídas."
                >
                    Restaurar padrões
                </button>
                <div class="erp-operacoes-fiscais__actions-main">
                    <button type="button" class="erp-operacoes-fiscais__btn erp-operacoes-fiscais__btn--ok" wire:click="salvar">
                        <span>✓</span> Gravar
                    </button>
                    <a href="{{ url('/admin') }}" class="erp-operacoes-fiscais__btn erp-operacoes-fiscais__btn--danger">
                        <span>×</span> Sair
                    </a>
                </div>
            </footer>
        </div>
    </div>
</x-filament-panels::page>
