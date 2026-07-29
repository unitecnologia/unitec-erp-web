<?php

namespace App\Support\Erp\Compra;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Estoque;
use App\Models\NotaFornecedor;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\NotaFornecedor\NotaFornecedorFornecedorCadastro;
use App\Support\Erp\ProductEstoqueSaldoService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Gera Compra + itens a partir da nota de fornecedor (XML já vinculado)
 * e lança entrada de estoque. Só então marca a nota como "Gerou Compras".
 */
final class GerarCompraFromNotaService
{
    public const OPERACAO = 'GERAR_COMPRA_XML';

    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
    ) {}

    /**
     * @param  list<array<string, mixed>>  $itensVinculados  linhas do modal (product_id, qtd_total, prc_unitario…)
     *
     * @throws DomainException
     */
    public function gerar(NotaFornecedor $nota, array $itensVinculados): Compra
    {
        if ($nota->status === NotaFornecedor::STATUS_GEROU_COMPRAS) {
            $compraExistente = $nota->compra_id
                ? Compra::query()->find($nota->compra_id)
                : null;

            if ($compraExistente && $compraExistente->status !== Compra::STATUS_CANCELADA) {
                throw new DomainException('Esta nota já gerou compra e entrada de estoque.');
            }
        }

        if ($nota->status === NotaFornecedor::STATUS_DESCONHECIDA) {
            throw new DomainException('Nota desconhecida não pode gerar compra.');
        }

        if ($nota->status === NotaFornecedor::STATUS_PENDENTE) {
            throw new DomainException('Confirme a nota (F4) antes de gerar a compra.');
        }

        $naoVinculados = collect($itensVinculados)->filter(
            fn (array $row): bool => empty($row['vinculado']) || empty($row['product_id'])
        )->count();

        if ($naoVinculados > 0) {
            throw new DomainException(
                "Ainda há {$naoVinculados} item(ns) sem vínculo de produto. Vincule todos antes de finalizar."
            );
        }

        $itens = $this->normalizarItens($itensVinculados);

        if ($itens === []) {
            throw new DomainException('Nenhum item com quantidade válida para gerar a compra.');
        }

        $fornecedorId = $this->resolveFornecedorId($nota);
        $empresaId = $nota->empresa_id ? (int) $nota->empresa_id : null;
        $estoqueId = $this->resolveEstoqueId($empresaId);
        $total = round(array_sum(array_column($itens, 'total')), 2);
        $momento = ErpTimezone::toLocal();

        $compra = DB::transaction(function () use (
            $nota,
            $itens,
            $fornecedorId,
            $empresaId,
            $estoqueId,
            $total,
            $momento,
        ): Compra {
            $compra = Compra::query()->create([
                'empresa_id' => $empresaId,
                'numero' => Compra::nextNumero(),
                'data_emissao' => $nota->data_emissao?->toDateString()
                    ?? $momento->toDateString(),
                'data_entrada' => $nota->data_entrada?->toDateString()
                    ?? $momento->toDateString(),
                'numero_nota' => $nota->numero ? (string) $nota->numero : null,
                'fornecedor_id' => $fornecedorId,
                'chave_nfe' => preg_replace('/\D/', '', (string) $nota->chave) ?: null,
                'total' => $total,
                'status' => Compra::STATUS_FECHADA,
            ]);

            foreach ($itens as $item) {
                CompraItem::query()->create([
                    'compra_id' => $compra->id,
                    'product_id' => $item['product_id'],
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['valor_unitario'],
                    'total' => $item['total'],
                ]);

                $product = Product::query()->find($item['product_id']);

                if ($product && ! $product->is_servico) {
                    $this->saldos->incrementar(
                        (int) $product->id,
                        (float) $item['quantidade'],
                        $estoqueId,
                    );
                }
            }

            $nota->forceFill([
                'compra_id' => $compra->id,
                'status' => NotaFornecedor::STATUS_GEROU_COMPRAS,
            ])->save();

            return $compra;
        });

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: 'Compra #'.$compra->numero.' gerada da NF '.$nota->numero.' (entrada de estoque).',
            origem: 'nota_fornecedor',
            documentoTipo: 'compra',
            documentoId: (int) $compra->id,
            documentoNumero: (string) $compra->numero,
            detalhes: [
                'nota_id' => $nota->id,
                'chave' => $nota->chave,
                'itens' => count($itens),
                'total' => $total,
                'estoque_id' => $estoqueId,
            ],
            empresaId: $empresaId,
        );

        return $compra;
    }

    /**
     * @param  list<array<string, mixed>>  $itensVinculados
     * @return list<array{product_id: int, quantidade: float, valor_unitario: float, total: float}>
     */
    private function normalizarItens(array $itensVinculados): array
    {
        $out = [];

        foreach ($itensVinculados as $row) {
            if (empty($row['vinculado']) || empty($row['product_id'])) {
                continue;
            }

            $productId = (int) $row['product_id'];
            $qtdEmb = BrDecimal::parse($row['qtd_emb'] ?? 0, 3);
            $qtdUnid = BrDecimal::parse($row['qtd_unid'] ?? 1, 3);
            $qtdTotal = BrDecimal::parse($row['qtd_total'] ?? 0, 3);
            $precoEmb = BrDecimal::parse($row['prc_unitario'] ?? 0, 4);

            if ($qtdUnid <= 0) {
                $qtdUnid = 1.0;
            }

            if ($qtdTotal <= 0) {
                $qtdTotal = round(max(0.0, $qtdEmb) * $qtdUnid, 3);
            }

            if ($productId <= 0 || $qtdTotal <= 0) {
                continue;
            }

            // Total monetário = qtd comercial (emb) × preço unitário da nota.
            // Se emb não veio, cai para total = estoque × (preço / fator).
            if ($qtdEmb > 0) {
                $totalLinha = round($qtdEmb * $precoEmb, 2);
            } else {
                $totalLinha = round($qtdTotal * ($precoEmb / $qtdUnid), 2);
            }

            // Custo por unidade de estoque = total / quantidade de estoque.
            $valorUnitarioEstoque = round($totalLinha / $qtdTotal, 4);

            $out[] = [
                'product_id' => $productId,
                'quantidade' => $qtdTotal,
                'valor_unitario' => round($valorUnitarioEstoque, 2),
                'total' => $totalLinha,
            ];
        }

        return $out;
    }

    private function resolveFornecedorId(NotaFornecedor $nota): ?int
    {
        $cadastro = (new NotaFornecedorFornecedorCadastro())->ensure([
            'cnpj' => $nota->cnpj,
            'nome' => $nota->nome,
        ]);

        return $cadastro['person']?->id;
    }

    private function resolveEstoqueId(?int $empresaId): ?int
    {
        if (! $empresaId) {
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
