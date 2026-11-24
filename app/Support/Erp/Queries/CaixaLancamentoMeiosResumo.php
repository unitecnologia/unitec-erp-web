<?php

namespace App\Support\Erp\Queries;

use App\Models\ForcaVendasOrder;
use App\Models\OrdemServico;
use App\Models\PdvCaixaMovimento;
use App\Support\Erp\Financeiro\CaixaTransferencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resumo compacto de recebimentos por meio de pagamento no escopo da grade Caixa.
 * Não altera lançamentos. Saídas financeiras (manual/CP/TRF) entram só em “outras saídas”.
 */
final class CaixaLancamentoMeiosResumo
{
    public const LABEL_SEM_MEIO = 'SEM MEIO';

    /**
     * @return array{
     *     linhas: list<array{forma: string, recebido: float, estorno: float, liquido: float}>,
     *     total_recebido: float,
     *     total_estorno: float,
     *     total_liquido: float,
     *     outras_saidas: float,
     *     saldo_liquido: float,
     *     total_entradas: float,
     *     sem_meio: float,
     *     visivel: bool
     * }
     */
    public function build(Builder $scopedQuery): array
    {
        $cols = ['id', 'documento', 'entrada', 'saida', 'historico'];
        if (Schema::hasColumn((new \App\Models\CaixaLancamento)->getTable(), 'forma_pagamento_id')) {
            $cols[] = 'forma_pagamento_id';
        }

        $rows = (clone $scopedQuery)
            ->select($cols)
            ->orderBy('id')
            ->limit(5000)
            ->get();

        $totalEntradas = round((float) $rows->sum(fn ($r) => (float) ($r->entrada ?? 0)), 2);

        if ($rows->isEmpty()) {
            return $this->vazio($totalEntradas);
        }

        $formasPorId = $this->resolverFormasEmLote($rows);

        /** @var array<string, array{recebido: float, estorno: float}> $acc */
        $acc = [];
        $semMeio = 0.0;
        $outrasSaidas = 0.0;

        foreach ($rows as $row) {
            $entrada = round((float) ($row->entrada ?? 0), 2);
            $saida = round((float) ($row->saida ?? 0), 2);
            $documento = trim((string) ($row->documento ?? ''));
            $historico = trim((string) ($row->historico ?? ''));

            $ehEstorno = $this->ehEstornoRecebimento($historico, $saida);
            $ehTransferencia = CaixaTransferencia::isDocumentoTransferencia($documento);

            // Saídas que não são estorno já exibido: despesas, CP, TRF saída, manuais, etc.
            if ($saida > 0.009 && ! $ehEstorno) {
                $outrasSaidas = round($outrasSaidas + $saida, 2);
            }

            // Transferências não entram como recebimento por meio.
            if ($ehTransferencia) {
                continue;
            }

            $ehRecebimento = $entrada > 0.009 && $saida < 0.009;

            if (! $ehRecebimento && ! $ehEstorno) {
                continue;
            }

            $forma = $formasPorId[(int) $row->id] ?? null;
            $valor = $ehEstorno ? $saida : $entrada;

            if ($forma === null || $forma === '') {
                if ($ehRecebimento) {
                    $semMeio = round($semMeio + $valor, 2);
                    $acc[self::LABEL_SEM_MEIO]['recebido'] = round(
                        (float) ($acc[self::LABEL_SEM_MEIO]['recebido'] ?? 0) + $valor,
                        2
                    );
                    $acc[self::LABEL_SEM_MEIO]['estorno'] = (float) ($acc[self::LABEL_SEM_MEIO]['estorno'] ?? 0);
                } elseif ($ehEstorno) {
                    // Estorno sem forma resolvida: ainda conta no total de estornos via SEM MEIO.
                    $acc[self::LABEL_SEM_MEIO]['recebido'] = (float) ($acc[self::LABEL_SEM_MEIO]['recebido'] ?? 0);
                    $acc[self::LABEL_SEM_MEIO]['estorno'] = round(
                        (float) ($acc[self::LABEL_SEM_MEIO]['estorno'] ?? 0) + $valor,
                        2
                    );
                }

                continue;
            }

            if (! isset($acc[$forma])) {
                $acc[$forma] = ['recebido' => 0.0, 'estorno' => 0.0];
            }

            if ($ehEstorno) {
                $acc[$forma]['estorno'] = round($acc[$forma]['estorno'] + $valor, 2);
            } else {
                $acc[$forma]['recebido'] = round($acc[$forma]['recebido'] + $valor, 2);
            }
        }

        $linhas = [];
        $totalRecebido = 0.0;
        $totalEstorno = 0.0;

        $ordem = $this->ordenarFormas(array_keys($acc));
        foreach ($ordem as $forma) {
            $recebido = round((float) ($acc[$forma]['recebido'] ?? 0), 2);
            $estorno = round((float) ($acc[$forma]['estorno'] ?? 0), 2);
            if ($recebido < 0.005 && $estorno < 0.005) {
                continue;
            }
            $liquido = round($recebido - $estorno, 2);
            $linhas[] = [
                'forma' => $forma,
                'recebido' => $recebido,
                'estorno' => $estorno,
                'liquido' => $liquido,
            ];
            $totalRecebido = round($totalRecebido + $recebido, 2);
            $totalEstorno = round($totalEstorno + $estorno, 2);
        }

        $totalLiquido = round($totalRecebido - $totalEstorno, 2);

        return [
            'linhas' => $linhas,
            'total_recebido' => $totalRecebido,
            'total_estorno' => $totalEstorno,
            'total_liquido' => $totalLiquido,
            'outras_saidas' => $outrasSaidas,
            'saldo_liquido' => round($totalLiquido - $outrasSaidas, 2),
            'total_entradas' => $totalEntradas,
            'sem_meio' => $semMeio,
            'visivel' => $linhas !== [] || $totalEntradas > 0.009 || $outrasSaidas > 0.009,
        ];
    }

    /**
     * @return array{
     *     linhas: list<array{forma: string, recebido: float, estorno: float, liquido: float}>,
     *     total_recebido: float,
     *     total_estorno: float,
     *     total_liquido: float,
     *     outras_saidas: float,
     *     saldo_liquido: float,
     *     total_entradas: float,
     *     sem_meio: float,
     *     visivel: bool
     * }
     */
    private function vazio(float $totalEntradas): array
    {
        return [
            'linhas' => [],
            'total_recebido' => 0.0,
            'total_estorno' => 0.0,
            'total_liquido' => 0.0,
            'outras_saidas' => 0.0,
            'saldo_liquido' => 0.0,
            'total_entradas' => $totalEntradas,
            'sem_meio' => 0.0,
            'visivel' => $totalEntradas > 0.009,
        ];
    }

    private function ehEstornoRecebimento(string $historico, float $saida): bool
    {
        if ($saida < 0.009) {
            return false;
        }

        return (bool) preg_match('/^ESTORNO\b/iu', $historico);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, string|null> id => forma
     */
    private function resolverFormasEmLote(Collection $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = null;
        }

        $this->preencherViaColunaForma($rows, $out);
        $this->preencherViaForcaVendas($rows, $out);
        $this->preencherViaContaReceber($rows, $out);
        $this->preencherViaContaPagar($rows, $out);
        $this->preencherViaOs($rows, $out);
        $this->preencherViaPdv($rows, $out);

        return $out;
    }

    /**
     * Forma gravada no próprio lançamento (manual / futuros vínculos).
     *
     * @param  Collection<int, object>  $rows
     * @param  array<int, string|null>  $out
     */
    private function preencherViaColunaForma(Collection $rows, array &$out): void
    {
        $ids = [];
        $map = [];
        foreach ($rows as $row) {
            $lancId = (int) $row->id;
            if ($out[$lancId] !== null) {
                continue;
            }
            $formaId = (int) ($row->forma_pagamento_id ?? 0);
            if ($formaId <= 0) {
                continue;
            }
            $ids[] = $formaId;
            $map[$lancId] = $formaId;
        }

        if ($ids === [] || ! Schema::hasTable('formas_pagamento')) {
            return;
        }

        $descricoes = DB::table('formas_pagamento')
            ->whereIn('id', array_values(array_unique($ids)))
            ->pluck('descricao', 'id');

        foreach ($map as $lancId => $formaId) {
            $label = mb_strtoupper(trim((string) ($descricoes[$formaId] ?? '')), 'UTF-8');
            $out[$lancId] = $label !== '' ? $label : null;
        }
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string|null>  $out
     */
    private function preencherViaForcaVendas(Collection $rows, array &$out): void
    {
        if (! Schema::hasTable((new ForcaVendasOrder)->getTable())) {
            return;
        }

        $orderIds = [];
        $map = []; // lancId => [orderId, pIndex|null]
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($out[$id] !== null) {
                continue;
            }
            $doc = trim((string) ($row->documento ?? ''));
            if (preg_match('/^FV-(\d+)(?:\/P(\d+))?(?:\/\d+)?$/i', $doc, $m) !== 1) {
                continue;
            }
            $orderId = (int) $m[1];
            $pIndex = isset($m[2]) ? ((int) $m[2]) - 1 : null;
            $orderIds[] = $orderId;
            $map[$id] = [$orderId, $pIndex];
        }

        if ($orderIds === []) {
            return;
        }

        $orders = ForcaVendasOrder::query()
            ->whereIn('id', array_values(array_unique($orderIds)))
            ->get(['id', 'payload'])
            ->keyBy('id');

        foreach ($map as $lancId => [$orderId, $pIndex]) {
            $order = $orders->get($orderId);
            if (! $order) {
                continue;
            }
            $payload = is_array($order->payload)
                ? $order->payload
                : (json_decode((string) $order->payload, true) ?: []);
            $pags = is_array($payload['pagamentos'] ?? null) ? $payload['pagamentos'] : [];

            if ($pIndex !== null && isset($pags[$pIndex]) && is_array($pags[$pIndex])) {
                $out[$lancId] = $this->labelDePayload($pags[$pIndex]);

                continue;
            }

            if (count($pags) === 1 && is_array($pags[0])) {
                $out[$lancId] = $this->labelDePayload($pags[0]) ?: $this->labelDePayload($payload);

                continue;
            }

            $out[$lancId] = $this->labelDePayload($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function labelDePayload(array $payload): ?string
    {
        foreach (['forma_pagamento', 'descricao', 'forma'] as $key) {
            $label = mb_strtoupper(trim((string) ($payload[$key] ?? '')), 'UTF-8');
            if ($label !== '') {
                return $label;
            }
        }

        $id = (int) ($payload['forma_pagamento_id'] ?? $payload['id'] ?? 0);
        if ($id <= 0 || ! Schema::hasTable('formas_pagamento')) {
            return null;
        }

        $desc = DB::table('formas_pagamento')->where('id', $id)->value('descricao');
        $label = mb_strtoupper(trim((string) $desc), 'UTF-8');

        return $label !== '' ? $label : null;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string|null>  $out
     */
    private function preencherViaContaReceber(Collection $rows, array &$out): void
    {
        if (! Schema::hasTable('conta_receber_pagamentos') || ! Schema::hasTable('contas_receber')) {
            return;
        }

        $docs = [];
        $byDoc = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($out[$id] !== null) {
                continue;
            }
            $doc = trim((string) ($row->documento ?? ''));
            if ($doc === '' || str_starts_with(mb_strtoupper($doc, 'UTF-8'), 'FV-')) {
                continue;
            }
            $valor = round((float) ($row->entrada ?? 0) + (float) ($row->saida ?? 0), 2);
            $docs[] = $doc;
            $byDoc[$doc][] = ['id' => $id, 'valor' => $valor];
        }

        if ($docs === []) {
            return;
        }

        $prefix = DB::getTablePrefix();
        $pags = DB::table(DB::raw('`'.$prefix.'conta_receber_pagamentos` as `p`'))
            ->join(DB::raw('`'.$prefix.'contas_receber` as `cr`'), DB::raw('`cr`.`id`'), '=', DB::raw('`p`.`conta_receber_id`'))
            ->leftJoin(DB::raw('`'.$prefix.'formas_pagamento` as `f`'), DB::raw('`f`.`id`'), '=', DB::raw('`p`.`forma_pagamento_id`'))
            ->whereIn(DB::raw('`cr`.`documento`'), array_values(array_unique($docs)))
            ->get([
                DB::raw('`cr`.`documento` as documento'),
                DB::raw('`p`.`valor_recebido` as valor'),
                DB::raw('`f`.`descricao` as forma'),
            ]);

        foreach ($pags as $pag) {
            $doc = (string) $pag->documento;
            $forma = mb_strtoupper(trim((string) ($pag->forma ?? '')), 'UTF-8');
            if ($forma === '' || ! isset($byDoc[$doc])) {
                continue;
            }
            $valorPag = round((float) $pag->valor, 2);
            foreach ($byDoc[$doc] as $item) {
                if ($out[$item['id']] !== null) {
                    continue;
                }
                if (abs($item['valor'] - $valorPag) < 0.015) {
                    $out[$item['id']] = $forma;
                }
            }
        }
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string|null>  $out
     */
    private function preencherViaContaPagar(Collection $rows, array &$out): void
    {
        // Pagamentos a pagar são saídas financeiras — não entram como recebimento.
        // Mantido vazio de propósito (não mapear CP como meio recebido).
        unset($rows, $out);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string|null>  $out
     */
    private function preencherViaOs(Collection $rows, array &$out): void
    {
        if (! Schema::hasTable((new OrdemServico)->getTable())) {
            return;
        }
        if (! Schema::hasColumn((new OrdemServico)->getTable(), 'faturamento_pagamentos')) {
            return;
        }

        $osNums = [];
        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($out[$id] !== null) {
                continue;
            }
            $doc = trim((string) ($row->documento ?? ''));
            if (preg_match('/^OS-?(\d+)/i', $doc, $m) !== 1) {
                continue;
            }
            $num = (int) $m[1];
            $osNums[] = $num;
            $valor = round((float) ($row->entrada ?? 0) + (float) ($row->saida ?? 0), 2);
            $map[$id] = ['num' => $num, 'valor' => $valor];
        }

        if ($osNums === []) {
            return;
        }

        $ordens = OrdemServico::query()
            ->whereIn('numero', array_map('strval', array_unique($osNums)))
            ->orWhereIn('id', array_unique($osNums))
            ->get(['id', 'numero', 'faturamento_pagamentos']);

        $byNum = [];
        foreach ($ordens as $os) {
            $digits = (int) preg_replace('/\D/', '', (string) $os->numero);
            $byNum[$digits] = $os;
            $byNum[(int) $os->id] = $os;
        }

        foreach ($map as $lancId => $info) {
            $os = $byNum[$info['num']] ?? null;
            if (! $os) {
                continue;
            }
            $snap = is_array($os->faturamento_pagamentos)
                ? $os->faturamento_pagamentos
                : (json_decode((string) $os->faturamento_pagamentos, true) ?: []);
            foreach ($snap as $linha) {
                if (! is_array($linha)) {
                    continue;
                }
                $forma = mb_strtoupper(trim((string) ($linha['forma'] ?? '')), 'UTF-8');
                $valor = round((float) ($linha['valor'] ?? 0), 2);
                if ($forma !== '' && abs($valor - $info['valor']) < 0.02) {
                    $out[$lancId] = $forma;
                    break;
                }
            }
        }
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string|null>  $out
     */
    private function preencherViaPdv(Collection $rows, array &$out): void
    {
        if (! Schema::hasTable((new PdvCaixaMovimento)->getTable())) {
            return;
        }

        $movIds = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($out[$id] !== null || $id >= 0) {
                continue;
            }
            $movIds[] = abs($id);
        }

        if ($movIds === []) {
            return;
        }

        $movs = PdvCaixaMovimento::query()
            ->whereIn('id', array_values(array_unique($movIds)))
            ->get(['id', 'forma_pagamento'])
            ->keyBy('id');

        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($out[$id] !== null || $id >= 0) {
                continue;
            }
            $mov = $movs->get(abs($id));
            if (! $mov) {
                continue;
            }
            $forma = mb_strtoupper(trim((string) ($mov->forma_pagamento ?? '')), 'UTF-8');
            $out[$id] = $forma !== '' ? $forma : null;
        }
    }

    /**
     * @param  list<string>  $formas
     * @return list<string>
     */
    private function ordenarFormas(array $formas): array
    {
        $prioridade = [
            'DINHEIRO' => 1,
            'PIX' => 2,
            'POS DEBITO' => 3,
            'CARTÃO DE DÉBITO' => 3,
            'CARTAO DE DEBITO' => 3,
            'POS CREDITO' => 4,
            'CARTÃO DE CRÉDITO' => 4,
            'CARTAO DE CREDITO' => 4,
            self::LABEL_SEM_MEIO => 90,
        ];

        usort($formas, function (string $a, string $b) use ($prioridade): int {
            $pa = $prioridade[$a] ?? 50;
            $pb = $prioridade[$b] ?? 50;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return $a <=> $b;
        });

        return $formas;
    }
}
