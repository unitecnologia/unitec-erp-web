<x-filament-panels::page>
    <div
        class="erp-os-window erp-mov-saidas-window"
        wire:keydown.f2.window="gravarMovimento"
        x-data
        x-on:erp-mov-saidas-focus-produto.window="$nextTick(() => { setTimeout(() => { const el = document.getElementById('mov-saidas-inclusao-produto'); if (!el) return; el.focus(); el.select?.(); }, 40); })"
        x-on:erp-mov-saidas-focus-qtd.window="$nextTick(() => { setTimeout(() => { const el = document.getElementById('mov-saidas-inclusao-qtd'); if (!el) return; el.focus(); el.select?.(); }, 40); })"
        x-on:erp-mov-saidas-focus-preco.window="$nextTick(() => { setTimeout(() => { const el = document.getElementById('mov-saidas-inclusao-preco'); if (!el) return; el.focus(); el.select?.(); }, 40); })"
        x-on:keydown.delete.window.prevent="$wire.solicitarExcluirItem()"
    >
        <header class="erp-os-window__titlebar">
            <span>Outras Saídas / Movimento</span>
            <a href="{{ url('/admin') }}" class="erp-os-window__close" title="ESC | Sair" aria-label="Fechar">&times;</a>
        </header>

        <div class="erp-os-window__body erp-mov-saidas-body">
            @if ($this->formAlert !== '')
                <div
                    @class([
                        'erp-mov-saidas-alert',
                        'is-ok' => str_starts_with($this->formAlert, 'OK:'),
                    ])
                    role="alert"
                    @if (str_starts_with($this->formAlert, 'OK:'))
                        x-data="{ timer: null }"
                        x-init="timer = setTimeout(() => $wire.set('formAlert', ''), 3000)"
                    @endif
                >
                    <span aria-hidden="true">{{ str_starts_with($this->formAlert, 'OK:') ? '✓' : '!' }}</span>
                    <div>
                        <strong>{{ str_starts_with($this->formAlert, 'OK:') ? 'Pronto' : 'Atenção' }}</strong>
                        <p>{{ str_starts_with($this->formAlert, 'OK:') ? substr($this->formAlert, 3) : $this->formAlert }}</p>
                    </div>
                    <button type="button" wire:click="$set('formAlert', '')" aria-label="Fechar aviso">×</button>
                </div>
            @endif

            <section class="erp-mov-saidas-panel">
                <div class="erp-mov-saidas-panel__head">
                    <strong>Dados do movimento</strong>
                    @php
                        $statusLabel = \App\Filament\Pages\OutrasSaidasMovimentoPage::situacaoLabels()[$this->situacao]
                            ?? 'Aberto';
                    @endphp
                    <span @class([
                        'erp-mov-saidas-status',
                        'is-fechado' => $this->situacao === 'finalizada',
                        'is-cancelado' => $this->situacao === 'cancelada',
                    ])>{{ $statusLabel }}</span>
                </div>

                <div class="erp-mov-saidas-fields">
                    <label class="erp-mov-saidas-field erp-mov-saidas-field--code">
                        <span>Código</span>
                        <input value="{{ $this->numero }}" readonly tabindex="-1">
                    </label>

                    <label class="erp-mov-saidas-field erp-mov-saidas-field--tipo">
                        <span>Tipo de movimentação</span>
                        <select wire:model="tipoMovimento">
                            <option value="">Selecione…</option>
                            <option value="uso_consumo">Saída para uso ou consumo</option>
                            <option value="perda">Saída por perda</option>
                            <option value="outras">Outras</option>
                        </select>
                    </label>

                    <label class="erp-mov-saidas-field">
                        <span>Data</span>
                        <input type="date" wire:model="dataMovimento">
                    </label>

                    <label class="erp-mov-saidas-field">
                        <span>Hora</span>
                        <input type="time" wire:model="horaMovimento">
                    </label>

                    <label class="erp-mov-saidas-field">
                        <span>NF emitida</span>
                        <input type="text" wire:model="nfEmitida" placeholder="Opcional" inputmode="numeric">
                    </label>

                    <label class="erp-mov-saidas-field erp-mov-saidas-field--plano">
                        <span>Plano de contas</span>
                        <select wire:model="planoContaId">
                            <option value="">Selecione…</option>
                            @foreach ($this->planosContas as $plano)
                                <option value="{{ $plano['id'] }}">{{ $plano['nome'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="erp-mov-saidas-field erp-mov-saidas-field--estoque">
                        <span>Estoque</span>
                        <select wire:model="estoqueId">
                            <option value="">Selecione…</option>
                            @foreach ($this->estoques as $estoque)
                                <option value="{{ $estoque['id'] }}">{{ $estoque['nome'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="erp-mov-saidas-field erp-mov-saidas-field--fornecedor">
                        <span>Fornecedor</span>
                        <input
                            type="search"
                            wire:model.live.debounce.250ms="fornecedorBusca"
                            wire:keydown.arrow-up.prevent="moverFornecedorSelecionado(-1)"
                            wire:keydown.arrow-down.prevent="moverFornecedorSelecionado(1)"
                            wire:keydown.enter.prevent="confirmarFornecedorSelecionado"
                            placeholder="Digite para localizar"
                            autocomplete="off"
                        >
                        @if ($this->fornecedorResultados !== [])
                            <div class="erp-mov-saidas-lookup">
                                @foreach ($this->fornecedorResultados as $index => $fornecedor)
                                    <button
                                        type="button"
                                        wire:click="selecionarFornecedor({{ $fornecedor['id'] }})"
                                        @class(['is-selected' => $this->fornecedorSelecionadoIndex === $index])
                                    >
                                        <strong>{{ $fornecedor['nome'] }}</strong>
                                        <em>{{ $fornecedor['cnpj'] !== '' ? $fornecedor['cnpj'] : 'CNPJ não informado' }}</em>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </label>

                    <label class="erp-mov-saidas-field erp-mov-saidas-field--obs">
                        <span>Observações</span>
                        <textarea wire:model="observacoes" rows="2" placeholder="Informações sobre a saída"></textarea>
                    </label>
                </div>
            </section>

            <section class="erp-mov-saidas-panel erp-mov-saidas-panel--items">
                <div class="erp-mov-saidas-panel__head">
                    <div>
                        <strong>Itens do movimento</strong>
                        <p>Código/barras/nome → Enter → Qtde → Enter → Preço compra → Enter.</p>
                    </div>
                </div>

                @include('filament.components.erp.outras-saidas-movimento.produto-inclusao')

                <div class="erp-mov-saidas-table-wrap">
                    <table class="erp-mov-saidas-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descrição</th>
                                <th>Qtde</th>
                                <th>Preço compra</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->itens as $index => $item)
                                <tr
                                    wire:key="mov-saidas-item-{{ $item['product_id'] ?? $item['codigo'] }}-{{ $index }}"
                                    wire:click="selecionarItem({{ $index }})"
                                    @class(['is-selected' => $this->itemSelecionadoIndex === $index])
                                >
                                    <td>{{ $item['codigo'] }}</td>
                                    <td>{{ $item['descricao'] }}</td>
                                    <td>{{ $item['qtd'] }}</td>
                                    <td>R$ {{ $item['preco'] }}</td>
                                    <td>R$ {{ $item['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="erp-mov-saidas-empty">Nenhum item incluído. Use a barra acima (igual à NF-e).</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($this->confirmarExcluirItem)
                <div class="erp-mov-saidas-confirm" role="alertdialog" aria-modal="true">
                    <div class="erp-mov-saidas-confirm__backdrop" wire:click="cancelarExcluirItem"></div>
                    <section class="erp-mov-saidas-confirm__dialog" wire:click.stop>
                        <strong>Excluir item?</strong>
                        <p>Deseja excluir o produto selecionado da grade?</p>
                        <div>
                            <button type="button" wire:click="excluirItemSelecionado">Sim, excluir</button>
                            <button type="button" wire:click="cancelarExcluirItem">Não</button>
                        </div>
                    </section>
                </div>
            @endif

            <footer class="erp-os-actions">
                <button type="button" class="erp-os-actions__btn erp-os-actions__btn--save" wire:click="gravarMovimento" data-erp-key="F2" title="Gravar movimento (F2)">
                    <span class="erp-os-actions__icon">✓</span>
                    <span class="erp-os-actions__label">Gravar</span>
                </button>
                <button type="button" class="erp-os-actions__btn" wire:click="abrirConsultaMovimentos">
                    <span class="erp-os-actions__icon">⌕</span>
                    <span class="erp-os-actions__label">Consultar</span>
                </button>
                <button
                    type="button"
                    class="erp-os-actions__btn"
                    wire:click="imprimirMovimento"
                    title="{{ $this->movimentoId ? 'Imprimir relatório do movimento' : 'Abrir relatório mensal de movimentações' }}"
                >
                    <span class="erp-os-actions__icon">🖨</span>
                    <span class="erp-os-actions__label">Imprimir</span>
                </button>
                <button
                    type="button"
                    class="erp-os-actions__btn erp-os-actions__btn--save"
                    wire:click="concluirMovimento"
                    data-erp-key="F9"
                    @disabled($this->movimentoId === null || $this->situacao !== 'aberta')
                    title="{{ $this->movimentoId && $this->situacao === 'aberta' ? 'Concluir movimento (F9)' : 'Grave um movimento aberto antes de concluir' }}"
                >
                    <span class="erp-os-actions__icon">✓</span>
                    <span class="erp-os-actions__label">Concluir</span>
                </button>
                <button
                    type="button"
                    class="erp-os-actions__btn erp-mov-saidas__reabrir"
                    wire:click="reabrirMovimento"
                    @disabled($this->movimentoId === null || $this->situacao !== 'finalizada')
                    title="{{ $this->situacao === 'finalizada' ? 'Reabrir movimento' : 'Disponível para movimento fechado' }}"
                >
                    <span class="erp-os-actions__icon">↶</span>
                    <span class="erp-os-actions__label">Reabrir</span>
                </button>
                <button
                    type="button"
                    class="erp-os-actions__btn erp-mov-saidas__emitir-nfe"
                    wire:click="emitirNfe"
                    @disabled(! $this->podeEmitirNfe)
                    title="{{ $this->podeEmitirNfe ? 'Emitir NF-e deste movimento' : $this->motivoEmitirNfeBloqueado }}"
                >
                    <span class="erp-os-actions__icon">NF</span>
                    <span class="erp-os-actions__label">Emitir NF-e</span>
                </button>
                <a href="{{ url('/admin') }}" class="erp-os-actions__btn erp-os-actions__btn--exit">
                    <span class="erp-os-actions__icon">✕</span>
                    <span class="erp-os-actions__label">Sair</span>
                </a>
            </footer>
        </div>
    </div>

    @if ($this->consultaAberta)
        @include('filament.components.erp.outras-saidas-movimento.consulta-modal')
    @endif
</x-filament-panels::page>
