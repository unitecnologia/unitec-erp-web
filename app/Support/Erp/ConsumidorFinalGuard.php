<?php

namespace App\Support\Erp;

use App\Models\FormaPagamento;
use App\Models\Person;
use App\Support\Erp\Financeiro\FormaPagamentoDestino;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use DomainException;

/**
 * Proteção do cadastro padrão CONSUMIDOR FINAL (código CF / 000001).
 * Não usa o nome como critério exclusivo.
 */
final class ConsumidorFinalGuard
{
    public static function mensagemCadastroProtegido(): string
    {
        return 'O cadastro padrão CONSUMIDOR FINAL não pode ser alterado nem excluído.';
    }

    public static function mensagemPagamentoBloqueado(): string
    {
        return 'CONSUMIDOR FINAL não pode gerar Contas a Receber (boleto, cheque, crediário ou crédito do cliente). Use pagamento à vista, cartão ou PIX.';
    }

    public static function isCadastro(?Person $person): bool
    {
        return $person instanceof Person && $person->isCadastroConsumidorFinal();
    }

    public static function assertPodeAlterarCadastro(?Person $person): void
    {
        if (self::isCadastro($person)) {
            throw new DomainException(self::mensagemCadastroProtegido());
        }
    }

    public static function assertPodeExcluirCadastro(?Person $person): void
    {
        self::assertPodeAlterarCadastro($person);
    }

    /**
     * Bloqueia formas que geram dívida/Contas a Receber em aberto para o CF.
     * Libera quitados no ato (Dinheiro/PIX/caixa/depósito) e cartão (mesmo com CR do adquirente).
     *
     * @param  array{forma?: string, descricao?: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool|int|string}|FormaPagamento|null  $forma
     */
    public static function formaBloqueada(array|FormaPagamento|null $forma): bool
    {
        if ($forma === null) {
            return false;
        }

        $pagamento = self::normalizarPagamento($forma);

        // Cartão: quitado no ato via adquirente — não é dívida do CONSUMIDOR FINAL.
        if (PdvFinalizarPagamentosHelper::isFormaCartao($pagamento)) {
            return false;
        }

        $movimento = FormaPagamentoDestino::from($pagamento);

        if ($movimento === 'credito_cliente') {
            return true;
        }

        $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');

        if ($tipo === 'crediario' || $tipo === 'credito_cliente') {
            return true;
        }

        // Destino financeiro Contas a Receber (boleto, cheque, etc.).
        if (FormaPagamentoDestino::geraContasReceber($pagamento)) {
            return true;
        }

        // Carnê por tipo/nome mesmo em cadastro legado sem tipo_movimento.
        if (PdvFinalizarPagamentosHelper::precisaParcelasCarne($pagamento)) {
            return true;
        }

        return PdvFinalizarPagamentosHelper::isFormaCrediario((string) ($pagamento['forma'] ?? ''));
    }

    /**
     * @param  array{forma?: string, descricao?: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool|int|string}|FormaPagamento  $forma
     * @return array{forma: string, tipo: string, tipo_movimento: string, aparece_contas_receber?: bool|int|string}
     */
    private static function normalizarPagamento(array|FormaPagamento $forma): array
    {
        if ($forma instanceof FormaPagamento) {
            return [
                'forma' => (string) ($forma->descricao ?? ''),
                'tipo' => (string) ($forma->tipo ?? ''),
                'tipo_movimento' => (string) ($forma->tipo_movimento ?? ''),
                'aparece_contas_receber' => (bool) ($forma->aparece_contas_receber ?? false),
            ];
        }

        return [
            'forma' => (string) ($forma['descricao'] ?? $forma['forma'] ?? ''),
            'tipo' => (string) ($forma['tipo'] ?? ''),
            'tipo_movimento' => (string) ($forma['tipo_movimento'] ?? ''),
            'aparece_contas_receber' => $forma['aparece_contas_receber'] ?? false,
        ];
    }

    /**
     * @param  array{forma?: string, descricao?: string, tipo?: string, tipo_movimento?: string}|FormaPagamento|null  $forma
     */
    public static function assertPagamentoPermitido(?Person $cliente, array|FormaPagamento|null $forma): void
    {
        if (! self::isCadastro($cliente)) {
            return;
        }

        if (self::formaBloqueada($forma)) {
            throw new DomainException(self::mensagemPagamentoBloqueado());
        }
    }

    /**
     * @param  list<array{forma?: string, descricao?: string, tipo?: string, tipo_movimento?: string}|FormaPagamento|null>  $formas
     */
    public static function assertPagamentosPermitidos(?Person $cliente, array $formas): void
    {
        if (! self::isCadastro($cliente)) {
            return;
        }

        foreach ($formas as $forma) {
            self::assertPagamentoPermitido($cliente, $forma);
        }
    }
}
