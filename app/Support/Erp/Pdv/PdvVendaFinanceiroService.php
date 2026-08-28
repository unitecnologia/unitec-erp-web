<?php

namespace App\Support\Erp\Pdv;

use App\Models\ContaReceber;
use App\Models\PdvVenda;
use App\Models\Person;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\FormaPagamentoDestino;
use Carbon\Carbon;

final class PdvVendaFinanceiroService
{
    /** @deprecated Use Person::CODIGO_CONSUMIDOR_FINAL */
    public const CONSUMIDOR_FINAL_CODIGO = Person::CODIGO_CONSUMIDOR_FINAL;

    /**
     * @param  array<int, array{forma: string, valor: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool}>  $pagamentos
     * @param  list<int>|null  $tabelaPrazoDias  Dias de vencimento do crediário (ex.: [30, 60, 90]).
     * @param  array{nsu?: string, autorizacao?: string, maquininha?: string, bandeira?: string, dias?: list<int>}|null  $cartaoCanhoto
     * @param  list<string>|null  $chequeNumeros  Número do cheque por parcela (mesmo índice dos dias).
     */
    public function gerarContasReceber(
        PdvVenda $venda,
        ?int $personId,
        array $pagamentos,
        ?array $tabelaPrazoDias = null,
        ?array $cartaoCanhoto = null,
        ?array $chequeNumeros = null,
    ): void {
        $venda->loadMissing('sessao');
        $personId = $personId ?: $this->resolveConsumidorFinalClienteId();
        $empresaId = $venda->sessao?->empresa_id !== null
            ? (int) $venda->sessao->empresa_id
            : null;

        $numeroVenda = str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);
        $documentoBase = 'PDV-'.$numeroVenda;
        $hoje = Carbon::today();

        foreach ($pagamentos as $pagamento) {
            $forma = mb_strtoupper(trim($pagamento['forma'] ?? ''), 'UTF-8');
            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor <= 0) {
                continue;
            }

            if (
                ! FormaPagamentoDestino::geraContasReceber($pagamento)
                && ! PdvFinalizarPagamentosHelper::precisaParcelasCarne($pagamento)
            ) {
                continue;
            }

            $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');
            $isCartao = PdvFinalizarPagamentosHelper::isFormaCartao($pagamento);
            $isCheque = $tipo === 'cheque' || PdvFinalizarPagamentosHelper::isFormaCheque($forma);

            $contaForma = match (true) {
                $isCartao || in_array($tipo, ['cartao_credito', 'cartao_debito', 'tef'], true) => ContaReceber::FORMA_CARTAO,
                $isCheque => ContaReceber::FORMA_CHEQUE,
                $tipo === 'boleto' || str_contains($forma, 'BOLETO') => ContaReceber::FORMA_BOLETO,
                $tipo === 'crediario' || PdvFinalizarPagamentosHelper::isFormaCrediario($forma) => ContaReceber::FORMA_CARTEIRA,
                $tipo === 'pix' || str_contains($forma, 'PIX') => ContaReceber::FORMA_PIX,
                default => ContaReceber::FORMA_CARTEIRA,
            };

            $dias = match (true) {
                $isCartao => $this->normalizarDias($cartaoCanhoto['dias'] ?? null),
                PdvFinalizarPagamentosHelper::precisaParcelasCarne($pagamento) => $this->normalizarDias($tabelaPrazoDias),
                default => [30],
            };

            $canhoto = $isCartao ? [
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
                empresaId: $empresaId,
                canhoto: $canhoto,
                chequeNumeros: $isCheque ? ($chequeNumeros ?? []) : null,
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
     * @param  list<string>|null  $chequeNumeros
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
        ?int $empresaId = null,
        ?array $canhoto = null,
        ?array $chequeNumeros = null,
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

            $numeroCheque = $chequeNumeros !== null
                ? trim((string) ($chequeNumeros[$i] ?? ''))
                : '';

            if ($numeroCheque !== '') {
                $historico .= ' | CHQ '.$numeroCheque;
            }

            ContaReceber::query()->create([
                'empresa_id' => $empresaId,
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
                'numero_cheque' => $numeroCheque !== '' ? mb_substr($numeroCheque, 0, 40) : null,
            ]);
        }
    }

    private function resolveConsumidorFinalClienteId(): int
    {
        return (int) Person::resolveConsumidorFinal()->id;
    }

    public function estornarContasReceber(PdvVenda $venda): ?string
    {
        $bloqueio = $this->motivoBloqueioEstornoContasReceber($venda);

        if ($bloqueio !== null) {
            return $bloqueio;
        }

        $documento = $this->documentoContasReceberPdv($venda);

        ContaReceber::query()
            ->where(function ($q) use ($documento): void {
                $q->where('documento', $documento)
                    ->orWhere('documento', 'like', $documento.'/%');
            })
            ->delete();

        return null;
    }

    /**
     * Valida se o estorno financeiro pode ocorrer, sem apagar títulos.
     * Usar antes de cancelar NFC-e na SEFAZ.
     */
    public function motivoBloqueioEstornoContasReceber(PdvVenda $venda): ?string
    {
        $documento = $this->documentoContasReceberPdv($venda);

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

        return null;
    }

    private function documentoContasReceberPdv(PdvVenda $venda): string
    {
        $numeroVenda = str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);

        return 'PDV-'.$numeroVenda;
    }
}
