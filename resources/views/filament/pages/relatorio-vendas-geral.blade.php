<x-filament-panels::page>
@php
    $options = $this->selectOptions();
@endphp

<div class="erp-os-window erp-rvg-window">
    <header class="erp-os-window__titlebar">
        <span>Relatório de Vendas Geral</span>
        <a href="{{ url('/admin') }}" class="erp-os-window__close" title="Fechar" aria-label="Fechar">&times;</a>
    </header>

    <div class="erp-os-window__body erp-rvg-body">
        @if ($erroValidacao !== '')
            <div class="erp-rvg-alert" role="alert">{{ $erroValidacao }}</div>
        @endif

        <section class="erp-rvg-panel">
            <div class="erp-rvg-panel__head">
                <strong>Filtros</strong>
                <p>Defina o recorte das vendas. A consulta só roda ao clicar em Gerar Relatório.</p>
            </div>

            <div class="erp-rvg-grid">
                <label class="erp-rvg-field">
                    <span>Data inicial</span>
                    <input type="date" wire:model="de">
                </label>
                <label class="erp-rvg-field">
                    <span>Data final</span>
                    <input type="date" wire:model="ate">
                </label>
                <label class="erp-rvg-field erp-rvg-field--span2">
                    <span>Empresa</span>
                    <div class="erp-rvg-static">{{ $this->empresaLabel() }}</div>
                </label>

                <label class="erp-rvg-field erp-rvg-field--span2">
                    <span>Cliente (código ou nome)</span>
                    <input type="text" wire:model="cliente_q" placeholder="Busca sob demanda" data-erp-uppercase autocomplete="off">
                </label>
                <label class="erp-rvg-field">
                    <span>Vendedor</span>
                    <select wire:model="vendedor">
                        @foreach (($options['vendedor'] ?? ['todos' => 'Todos']) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-rvg-field">
                    <span>Cidade do cliente</span>
                    <input type="text" wire:model="cidade_q" placeholder="Nome da cidade" data-erp-uppercase autocomplete="off">
                </label>

                <label class="erp-rvg-field">
                    <span>Grupo de produtos</span>
                    <select wire:model="grupo">
                        @foreach (($options['grupo'] ?? ['todos' => 'Todos']) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-rvg-field">
                    <span>Caixa (PDV)</span>
                    <select wire:model="caixa">
                        @foreach (($options['caixa'] ?? ['todos' => 'Todos']) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-rvg-field">
                    <span>Forma de pagamento</span>
                    <select wire:model="forma">
                        @foreach (($options['forma'] ?? ['todos' => 'Todas']) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-rvg-field">
                    <span>Usuário responsável (PDV)</span>
                    <select wire:model="usuario">
                        @foreach (($options['usuario'] ?? ['todos' => 'Todos']) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-rvg-field">
                    <span>Nº venda / documento fiscal</span>
                    <input type="text" wire:model="numero_q" placeholder="Venda, NF-e ou NFC-e" autocomplete="off">
                </label>
                <label class="erp-rvg-field">
                    <span>Origem da venda</span>
                    <select wire:model="origem">
                        @foreach (($options['origem'] ?? ['todos' => 'Todas']) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-rvg-field">
                    <span>Situação</span>
                    <select wire:model="status">
                        @foreach (($options['status'] ?? []) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-rvg-field">
                    <span>Tipo de documento fiscal</span>
                    <select wire:model="doc_tipo">
                        @foreach (($options['doc_tipo'] ?? ['todos' => 'Todos']) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <div class="erp-rvg-columns">
            <section class="erp-rvg-panel">
                <div class="erp-rvg-panel__head">
                    <strong>Parâmetros</strong>
                    <p>Controlam o detalhamento da visualização gerada.</p>
                </div>
                <div class="erp-rvg-checks">
                    <label class="erp-rvg-check"><input type="checkbox" wire:model="exibir_produtos"> Exibir produtos vendidos</label>
                    <label class="erp-rvg-check"><input type="checkbox" wire:model="mostrar_resumo"> Mostrar resumo</label>
                    <label class="erp-rvg-check"><input type="checkbox" wire:model="apenas_resumo"> Exibir apenas resumo</label>
                    <label class="erp-rvg-check"><input type="checkbox" wire:model="exibir_formas"> Exibir formas de pagamento</label>
                    <label class="erp-rvg-check"><input type="checkbox" wire:model="exibir_grafico"> Análise gráfica diária</label>
                    <label class="erp-rvg-check"><input type="checkbox" wire:model="exibir_pct_lucro"> Exibir % de lucro</label>
                </div>
            </section>

            <section class="erp-rvg-panel">
                <div class="erp-rvg-panel__head">
                    <strong>Agrupamento</strong>
                    <p>Organiza as linhas do relatório gerado.</p>
                </div>
                <label class="erp-rvg-field">
                    <span>Agrupar por</span>
                    <select wire:model="agrupar">
                        @foreach (($options['agrupar'] ?? []) as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </section>
        </div>

        <footer class="erp-rvg-actions">
            <button type="button" class="erp-rvg-btn erp-rvg-btn--primary" wire:click="gerarRelatorio" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="gerarRelatorio">Gerar Relatório</span>
                <span wire:loading wire:target="gerarRelatorio">Gerando…</span>
            </button>
            <button type="button" class="erp-rvg-btn" wire:click="limparFiltros">Limpar Filtros</button>
        </footer>
    </div>
</div>
</x-filament-panels::page>
