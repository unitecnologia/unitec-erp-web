<?php

namespace App\Support\Erp\Pdv;

use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\FormaPagamentoDestino;

final class PdvFinalizarPagamentosHelper
{
    /**
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string}  $pagamento
     */
    public static function isFormaAPrazoPagamento(array $pagamento): bool
    {
        // Cartão em CR usa canhoto, não zera as demais formas.
        if (self::isFormaCartao($pagamento)) {
            return false;
        }

        if (FormaPagamentoDestino::geraContasReceber($pagamento)) {
            return true;
        }

        return self::isFormaAPrazo((string) ($pagamento['forma'] ?? ''));
    }

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

    public static function isFormaCheque(string $forma): bool
    {
        return str_contains(mb_strtoupper(trim($forma), 'UTF-8'), 'CHEQUE');
    }

    public static function isFormaBoleto(string $forma): bool
    {
        return str_contains(mb_strtoupper(trim($forma), 'UTF-8'), 'BOLETO');
    }

    /**
     * Crediário / cheque / boleto: abre a tela Contas Receber | Parcelas (carnê).
     * Cartão usa canhoto e não entra aqui.
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string}  $pagamento
     */
    public static function precisaParcelasCarne(array $pagamento): bool
    {
        if (self::isFormaCartao($pagamento)) {
            return false;
        }

        $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');
        $forma = (string) ($pagamento['forma'] ?? '');

        if (in_array($tipo, ['crediario', 'cheque', 'boleto'], true)) {
            return true;
        }

        return self::isFormaCrediario($forma)
            || self::isFormaCheque($forma)
            || self::isFormaBoleto($forma);
    }

    /**
     * Cartão (crédito/débito/POS) com tipo_movimento Contas à Receber.
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool|int|string}  $pagamento
     */
    public static function isFormaCartaoContasReceber(array $pagamento): bool
    {
        if (! self::isFormaCartao($pagamento)) {
            return false;
        }

        return FormaPagamentoDestino::geraContasReceber($pagamento);
    }

    /**
     * Decide se o cartão vai para Contas a Receber.
     * Fonte da verdade: tipo_movimento da forma (parametro empresa só se movimento vazio/legado).
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool|int|string}  $pagamento
     */
    public static function cartaoVaiParaContasReceber(array $pagamento, bool $lancarCartaoNoCaixa = true): bool
    {
        if (! self::isFormaCartao($pagamento)) {
            return false;
        }

        $movimento = FormaPagamentoDestino::from($pagamento);

        if ($movimento === 'contas_receber') {
            return true;
        }

        if ($movimento === 'caixa') {
            return false;
        }

        // Legado sem tipo_movimento: parâmetro empresa + flag aparece_contas_receber
        if ($movimento === 'nenhum') {
            $apareceCr = filter_var($pagamento['aparece_contas_receber'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($apareceCr) {
                return true;
            }

            return ! $lancarCartaoNoCaixa;
        }

        return false;
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
     * TEF integrado: fluxo próprio (não concluir venda só porque o restante zerou).
     *
     * @param  array{forma?: string, tipo?: string, usa_tef?: bool|int|string}  $pagamento
     */
    public static function isFormaTef(array $pagamento): bool
    {
        $tipo = strtolower(trim((string) ($pagamento['tipo'] ?? '')));

        if ($tipo === 'tef') {
            return true;
        }

        if (filter_var($pagamento['usa_tef'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');

        return str_contains($forma, 'TEF');
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
     * Prazo financeiro do carnê (max_parcelas + intervalo_parcelas).
     *
     * Com modo_prazo explícito:
     * - financeiro → max>=1 e intervalo>0 (inclui 1×30 intencional)
     * - tabela → nunca usa max/intervalo para auto-prazo
     *
     * Sem modo (legado / app antigo): mantém a heurística histórica
     * excluindo o default de instalação (1 × 30).
     */
    public static function isPrazoFinanceiroValido(
        int $maxParcelas,
        int $intervaloParcelas,
        ?string $modoPrazo = null,
    ): bool {
        $modo = mb_strtolower(trim((string) $modoPrazo), 'UTF-8');

        if ($modo === 'tabela') {
            return false;
        }

        if ($modo === 'financeiro') {
            return $maxParcelas >= 1 && $intervaloParcelas > 0;
        }

        // Legado sem modo_prazo (compatibilidade).
        if ($maxParcelas < 1 || $intervaloParcelas <= 0) {
            return false;
        }

        if ($maxParcelas === 1 && $intervaloParcelas === 30) {
            return false;
        }

        return true;
    }

    /**
     * Gera dias absolutos a partir do prazo financeiro: 1..max → intervalo*i.
     *
     * @return list<int>
     */
    public static function diasDePrazoFinanceiro(int $maxParcelas, int $intervaloParcelas): array
    {
        $max = max(1, $maxParcelas);
        $intervalo = max(0, $intervaloParcelas);
        $dias = [];

        for ($i = 1; $i <= $max; $i++) {
            $dias[] = $intervalo * $i;
        }

        return $dias;
    }

    /**
     * Prioridade do carnê (boleto/cheque/crediário):
     * 1) tabela fixa do cliente
     * 2) prazo financeiro da forma (se modo_prazo permitir)
     * 3) null → fallback UI atual (F2/F8/defaults)
     *
     * @param  list<int>|null  $diasTabelaCliente
     * @return list<int>|null
     */
    public static function resolverDiasCarnePrioridade(
        ?array $diasTabelaCliente,
        int $maxParcelas,
        int $intervaloParcelas,
        ?string $modoPrazo = null,
    ): ?array {
        $doCliente = collect($diasTabelaCliente ?? [])
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0)
            ->values()
            ->all();

        if ($doCliente !== []) {
            return $doCliente;
        }

        if (! self::isPrazoFinanceiroValido($maxParcelas, $intervaloParcelas, $modoPrazo)) {
            return null;
        }

        return self::diasDePrazoFinanceiro($maxParcelas, $intervaloParcelas);
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
