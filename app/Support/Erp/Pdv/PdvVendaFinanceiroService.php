<?php

namespace App\Support\Erp\Pdv;

use App\Models\ContaReceber;
use App\Models\PdvVenda;
use App\Models\Person;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;

final class PdvVendaFinanceiroService
{
    /** @deprecated Use Person::CODIGO_CONSUMIDOR_FINAL */
    public const CONSUMIDOR_FINAL_CODIGO = Person::CODIGO_CONSUMIDOR_FINAL;

    /**
     * @param  array<int, array{forma: string, valor: string, tipo?: string, aparece_contas_receber?: bool}>  $pagamentos
     * @param  list<int>|null  $tabelaPrazoDias  Dias de vencimento do crediário (ex.: [30, 60, 90]).
     * @param  array{nsu?: string, autorizacao?: string, maquininha?: string, bandeira?: string, dias?: list<int>}|null  $cartaoCanhoto
     */
    public function gerarContasReceber(
        PdvVenda $venda,
        ?int $personId,
        array $pagamentos,
        ?array $tabelaPrazoDias = null,
        ?array $cartaoCanhoto = null,
    ): void {
        $personId = $personId ?: $this->resolveConsumidorFinalClienteId();

        $numeroVenda = str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);
        $documentoBase = 'PDV-'.$numeroVenda;
        $hoje = Carbon::today();

        foreach ($pagamentos as $pagamento) {
            $forma = mb_strtoupper(trim($pagamento['forma'] ?? ''), 'UTF-8');
            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor <= 0) {
                continue;
            }

            $isCartaoCr = PdvFinalizarPagamentosHelper::cartaoVaiParaContasReceber(
                $pagamento,
                PdvConfig::make()->lancarCartaoNoCaixa(),
            );

            $contaForma = match (true) {
                $isCartaoCr => ContaReceber::FORMA_CARTAO,
                str_contains($forma, 'CHEQUE') => ContaReceber::FORMA_CHEQUE,
                str_contains($forma, 'BOLETO') => ContaReceber::FORMA_BOLETO,
                PdvFinalizarPagamentosHelper::isFormaCrediario($forma) => ContaReceber::FORMA_CARTEIRA,
                default => null,
            };

            if ($contaForma === null) {
                continue;
            }

            $dias = match (true) {
                $isCartaoCr => $this->normalizarDias($cartaoCanhoto['dias'] ?? null),
                PdvFinalizarPagamentosHelper::isFormaCrediario($forma) => $this->normalizarDias($tabelaPrazoDias),
                default => [30],
            };

            $canhoto = $isCartaoCr ? [
                'nsu' => trim((string) ($cartaoCanhoto['nsu'] ?? '')),
                'autorizacao' => trim((string) ($cartaoCanhoto['autorizacao'] ?? '')),
                'maquininha' => trim((string) ($cartaoCanhoto['maquininha'] ?? '')),
                'bandeira' => trim((string) ($cartaoCanhoto['bandeira'] ?? '')),
            ] : null;

            $this->criarParcelas(
                personId: $personId,
                contaForma: $contaForma,
                formaLabel: $forma,
                total: $valor,
                dias: $dias,
                numeroVenda: $numeroVenda,
                documentoBase: $documentoBase,
                hoje: $hoje,
                canhoto: $canhoto,
            );
        }
    }

    /**
     * @param  list<int>|null  $dias
     * @return list<int>
     */
    private function normalizarDias(?array $dias): array
    {
        $normalizados = collect($dias ?? [])
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0)
            ->values()
            ->all();

        return $normalizados !== [] ? $normalizados : [30];
    }

    /**
     * @param  list<int>  $dias
     * @param  array{nsu: string, autorizacao: string, maquininha?: string, bandeira: string}|null  $canhoto
     */
    private function criarParcelas(
        int $personId,
        string $contaForma,
        string $formaLabel,
        float $total,
        array $dias,
        string $numeroVenda,
        string $documentoBase,
        Carbon $hoje,
        ?array $canhoto = null,
    ): void {
        $n = count($dias);
        $parcelaBase = floor($total / $n * 100) / 100;

        foreach (array_values($dias) as $i => $dia) {
            $valor = $i === $n - 1
                ? round($total - $parcelaBase * ($n - 1), 2)
                : $parcelaBase;

            $parcelaLabel = $n > 1 ? ($i + 1).'/'.$n : '1/1';
            $historico = 'VENDA PDV #'.$numeroVenda.' - '.$formaLabel
                .($n > 1 ? ' ('.$parcelaLabel.')' : '');

            if ($canhoto !== null) {
                $extra = array_filter([
                    ($canhoto['maquininha'] ?? '') !== '' ? 'POS '.$canhoto['maquininha'] : null,
                    ($canhoto['bandeira'] ?? '') !== '' ? 'BANDEIRA '.$canhoto['bandeira'] : null,
                    ($canhoto['nsu'] ?? '') !== '' ? 'NSU '.$canhoto['nsu'] : null,
                    ($canhoto['autorizacao'] ?? '') !== '' ? 'AUT '.$canhoto['autorizacao'] : null,
                ]);

                if ($extra !== []) {
                    $historico .= ' | '.implode(' ', $extra);
                }
            }

            ContaReceber::query()->create([
                'numero' => ContaReceber::nextNumero(),
                'emissao' => $hoje,
                'historico' => mb_substr($historico, 0, 500),
                'documento' => $n > 1 ? $documentoBase.'/'.($i + 1) : $documentoBase,
                'cliente_id' => $personId,
                'vencimento' => $hoje->copy()->addDays(max(0, $dia)),
                'valor' => $valor,
                'forma' => $contaForma,
                'cartao_nsu' => $canhoto['nsu'] ?? null,
                'cartao_autorizacao' => $canhoto['autorizacao'] ?? null,
                'cartao_maquininha' => $canhoto['maquininha'] ?? null,
                'cartao_bandeira' => $canhoto['bandeira'] ?? null,
                'cartao_parcela' => $canhoto !== null ? $parcelaLabel : null,
            ]);
        }
    }

    private function resolveConsumidorFinalClienteId(): int
    {
        $person = Person::query()
            ->whereIn('codigo', Person::codigosConsumidorFinal())
            ->orderByRaw('CASE WHEN codigo = ? THEN 0 ELSE 1 END', [Person::CODIGO_CONSUMIDOR_FINAL])
            ->orderBy('id')
            ->first();

        if ($person) {
            if (
                (string) $person->codigo === Person::CODIGO_CONSUMIDOR_FINAL_LEGADO
                && ! Person::query()->where('codigo', Person::CODIGO_CONSUMIDOR_FINAL)->exists()
            ) {
                $person->forceFill(['codigo' => Person::CODIGO_CONSUMIDOR_FINAL])->save();
            }

            return (int) $person->id;
        }

        $person = Person::query()->create([
            'codigo' => Person::CODIGO_CONSUMIDOR_FINAL,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CONSUMIDOR FINAL',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        return (int) $person->id;
    }

    public function estornarContasReceber(PdvVenda $venda): ?string
    {
        $numeroVenda = str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);
        $documento = 'PDV-'.$numeroVenda;

        $contas = ContaReceber::query()
            ->where(function ($q) use ($documento): void {
                $q->where('documento', $documento)
                    ->orWhere('documento', 'like', $documento.'/%');
            })
            ->get();

        if ($contas->isEmpty()) {
            return null;
        }

        foreach ($contas as $conta) {
            if ((float) $conta->valor_recebido > 0) {
                return 'Não é possível estornar: existem títulos a receber já baixados para esta venda.';
            }
        }

        ContaReceber::query()
            ->where(function ($q) use ($documento): void {
                $q->where('documento', $documento)
                    ->orWhere('documento', 'like', $documento.'/%');
            })
            ->delete();

        return null;
    }
}
