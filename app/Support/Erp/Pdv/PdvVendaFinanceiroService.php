<?php

namespace App\Support\Erp\Pdv;

use App\Models\ContaReceber;
use App\Models\PdvVenda;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;

final class PdvVendaFinanceiroService
{
    /**
     * @param  array<int, array{forma: string, valor: string}>  $pagamentos
     */
    public function gerarContasReceber(PdvVenda $venda, ?int $personId, array $pagamentos): void
    {
        if (! $personId) {
            return;
        }

        $numeroVenda = str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);
        $documento = 'PDV-' . $numeroVenda;

        foreach ($pagamentos as $pagamento) {
            $forma = mb_strtoupper(trim($pagamento['forma'] ?? ''), 'UTF-8');
            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor <= 0) {
                continue;
            }

            $contaForma = match (true) {
                str_contains($forma, 'CHEQUE') => ContaReceber::FORMA_CHEQUE,
                str_contains($forma, 'BOLETO') => ContaReceber::FORMA_BOLETO,
                str_contains($forma, 'CREDI') => ContaReceber::FORMA_CARTEIRA,
                default => null,
            };

            if ($contaForma === null) {
                continue;
            }

            ContaReceber::query()->create([
                'numero' => ContaReceber::nextNumero(),
                'emissao' => Carbon::today(),
                'historico' => 'VENDA PDV #' . $numeroVenda . ' - ' . $forma,
                'documento' => $documento,
                'cliente_id' => $personId,
                'vencimento' => Carbon::today()->addDays(30),
                'valor' => $valor,
                'forma' => $contaForma,
            ]);
        }
    }

    public function estornarContasReceber(PdvVenda $venda): ?string
    {
        $numeroVenda = str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);
        $documento = 'PDV-' . $numeroVenda;

        $contas = ContaReceber::query()
            ->where('documento', $documento)
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
            ->where('documento', $documento)
            ->delete();

        return null;
    }
}
