<?php

namespace App\Support\Erp;

use App\Models\EstoqueReserva;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use Illuminate\Support\Facades\Schema;

/**
 * Compatibilidade Etapa 1: impede transferência com saldo de depósito incorreto.
 *
 * PDV/OS baixam só products.estoque (global). Compras/NF-e/FV/ajuste usam depósitos.
 * products.estoque é espelho multiempresa — NÃO representa estoque de uma empresa.
 *
 * Regras (sem recalcular histórico, sem alterar PDV/OS):
 * - Disponível = saldo explícito em product_estoque_saldos do depósito origem − reservas.
 * - NÃO usa o fallback legado que atribui o global inteiro ao depósito principal.
 * - Bloqueia o produto se sum(saldos de todos os depósitos) > products.estoque
 *   (depósitos superestimam o espelho global — tipicamente após baixas só-globais).
 */
final class EstoqueTransferenciaSaldoCompat
{
    private const EPS = 0.0005;

    public function __construct(
        private readonly ?EstoqueReservaService $reservas = null,
    ) {}

    public function tabelaSaldosDisponivel(): bool
    {
        return Schema::hasTable('product_estoque_saldos');
    }

    /**
     * Saldo físico explícito no depósito (0 se não houver linha).
     * Não inclui “não distribuído” / global legado.
     */
    public function saldoExplicitoDeposito(int $productId, int $estoqueId): float
    {
        if (! $this->tabelaSaldosDisponivel() || $productId <= 0 || $estoqueId <= 0) {
            return 0.0;
        }

        $qty = ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->where('estoque_id', $estoqueId)
            ->value('quantidade');

        return round((float) ($qty ?? 0), 3);
    }

    public function reservadoAtivo(int $productId, int $estoqueId): float
    {
        if ($productId <= 0 || $estoqueId <= 0 || ! Schema::hasTable('estoque_reservas')) {
            return 0.0;
        }

        if ($this->reservas !== null) {
            return round($this->reservas->reservadoAtivo($productId, $estoqueId), 3);
        }

        return round((float) EstoqueReserva::query()
            ->where('product_id', $productId)
            ->where('estoque_id', $estoqueId)
            ->where('status', EstoqueReserva::STATUS_ATIVA)
            ->sum('quantidade'), 3);
    }

    /**
     * Quantidade que pode sair do depósito origem na transferência.
     */
    public function disponivelParaTransferir(int $productId, int $estoqueOrigemId): float
    {
        $saldo = $this->saldoExplicitoDeposito($productId, $estoqueOrigemId);
        $reservado = $this->reservadoAtivo($productId, $estoqueOrigemId);

        return round(max(0.0, $saldo - $reservado), 3);
    }

    /**
     * Soma de todos os saldos de depósito do produto (todas as empresas).
     */
    public function somaSaldosDepositos(int $productId): float
    {
        if (! $this->tabelaSaldosDisponivel() || $productId <= 0) {
            return 0.0;
        }

        return round((float) ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->sum('quantidade'), 3);
    }

    public function estoqueGlobal(int $productId): float
    {
        return round((float) (Product::query()->whereKey($productId)->value('estoque') ?? 0), 3);
    }

    /**
     * true quando depósitos superestimam o espelho global (risco de transferir saldo fantasma).
     */
    public function depositosSuperestimamGlobal(int $productId): bool
    {
        if (! $this->tabelaSaldosDisponivel()) {
            return false;
        }

        return ($this->somaSaldosDepositos($productId) - $this->estoqueGlobal($productId)) > self::EPS;
    }

    /**
     * Valida o produto para transferência a partir do depósito origem.
     *
     * @throws EstoqueTransferenciaException
     */
    public function assertProdutoTransferivel(int $productId, int $estoqueOrigemId, float $quantidade): float
    {
        if (! $this->tabelaSaldosDisponivel()) {
            throw new EstoqueTransferenciaException(
                'Tabela de saldos por depósito indisponível. Transferência bloqueada.'
            );
        }

        if ($productId <= 0) {
            throw new EstoqueTransferenciaException('Produto inválido.');
        }

        if ($quantidade <= self::EPS) {
            throw new EstoqueTransferenciaException('Informe uma quantidade maior que zero.');
        }

        $product = Product::query()->whereKey($productId)->first(['id', 'codigo', 'descricao', 'estoque', 'ativo']);
        if ($product === null) {
            throw new EstoqueTransferenciaException('Produto não encontrado.');
        }

        $label = trim((string) ($product->descricao ?? $product->codigo ?? 'produto'));

        if ($this->depositosSuperestimamGlobal($productId)) {
            $soma = $this->somaSaldosDepositos($productId);
            $global = $this->estoqueGlobal($productId);
            throw new EstoqueTransferenciaException(
                'Transferência bloqueada para '.$label.': saldo dos depósitos ('
                .number_format($soma, 3, ',', '.')
                .') é maior que o estoque global do produto ('
                .number_format($global, 3, ',', '.')
                .'). Isso indica divergência (ex.: baixas do PDV/OS só no global). '
                .'Corrija a consistência antes de transferir este item. '
                .'Nenhum saldo histórico foi recalculado automaticamente.'
            );
        }

        $disponivel = $this->disponivelParaTransferir($productId, $estoqueOrigemId);
        if ($quantidade - $disponivel > self::EPS) {
            throw new EstoqueTransferenciaException(
                'Estoque insuficiente na origem para '.$label
                .' (disponível no depósito: '.number_format($disponivel, 3, ',', '.')
                .', solicitado: '.number_format($quantidade, 3, ',', '.')
                .'). Só entram saldos explícitos do depósito — estoque global não é usado como saldo da empresa.'
            );
        }

        return $disponivel;
    }
}
