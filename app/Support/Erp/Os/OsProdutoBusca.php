<?php

namespace App\Support\Erp\Os;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductSerial;
use Illuminate\Database\Eloquent\Builder;

/**
 * Busca de peça/serviço da OS.
 * Não usa só LIKE + ORDER BY descricao: o ranking prioriza nome e código exatos.
 *
 * 100 descrição igual ao termo
 *  90 código, EAN, IMEI ou série exatos
 *  80 descrição começa com o termo
 *  70 alguma palavra da descrição começa com o termo
 *  50 contém o termo (meio da frase, código parcial, IMEI/série parciais)
 */
class OsProdutoBusca
{
    public static function aplicar(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            $query->orderBy('descricao');

            return;
        }

        $prefix = $query->getConnection()->getTablePrefix();
        $products = (new Product)->getTable();
        $imeis = (new ProductImei)->getTable();
        $series = (new ProductSerial)->getTable();
        $productsSql = $prefix.$products;
        $imeisSql = $prefix.$imeis;
        $seriesSql = $prefix.$series;
        $like = self::like($term);
        $contem = '%'.$like.'%';
        $comeca = $like.'%';
        $palavra = '% '.$like.'%';

        $query->where(function (Builder $sub) use ($contem, $term, $imeis, $series, $products): void {
            $sub->where('descricao', 'like', $contem)
                ->orWhere('codigo', 'like', $contem)
                ->orWhere('codigo_barras', 'like', $contem)
                ->orWhere('codigo_barras_caixa', 'like', $contem)
                ->orWhereExists(function ($exists) use ($contem, $imeis, $products): void {
                    $exists->selectRaw('1')
                        ->from($imeis)
                        ->whereColumn($imeis.'.product_id', $products.'.id')
                        ->where($imeis.'.imei', 'like', $contem);
                })
                ->orWhereExists(function ($exists) use ($contem, $series, $products): void {
                    $exists->selectRaw('1')
                        ->from($series)
                        ->whereColumn($series.'.product_id', $products.'.id')
                        ->where($series.'.numero_serie', 'like', $contem);
                });

            if (ctype_digit($term)) {
                $sub->orWhere($products.'.id', (int) $term);
            }
        });

        $query->orderByRaw(
            'CASE
                WHEN descricao = ? THEN 100
                WHEN codigo = ? OR codigo_barras = ? OR codigo_barras_caixa = ?
                    OR EXISTS (
                        SELECT 1 FROM '.$imeisSql.' AS os_imei
                        WHERE os_imei.product_id = '.$productsSql.'.id AND os_imei.imei = ?
                    )
                    OR EXISTS (
                        SELECT 1 FROM '.$seriesSql.' AS os_serie
                        WHERE os_serie.product_id = '.$productsSql.'.id AND os_serie.numero_serie = ?
                    )
                    THEN 90
                WHEN descricao LIKE ? THEN 80
                WHEN descricao LIKE ? THEN 70
                ELSE 50
            END DESC',
            [$term, $term, $term, $term, $term, $term, $comeca, $palavra],
        )->orderBy('descricao');
    }

    public static function like(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
