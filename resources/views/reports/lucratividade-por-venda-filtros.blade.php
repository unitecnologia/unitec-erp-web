<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lucratividade por Venda — Filtros</title>
    <link rel="stylesheet" href="{{ asset('css/erp-relatorio-vendas-geral.css') }}?v=8">
</head>
<body class="erp-rvg-body-page">
<div class="erp-rvg-page">
    <div class="erp-rvg-window" role="dialog" aria-labelledby="erp-lpv-title">
        <header class="erp-rvg-titlebar">
            <span id="erp-lpv-title">Lucratividade por Venda</span>
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

            <form method="get" action="{{ $reportUrl }}" id="erp-lpv-form" class="erp-rvg-form">
                <input type="hidden" name="gerar" value="1">
                <input type="hidden" name="return" value="{{ $filtersUrl }}">

                <div class="erp-rvg-scroll">
                    <section class="erp-rvg-block" aria-label="Filtros">
                        <div class="erp-rvg-grid">
                            <label class="erp-rvg-field">
                                <span>Data inicial</span>
                                <input
                                    type="date"
                                    name="de"
                                    value="{{ $values['de'] }}"
                                    required
                                    autocomplete="off"
                                    data-erp-native-date
                                >
                            </label>
                            <label class="erp-rvg-field">
                                <span>Data final</span>
                                <input
                                    type="date"
                                    name="ate"
                                    value="{{ $values['ate'] }}"
                                    required
                                    autocomplete="off"
                                    data-erp-native-date
                                >
                            </label>
                            <label class="erp-rvg-field erp-rvg-field--cliente">
                                <span>Cliente (código ou nome)</span>
                                <input
                                    type="text"
                                    name="cliente_q"
                                    value="{{ $values['cliente_q'] }}"
                                    placeholder="Digite código ou nome"
                                    autocomplete="off"
                                >
                            </label>
                            <label class="erp-rvg-field">
                                <span>Vendedor</span>
                                <select name="vendedor">
                                    @foreach ($vendedorOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($values['vendedor'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-rvg-field">
                                <span>Tipo de Relatório</span>
                                <select name="tipo" id="erp-lpv-tipo">
                                    @foreach ($tipoOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($values['tipo'] === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </section>

                    <section class="erp-rvg-meta" id="erp-lpv-cols" aria-label="Colunas">
                        <div class="erp-rvg-params">
                            <div class="erp-rvg-meta__title">Colunas</div>
                            <div class="erp-rvg-checks">
                                @foreach ($columnOptions as $colKey => $colLabel)
                                    <label class="erp-rvg-check">
                                        <input
                                            type="checkbox"
                                            name="cols[]"
                                            value="{{ $colKey }}"
                                            @checked(in_array($colKey, $selectedCols, true))
                                            data-erp-lpv-col
                                        >
                                        {{ $colLabel }}
                                    </label>
                                @endforeach
                            </div>
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
<script>
(function () {
    var form = document.getElementById('erp-lpv-form');
    var tipo = document.getElementById('erp-lpv-tipo');
    var cols = document.getElementById('erp-lpv-cols');

    function toIsoDate(value) {
        var raw = String(value || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            return raw;
        }
        var m = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (m) {
            return m[3] + '-' + m[2] + '-' + m[1];
        }
        return raw;
    }

    function sync() {
        if (!tipo || !cols) return;
        var resumido = tipo.value === 'resumido';
        cols.hidden = resumido;
        cols.querySelectorAll('[data-erp-lpv-col]').forEach(function (el) {
            el.disabled = resumido;
        });
    }

    if (tipo && cols) {
        tipo.addEventListener('change', sync);
        sync();
    }

    // Garante Y-m-d no GET mesmo se o shell Filament tiver alterado o campo.
    if (form) {
                form.addEventListener('submit', function () {
            ['de', 'ate'].forEach(function (name) {
                var input = form.querySelector('input[name="' + name + '"]');
                if (!input) return;
                var iso = toIsoDate(input.value);
                if (!iso) return;
                if (input.type !== 'date') {
                    input.type = 'date';
                }
                input.value = iso;
            });
        });
    }
})();
</script>
</body>
</html>
