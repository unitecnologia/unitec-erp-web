<?php

namespace App\Support\Erp\Compra;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\NotaFornecedor;
use App\Models\NotaFornecedorItem;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\NotaFornecedor\NotaFornecedorDanfeReportService;
use App\Support\Erp\NotaFornecedor\NotaFornecedorFornecedorCadastro;
use App\Support\Erp\NotaFornecedor\NotaFornecedorItensSyncService;
use App\Support\Erp\NotaFornecedor\NotaFornecedorXmlProdutoMatcher;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Gera Compra + itens a partir da nota de fornecedor (XML já vinculado),
 * deixa a compra ABERTA (estoque/financeiro só no Finalizar do lançamento)
 * e marca a nota como "Gerou Compras".
 */
final class GerarCompraFromNotaService
{
    public const OPERACAO = 'GERAR_COMPRA_XML';

    public function __construct(
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
    ) {}

    /**
     * @param  list<array<string, mixed>>  $itensVinculados  linhas do modal (product_id, qtd_total, prc_unitario…)
     * @param  (callable(array<int, array<string, mixed>>): array<int, array<string, mixed>>)|null  $materializarPendentes
     *
     * @throws DomainException
     */
    public function gerar(NotaFornecedor $nota, array $itensVinculados, ?callable $materializarPendentes = null): Compra
    {
        $empresaAtual = ErpContext::currentEmpresaId();
        if ($empresaAtual !== null && $nota->empresa_id !== null && (int) $nota->empresa_id !== (int) $empresaAtual) {
            throw new DomainException('Esta nota pertence a outra empresa.');
        }

        if ($nota->status === NotaFornecedor::STATUS_GEROU_COMPRAS) {
            $compraExistente = $nota->compra_id
                ? Compra::query()->find($nota->compra_id)
                : null;

            if ($compraExistente && $compraExistente->status !== Compra::STATUS_CANCELADA) {
                throw new DomainException('Esta nota já gerou compra. Abra o lançamento para finalizar.');
            }
        }

        if ($nota->status === NotaFornecedor::STATUS_DESCONHECIDA) {
            throw new DomainException('Nota desconhecida não pode gerar compra.');
        }

        if ($nota->status === NotaFornecedor::STATUS_PENDENTE) {
            throw new DomainException('Confirme a nota (F4) antes de gerar a compra.');
        }

        $naoVinculados = collect($itensVinculados)->filter(
            fn (array $row): bool => empty($row['vinculado'])
                || (empty($row['product_id']) && empty($row['cadastro_pendente']))
        )->count();

        if ($naoVinculados > 0) {
            throw new DomainException(
                "Ainda há {$naoVinculados} item(ns) sem vínculo de produto. Vincule todos antes de finalizar."
            );
        }

        (new NotaFornecedorItensSyncService())->sync($nota);
        $nota->refresh();

        $momento = ErpTimezone::toLocal();

        $compra = DB::transaction(function () use (
            $nota,
            $itensVinculados,
            $materializarPendentes,
            $momento,
        ): Compra {
            $notaTravada = NotaFornecedor::query()
                ->whereKey($nota->getKey())
                ->lockForUpdate()
                ->first();

            if (! $notaTravada) {
                throw new DomainException('Nota não encontrada.');
            }

            $empresaAtual = ErpContext::currentEmpresaId();
            if ($empresaAtual !== null && $notaTravada->empresa_id !== null && (int) $notaTravada->empresa_id !== (int) $empresaAtual) {
                throw new DomainException('Esta nota pertence a outra empresa.');
            }

            if ($notaTravada->compra_id) {
                $compraExistente = Compra::query()
                    ->whereKey($notaTravada->compra_id)
                    ->lockForUpdate()
                    ->first();

                if ($compraExistente && $compraExistente->status !== Compra::STATUS_CANCELADA) {
                    return $compraExistente;
                }
            }

            if ($notaTravada->status === NotaFornecedor::STATUS_DESCONHECIDA) {
                throw new DomainException('Nota desconhecida não pode gerar compra.');
            }

            if ($notaTravada->status === NotaFornecedor::STATUS_PENDENTE) {
                throw new DomainException('Confirme a nota (F4) antes de gerar a compra.');
            }

            $fornecedorId = $this->resolveFornecedorId($notaTravada);
            $fornecedor = $fornecedorId
                ? Person::query()->whereKey($fornecedorId)->first()
                : null;

            if ($materializarPendentes !== null) {
                $itensVinculados = $materializarPendentes($itensVinculados);
            }

            $itens = $this->normalizarItens($itensVinculados);

            if ($itens === []) {
                throw new DomainException('Nenhum item com quantidade válida para gerar a compra.');
            }

            $subtotal = round(array_sum(array_column($itens, 'total')), 2);
            $total = $this->totalCompraComoLancamento($notaTravada, $subtotal);
            $empresaCompra = $notaTravada->empresa_id ? (int) $notaTravada->empresa_id : null;
            if ($empresaCompra === null) {
                $atual = ErpContext::currentEmpresaId();
                if ($atual) {
                    $empresaCompra = (int) $atual;
                    $notaTravada->empresa_id = $empresaCompra;
                }
            }

            $compra = Compra::query()->create([
                'empresa_id' => $empresaCompra,
                'numero' => Compra::nextNumero(),
                'data_emissao' => $notaTravada->data_emissao?->toDateString()
                    ?? $momento->toDateString(),
                'data_entrada' => $notaTravada->data_entrada?->toDateString()
                    ?? $momento->toDateString(),
                'numero_nota' => $notaTravada->numero ? (string) $notaTravada->numero : null,
                'fornecedor_id' => $fornecedorId,
                'chave_nfe' => preg_replace('/\D/', '', (string) $notaTravada->chave) ?: null,
                'total' => $total,
                'status' => Compra::STATUS_ABERTA,
            ]);

            $productIds = array_values(array_unique(array_map(
                static fn (array $item): int => (int) $item['product_id'],
                $itens,
            )));
            $produtos = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

            if ($produtos->count() !== count($productIds)) {
                throw new DomainException('Há produto informado que não existe no cadastro.');
            }

            foreach ($itens as $item) {
                $notaItemId = $this->resolveNotaFornecedorItemId($nota, $item);
                $product = $produtos->get($item['product_id']);

                if (! $product instanceof Product) {
                    throw new DomainException('Há produto informado que não existe no cadastro.');
                }

                CompraItem::query()->create([
                    'compra_id' => $compra->id,
                    'product_id' => $item['product_id'],
                    'nota_fornecedor_item_id' => $notaItemId,
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['valor_unitario'],
                    'total' => $item['total'],
                ]);

                if ($notaItemId) {
                    $itemNota = ['product_id' => $item['product_id']];
                    $cfop = (string) ($item['cfop'] ?? '');
                    if (strlen($cfop) === 4) {
                        $itemNota['cfop'] = $cfop;
                    }

                    NotaFornecedorItem::query()
                        ->whereKey($notaItemId)
                        ->update($itemNota);
                }

                $this->aplicarCadastroDoItem($product, $item, $fornecedor);
            }

            $notaTravada->forceFill([
                'compra_id' => $compra->id,
                'status' => NotaFornecedor::STATUS_GEROU_COMPRAS,
            ])->save();

            return $compra;
        });

        if (! $compra->wasRecentlyCreated) {
            return $compra;
        }

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: 'Compra #'.$compra->numero.' gerada da NF '.$nota->numero.' (aberta — finalizar no lançamento).',
            origem: 'nota_fornecedor',
            documentoTipo: 'compra',
            documentoId: (int) $compra->id,
            documentoNumero: (string) $compra->numero,
            detalhes: [
                'nota_id' => $nota->id,
                'chave' => $nota->chave,
                'itens' => $compra->itens()->count(),
                'total' => (float) $compra->total,
            ],
            empresaId: $compra->empresa_id ? (int) $compra->empresa_id : null,
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
            // Qtd.Compra no lançamento = Qtd. Total do XML.
            $qtdTotal = BrDecimal::parse($row['qtd_total'] ?? 0, 3);
            $precoEmb = BrDecimal::parse($row['prc_unitario'] ?? 0, 4);
            $valorCheioInformado = BrDecimal::parse($row['valor_total'] ?? 0, 2);

            if ($qtdUnid <= 0) {
                $qtdUnid = 1.0;
            }

            if ($qtdTotal <= 0) {
                $qtdTotal = round(max(0.0, $qtdEmb) * $qtdUnid, 3);
            }

            if ($productId <= 0 || $qtdTotal <= 0) {
                continue;
            }

            // Valor cheio da nota (vProd / emb × prc). Preferência: valor_total do modal.
            if ($valorCheioInformado > 0) {
                $totalLinha = round($valorCheioInformado, 2);
            } elseif ($qtdEmb > 0) {
                $totalLinha = round($qtdEmb * $precoEmb, 2);
            } else {
                $totalLinha = round($qtdTotal * ($precoEmb / $qtdUnid), 2);
            }

            // vL. custo = valor cheio ÷ Qtd.Compra (qtd total).
            $vlCusto = round($totalLinha / $qtdTotal, 4);

            $out[] = [
                'product_id' => $productId,
                'quantidade' => $qtdTotal,
                'valor_unitario' => $vlCusto,
                'total' => $totalLinha,
                'grupo' => trim((string) ($row['grupo'] ?? '')),
                'ncm' => (string) ($row['ncm'] ?? ''),
                'cest' => (string) ($row['cest'] ?? ''),
                'descricao' => trim((string) ($row['produto_descricao'] ?? '')),
                'cfop' => preg_replace('/\D/', '', (string) ($row['cfop'] ?? '')) ?? '',
                'n_item' => isset($row['n_item']) ? (int) $row['n_item'] : null,
                'c_prod' => trim((string) ($row['codigo'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Mesma conta do lançamento de Compra: subtotal − desconto + frete + seguro + outras + IPI + ST.
     */
    private function totalCompraComoLancamento(NotaFornecedor $nota, float $subtotalItens): float
    {
        $xml = trim((string) $nota->xml);
        $totais = [];

        if ($xml !== '') {
            $parsed = (new NotaFornecedorDanfeReportService())->parseXml($xml);
            $totais = is_array($parsed['totais'] ?? null) ? $parsed['totais'] : [];
        }

        $money = static function (mixed $value): float {
            $text = trim((string) $value);
            if ($text === '' || $text === '—') {
                return 0.0;
            }

            return BrDecimal::parse($text, 2);
        };

        $subtotalXml = $money($totais['subtotal'] ?? $totais['total_produtos'] ?? null);
        $subtotal = $subtotalXml > 0 ? $subtotalXml : $subtotalItens;
        $desconto = $money($totais['desconto'] ?? null);
        $frete = $money($totais['frete'] ?? null);
        $seguro = $money($totais['seguro'] ?? null);
        $outras = $money($totais['outras'] ?? $totais['despesas'] ?? null);
        $ipi = $money($totais['total_ipi'] ?? null);
        $st = $money($totais['total_st'] ?? $totais['valor_icms_st'] ?? null);
        $total = round($subtotal - $desconto + $frete + $seguro + $outras + $ipi + $st, 2);

        return $total > 0 ? $total : round($subtotalItens, 2);
    }

    /**
     * Grava vínculo, NCM/CEST, descrição e grupo. Unidade e custo ficam no fluxo do lançamento.
     *
     * @param  array<string, mixed>  $item
     */
    private function aplicarCadastroDoItem(Product $product, array $item, ?Person $fornecedor): void
    {
        $updates = [];
        $ncm = preg_replace('/\D/', '', (string) ($item['ncm'] ?? '')) ?? '';
        $cest = preg_replace('/\D/', '', (string) ($item['cest'] ?? '')) ?? '';
            $descricao = trim((string) ($item['descricao'] ?? ''));
        $grupo = trim((string) ($item['grupo'] ?? ''));

        if (strlen($ncm) >= 8) {
            $ncm = substr($ncm, 0, 8);
            if ($ncm !== (string) $product->ncm) {
                $updates['ncm'] = $ncm;
            }
        }

        if (strlen($cest) >= 7) {
            $cest = substr($cest, 0, 7);
            if ($cest !== (string) $product->cest) {
                $updates['cest'] = $cest;
            }
        }

        if ($descricao !== '' && $descricao !== '—' && $descricao !== (string) $product->descricao) {
            $updates['descricao'] = $descricao;
        }

        if ($grupo !== '' && $grupo !== (string) $product->grupo) {
            $updates['grupo'] = $grupo;
        }

        if ($updates !== []) {
            $product->forceFill($updates)->save();
        }

        if ($fornecedor instanceof Person) {
            (new NotaFornecedorXmlProdutoMatcher())->vincularProduto(
                $product,
                $fornecedor,
                (string) ($item['c_prod'] ?? $product->codigo),
            );
        }
    }

    /**
     * @param  array{n_item?: int|null, c_prod?: string, product_id: int}  $item
     */
    private function resolveNotaFornecedorItemId(NotaFornecedor $nota, array $item): ?int
    {
        $nItem = (int) ($item['n_item'] ?? 0);

        if ($nItem > 0) {
            $id = NotaFornecedorItem::query()
                ->where('nota_fornecedor_id', $nota->id)
                ->where('n_item', $nItem)
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        $cProd = trim((string) ($item['c_prod'] ?? ''));

        if ($cProd !== '' && $cProd !== '—') {
            $id = NotaFornecedorItem::query()
                ->where('nota_fornecedor_id', $nota->id)
                ->where('c_prod', $cProd)
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    private function resolveFornecedorId(NotaFornecedor $nota): ?int
    {
        $cadastro = (new NotaFornecedorFornecedorCadastro())->ensure([
            'cnpj' => $nota->cnpj,
            'nome' => $nota->nome,
        ]);

        return $cadastro['person']?->id;
    }
}
