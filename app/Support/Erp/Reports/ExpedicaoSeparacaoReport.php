<?php



namespace App\Support\Erp\Reports;



use App\Models\Entrega;

use App\Support\Erp\ProductLocalizacao;

use Illuminate\Support\Collection;



final class ExpedicaoSeparacaoReport

{

    /**

     * @return array<string, string>

     */

    public static function columnDefinitions(): array

    {

        return [

            'pedido' => 'Nº PEDIDO',

            'codigo' => 'CÓD.',

            'codigo_barras' => 'CÓD. BARRAS',

            'descricao' => 'DESCRIÇÃO',

            'localizacao' => 'LOCAL',

            'quantidade' => 'QTD.',

        ];

    }



    /**

     * @return list<string>

     */

    public static function defaultColumns(): array

    {

        return array_keys(self::columnDefinitions());

    }



    /**

     * @param  Collection<int, Entrega>  $entregas

     * @return list<array<string, mixed>>

     */

    public static function buildLinhas(Collection $entregas, string $ordenacao = 'localizacao'): array

    {

        $linhas = [];



        foreach ($entregas as $entrega) {

            $numeroPed = self::formatNumeroPedido($entrega);

            $cliente = mb_strtoupper($entrega->cliente_nome ?? 'CONSUMIDOR', 'UTF-8');



            foreach ($entrega->itens as $item) {

                $localizacao = ProductLocalizacao::resolveFromEntregaItem(

                    $item->localizacao,

                    $item->product?->localizacao,

                );



                $linhas[] = [

                    'tipo' => 'item',

                    'entrega_id' => $entrega->id,

                    'pedido' => $numeroPed,

                    'cliente' => $cliente,

                    'codigo' => (string) ($item->codigo ?? '—'),

                    'codigo_barras' => (string) ($item->codigo_barras ?? '—'),

                    'descricao' => (string) $item->descricao,

                    'localizacao' => mb_strtoupper($localizacao, 'UTF-8'),

                    'quantidade' => (float) $item->quantidade_pedida,

                ];

            }

        }



        self::sortLinhas($linhas, $ordenacao);



        if ($ordenacao === 'localizacao') {

            return self::injectCorredorSeparators($linhas);

        }



        if ($ordenacao === 'pedido') {

            return self::injectPedidoSeparators($linhas);

        }



        return $linhas;

    }



    /**

     * @param  list<array<string, mixed>>  $linhas

     * @return list<array<string, mixed>>

     */

    public static function injectCorredorSeparators(array $linhas): array

    {

        $result = [];

        $corredorAtual = null;



        foreach ($linhas as $linha) {

            if (($linha['tipo'] ?? 'item') !== 'item') {

                $result[] = $linha;



                continue;

            }



            $corredor = ProductLocalizacao::corredorFromLocalizacao($linha['localizacao'] ?? null);



            if ($corredor !== $corredorAtual) {

                $result[] = [

                    'tipo' => 'corredor_sep',

                    'corredor' => $corredor,

                    'label' => ProductLocalizacao::corredorLabel($linha['localizacao'] ?? null),

                ];

                $corredorAtual = $corredor;

            }



            $result[] = $linha;

        }



        return $result;

    }



    /**

     * @param  list<array<string, mixed>>  $linhas

     * @return list<array<string, mixed>>

     */

    public static function injectPedidoSeparators(array $linhas): array

    {

        $result = [];

        $entregaAtual = null;



        foreach ($linhas as $linha) {

            if (($linha['tipo'] ?? 'item') !== 'item') {

                $result[] = $linha;



                continue;

            }



            $entregaId = $linha['entrega_id'] ?? null;



            if ($entregaId !== $entregaAtual) {

                $result[] = [

                    'tipo' => 'pedido_sep',

                    'entrega_id' => $entregaId,

                    'label' => 'Pedido ' . ($linha['pedido'] ?? '—') . ' — ' . ($linha['cliente'] ?? 'CONSUMIDOR'),

                ];

                $entregaAtual = $entregaId;

            }



            $result[] = $linha;

        }



        return $result;

    }



    public static function isCorredorSeparatorRow(array $linha): bool

    {

        return ($linha['tipo'] ?? 'item') === 'corredor_sep';

    }



    public static function isPedidoSeparatorRow(array $linha): bool

    {

        return ($linha['tipo'] ?? 'item') === 'pedido_sep';

    }



    public static function isSeparatorRow(array $linha): bool

    {

        return self::isCorredorSeparatorRow($linha) || self::isPedidoSeparatorRow($linha);

    }



    /**

     * @param  list<array<string, mixed>>  $linhas

     */

    public static function sortLinhas(array &$linhas, string $ordenacao): void

    {

        usort($linhas, function (array $a, array $b) use ($ordenacao): int {

            return match ($ordenacao) {

                'localizacao' => ProductLocalizacao::compareForBipagemSort(

                    $a['localizacao'],

                    $b['localizacao'],

                    $a['descricao'],

                    $b['descricao'],

                    $a['codigo'],

                    $b['codigo'],

                    (float) $a['quantidade'],

                    (float) $b['quantidade'],

                ),

                'alfabetica' => self::compareAlfabetica($a, $b),

                'codigo' => self::compareCodigo($a, $b),

                'quantidade' => self::compareQuantidade($a, $b),

                'pedido' => self::comparePedido($a, $b),

                default => 0,

            };

        });

    }



    /**

     * @param  array<string, mixed>  $a

     * @param  array<string, mixed>  $b

     */

    private static function compareAlfabetica(array $a, array $b): int

    {

        $descCmp = strcasecmp((string) $a['descricao'], (string) $b['descricao']);



        if ($descCmp !== 0) {

            return $descCmp;

        }



        $codCmp = strcmp((string) $a['codigo'], (string) $b['codigo']);



        if ($codCmp !== 0) {

            return $codCmp;

        }



        return (float) $a['quantidade'] <=> (float) $b['quantidade'];

    }



    /**

     * @param  array<string, mixed>  $a

     * @param  array<string, mixed>  $b

     */

    private static function compareCodigo(array $a, array $b): int

    {

        $codCmp = strcmp((string) $a['codigo'], (string) $b['codigo']);



        if ($codCmp !== 0) {

            return $codCmp;

        }



        $descCmp = strcasecmp((string) $a['descricao'], (string) $b['descricao']);



        if ($descCmp !== 0) {

            return $descCmp;

        }



        return (float) $a['quantidade'] <=> (float) $b['quantidade'];

    }



    /**

     * @param  array<string, mixed>  $a

     * @param  array<string, mixed>  $b

     */

    private static function compareQuantidade(array $a, array $b): int

    {

        $qtdCmp = (float) $a['quantidade'] <=> (float) $b['quantidade'];



        if ($qtdCmp !== 0) {

            return $qtdCmp;

        }



        $descCmp = strcasecmp((string) $a['descricao'], (string) $b['descricao']);



        if ($descCmp !== 0) {

            return $descCmp;

        }



        return strcmp((string) $a['codigo'], (string) $b['codigo']);

    }



    /**

     * @param  array<string, mixed>  $a

     * @param  array<string, mixed>  $b

     */

    private static function comparePedido(array $a, array $b): int

    {

        $pedCmp = strcmp((string) ($a['pedido'] ?? ''), (string) ($b['pedido'] ?? ''));



        if ($pedCmp !== 0) {

            return $pedCmp;

        }



        $entregaCmp = ($a['entrega_id'] ?? 0) <=> ($b['entrega_id'] ?? 0);



        if ($entregaCmp !== 0) {

            return $entregaCmp;

        }



        return strcmp((string) ($a['codigo'] ?? ''), (string) ($b['codigo'] ?? ''));

    }



    /**

     * @param  Collection<int, Entrega>  $entregas

     */

    public static function pedidosSummary(Collection $entregas): string

    {

        if ($entregas->isEmpty()) {

            return '—';

        }



        return $entregas

            ->map(function (Entrega $entrega): string {

                $numero = self::formatNumeroPedido($entrega);

                $cliente = mb_strtoupper($entrega->cliente_nome ?? 'CONSUMIDOR', 'UTF-8');



                return "PED. {$numero} — {$cliente}";

            })

            ->join(' · ');

    }



    /**

     * @param  array<string, mixed>  $linha

     */

    public static function cellValue(array $linha, string $column): string

    {

        if (self::isSeparatorRow($linha)) {

            return '';

        }



        return match ($column) {

            'quantidade' => self::formatQuantidade((float) ($linha['quantidade'] ?? 0)),

            'descricao' => mb_strtoupper((string) ($linha['descricao'] ?? ''), 'UTF-8'),

            default => (string) ($linha[$column] ?? ''),

        };

    }



    public static function formatQuantidade(float $quantidade): string

    {

        return fmod($quantidade, 1.0) === 0.0

            ? number_format($quantidade, 0, ',', '.')

            : number_format($quantidade, 2, ',', '.');

    }



    public static function isNumericColumn(string $column): bool

    {

        return $column === 'quantidade';

    }



    /**

     * @param  list<array<string, mixed>>  $linhas

     * @param  list<string>  $columns

     * @return array<string, string>

     */

    public static function columnTotals(array $linhas, array $columns): array

    {

        $totals = array_fill_keys($columns, '');



        if ($columns === []) {

            return $totals;

        }



        $itemLinhas = array_values(array_filter(

            $linhas,

            fn (array $linha): bool => ! self::isSeparatorRow($linha),

        ));



        $totals[$columns[0]] = 'TOTAIS';



        if (in_array('quantidade', $columns, true)) {

            $totalQuantidade = array_sum(array_map(

                fn (array $linha): float => (float) ($linha['quantidade'] ?? 0),

                $itemLinhas,

            ));



            $totals['quantidade'] = self::formatQuantidade($totalQuantidade);

        }



        return $totals;

    }



    public static function formatNumeroPedido(Entrega $entrega): string

    {

        $numero = ltrim((string) ($entrega->venda?->numero ?? $entrega->numero), '0');



        return $numero !== '' ? $numero : '0';

    }

}


