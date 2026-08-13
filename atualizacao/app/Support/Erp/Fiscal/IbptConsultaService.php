<?php

namespace App\Support\Erp\Fiscal;

use App\Models\Empresa;
use App\Models\FiscalIbptItem;
use App\Models\Product;

/**
 * Consulta tributos IBPT (API De Olho no Imposto) por produto ou serviço.
 */
final class IbptConsultaService
{
    /**
     * @return array{payload: array<string, mixed>, item: array<string, mixed>}
     */
    public function consultarProduto(Empresa $empresa, Product $product, ?float $valor = null): array
    {
        $client = IbptApiClient::forEmpresa($empresa);

        $payload = $client->produto([
            'codigo' => (string) ($product->ncm ?? ''),
            'uf' => (string) ($empresa->uf ?? ''),
            'ex' => 0,
            'descricao' => (string) ($product->descricao ?? $product->nome ?? 'Produto'),
            'unidade_medida' => (string) ($product->unidade ?? 'UN'),
            'valor' => $valor ?? $product->preco_venda ?? 0,
            'gtin' => (string) ($product->codigo_barras ?: 'SEM GTIN'),
            'codigo_interno' => (string) ($product->codigo ?? $product->id ?? ''),
        ]);

        return [
            'payload' => $payload,
            'item' => $client->toIbptItem($payload),
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, item: array<string, mixed>}
     */
    public function consultarServico(
        Empresa $empresa,
        string $codigoNbsOuLc116,
        string $descricao,
        string $unidadeMedida = 'UN',
        float|int|string $valor = 0,
    ): array {
        $client = IbptApiClient::forEmpresa($empresa);

        $payload = $client->servico([
            'codigo' => $codigoNbsOuLc116,
            'uf' => (string) ($empresa->uf ?? ''),
            'descricao' => $descricao,
            'unidade_medida' => $unidadeMedida,
            'valor' => $valor,
        ]);

        return [
            'payload' => $payload,
            'item' => $client->toIbptItem($payload),
        ];
    }

    /**
     * Grava/atualiza o item consultado em fiscal_ibpt_itens.
     *
     * @param  array<string, mixed>  $item
     */
    public function persistirItem(array $item): FiscalIbptItem
    {
        $ncm = (string) ($item['ncm'] ?? '');
        $ex = (string) ($item['ex_tipi'] ?? '0');
        $tipo = (string) ($item['tipo'] ?? '');
        $versao = (string) ($item['versao'] ?? '');

        return FiscalIbptItem::query()->updateOrCreate(
            [
                'ncm' => $ncm,
                'ex_tipi' => $ex === '' ? '0' : $ex,
                'tipo' => $tipo === '' ? null : $tipo,
                'versao' => $versao === '' ? null : $versao,
            ],
            [
                'descricao' => $item['descricao'] ?? null,
                'aliq_nacional' => $item['aliq_nacional'] ?? 0,
                'aliq_importado' => $item['aliq_importado'] ?? 0,
                'aliq_estadual' => $item['aliq_estadual'] ?? 0,
                'aliq_municipal' => $item['aliq_municipal'] ?? 0,
                'vigencia_inicio' => $item['vigencia_inicio'] ?? null,
                'vigencia_fim' => $item['vigencia_fim'] ?? null,
                'chave' => $item['chave'] ?? null,
                'fonte' => $item['fonte'] ?? null,
            ],
        );
    }
}
