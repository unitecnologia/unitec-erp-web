<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use App\Models\FormaPagamento;
use App\Models\PlanoConta;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Baixa de Contas a Pagar + saída no Livro Caixa quando a forma movimenta caixa.
 *
 * Falha ao lançar no Livro Caixa propaga a exceção (e a transaction da baixa
 * faz rollback) — não engole erro silenciosamente.
 */
final class ContaPagarBaixaService
{
    /**
     * Dinheiro/Pix (mesmo com movimento legado vazio) ou tipo_movimento = caixa.
     * Boleto, cartão, cheque e demais não geram saída no Livro Caixa.
     */
    public function formaMovimentaCaixa(FormaPagamento $forma): bool
    {
        $movimento = FormaPagamentoDestino::from($forma);

        if ($movimento === 'caixa') {
            return true;
        }

        if ($movimento !== 'nenhum') {
            return false;
        }

        $tipo = mb_strtolower(trim((string) ($forma->tipo ?? '')), 'UTF-8');
        $nome = mb_strtoupper(trim((string) ($forma->descricao ?? '')), 'UTF-8');

        return in_array($tipo, ['dinheiro', 'pix'], true)
            || str_contains($nome, 'PIX')
            || str_contains($nome, 'DINHEIRO')
            || str_contains($nome, 'ESPÉCIE')
            || str_contains($nome, 'ESPECIE');
    }

    public function formaExigeCheque(FormaPagamento $forma): bool
    {
        $tipo = mb_strtolower(trim((string) ($forma->tipo ?? '')), 'UTF-8');
        $nome = mb_strtoupper(trim((string) ($forma->descricao ?? '')), 'UTF-8');

        return $tipo === 'cheque' || str_contains($nome, 'CHEQUE');
    }

    /**
     * @return list<array{
     *     id: int,
     *     label: string,
     *     tipo: string|null,
     *     caixa_conta_id: int|null,
     *     caixa_label: string,
     *     movimenta_caixa: bool,
     *     exige_cheque: bool
     * }>
     */
    public function formasDisponiveis(): array
    {
        $formas = FormaPagamento::query()
            ->with('contaDestino:id,codigo,nome,ativo')
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('descricao')
            ->get(['id', 'codigo', 'descricao', 'tipo', 'tipo_movimento', 'conta_destino_id']);

        return $formas
            ->map(function (FormaPagamento $forma): array {
                $codigo = (int) ($forma->codigo ?? 0);
                $descricao = trim((string) ($forma->descricao ?? ''));
                $label = $codigo > 0
                    ? str_pad((string) $codigo, 2, '0', STR_PAD_LEFT).' — '.($descricao !== '' ? $descricao : 'Sem descrição')
                    : ($descricao !== '' ? $descricao : 'Forma #'.$forma->id);

                $movimenta = $this->formaMovimentaCaixa($forma);
                $caixa = $forma->contaDestino;
                $caixaAtivo = $caixa && (bool) $caixa->ativo;
                $caixaId = $movimenta && $caixaAtivo ? (int) $caixa->id : null;

                return [
                    'id' => (int) $forma->id,
                    'label' => $label,
                    'tipo' => $forma->tipo,
                    'caixa_conta_id' => $caixaId,
                    'caixa_label' => $movimenta
                        ? ($caixaAtivo ? $this->rotuloCaixa($caixa) : 'Sem conta de destino')
                        : 'Não movimenta o caixa',
                    'movimenta_caixa' => $movimenta,
                    'exige_cheque' => $this->formaExigeCheque($forma),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function planosDisponiveis(): array
    {
        return PlanoConta::query()
            ->where('ativo', true)
            ->where('dc', 'D')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao'])
            ->map(fn (PlanoConta $plano): array => [
                'id' => (int) $plano->id,
                'label' => trim((string) $plano->codigo).' — '.mb_strtoupper((string) $plano->descricao, 'UTF-8'),
            ])
            ->values()
            ->all();
    }

    /**
     * Baixa total do saldo de um título a pagar (compatível com fluxo antigo).
     *
     * @return array{ok: int, total: float}
     */
    public function baixarUma(int $contaId, int $formaPagamentoId, array $opcoes = []): array
    {
        return $this->baixarMuitas([$contaId], $formaPagamentoId, $opcoes);
    }

    /**
     * @param  list<int>  $contaIds
     * @param  array{
     *     plano_conta_id?: int|null,
     *     caixa_conta_id?: int|null,
     *     perc_juros?: float,
     *     juros?: float,
     *     perc_desconto?: float,
     *     desconto?: float,
     *     valor_pago?: float|null,
     *     pago_em?: string|null,
     *     numero_cheque?: string|null,
     *     grupo?: array{juros_editado?: bool, desconto_editado?: bool, valor_editado?: bool}
     * }  $opcoes
     * @return array{ok: int, total: float}
     */
    public function baixarMuitas(array $contaIds, int $formaPagamentoId, array $opcoes = []): array
    {
        $ids = collect($contaIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            throw new InvalidArgumentException('Nenhuma conta selecionada para pagar.');
        }

        $forma = FormaPagamento::query()
            ->whereKey($formaPagamentoId)
            ->where('ativo', true)
            ->first();

        if (! $forma) {
            throw new InvalidArgumentException('Meio de pagamento inválido ou inativo.');
        }

        $pagoEm = trim((string) ($opcoes['pago_em'] ?? ''));
        if ($pagoEm === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pagoEm)) {
            $pagoEm = ErpTimezone::toLocal()->toDateString();
        }

        $planoContaId = filled($opcoes['plano_conta_id'] ?? null) ? (int) $opcoes['plano_conta_id'] : null;
        if (! $planoContaId || ! PlanoConta::query()->whereKey($planoContaId)->where('ativo', true)->where('dc', 'D')->exists()) {
            throw new InvalidArgumentException('Selecione um plano de contas de débito.');
        }

        $movimentaCaixa = $this->formaMovimentaCaixa($forma);
        $caixaContaId = null;

        if ($movimentaCaixa) {
            $caixaContaId = (int) ($opcoes['caixa_conta_id'] ?? 0) ?: (int) ($forma->conta_destino_id ?? 0);
            if ($caixaContaId <= 0 || ! CaixaConta::query()->whereKey($caixaContaId)->where('ativo', true)->exists()) {
                throw new InvalidArgumentException('A forma de pagamento não possui conta de destino válida para o caixa.');
            }
        }

        $percJuros = round((float) ($opcoes['perc_juros'] ?? 0), 4);
        $jurosInformado = round((float) ($opcoes['juros'] ?? 0), 2);
        $percDesconto = round((float) ($opcoes['perc_desconto'] ?? 0), 4);
        $descontoInformado = round((float) ($opcoes['desconto'] ?? 0), 2);
        $valorPagoInformado = array_key_exists('valor_pago', $opcoes) && $opcoes['valor_pago'] !== null
            ? round((float) $opcoes['valor_pago'], 2)
            : null;
        $numeroCheque = trim((string) ($opcoes['numero_cheque'] ?? ''));
        $numeroCheque = $numeroCheque !== '' ? mb_substr($numeroCheque, 0, 40) : null;
        $grupo = is_array($opcoes['grupo'] ?? null) ? $opcoes['grupo'] : null;
        $unica = count($ids) === 1;

        $ok = 0;
        $total = 0.0;

        DB::transaction(function () use (
            $ids,
            $forma,
            $pagoEm,
            $planoContaId,
            $movimentaCaixa,
            $caixaContaId,
            $percJuros,
            $jurosInformado,
            $percDesconto,
            $descontoInformado,
            $valorPagoInformado,
            $numeroCheque,
            $grupo,
            $unica,
            &$ok,
            &$total,
        ): void {
            $contas = ContaPagar::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            $rateio = ! $unica
                ? $this->ratearGrupo(
                    $contas,
                    $jurosInformado,
                    $descontoInformado,
                    (float) ($valorPagoInformado ?? 0),
                    is_array($grupo) ? $grupo : [
                        'juros_editado' => $jurosInformado > 0 || $percJuros > 0,
                        'desconto_editado' => $descontoInformado > 0 || $percDesconto > 0,
                        'valor_editado' => $valorPagoInformado !== null,
                    ],
                )
                : null;

            foreach ($contas as $conta) {
                $item = is_array($rateio) ? ($rateio[(int) $conta->id] ?? null) : null;

                if (is_array($rateio)) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $juros = $item['juros'];
                    $desconto = $item['desconto'];
                    $percJ = $item['perc_juros'];
                    $percD = $item['perc_desconto'];
                    $valorAPagar = $item['valor_a_pagar'];
                    $valorPago = $item['valor_pago'];
                } else {
                    $saldo = round((float) $conta->saldo, 2);

                    if ($saldo <= 0) {
                        continue;
                    }

                    $juros = $jurosInformado;
                    $desconto = $descontoInformado;
                    $percJ = $percJuros;
                    $percD = $percDesconto;

                    if ($juros <= 0 && $percJ > 0) {
                        $juros = round($saldo * ($percJ / 100), 2);
                    }

                    $saldoComJuros = round($saldo + $juros, 2);

                    if ($desconto <= 0 && $percD > 0) {
                        $desconto = round($saldoComJuros * ($percD / 100), 2);
                    }

                    $desconto = min($desconto, $saldoComJuros);
                    $valorAPagar = round(max(0, $saldoComJuros - $desconto), 2);
                    $valorPago = $valorPagoInformado ?? $valorAPagar;

                    if ($valorPago <= 0) {
                        throw new InvalidArgumentException('Informe o valor pago.');
                    }

                    if ($valorPago > $valorAPagar + 0.009) {
                        throw new InvalidArgumentException('Valor pago maior que o valor a pagar.');
                    }
                }

                $pagamento = ContaPagarPagamento::query()->create([
                    'codigo_legado' => $this->nextCodigoLegado(),
                    'conta_pagar_id' => (int) $conta->id,
                    'data' => $pagoEm,
                    'valor_parcela' => round((float) $conta->valor, 2),
                    'perc_juros' => $percJ,
                    'juros' => $juros,
                    'perc_desconto' => $percD,
                    'desconto' => $desconto,
                    'valor_pago' => $valorPago,
                    'plano_conta_id' => $planoContaId,
                    'forma_pagamento_id' => (int) $forma->id,
                    'caixa_conta_id' => $caixaContaId,
                    'numero_cheque' => $numeroCheque,
                    'fornecedor_id' => $conta->fornecedor_id,
                ]);

                $conta->juros = round((float) $conta->juros + $juros, 2);
                $conta->desconto = round((float) $conta->desconto + $desconto, 2);
                $conta->valor_pago = round((float) $conta->valor_pago + $valorPago, 2);
                $conta->pago_em = $pagoEm;
                $conta->save();

                app(ComissaoPeriodoService::class)->syncStatusPagaFromContaPagar($conta->fresh() ?? $conta);

                if ($movimentaCaixa && $caixaContaId) {
                    $this->lancarSaidaCaixa(
                        valor: $valorPago,
                        data: $pagoEm,
                        documento: (string) ($conta->documento ?: $conta->numero ?: ('CP-'.$conta->id)),
                        historico: $this->historicoPagamento($conta, (int) $pagamento->id),
                        caixaContaId: $caixaContaId,
                        planoContaId: $planoContaId,
                    );
                }

                $ok++;
                $total += $valorPago;
            }
        });

        return [
            'ok' => $ok,
            'total' => round($total, 2),
        ];
    }

    public function historicoPagamento(ContaPagar $conta, int $pagamentoId): string
    {
        return $this->historicoComMarca(
            'Pagamento conta a pagar #'.($conta->numero ?: $conta->id),
            $pagamentoId,
        );
    }

    public function historicoEstorno(ContaPagar $conta, int $pagamentoId): string
    {
        return $this->historicoComMarca(
            'Estorno pagamento conta a pagar #'.($conta->numero ?: $conta->id),
            $pagamentoId,
        );
    }

    private function historicoComMarca(string $base, int $pagamentoId): string
    {
        $marca = ' baixa:'.$pagamentoId;
        $limite = 180 - mb_strlen($marca);

        if ($limite < 1) {
            return mb_substr($marca, -180);
        }

        return mb_substr($base, 0, $limite).$marca;
    }

    /**
     * Reparte juros, desconto e valor do formulário entre os títulos do mesmo fornecedor.
     * O que não foi editado permanece com a sugestão de cada título (juros e desconto zerados).
     *
     * @param  Collection<int, ContaPagar>  $contas
     * @param  array{juros_editado?: bool, desconto_editado?: bool, valor_editado?: bool}  $flags
     * @return array<int, array{juros: float, desconto: float, perc_juros: float, perc_desconto: float, valor_a_pagar: float, valor_pago: float}>
     */
    private function ratearGrupo(
        Collection $contas,
        float $jurosTotal,
        float $descontoTotal,
        float $valorTotal,
        array $flags,
    ): array {
        $linhas = [];

        foreach ($contas as $conta) {
            $saldo = round((float) $conta->saldo, 2);
            if ($saldo <= 0) {
                continue;
            }

            $linhas[] = [
                'id' => (int) $conta->id,
                'saldo' => $saldo,
                'juros' => 0.0,
            ];
        }

        if ($linhas === []) {
            return [];
        }

        if (! empty($flags['juros_editado'])) {
            $this->distribuirProporcional($linhas, 'juros', $jurosTotal, 'saldo');
        }

        foreach ($linhas as $i => $linha) {
            $base = round((float) $linha['saldo'] + (float) $linha['juros'], 2);
            $linhas[$i]['base'] = $base;
            $linhas[$i]['desconto'] = 0.0;
        }

        if (! empty($flags['desconto_editado'])) {
            $this->distribuirProporcional($linhas, 'desconto', $descontoTotal, 'base');
            foreach ($linhas as $i => $linha) {
                $linhas[$i]['desconto'] = round(min((float) $linha['desconto'], (float) $linha['base']), 2);
            }
        }

        $somaDevido = 0.0;
        foreach ($linhas as $i => $linha) {
            $devido = round(max(0, (float) $linha['base'] - (float) $linha['desconto']), 2);
            $linhas[$i]['devido'] = $devido;
            $somaDevido = round($somaDevido + $devido, 2);
        }

        if (! empty($flags['valor_editado'])) {
            if ($valorTotal <= 0) {
                throw new InvalidArgumentException('Informe o valor pago.');
            }

            if ($valorTotal > $somaDevido + 0.009) {
                throw new InvalidArgumentException('Valor pago maior que o valor a pagar.');
            }

            $this->distribuirProporcional($linhas, 'valor', $valorTotal, 'devido');
            foreach ($linhas as $i => $linha) {
                $linhas[$i]['valor'] = round(min((float) $linha['valor'], (float) $linha['devido']), 2);
            }

            $falta = round($valorTotal - array_sum(array_map(fn (array $linha): float => (float) $linha['valor'], $linhas)), 2);
            if ($falta > 0) {
                foreach ($linhas as $i => $linha) {
                    $espaco = round((float) $linha['devido'] - (float) $linha['valor'], 2);
                    if ($espaco <= 0) {
                        continue;
                    }

                    $add = min($espaco, $falta);
                    $linhas[$i]['valor'] = round((float) $linha['valor'] + $add, 2);
                    $falta = round($falta - $add, 2);
                    if ($falta <= 0) {
                        break;
                    }
                }
            }
        } else {
            foreach ($linhas as $i => $linha) {
                $linhas[$i]['valor'] = $linha['devido'];
            }
        }

        $saida = [];
        foreach ($linhas as $linha) {
            if ((float) $linha['valor'] <= 0) {
                continue;
            }

            $saida[(int) $linha['id']] = [
                'juros' => round((float) $linha['juros'], 2),
                'desconto' => round((float) $linha['desconto'], 2),
                'perc_juros' => (float) $linha['saldo'] > 0 ? round(((float) $linha['juros'] / (float) $linha['saldo']) * 100, 4) : 0.0,
                'perc_desconto' => (float) $linha['base'] > 0 ? round(((float) $linha['desconto'] / (float) $linha['base']) * 100, 4) : 0.0,
                'valor_a_pagar' => (float) $linha['devido'],
                'valor_pago' => round((float) $linha['valor'], 2),
            ];
        }

        if ($saida === []) {
            throw new InvalidArgumentException('Informe o valor pago.');
        }

        return $saida;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     */
    private function distribuirProporcional(array &$linhas, string $campo, float $total, string $peso): void
    {
        $total = round(max(0, $total), 2);

        foreach ($linhas as $i => $linha) {
            $linhas[$i][$campo] = 0.0;
        }

        $indices = [];
        $pesoTotal = 0.0;
        foreach ($linhas as $i => $linha) {
            $p = round((float) ($linha[$peso] ?? 0), 2);
            if ($p > 0) {
                $indices[] = $i;
                $pesoTotal = round($pesoTotal + $p, 2);
            }
        }

        if ($indices === [] || $total <= 0 || $pesoTotal <= 0) {
            return;
        }

        $acumulado = 0.0;
        $ultimo = $indices[array_key_last($indices)];
        foreach ($indices as $i) {
            if ($i === $ultimo) {
                $linhas[$i][$campo] = round(max(0, $total - $acumulado), 2);
            } else {
                $parte = round($total * ((float) $linhas[$i][$peso] / $pesoTotal), 2);
                $linhas[$i][$campo] = $parte;
                $acumulado = round($acumulado + $parte, 2);
            }
        }
    }

    private function rotuloCaixa(CaixaConta $caixa): string
    {
        $codigo = trim((string) ($caixa->codigo ?? ''));
        $nome = mb_strtoupper(trim((string) ($caixa->nome ?? '')), 'UTF-8');

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' — '.$nome;
        }

        return $nome !== '' ? $nome : ($codigo !== '' ? $codigo : '—');
    }

    private function nextCodigoLegado(): int
    {
        $max = (int) ContaPagarPagamento::query()->max('codigo_legado');

        return max($max + 1, 1);
    }

    private function lancarSaidaCaixa(
        float $valor,
        string $data,
        string $documento,
        string $historico,
        ?int $caixaContaId,
        ?int $planoContaId = null,
    ): void {
        if (! Schema::hasTable((new CaixaLancamento)->getTable()) || $valor <= 0) {
            return;
        }

        $planoNome = null;
        if ($planoContaId) {
            $planoNome = PlanoConta::query()->whereKey($planoContaId)->value('descricao');
        }

        $payload = [
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $data,
            'documento' => mb_substr($documento, 0, 40),
            'historico' => mb_substr($historico, 0, 180),
            'plano_contas' => $planoNome ? mb_substr(mb_strtoupper((string) $planoNome, 'UTF-8'), 0, 80) : null,
            'plano_conta_id' => $planoContaId,
            'caixa_conta_id' => $caixaContaId,
            'entrada' => 0,
            'saida' => $valor,
        ];

        if (Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id')) {
            $payload['empresa_id'] = ErpContext::currentEmpresaId();
        }

        CaixaLancamento::query()->create($payload);
    }
}
