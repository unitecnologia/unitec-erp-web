<x-filament-panels::page>
    <div class="erp-os-window erp-operacoes-fiscais">
        <header class="erp-os-window__titlebar">
            <span>CFOP — Operações fiscais</span>
            <a href="{{ url('/admin') }}" class="erp-os-window__close" title="ESC | Sair" aria-label="Fechar">&times;</a>
        </header>

        <div class="erp-os-window__body erp-operacoes-fiscais__body">
        <header class="erp-operacoes-fiscais__header">
            <div>
                <span>Fiscal</span>
                <h1>CFOP — Operações fiscais</h1>
                <p>Defina o CFOP padrão por operação e destino da nota.</p>
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
                            <th>Estadual</th>
                            <th>Interestadual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->operacoes() as $key => $label)
                            <tr wire:key="operacao-fiscal-linha-{{ $key }}">
                                <td>{{ $label }}</td>
                                <td class="erp-operacoes-fiscais__cfop-cell">
                                    <input
                                        wire:key="operacao-fiscal-input-{{ $key }}-estadual"
                                        type="text"
                                        wire:model.live.debounce.200ms="form.{{ $key }}_estadual"
                                        wire:focus="abrirBuscaCfop('{{ $key }}_estadual')"
                                        wire:input="atualizarBuscaCfop('{{ $key }}_estadual', $event.target.value)"
                                        autocomplete="off"
                                        placeholder="Código ou descrição"
                                    >
                                    @if ($this->cfopLookupCampo === $key.'_estadual')
                                        @include('filament.pages.partials.operacoes-fiscais-cfop-lookup')
                                    @endif
                                </td>
                                <td class="erp-operacoes-fiscais__cfop-cell">
                                    <input
                                        wire:key="operacao-fiscal-input-{{ $key }}-interestadual"
                                        type="text"
                                        wire:model.live.debounce.200ms="form.{{ $key }}_interestadual"
                                        wire:focus="abrirBuscaCfop('{{ $key }}_interestadual')"
                                        wire:input="atualizarBuscaCfop('{{ $key }}_interestadual', $event.target.value)"
                                        autocomplete="off"
                                        placeholder="Código ou descrição"
                                    >
                                    @if ($this->cfopLookupCampo === $key.'_interestadual')
                                        @include('filament.pages.partials.operacoes-fiscais-cfop-lookup')
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <label class="erp-operacoes-fiscais__mensagem">
                <span>Mensagem</span>
                <textarea wire:model="form.mensagem" rows="2" placeholder="Mensagem padrão para operações fiscais (opcional)"></textarea>
            </label>
        </section>

        <footer class="erp-operacoes-fiscais__actions">
            <button type="button" wire:click="salvar">
                <span>✓</span> Gravar
            </button>
            <a href="{{ url('/admin') }}"><span>×</span> Sair</a>
        </footer>
        </div>
    </div>
</x-filament-panels::page>
