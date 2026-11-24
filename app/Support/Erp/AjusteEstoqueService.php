<?php

namespace App\Support\Erp;

use App\Models\AjusteEstoque;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class AjusteEstoqueService
{
    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
    ) {}

    public function criar(
        int $productId,
        string $data,
        float $qtdInformada,
        string $modo = 'somar',
        ?int $estoqueId = null,
        ?string $observacao = null,
    ): AjusteEstoque {
        return DB::transaction(function () use ($productId, $data, $qtdInformada, $modo, $estoqueId, $observacao): AjusteEstoque {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            [$depositoId, $empresa] = $this->resolverDeposito($estoqueId);

            $atual = $this->saldos->fisico((int) $product->id, $depositoId);
            $qtdAjust = $modo === 'substituir'
                ? round($qtdInformada - $atual, 3)
                : round($qtdInformada, 3);

            if (abs($qtdAjust) < 0.0005) {
                throw new \DomainException(
                    $modo === 'substituir'
                        ? 'O estoque já está com o valor informado.'
                        : 'Informe a quantidade do ajuste.'
                );
            }

            $this->garantirEstoquePermitido($product, $qtdAjust, $depositoId);

            $modoNorm = $modo === 'substituir'
                ? AjusteEstoque::MODO_SUBSTITUIR
                : AjusteEstoque::MODO_SOMAR;

            $payload = [
                'data' => $data,
                'product_id' => $productId,
                'qtd_ajust' => $qtdAjust,
            ];
            if (ErpSchema::hasColumn((new AjusteEstoque)->getTable(), 'modo')) {
                $payload['modo'] = $modoNorm;
            }

            $ajuste = AjusteEstoque::query()->create($payload);

            $this->aplicarDelta(
                $productId,
                $qtdAjust,
                $depositoId,
                $empresa,
                (int) $ajuste->id,
                EstoqueMovimentacao::TIPO_AJUSTE,
                $observacao,
            );

            return $ajuste;
        });
    }

    public function atualizar(AjusteEstoque $ajuste, string $data, float $qtdAjust): AjusteEstoque
    {
        throw new \DomainException('Não é possível alterar um ajuste após a gravação. Exclua e lance um novo, se necessário.');
    }

    public function excluir(AjusteEstoque $ajuste): void
    {
        DB::transaction(function () use ($ajuste): void {
            $ajuste = AjusteEstoque::query()->whereKey($ajuste->getKey())->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($ajuste->product_id)->lockForUpdate()->first();

            if ($product) {
                [$estoqueId, $empresa] = $this->resolverDeposito();
                $qtdReversao = -1 * (float) $ajuste->qtd_ajust;
                $this->garantirEstoquePermitido($product, $qtdReversao, $estoqueId);
                $this->aplicarDelta(
                    (int) $product->id,
                    $qtdReversao,
                    $estoqueId,
                    $empresa,
                    (int) $ajuste->id,
                    EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
                );
            }

            $ajuste->delete();
        });
    }

    public function proximoCodigoExibicao(): int
    {
        return (int) (AjusteEstoque::query()->max('id') ?? 0) + 1;
    }

    private function aplicarDelta(
        int $productId,
        float $delta,
        ?int $estoqueId,
        ?Empresa $empresa,
        ?int $ajusteId = null,
        string $tipo = EstoqueMovimentacao::TIPO_AJUSTE,
        ?string $observacao = null,
    ): void {
        if (abs($delta) < 0.0005) {
            return;
        }

        $obs = $observacao !== null ? trim($observacao) : null;

        $ctx = EstoqueMovimentacaoContext::make(
            $tipo,
            empresaId: $empresa?->id !== null ? (int) $empresa->id : null,
            origemTipo: 'ajuste_estoque',
            origemId: $ajusteId,
            origemNumero: $ajusteId !== null ? (string) $ajusteId : null,
            observacao: $obs !== '' ? $obs : null,
        );

        $product = Product::query()->find($productId);
        $lotes = new ProductLoteService();
        $permitirNegativo = ! EstoqueNegativoPolicy::ativo($empresa);

        if ($delta > 0) {
            $this->saldos->incrementar($productId, $delta, $estoqueId, $empresa, $ctx);
            if ($product && $product->controla_lote_validade) {
                // Exclusão de ajuste de saída: restitui pelos lotes rastreados.
                // Ajuste positivo sem rastro: devolve a lote real existente (nunca cria fictício).
                if ($ajusteId && $lotes->origemTemRastro('ajuste_estoque', $ajusteId, (int) $product->id)) {
                    $lotes->estornarPorOrigem($product, 'ajuste_estoque', $ajusteId, $delta);
                } else {
                    $lotes->devolver($product, $delta);
                }
            }
        } else {
            $saida = abs($delta);
            if ($product && $product->controla_lote_validade) {
                $fatias = $lotes->consumirFefo($product, $saida, $permitirNegativo);
                if ($ajusteId) {
                    $lotes->registrarSaidas($product, $fatias, 'ajuste_estoque', $ajusteId);
                }
            }
            $this->saldos->decrementar($productId, $saida, $estoqueId, $empresa, $ctx);
        }
    }

    /**
     * @return array{0: ?int, 1: ?Empresa}
     */
    private function resolverDeposito(?int $estoqueIdOverride = null): array
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);
        $empresaId = $empresaId ? (int) $empresaId : null;
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        if ($estoqueIdOverride !== null && $estoqueIdOverride > 0) {
            $query = Estoque::query()
                ->whereKey($estoqueIdOverride)
                ->where('ativo', true);

            if ($empresaId) {
                $query->where('empresa_id', $empresaId);
            }

            $estoque = $query->first();

            if ($estoque === null) {
                throw new \DomainException('Depósito inválido para a empresa atual.');
            }

            return [(int) $estoque->id, $empresa];
        }

        $estoqueId = $this->saldos->estoqueIdParaEmpresa($empresaId);

        return [$estoqueId, $empresa];
    }

    private function garantirEstoquePermitido(Product $product, float $qtdAjust, ?int $estoqueId): void
    {
        if (! $this->bloquearEstoqueNegativo()) {
            return;
        }

        $atual = $this->saldos->fisico((int) $product->id, $estoqueId);

        if (($atual + $qtdAjust) < 0) {
            throw new \RuntimeException('Ajuste deixaria o estoque negativo.');
        }
    }

    private function bloquearEstoqueNegativo(): bool
    {
        return EstoqueNegativoPolicy::ativo();
    }
}
