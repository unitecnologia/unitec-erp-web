<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de Vendas Geral — Filtros</title>
    <link rel="stylesheet" href="{{ asset('css/erp-relatorio-vendas-geral.css') }}?v=8">
</head>
<body class="erp-rvg-body-page">
<div class="erp-rvg-page">
    <div class="erp-rvg-window" role="dialog" aria-labelledby="erp-rvg-title">
        <header class="erp-rvg-titlebar">
            <span id="erp-rvg-title">Relatório de Vendas Geral</span>
            <a href="{{ $closeUrl }}" class="erp-rvg-close" title="Fechar" aria-label="Fechar">&times;</a>
        </header>

        <div class="erp-rvg-shell">
            @if ($erro !== '')
                <div class="erp-rvg-alert" role="alert">{{ $erro }}</div>
            @endif

            <div class="erp-rvg-empresa" title="Empresa logada">
                <span class="erp-rvg-empresa__label">Empresa</span>
                <strong>{{ $empresaLabel }}</strong>
            </div>

            <form method="get" action="{{ $reportUrl }}" id="erp-rvg-form" class="erp-rvg-form">
                <input type="hidden" name="gerar" value="1">
                <input type="hidden" name="return" value="{{ $filtersUrl }}">

                <div class="erp-rvg-scroll">
                    <section class="erp-rvg-block" aria-label="Filtros">
                        <div class="erp-rvg-grid">
                            <label class="erp-rvg-field">
                                <span>Data inicial</span>
                                <input type="date" name="de" value="{{ $values['de'] }}" required data-erp-native-date>
                            </label>
                            <label class="erp-rvg-field">
                                <span>Data final</span>
                                <input type="date" name="ate" value="{{ $values['ate'] }}" required data-erp-native-date>
                            </label>
                            <label class="erp-rvg-field erp-rvg-field--cliente">
                                <span>Cliente (código ou nome)</span>
                                <div
                                    id="erp-rvg-cliente-lookup"
                                    class="erp-rvg-cliente-lookup"
                                    data-lookup-url="{{ $clienteLookupUrl }}"
                                >
                                    <input
                                        id="erp-rvg-cliente-q"
                                        type="text"
                                        name="cliente_q"
                                        value="{{ $values['cliente_q'] }}"
                                        placeholder="Digite código, nome ou CPF/CNPJ"
                                        autocomplete="off"
                                        aria-autocomplete="list"
                                        aria-expanded="false"
                                        aria-controls="erp-rvg-cliente-lookup-panel"
                                    >
                                </div>
                            </label>

                            <label class="erp-rvg-field">
                                <span>Vendedor</span>
                                <select name="vendedor">
                                    @foreach (($options['vendedor'] ?? ['todos' => 'Todos']) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['vendedor'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rvg-field">
                                <span>Situação</span>
                                <select name="status">
                                    @foreach (($options['status'] ?? []) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['status'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rvg-field">
                                <span>Origem da venda</span>
                                <select name="origem">
                                    @foreach (($options['origem'] ?? ['todos' => 'Todas']) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['origem'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="erp-rvg-field">
                                <span>Forma de pagamento</span>
                                <select name="forma">
                                    @foreach (($options['forma'] ?? ['todos' => 'Todas']) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['forma'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rvg-field erp-rvg-field--span2">
                                <span>Nº venda / documento fiscal</span>
                                <input type="text" name="numero_q" value="{{ $values['numero_q'] }}" placeholder="Venda, NF-e ou NFC-e" autocomplete="off">
                            </label>

                            <label class="erp-rvg-field erp-rvg-field--cidade">
                                <span>Cidade do cliente</span>
                                <div
                                    id="erp-rvg-cidade-lookup"
                                    class="erp-rvg-cliente-lookup"
                                    data-lookup-url="{{ $cidadeLookupUrl }}"
                                >
                                    <input
                                        id="erp-rvg-cidade-q"
                                        type="text"
                                        name="cidade_q"
                                        value="{{ $values['cidade_q'] }}"
                                        placeholder="Digite a cidade do cliente"
                                        autocomplete="off"
                                        aria-autocomplete="list"
                                        aria-expanded="false"
                                        aria-controls="erp-rvg-cidade-lookup-panel"
                                    >
                                </div>
                            </label>
                            <label class="erp-rvg-field">
                                <span>Grupo de produtos</span>
                                <select name="grupo">
                                    @foreach (($options['grupo'] ?? ['todos' => 'Todos']) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['grupo'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rvg-field">
                                <span>Caixa (PDV)</span>
                                <select name="caixa">
                                    @foreach (($options['caixa'] ?? ['todos' => 'Todos']) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['caixa'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rvg-field">
                                <span>Usuário responsável (PDV)</span>
                                <select name="usuario">
                                    @foreach (($options['usuario'] ?? ['todos' => 'Todos']) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['usuario'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rvg-field">
                                <span>Tipo de documento fiscal</span>
                                <select name="doc_tipo">
                                    @foreach (($options['doc_tipo'] ?? ['todos' => 'Todos']) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['doc_tipo'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </section>

                    <section class="erp-rvg-meta" aria-label="Parâmetros e agrupamento">
                        <div class="erp-rvg-params">
                            <div class="erp-rvg-meta__title">Parâmetros</div>
                            <div class="erp-rvg-checks">
                                <label class="erp-rvg-check"><input type="checkbox" name="exibir_produtos" value="1" @checked($values['exibir_produtos'])> Exibir produtos vendidos</label>
                                <label class="erp-rvg-check"><input type="checkbox" name="mostrar_resumo" value="1" @checked($values['mostrar_resumo'])> Mostrar resumo</label>
                                <label class="erp-rvg-check"><input type="checkbox" name="apenas_resumo" value="1" @checked($values['apenas_resumo'])> Exibir apenas resumo</label>
                                <label class="erp-rvg-check"><input type="checkbox" name="exibir_formas" value="1" @checked($values['exibir_formas'])> Exibir formas de pagamento (por venda)</label>
                                <label class="erp-rvg-check"><input type="checkbox" name="exibir_grafico" value="1" @checked($values['exibir_grafico'])> Análise gráfica diária</label>
                                <label class="erp-rvg-check"><input type="checkbox" name="exibir_resumo_hora" value="1" @checked($values['exibir_resumo_hora'])> Exibir resumo por hora</label>
                            </div>
                        </div>

                        <div class="erp-rvg-group">
                            <div class="erp-rvg-meta__title">Agrupamento</div>
                            <label class="erp-rvg-field">
                                <span>Agrupar por</span>
                                <select name="agrupar">
                                    @foreach (($options['agrupar'] ?? []) as $value => $label)
                                        <option value="{{ $value }}" @selected($values['agrupar'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </section>
                </div>

                <footer class="erp-rvg-actions">
                    <div class="erp-rvg-actions__left">
                        <a class="erp-rvg-btn" href="{{ $filtersUrl }}">Limpar Filtros</a>
                        <button type="submit" class="erp-rvg-btn erp-rvg-btn--primary">Gerar Relatório</button>
                    </div>
                    <div class="erp-rvg-actions__right">
                        <a class="erp-rvg-btn" href="{{ $closeUrl }}">Sair</a>
                    </div>
                </footer>
            </form>
        </div>
    </div>
</div>
<script src="{{ asset('js/erp-rvg-cliente-lookup.js') }}?v=2" defer></script>
</body>
</html>
