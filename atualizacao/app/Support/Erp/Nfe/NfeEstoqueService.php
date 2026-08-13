<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Cfop;
use App\Models\Nfe;
use App\Models\Product;
use App\Support\Erp\EstoqueNegativoPolicy;
use App\Support\Erp\Pdv\PdvStockService;
use Illuminate\Support\Facades\Schema;

final class NfeEstoqueService
{
    public function __construct(
        private readonly PdvStockService $stock = new PdvStockService(),
    ) {}

    /**
     * Valida estoque ANTES da autorização na SEFAZ (quando baixa + bloqueio negativo ativos).
     *
     * @throws \RuntimeException
     */
    public function validarAntesDeTransmitir(Nfe $nfe, Empresa $empresa): void
    {
        if (! $this->parametroAtivo($empresa)) {
            return;
        }

        if (! $this->deveMovimentarSaida($nfe)) {
            return;
        }

        if (! $this->cfopMovimentaEstoque($nfe)) {
            return;
        }

        if ($this->jaBaixou($nfe)) {
            return;
        }

        if (! EstoqueNegativoPolicy::ativo($empresa)) {
            return;
        }

        $estoqueId = $this->resolveEstoqueId((int) $empresa->id);
        $nfe->loadMissing('itens');

        foreach ($nfe->itens as $item) {
            $productId = (int) ($item->product_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()->find($productId);

            if (! $product) {
                continue;
            }

            $qtd = (float) ($item->quantidade ?? 0);

            if ($qtd <= 0) {
                continue;
            }

            EstoqueNegativoPolicy::garantirSaidaPermitida($product, $qtd, $estoqueId, $empresa);
        }
    }

    /**
     * Baixa estoque após autorização da NF-e (saída), se o parâmetro estiver ativo.
     */
    public function baixarSeAplicavel(Nfe $nfe, Empresa $empresa): void
    {
        if (! $this->parametroAtivo($empresa)) {
            return;
        }

        if (! $this->deveMovimentarSaida($nfe)) {
            return;
        }

        if (! $this->cfopMovimentaEstoque($nfe)) {
            return;
        }

        if ($this->jaBaixou($nfe)) {
            return;
        }

        $estoqueId = $this->resolveEstoqueId((int) $empresa->id);
        $docSaida = 'NFE-'.ltrim((string) $nfe->numero, '0');

        $nfe->loadMissing('itens');

        foreach ($nfe->itens as $item) {
            $productId = (int) ($item->product_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()->find($productId);

            if (! $product) {
                continue;
            }

            $qtd = (float) ($item->quantidade ?? 0);

            if ($qtd <= 0) {
                continue;
            }

            $this->stock->baixaItemVenda(
                product: $product,
                quantidade: $qtd,
                docSaida: $docSaida,
                estoqueId: $estoqueId,
                empresa: $empresa,
            );
        }

        $this->marcarBaixado($nfe, true);
    }

    /**
     * Estorna estoque após cancelamento, somente se a NF-e havia baixado.
     */
    public function estornarSeAplicavel(Nfe $nfe, Empresa $empresa): void
    {
        if (! $this->jaBaixou($nfe)) {
            return;
        }

        $estoqueId = $this->resolveEstoqueId((int) $empresa->id);

        $nfe->loadMissing('itens');

        foreach ($nfe->itens as $item) {
            $productId = (int) ($item->product_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()->find($productId);

            if (! $product) {
                continue;
            }

            $qtd = (float) ($item->quantidade ?? 0);

            if ($qtd <= 0) {
                continue;
            }

            $this->stock->estornoItemVenda(
                product: $product,
                quantidade: $qtd,
                estoqueId: $estoqueId,
            );
        }

        $this->marcarBaixado($nfe, false);
    }

    private function parametroAtivo(Empresa $empresa): bool
    {
        if (! Schema::hasColumn('empresas', 'param_fiscal_nfe_baixa_estoque')) {
            return true;
        }

        return (bool) ($empresa->param_fiscal_nfe_baixa_estoque ?? true);
    }

    private function deveMovimentarSaida(Nfe $nfe): bool
    {
        // Entrada não baixa; pedido/venda/PDV já movimentaram estoque na origem.
        if ((string) ($nfe->movimento ?? '1') === '0') {
            return false;
        }

        if (filled($nfe->venda_id) || filled($nfe->pdv_venda_id)) {
            return false;
        }

        if (filled($nfe->devolucao_compra_id)) {
            return false;
        }

        // Notas antigas importadas de pedido (antes do vínculo venda_id).
        $obs = mb_strtoupper((string) ($nfe->obs_contribuinte ?? ''), 'UTF-8');

        if (
            filled($nfe->npedido)
            && (str_contains($obs, 'IMPORTADO DO PEDIDO') || str_contains($obs, 'IMPORTADO DOS PEDIDOS'))
        ) {
            return false;
        }

        if (str_contains($obs, 'IMPORTADO DA NFC-E') || str_contains($obs, 'IMPORTADO DA NFCE')) {
            return false;
        }

        return true;
    }

    /**
     * Uma NF-e só movimenta quando pelo menos um CFOP usado nos itens está
     * configurado com "Movimenta Estoque". Para cadastros fiscais legados
     * sem CFOP correspondente, mantém o comportamento histórico de baixar.
     */
    private function cfopMovimentaEstoque(Nfe $nfe): bool
    {
        $nfe->loadMissing('itens');

        $codigos = $nfe->itens
            ->pluck('cfop')
            ->filter(fn ($cfop): bool => filled($cfop))
            ->map(fn ($cfop): string => preg_replace('/\D/', '', (string) $cfop))
            ->filter()
            ->unique()
            ->values();

        if ($codigos->isEmpty()) {
            $cabecalho = preg_replace('/\D/', '', (string) ($nfe->cfop ?? ''));

            if ($cabecalho !== '') {
                $codigos->push($cabecalho);
            }
        }

        if ($codigos->isEmpty()) {
            return true;
        }

        $cfops = Cfop::query()
            ->whereIn('codigo', $codigos->all())
            ->pluck('movimenta_estoque', 'codigo');

        // Nota antiga ou CFOP ainda não cadastrado: não altera a regra anterior.
        if ($cfops->isEmpty()) {
            return true;
        }

        return $cfops->contains(fn ($movimenta): bool => (bool) $movimenta);
    }

    private function jaBaixou(Nfe $nfe): bool
    {
        if (! Schema::hasColumn('nfes', 'estoque_baixado')) {
            return false;
        }

        return (bool) ($nfe->estoque_baixado ?? false);
    }

    private function marcarBaixado(Nfe $nfe, bool $baixado): void
    {
        if (! Schema::hasColumn('nfes', 'estoque_baixado')) {
            return;
        }

        $nfe->update(['estoque_baixado' => $baixado]);
        $nfe->estoque_baixado = $baixado;
    }

    private function resolveEstoqueId(int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        $id = Estoque::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('codigo')
            ->value('id');

        return $id ? (int) $id : null;
    }
}
