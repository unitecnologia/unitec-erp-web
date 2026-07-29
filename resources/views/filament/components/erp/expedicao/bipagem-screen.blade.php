@php

    use App\Models\Entrega;



    $entregas = $this->entregas();

    $titulos = $entregas->map(function (Entrega $e): string {

        $num = ltrim((string) ($e->venda?->numero ?? $e->numero), '0') ?: '0';

        $cliente = $e->cliente_nome ?? 'CONSUMIDOR';



        return "Pedido {$num} — {$cliente}";

    });

    $titulo = $titulos->join(' · ');

    $statusGeral = $entregas->every(fn (Entrega $e): bool => $e->estaCompleta()) ? 'Completo' : 'Pendente';



    $totPedida = 0.0;

    $totExpedida = 0.0;

    $totSaldo = 0.0;

    $linhasGrid = $this->linhasGrid();

    foreach ($linhasGrid as $linha) {
        if (($linha['tipo'] ?? 'item') !== 'item') {
            continue;
        }

        $item = $linha['item'];

        $pedida = (float) $item->quantidade_pedida;

        $expedida = (float) $item->quantidade_expedida;

        $totPedida += $pedida;

        $totExpedida += $expedida;

        $totSaldo += max(0, $pedida - $expedida);

    }

@endphp



<div class="erp-expedicao-root erp-expedicao-bipagem-root">

<div class="erp-nfe erp-expedicao erp-expedicao-bipagem">



    <div class="erp-expedicao__topbar">

        <span class="erp-expedicao__topbar-title">Expedição — Bipagem</span>

        <span class="erp-expedicao-bipagem__status erp-expedicao-bipagem__status--{{ strtolower($statusGeral) }}">{{ $statusGeral }}</span>

    </div>



    <div class="erp-expedicao-bipagem__header">

        <strong>{{ $titulo ?: 'Selecione pedidos no controle' }}</strong>

    </div>



    <fieldset class="erp-expedicao__consulta erp-expedicao-bipagem__scan">

        <legend>Leitura de produtos</legend>

        <div class="erp-expedicao__consulta-row">

            <div class="erp-expedicao__inputs">

                <label class="erp-expedicao__field erp-expedicao__field--scan">

                    <span>Código de barras</span>

                    <input

                        type="text"

                        wire:model="codigoBarras"

                        wire:keydown.enter.prevent="bipar"

                        class="erp-nfe__input erp-expedicao__scan-input"

                        autofocus

                        autocomplete="off"

                        placeholder="Bipe ou digite o código"

                    >

                </label>

                @if ($this->pedirQuantidade())

                    <label class="erp-expedicao__field erp-expedicao__field--qtd">

                        <span>Quantidade</span>

                        <input type="text" wire:model="quantidade" class="erp-nfe__input erp-expedicao__qtd-input">

                    </label>

                @endif

                <label class="erp-expedicao__field erp-expedicao__field--ordem">

                    <span>Ordenar</span>

                    <select
                        id="exp-bipagem-ordem"
                        wire:model.live="ordenacaoGrid"
                        class="erp-nfe__select erp-expedicao__ordem-select"
                        title="Ordenação da lista"
                    >
                        <option value="localizacao">Localização</option>
                        <option value="pedido">Pedido</option>
                        <option value="alfabetica">Ordem alfabética</option>
                        <option value="codigo">Código</option>
                        <option value="quantidade">Quantidade</option>
                    </select>

                </label>

            </div>

        </div>

    </fieldset>



    <div class="erp-expedicao-bipagem__grid-ctn">

        <div
            class="erp-expedicao-bipagem__grid-wrap"
            x-data
            @expedicao-bipagem-scroll-top.window="$el.scrollTop = 0"
        >

            <table class="erp-expedicao-bipagem__grid">

                <thead>

                    <tr>

                        <th class="erp-expedicao-bipagem__col-check">#</th>

                        <th class="erp-expedicao-bipagem__col-pedido">Nº Pedido</th>

                        <th class="erp-expedicao-bipagem__col-cod">Cód.</th>

                        <th class="erp-expedicao-bipagem__col-barras">Código de barras</th>

                        <th class="erp-expedicao-bipagem__col-desc">Descrição</th>

                        <th class="erp-expedicao-bipagem__col-local">Localização</th>

                        <th class="erp-expedicao-bipagem__col-num">Quantidade</th>

                        <th class="erp-expedicao-bipagem__col-num">Qtde Expedida</th>

                        <th class="erp-expedicao-bipagem__col-num">Saldo</th>

                    </tr>

                </thead>

                <tbody>

                    @foreach ($linhasGrid as $linha)

                        @if (($linha['tipo'] ?? 'item') === 'pedido_sep')

                            <tr wire:key="exp-ped-sep-{{ $linha['entrega']->id }}" class="erp-expedicao-bipagem__pedido-sep">

                                <td colspan="9"><strong>{{ $linha['label'] }}</strong></td>

                            </tr>

                        @else

                        @php

                            $entrega = $linha['entrega'];

                            $item = $linha['item'];

                            $pedida = (float) $item->quantidade_pedida;

                            $expedida = (float) $item->quantidade_expedida;

                            $saldo = max(0, $pedida - $expedida);

                            $rowClass = match (true) {

                                $expedida <= 0 => 'erp-expedicao-row--zero',

                                $saldo > 0 => 'erp-expedicao-row--partial',

                                default => 'erp-expedicao-row--complete',

                            };

                            if (in_array((string) $item->id, $this->itensSelecionados, true)) {

                                $rowClass .= ' erp-expedicao-bipagem-row--checked';

                            }

                            $numeroPed = ltrim((string) ($entrega->venda?->numero ?? $entrega->numero), '0') ?: '0';

                        @endphp

                            <tr wire:key="exp-item-{{ $item->id }}" class="{{ $rowClass }}">

                                <td class="erp-expedicao-bipagem__col-check">

                                    <input

                                        type="checkbox"

                                        class="erp-expedicao__check"

                                        value="{{ $item->id }}"

                                        wire:model.live="itensSelecionados"

                                    >

                                </td>

                                <td>{{ $numeroPed }}</td>

                                <td>{{ $item->codigo ?? '—' }}</td>

                                <td>{{ $item->codigo_barras ?? '—' }}</td>

                                <td>{{ $item->descricao }}</td>

                                <td class="erp-expedicao-bipagem__col-local">{{ filled($item->localizacao) ? $item->localizacao : ($item->product?->localizacao ?: '—') }}</td>

                                <td class="erp-expedicao__col-num">{{ number_format($pedida, 2, ',', '.') }}</td>

                                <td class="erp-expedicao__col-num">{{ number_format($expedida, 2, ',', '.') }}</td>

                                <td class="erp-expedicao__col-num">{{ number_format($saldo, 2, ',', '.') }}</td>

                            </tr>

                        @endif

                    @endforeach

                </tbody>

            </table>

        </div>

        <div class="erp-expedicao-bipagem__totals" aria-label="Totais da expedição">
            <table class="erp-expedicao-bipagem__grid erp-expedicao-bipagem__grid--totals">
                <tbody>
                    <tr>
                        <td class="erp-expedicao-bipagem__col-check"></td>
                        <td class="erp-expedicao-bipagem__col-pedido"></td>
                        <td class="erp-expedicao-bipagem__col-cod"></td>
                        <td class="erp-expedicao-bipagem__col-barras"></td>
                        <td class="erp-expedicao-bipagem__col-desc"><strong>Totais</strong></td>
                        <td class="erp-expedicao-bipagem__col-local"></td>
                        <td class="erp-expedicao__col-num erp-expedicao-bipagem__col-num"><strong>{{ number_format($totPedida, 2, ',', '.') }}</strong></td>
                        <td class="erp-expedicao__col-num erp-expedicao-bipagem__col-num"><strong>{{ number_format($totExpedida, 2, ',', '.') }}</strong></td>
                        <td class="erp-expedicao__col-num erp-expedicao-bipagem__col-num"><strong>{{ number_format($totSaldo, 2, ',', '.') }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>



</div>

</div>


