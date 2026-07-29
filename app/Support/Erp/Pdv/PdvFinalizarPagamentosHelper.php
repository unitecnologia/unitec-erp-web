<?php

namespace App\Support\Erp\Pdv;

use App\Support\Erp\ErpMoney;

final class PdvFinalizarPagamentosHelper
{
    public static function isFormaAPrazo(string $forma): bool
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');

        if (str_contains($forma, 'CHEQUE') || str_contains($forma, 'BOLETO')) {
            return true;
        }

        return self::isFormaCrediario($forma);
    }

    public static function isFormaCrediario(string $forma): bool
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');

        return str_contains($forma, 'CREDIÁRIO') || str_contains($forma, 'CREDIARIO');
    }

    /**
     * Cartão (crédito/débito/POS) marcado para Contas à Receber → conciliação via canhoto.
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool|int|string}  $pagamento
     */
    public static function isFormaCartaoContasReceber(array $pagamento): bool
    {
        if (! self::isFormaCartao($pagamento)) {
            return false;
        }

        $apareceCr = filter_var($pagamento['aparece_contas_receber'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $movimentoCr = strtolower(trim((string) ($pagamento['tipo_movimento'] ?? ''))) === 'contas_receber';

        return $apareceCr || $movimentoCr;
    }

    /**
     * Decide se o cartão vai para Contas a Receber (ainda não caiu na conta)
     * ou para o movimento de caixa.
     *
     * Regra:
     * - Forma com Contas a Receber / tipo_movimento CR → sempre Contas a Receber
     * - Parâmetro empresa "Lançar Cartão no Caixa" desmarcado → Contas a Receber
     * - Parâmetro marcado e forma sem CR → lança no caixa
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool|int|string}  $pagamento
     */
    public static function cartaoVaiParaContasReceber(array $pagamento, bool $lancarCartaoNoCaixa = true): bool
    {
        if (! self::isFormaCartao($pagamento)) {
            return false;
        }

        if (self::isFormaCartaoContasReceber($pagamento)) {
            return true;
        }

        return ! $lancarCartaoNoCaixa;
    }

    /**
     * @param  array{forma?: string, tipo?: string}  $pagamento
     */
    public static function isFormaCartao(array $pagamento): bool
    {
        $tipo = strtolower(trim((string) ($pagamento['tipo'] ?? '')));

        if (in_array($tipo, ['cartao_credito', 'cartao_debito', 'tef'], true)) {
            return true;
        }

        $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');

        return str_contains($forma, 'CARTÃO')
            || str_contains($forma, 'CARTAO')
            || str_contains($forma, 'POS ')
            || str_starts_with($forma, 'POS');
    }

    /**
     * Converte "30,60,90" numa lista de dias [30, 60, 90].
     *
     * @return list<int>
     */
    public static function diasDeString(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($d): string => trim((string) $d))
            ->filter(fn (string $d): bool => $d !== '' && is_numeric($d))
            ->map(fn (string $d): int => (int) $d)
            ->values()
            ->all();
    }

    /**
     * Ao escolher crediário/cheque/boleto, zera as demais formas e concentra o valor na linha escolhida.
     *
     * @param  array<int, array{forma: string, atalho: string, valor: string}>  $pagamentos
     * @return array<int, array{forma: string, atalho: string, valor: string}>
     */
    public static function aplicarFormaPrazoExclusiva(array $pagamentos, int $index, float $totalVenda): array
    {
        if (! isset($pagamentos[$index])) {
            return $pagamentos;
        }

        $total = max(0, round($totalVenda, 2));
        $valorLinha = ErpMoney::parseBr($pagamentos[$index]['valor'] ?? '0');
        $valorFinal = $valorLinha > 0 ? min($valorLinha, $total) : $total;

        foreach ($pagamentos as $i => $pagamento) {
            $pagamentos[$i]['valor'] = $i === $index
                ? ErpMoney::formatBr($valorFinal)
                : '0,00';
        }

        return $pagamentos;
    }
}
