<?php

namespace Tests\Unit;

use App\Support\Erp\Queries\VendaListQueryBuilder;
use Tests\TestCase;

class VendaListQueryBuilderMultiSearchTest extends TestCase
{
    public function test_busca_textual_gera_or_entre_campos(): void
    {
        $sql = (new VendaListQueryBuilder(
            searchFieldsActive: ['numero', 'cliente', 'vendedor'],
            localSearch: 'ALFA',
        ))->buildForList()->toSql();

        $this->assertStringContainsString(' or ', mb_strtolower($sql));
        $this->assertStringContainsString('numero', mb_strtolower($sql));
    }

    public function test_palavras_geram_and_com_or_por_campo(): void
    {
        $builder = new VendaListQueryBuilder(
            searchFieldsActive: ['vendedor', 'meio_pagamento'],
            localSearch: 'PAULO DINHEIRO',
        );
        $query = $builder->buildForList();
        $sql = mb_strtolower($query->toSql());
        $bindings = array_map(
            static fn (mixed $b): string => trim((string) $b, '%'),
            $query->getBindings(),
        );

        $this->assertStringContainsString(' or ', $sql);
        $this->assertGreaterThanOrEqual(2, substr_count($sql, 'vendedor_nome'));
        $this->assertStringContainsString('forma_pagamento', $sql);
        $this->assertContains('PAULO', $bindings);
        $this->assertContains('DINHEIRO', $bindings);
    }

    public function test_data_aplica_periodo_junto_com_or_textual(): void
    {
        $sql = (new VendaListQueryBuilder(
            searchFieldsActive: ['data', 'cliente', 'vendedor'],
            localSearch: 'ALFA',
            localSearchDe: '2026-10-01',
            localSearchAte: '2026-10-23',
        ))->buildForList()->toSql();

        $lower = mb_strtolower($sql);
        $this->assertStringContainsString('data', $lower);
        $this->assertStringContainsString(' or ', $lower);
        $this->assertTrue(
            str_contains($lower, '>=') || str_contains($lower, 'date(') || str_contains($lower, 'where date'),
            'Esperava filtro de período na SQL',
        );
    }

    public function test_selecao_vazia_nao_aplica_busca_textual(): void
    {
        $sql = (new VendaListQueryBuilder(
            searchColumn: '',
            searchFieldsActive: [],
            localSearch: 'qualquer',
        ))->buildForList()->toSql();

        $this->assertIsString($sql);
        $this->assertStringNotContainsString('nome_razao', mb_strtolower($sql));
        $this->assertStringNotContainsString('qualquer', mb_strtolower($sql));
    }

    public function test_active_search_columns_respeita_multipla_selecao(): void
    {
        $builder = new VendaListQueryBuilder(
            searchFieldsActive: ['numero', 'data', 'cliente', 'tipo'],
        );

        $this->assertSame(
            ['numero', 'data', 'cliente', 'tipo'],
            $builder->activeSearchColumns(),
        );
        $this->assertTrue($builder->isDateSearchColumn());
        $this->assertFalse($builder->isTimeSearchColumn());
    }
}
