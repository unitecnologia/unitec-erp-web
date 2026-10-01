<?php

namespace App\Support\Erp;

use App\Models\AjusteEstoque;
use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class AjusteEstoqueService
{
    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
    ) {}

    public function criar(int $productId, string $data, float $qtdInformada, string $modo = 'somar'): AjusteEstoque
    {
        return DB::transaction(function () use ($productId, $data, $qtdInformada, $modo): AjusteEstoque {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            [$estoqueId, $empresa] = $this->resolverDeposito();

            $atual = $this->saldos->fisico((int) $product->id, $estoqueId);
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

            $this->garantirEstoquePermitido($product, $qtdAjust, $estoqueId);

            $ajuste = AjusteEstoque::query()->create([
                'data' => $data,
                'product_id' => $productId,
                'qtd_ajust' => $qtdAjust,
            ]);

            $this->aplicarDelta($productId, $qtdAjust, $estoqueId, $empresa, (int) $ajuste->id);

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
    ): void {
        if (abs($delta) < 0.0005) {
            return;
        }

        $ctx = EstoqueMovimentacaoContext::make(
            $tipo,
            empresaId: $empresa?->id !== null ? (int) $empresa->id : null,
            origemTipo: 'ajuste_estoque',
            origemId: $ajusteId,
            origemNumero: $ajusteId !== null ? (string) $ajusteId : null,
        );

        if ($delta > 0) {
            $this->saldos->incrementar($productId, $delta, $estoqueId, $empresa, $ctx);
        } else {
            $this->saldos->decrementar($productId, abs($delta), $estoqueId, $empresa, $ctx);
        }
    }

    /**
     * @return array{0: ?int, 1: ?Empresa}
     */
    private function resolverDeposito(): array
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);
        $empresaId = $empresaId ? (int) $empresaId : null;
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;
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
