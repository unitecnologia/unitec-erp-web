<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\Erp\Queries\ProductListQueryBuilder;
use Tests\TestCase;

class ProductListQueryBuilderEtapaATest extends TestCase
{
    public function test_list_columns_cobre_grade_sem_campos_fiscais(): void
    {
        $columns = ProductListQueryBuilder::LIST_COLUMNS;

        foreach ([
            'id', 'codigo', 'referencia', 'codigo_barras', 'descricao', 'grupo',
            'preco_venda', 'estoque', 'estoque_minimo', 'localizacao', 'validade', 'lote', 'ativo',
        ] as $required) {
            $this->assertContains($required, $columns);
        }

        foreach (['ncm', 'cfop_interno', 'aliq_icms', 'foto_path', 'nutri_sodio', 'info_adicionais'] as $heavy) {
            $this->assertNotContains($heavy, $columns);
        }
    }

    public function test_alias_do_join_de_estoque_esta_estavel(): void
    {
        $this->assertSame('erp_est_agg', ProductListQueryBuilder::ESTOQUE_AGG_ALIAS);
    }

    public function test_descricao_partes_aplica_palavras_mais_longas_primeiro(): void
    {
        $query = Product::query();
        ProductListQueryBuilder::whereDescricaoPartes($query, 'descricao', 'OLEO FILTRO');

        $bindings = $query->getBindings();

        $this->assertSame(['%FILTRO%', '%OLEO%'], $bindings);
    }
}
