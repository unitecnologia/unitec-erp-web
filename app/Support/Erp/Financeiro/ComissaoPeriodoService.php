<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ComissaoPeriodo;
use App\Models\ComissaoPeriodoVenda;
use App\Models\ComissaoVendaAtiva;
use App\Models\ContaPagar;
use App\Models\Person;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\ComissaoVendedoresReport;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ComissaoPeriodoService
{
    /**
     * Calcula/atualiza rascunho Aberta para empresa + vendedor + período.
     */
    public function calcular(
        int $empresaId,
        int $vendedorId,
        Carbon $de,
        Carbon $ate,
        ?int $credorPersonId = null,
    ): ComissaoPeriodo {
        if ($empresaId <= 0 || $vendedorId <= 0) {
            throw new InvalidArgumentException('Empresa e vendedor são obrigatórios.');
        }

        if ($de->greaterThan($ate)) {
            [$de, $ate] = [$ate->copy(), $de->copy()];
        }

        $vendedor = Vendedor::query()->find($vendedorId);
        if (! $vendedor) {
            throw new InvalidArgumentException('Vendedor não encontrado.');
        }

        $calc = $this->calcularTotaisELinhas($vendedor, $de, $ate);

        return DB::transaction(function () use ($empresaId, $vendedorId, $de, $ate, $calc, $credorPersonId, $vendedor): ComissaoPeriodo {
            $existente = ComissaoPeriodo::query()
                ->where('empresa_id', $empresaId)
                ->where('vendedor_id', $vendedorId)
                ->whereDate('periodo_de', $de->toDateString())
                ->whereDate('periodo_ate', $ate->toDateString())
                ->where('status', ComissaoPeriodo::STATUS_ABERTA)
                ->lockForUpdate()
                ->first();

            $payload = [
                'empresa_id' => $empresaId,
                'vendedor_id' => $vendedorId,
                'periodo_de' => $de->toDateString(),
                'periodo_ate' => $ate->toDateString(),
                'status' => ComissaoPeriodo::STATUS_ABERTA,
                'base_avista' => $calc['base_avista'],
                'base_aprazo' => $calc['base_aprazo'],
                'comissao_avista' => $calc['comissao_avista'],
                'comissao_aprazo' => $calc['comissao_aprazo'],
                'comissao_total' => $calc['comissao_total'],
                'percentual_av' => (float) $vendedor->comissao_av,
                'percentual_ap' => (float) $vendedor->comissao_ap,
                'credor_person_id' => $credorPersonId ?: $this->resolverCredorPersonId($vendedor),
            ];

            if ($existente) {
                $existente->update($payload);

                return $existente->fresh() ?? $existente;
            }

            return ComissaoPeriodo::query()->create($payload);
        });
    }

    /**
     * Fecha comissão + snapshot + CAP na mesma transaction (idempotente + lock).
     */
    public function fechar(int $comissaoPeriodoId, ?int $credorPersonId = null): ComissaoPeriodo
    {
        try {
            return DB::transaction(function () use ($comissaoPeriodoId, $credorPersonId): ComissaoPeriodo {
                /** @var ComissaoPeriodo|null $periodo */
                $periodo = ComissaoPeriodo::query()
                    ->whereKey($comissaoPeriodoId)
                    ->lockForUpdate()
                    ->first();

                if (! $periodo) {
                    throw new InvalidArgumentException('Comissão não encontrada.');
                }

                // Idempotência: já fechada/paga com CAP → devolve sem criar de novo.
                if ($periodo->isFechadaOuPaga() && filled($periodo->conta_pagar_id)) {
                    return $periodo->fresh(['vendedor', 'contaPagar', 'fechadoPor', 'vendas']) ?? $periodo;
                }

                if ($periodo->status === ComissaoPeriodo::STATUS_CANCELADA) {
                    throw new InvalidArgumentException('Comissão cancelada não pode ser fechada.');
                }

                if ($periodo->status !== ComissaoPeriodo::STATUS_ABERTA) {
                    throw new InvalidArgumentException('Somente comissão aberta pode ser fechada.');
                }

                $vendedor = Vendedor::query()->whereKey($periodo->vendedor_id)->lockForUpdate()->first();
                if (! $vendedor) {
                    throw new InvalidArgumentException('Vendedor não encontrado.');
                }

                $de = Carbon::parse($periodo->periodo_de)->startOfDay();
                $ate = Carbon::parse($periodo->periodo_ate)->startOfDay();
                $calc = $this->calcularTotaisELinhas($vendedor, $de, $ate);

                if ($calc['linhas'] === []) {
                    throw new InvalidArgumentException('Não há vendas fechadas no período para comissionar.');
                }

                if ($calc['comissao_total'] <= 0) {
                    throw new InvalidArgumentException('Comissão total zerada — nada a fechar.');
                }

                $vendaIds = array_map(static fn (array $l): int => (int) $l['venda_id'], $calc['linhas']);
                sort($vendaIds);

                // Lock ordenado nas vendas envolvidas (evita deadlocks entre sessões).
                Venda::query()
                    ->whereIn('id', $vendaIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                // Lock + revalidação de conflitos (fechada/paga) sob concorrência.
                $conflitos = $this->buscarConflitosComLock(
                    (int) $periodo->empresa_id,
                    (int) $periodo->vendedor_id,
                    $vendaIds,
                );

                if ($conflitos !== []) {
                    throw new InvalidArgumentException($this->mensagemConflitos($conflitos));
                }

                $credorId = $credorPersonId
                    ?: (int) ($periodo->credor_person_id ?? 0)
                    ?: $this->resolverCredorPersonId($vendedor);

                if ($credorId <= 0) {
                    throw new InvalidArgumentException(
                        'Informe o credor (fornecedor/pessoa) para gerar a Conta a Pagar da comissão.'
                    );
                }

                $userId = (int) (Auth::id() ?? 0) ?: null;
                $agora = now();

                $periodo->fill([
                    'base_avista' => $calc['base_avista'],
                    'base_aprazo' => $calc['base_aprazo'],
                    'comissao_avista' => $calc['comissao_avista'],
                    'comissao_aprazo' => $calc['comissao_aprazo'],
                    'comissao_total' => $calc['comissao_total'],
                    'percentual_av' => (float) $vendedor->comissao_av,
                    'percentual_ap' => (float) $vendedor->comissao_ap,
                    'credor_person_id' => $credorId,
                    'status' => ComissaoPeriodo::STATUS_FECHADA,
                    'fechado_em' => $agora,
                    'fechado_por_user_id' => $userId,
                ]);
                $periodo->save();

                // Snapshot obrigatório
                foreach ($calc['linhas'] as $linha) {
                    ComissaoPeriodoVenda::query()->create([
                        'comissao_periodo_id' => (int) $periodo->id,
                        'venda_id' => (int) $linha['venda_id'],
                        'data' => $linha['data'],
                        'base' => $linha['base'],
                        'tipo' => $linha['tipo'],
                        'percentual' => $linha['percentual'],
                        'comissao' => $linha['comissao'],
                    ]);
                }

                // Reserva ativa (unique venda_id) — falha se outro fechamento ganhou a corrida.
                foreach ($vendaIds as $vendaId) {
                    ComissaoVendaAtiva::query()->create([
                        'empresa_id' => (int) $periodo->empresa_id,
                        'vendedor_id' => (int) $periodo->vendedor_id,
                        'venda_id' => $vendaId,
                        'comissao_periodo_id' => (int) $periodo->id,
                    ]);
                }

                $deFmt = $de->format('d/m/Y');
                $ateFmt = $ate->format('d/m/Y');
                $nomeVend = mb_strtoupper(trim((string) $vendedor->nome), 'UTF-8');

                $conta = ContaPagar::query()->create([
                    'empresa_id' => (int) $periodo->empresa_id,
                    'numero' => ContaPagar::nextNumero(),
                    'emissao' => ErpTimezone::toLocal()->toDateString(),
                    'documento' => 'COMISSAO '.$de->format('Ymd').'-'.$ate->format('Ymd'),
                    'produto' => 'COMISSAO '.$nomeVend.' '.$deFmt.' A '.$ateFmt,
                    'fornecedor_id' => $credorId,
                    'vencimento' => ErpTimezone::toLocal()->toDateString(),
                    'valor' => $calc['comissao_total'],
                    'desconto' => 0,
                    'juros' => 0,
                    'valor_pago' => 0,
                    'pago_em' => null,
                ]);

                $periodo->conta_pagar_id = (int) $conta->id;
                $periodo->save();

                return $periodo->fresh(['vendedor', 'contaPagar', 'fechadoPor', 'vendas', 'credor']) ?? $periodo;
            });
        } catch (QueryException $e) {
            if ($this->isUniqueVendaAtivaViolation($e)) {
                throw new InvalidArgumentException(
                    'Uma ou mais vendas já foram comissionadas em outro fechamento (concorrência). Recarregue e tente novamente.'
                );
            }

            throw $e;
        }
    }

    public function cancelar(int $comissaoPeriodoId, string $motivo = ''): ComissaoPeriodo
    {
        return DB::transaction(function () use ($comissaoPeriodoId, $motivo): ComissaoPeriodo {
            /** @var ComissaoPeriodo|null $periodo */
            $periodo = ComissaoPeriodo::query()->whereKey($comissaoPeriodoId)->lockForUpdate()->first();

            if (! $periodo) {
                throw new InvalidArgumentException('Comissão não encontrada.');
            }

            if ($periodo->status === ComissaoPeriodo::STATUS_CANCELADA) {
                return $periodo;
            }

            if ($periodo->status === ComissaoPeriodo::STATUS_PAGA) {
                throw new InvalidArgumentException(
                    'Comissão já paga. Estorne a Conta a Pagar manualmente e depois cancele com motivo.'
                );
            }

            if ($periodo->status === ComissaoPeriodo::STATUS_FECHADA && filled($periodo->conta_pagar_id)) {
                $conta = ContaPagar::query()->whereKey($periodo->conta_pagar_id)->lockForUpdate()->first();
                if ($conta) {
                    $saldo = (float) $conta->saldo;
                    $valor = (float) $conta->valor;
                    $pago = (float) $conta->valor_pago;
                    if ($pago > 0.0001 || $saldo + 0.0001 < $valor) {
                        throw new InvalidArgumentException(
                            'O CAP vinculado já possui pagamento parcial/total. Estorne no Contas a Pagar antes de cancelar.'
                        );
                    }
                    // Zera o título (mantém histórico do número; saldo 0 via valor=0 ou marca como quitado sem pagar)
                    $conta->valor = 0;
                    $conta->valor_pago = 0;
                    $conta->desconto = 0;
                    $conta->juros = 0;
                    $conta->pago_em = null;
                    $conta->produto = trim((string) $conta->produto.' [COMISSAO CANCELADA]');
                    $conta->save();
                }
            }

            ComissaoVendaAtiva::query()
                ->where('comissao_periodo_id', $periodo->id)
                ->delete();

            $periodo->update([
                'status' => ComissaoPeriodo::STATUS_CANCELADA,
                'cancelado_em' => now(),
                'cancelado_por_user_id' => (int) (Auth::id() ?? 0) ?: null,
                'motivo_cancelamento' => mb_strtoupper(trim($motivo), 'UTF-8') ?: 'CANCELADA',
            ]);

            return $periodo->fresh(['vendedor', 'contaPagar', 'fechadoPor', 'vendas']) ?? $periodo;
        });
    }

    public function syncStatusPagaFromContaPagar(ContaPagar $conta): void
    {
        $periodo = ComissaoPeriodo::query()
            ->where('conta_pagar_id', $conta->id)
            ->whereIn('status', [ComissaoPeriodo::STATUS_FECHADA, ComissaoPeriodo::STATUS_PAGA])
            ->first();

        if (! $periodo) {
            return;
        }

        $quitada = (float) $conta->saldo <= 0.0001 && (float) $conta->valor_pago > 0;

        if ($quitada && $periodo->status !== ComissaoPeriodo::STATUS_PAGA) {
            $periodo->update(['status' => ComissaoPeriodo::STATUS_PAGA]);
        } elseif (! $quitada && $periodo->status === ComissaoPeriodo::STATUS_PAGA) {
            $periodo->update(['status' => ComissaoPeriodo::STATUS_FECHADA]);
        }
    }

    /**
     * @return array{
     *   base_avista: float,
     *   base_aprazo: float,
     *   comissao_avista: float,
     *   comissao_aprazo: float,
     *   comissao_total: float,
     *   linhas: list<array{venda_id: int, data: string, base: float, tipo: string, percentual: float, comissao: float, comissao_exata: float}>
     * }
     */
    public function calcularTotaisELinhas(Vendedor $vendedor, Carbon $de, Carbon $ate): array
    {
        $pctAv = (float) ($vendedor->comissao_av ?? 0);
        $pctAp = (float) ($vendedor->comissao_ap ?? 0);

        $vendas = Venda::query()
            ->where('status', Venda::STATUS_FECHADO)
            ->where('vendedor_id', $vendedor->id)
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->orderBy('data')
            ->orderBy('id')
            ->get(['id', 'data', 'total', 'forma_pagamento']);

        $baseAv = 0.0;
        $baseAp = 0.0;
        /** @var list<array{venda_id: int, data: string, base: float, tipo: string, percentual: float, comissao_exata: float}> $linhas */
        $linhas = [];

        foreach ($vendas as $venda) {
            $base = round((float) $venda->total, 2);
            $aPrazo = ComissaoVendedoresReport::isAPrazo($venda->forma_pagamento);
            $pct = $aPrazo ? $pctAp : $pctAv;
            $tipo = $aPrazo ? 'ap' : 'av';

            if ($aPrazo) {
                $baseAp += $base;
            } else {
                $baseAv += $base;
            }

            $data = $venda->data instanceof Carbon
                ? $venda->data->toDateString()
                : substr((string) $venda->data, 0, 10);

            $linhas[] = [
                'venda_id' => (int) $venda->id,
                'data' => $data,
                'base' => $base,
                'tipo' => $tipo,
                'percentual' => $pct,
                'comissao_exata' => $base * $pct / 100,
            ];
        }

        $comissaoAv = round($baseAv * $pctAv / 100, 2);
        $comissaoAp = round($baseAp * $pctAp / 100, 2);
        $comissaoTotal = round($comissaoAv + $comissaoAp, 2);

        $this->aplicarRateioCentavos($linhas, 'av', $comissaoAv);
        $this->aplicarRateioCentavos($linhas, 'ap', $comissaoAp);

        return [
            'base_avista' => round($baseAv, 2),
            'base_aprazo' => round($baseAp, 2),
            'comissao_avista' => $comissaoAv,
            'comissao_aprazo' => $comissaoAp,
            'comissao_total' => $comissaoTotal,
            'linhas' => $linhas,
        ];
    }

    /**
     * @param  list<int>  $vendaIds
     * @return list<array{venda_id: int, numero: string|null, data: string|null, periodo_de: string, periodo_ate: string, comissao_periodo_id: int}>
     */
    private function buscarConflitosComLock(int $empresaId, int $vendedorId, array $vendaIds): array
    {
        if ($vendaIds === []) {
            return [];
        }

        // Lock nas reservas ativas existentes dessas vendas.
        ComissaoVendaAtiva::query()
            ->whereIn('venda_id', $vendaIds)
            ->lockForUpdate()
            ->get();

        $rows = DB::table('comissao_periodo_vendas as cv')
            ->join('comissao_periodos as cp', 'cp.id', '=', 'cv.comissao_periodo_id')
            ->leftJoin('vendas as v', 'v.id', '=', 'cv.venda_id')
            ->whereIn('cv.venda_id', $vendaIds)
            ->where('cp.empresa_id', $empresaId)
            ->where('cp.vendedor_id', $vendedorId)
            ->whereIn('cp.status', [ComissaoPeriodo::STATUS_FECHADA, ComissaoPeriodo::STATUS_PAGA])
            ->lockForUpdate()
            ->get([
                'cv.venda_id',
                'v.numero as venda_numero',
                'cv.data as venda_data',
                'cp.periodo_de',
                'cp.periodo_ate',
                'cp.id as comissao_periodo_id',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'venda_id' => (int) $r->venda_id,
                'numero' => $r->venda_numero,
                'data' => $r->venda_data ? substr((string) $r->venda_data, 0, 10) : null,
                'periodo_de' => substr((string) $r->periodo_de, 0, 10),
                'periodo_ate' => substr((string) $r->periodo_ate, 0, 10),
                'comissao_periodo_id' => (int) $r->comissao_periodo_id,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{venda_id: int, numero: string|null, data: string|null, periodo_de: string, periodo_ate: string, comissao_periodo_id: int}>  $conflitos
     */
    private function mensagemConflitos(array $conflitos): string
    {
        $parts = [];
        foreach (array_slice($conflitos, 0, 8) as $c) {
            $parts[] = sprintf(
                'venda %s (%s) já no período %s a %s',
                $c['numero'] ?: '#'.$c['venda_id'],
                $c['data'] ?: '—',
                Carbon::parse($c['periodo_de'])->format('d/m/Y'),
                Carbon::parse($c['periodo_ate'])->format('d/m/Y'),
            );
        }

        $extra = count($conflitos) > 8 ? ' (+'.(count($conflitos) - 8).')' : '';

        return 'Não é possível fechar: vendas já comissionadas. '.implode('; ', $parts).$extra;
    }

    private function resolverCredorPersonId(Vendedor $vendedor): int
    {
        $nome = mb_strtoupper(trim((string) $vendedor->nome), 'UTF-8');
        if ($nome === '') {
            return 0;
        }

        $existente = Person::query()
            ->where(function ($q) use ($nome): void {
                $q->where('nome_razao', $nome)
                    ->orWhere('apelido_fantasia', $nome);
            })
            ->where(function ($q): void {
                $q->where('is_fornecedor', true)->orWhere('is_funcionario', true);
            })
            ->orderByDesc('is_fornecedor')
            ->value('id');

        if ($existente) {
            $id = (int) $existente;
            Person::query()->whereKey($id)->update(['is_fornecedor' => true]);

            return $id;
        }

        $person = Person::query()->create([
            'codigo' => Person::nextCodigo(),
            'nome_razao' => $nome,
            'apelido_fantasia' => $nome,
            'is_fornecedor' => true,
            'is_funcionario' => true,
            'is_cliente' => false,
            'ativo' => true,
        ]);

        return (int) $person->id;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     */
    private function aplicarRateioCentavos(array &$linhas, string $tipo, float $alvoReais): void
    {
        $indices = [];
        foreach ($linhas as $i => $item) {
            if (($item['tipo'] ?? '') === $tipo) {
                $indices[] = $i;
            }
        }

        if ($indices === []) {
            return;
        }

        $alvoCent = (int) round($alvoReais * 100);
        $floors = [];
        $fracs = [];
        $somaFloor = 0;

        foreach ($indices as $i) {
            $exata = (float) $linhas[$i]['comissao_exata'];
            $cent = $exata * 100;
            $floor = (int) floor($cent + 1e-9);
            $floors[$i] = $floor;
            $fracs[$i] = $cent - $floor;
            $somaFloor += $floor;
        }

        $resto = $alvoCent - $somaFloor;
        uasort($fracs, static function (float $a, float $b): int {
            if (abs($a - $b) < 1e-12) {
                return 0;
            }

            return $a > $b ? -1 : 1;
        });

        $ordem = array_keys($fracs);
        $idx = 0;
        $n = count($ordem);
        while ($resto > 0 && $n > 0) {
            $floors[$ordem[$idx % $n]]++;
            $resto--;
            $idx++;
        }
        while ($resto < 0 && $n > 0) {
            $i = $ordem[$idx % $n];
            if ($floors[$i] > 0) {
                $floors[$i]--;
                $resto++;
            }
            $idx++;
            if ($idx > $n * 100) {
                break;
            }
        }

        foreach ($indices as $i) {
            $linhas[$i]['comissao'] = round($floors[$i] / 100, 2);
        }
    }

    private function isUniqueVendaAtivaViolation(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'comissao_venda_ativa_venda_uq')
            || (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'venda_id'));
    }
}
