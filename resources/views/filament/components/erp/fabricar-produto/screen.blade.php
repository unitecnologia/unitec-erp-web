@php
    $preview = is_array($this->preview ?? null) ? $this->preview : [];
    $ingredientes = is_array($preview['ingredientes'] ?? null) ? $preview['ingredientes'] : [];
    $bloqueiosFlat = is_array($preview['bloqueios'] ?? null) ? $preview['bloqueios'] : [];
    $pode = (bool) ($preview['pode_confirmar'] ?? false) && ! $this->confirmando;
    $hasSuggestions = $this->suggestionsOpen && $this->produtoSuggestions !== [];

    $formatBloqueio = static function (string $msg): string {
        $lower = mb_strtolower($msg, 'UTF-8');
        if (str_contains($lower, 'sem custo') || str_contains($lower, 'custo confiável') || str_contains($lower, 'custo:')) {
            return 'Sem custo cadastrado';
        }
        if (str_contains($lower, 'saldo insuficiente') || str_contains($lower, 'estoque insuficiente') || str_contains($lower, 'insuficiente')) {
            return 'Saldo insuficiente';
        }
        if (str_contains($lower, 'lote')) {
            return 'Lote/validade inválido';
        }
        if (str_contains($lower, 'inteira')) {
            return 'Quantidade deve ser inteira';
        }
        if (str_contains($lower, 'serviço') || str_contains($lower, 'servico')) {
            return 'Não pode ser serviço';
        }
        if (str_contains($lower, 'inativo')) {
            return 'Produto inativo';
        }
        if (str_contains($lower, 'imei')) {
            return 'Controle por IMEI não suportado';
        }
        if (str_contains($lower, 'grade')) {
            return 'Controle por grade não suportado';
        }

        return $msg;
    };

    $bloqueiosAgrupados = [];
    $msgsUsadas = [];
    foreach ($ingredientes as $row) {
        $msgs = array_values(array_filter(array_map('strval', $row['bloqueios'] ?? [])));
        if ($msgs === []) {
            continue;
        }
        $titulo = trim((string) ($row['descricao'] ?? ''));
        if ($titulo === '') {
            $titulo = trim((string) ($row['codigo'] ?? '')) ?: 'Ingrediente';
        }
        $motivos = [];
        foreach ($msgs as $msg) {
            $motivos[] = $formatBloqueio($msg);
            $msgsUsadas[$msg] = true;
        }
        $bloqueiosAgrupados[] = [
            'titulo' => $titulo,
            'motivos' => array_values(array_unique($motivos)),
        ];
    }

    $bloqueiosGerais = [];
    foreach ($bloqueiosFlat as $msg) {
        $msg = (string) $msg;
        if (isset($msgsUsadas[$msg])) {
            continue;
        }
        $bloqueiosGerais[] = $formatBloqueio($msg);
    }
    $bloqueiosGerais = array_values(array_unique($bloqueiosGerais));
@endphp

<div class="erp-fab" wire:ignore.self>
    <div class="erp-fab__toolbar">
        <div class="erp-fab__toolbar-row">
            <div class="erp-fab__field erp-fab__field--produto">
                <label for="erp-fab-produto">Produto a fabricar</label>
                <div class="erp-fab__lookup">
                    <input
                        id="erp-fab-produto"
                        type="text"
                        class="erp-pcad-form__input"
                        wire:model.live.debounce.200ms="produtoSearch"
                        wire:focus="openSuggestions"
                        wire:keydown.escape.prevent="closeSuggestions"
                        placeholder="Código ou descrição (somente com receita ativa)"
                        autocomplete="off"
                        data-erp-uppercase
                    >
                    @if ($hasSuggestions)
                        <ul class="erp-fab__suggest" role="listbox">
                            @foreach ($this->produtoSuggestions as $sug)
                                <li wire:key="fab-sug-{{ $sug['id'] }}">
                                    <button type="button" wire:click="selectProduto({{ $sug['id'] }})">
                                        <span class="erp-fab__suggest-code">{{ $sug['codigo'] ?: '—' }}</span>
                                        <span class="erp-fab__suggest-nome">{{ $sug['descricao'] ?? '—' }}</span>
                                        <span class="erp-fab__suggest-un">{{ $sug['unidade'] ?? '' }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="erp-fab__field erp-fab__field--qtd">
                <label for="erp-fab-qtd">Quantidade</label>
                <input
                    id="erp-fab-qtd"
                    type="text"
                    class="erp-pcad-form__input erp-fab__input-num"
                    wire:model.live.debounce.250ms="quantidade"
                    data-mask="decimal3"
                    inputmode="decimal"
                >
            </div>

            <div class="erp-fab__field erp-fab__field--deposito">
                <label>Depósito da empresa</label>
                <div class="erp-fab__readonly" title="Definido pela empresa ativa da sessão">
                    {{ $preview['estoque_label'] ?? '—' }}
                </div>
            </div>

            <div class="erp-fab__toolbar-actions">
                <button type="button" class="erp-fab__btn-ghost" wire:click="closeScreen" title="Fechar">
                    Fechar
                </button>
            </div>
        </div>
        @if ($preview !== [])
            <p class="erp-fab__hint">
                Rendimento {{ number_format((float) ($preview['rendimento'] ?? 0), 3, ',', '.') }}
                · Fator {{ number_format((float) ($preview['fator'] ?? 0), 4, ',', '.') }}
                · Consome ingredientes e entra o acabado neste depósito (sem fiscal/financeiro)
            </p>
        @endif
    </div>

    <section class="erp-fab__panel">
        <div class="erp-fab__section-head">
            <h2>Ingredientes necessários</h2>
        </div>

        <div class="erp-fab__table-wrap">
            <table class="erp-fab__table">
                <thead>
                    <tr>
                        <th class="erp-fab__col-codigo">Código</th>
                        <th class="erp-fab__col-desc">Descrição</th>
                        <th class="erp-fab__col-un">UN</th>
                        <th class="erp-fab__num">Qtd</th>
                        <th class="erp-fab__num">Saldo</th>
                        <th class="erp-fab__col-sit">Situação</th>
                        <th class="erp-fab__num">Custo</th>
                        <th class="erp-fab__num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ingredientes as $row)
                        @php $ok = (bool) ($row['disponivel'] ?? false); @endphp
                        <tr @class(['erp-fab__row--bad' => ! $ok])>
                            <td class="erp-fab__col-codigo">{{ $row['codigo'] ?? '—' }}</td>
                            <td class="erp-fab__col-desc" title="{{ $row['descricao'] ?? '' }}">{{ $row['descricao'] ?? '—' }}</td>
                            <td class="erp-fab__col-un">{{ $row['unidade'] ?? '—' }}</td>
                            <td class="erp-fab__num">{{ number_format((float) ($row['quantidade_necessaria'] ?? 0), 3, ',', '.') }}</td>
                            <td class="erp-fab__num">{{ number_format((float) ($row['saldo_disponivel'] ?? 0), 3, ',', '.') }}</td>
                            <td class="erp-fab__col-sit">
                                <span @class(['erp-fab__badge', 'erp-fab__badge--ok' => $ok, 'erp-fab__badge--bad' => ! $ok])>
                                    {{ $ok ? 'OK' : 'Bloqueado' }}
                                </span>
                            </td>
                            <td class="erp-fab__num">{{ number_format((float) ($row['custo_unitario'] ?? 0), 2, ',', '.') }}</td>
                            <td class="erp-fab__num">{{ number_format((float) ($row['custo_total'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="erp-fab__empty">
                                Selecione um produto com receita para ver os ingredientes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($bloqueiosAgrupados !== [] || $bloqueiosGerais !== [])
        <section class="erp-fab__alerts" role="alert">
            <strong class="erp-fab__alerts-title">Bloqueios</strong>
            <div class="erp-fab__alerts-grid">
                @foreach ($bloqueiosAgrupados as $grupo)
                    <div class="erp-fab__alert-item">
                        <div class="erp-fab__alert-name">{{ $grupo['titulo'] }}</div>
                        <ul>
                            @foreach ($grupo['motivos'] as $motivo)
                                <li>{{ $motivo }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
                @if ($bloqueiosGerais !== [])
                    <div class="erp-fab__alert-item">
                        <div class="erp-fab__alert-name">Geral</div>
                        <ul>
                            @foreach ($bloqueiosGerais as $motivo)
                                <li>{{ $motivo }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="erp-fab__resumo">
        <div class="erp-fab__resumo-item">
            <span class="erp-fab__label">Qtd. produzida</span>
            <strong>{{ number_format((float) ($preview['quantidade'] ?? 0), 3, ',', '.') }} {{ $preview['produto_unidade'] ?? '' }}</strong>
        </div>
        <div class="erp-fab__resumo-item">
            <span class="erp-fab__label">Custo total</span>
            <strong>R$ {{ number_format((float) ($preview['custo_total'] ?? 0), 2, ',', '.') }}</strong>
        </div>
        <div class="erp-fab__resumo-item">
            <span class="erp-fab__label">Custo unitário</span>
            <strong>R$ {{ number_format((float) ($preview['custo_unitario'] ?? 0), 2, ',', '.') }}</strong>
        </div>
        <div class="erp-fab__resumo-actions">
            <button
                type="button"
                class="erp-fab__btn-confirm"
                wire:click="confirmarFabricacao"
                wire:loading.attr="disabled"
                @disabled(! $pode)
            >
                <span wire:loading.remove wire:target="confirmarFabricacao">Confirmar Fabricação</span>
                <span wire:loading wire:target="confirmarFabricacao">Processando…</span>
            </button>
        </div>
    </section>
</div>
