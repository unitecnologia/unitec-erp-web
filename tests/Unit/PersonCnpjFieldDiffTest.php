<?php

namespace Tests\Unit;

use App\Support\Erp\PersonCnpjFieldDiff;
use Tests\TestCase;

class PersonCnpjFieldDiffTest extends TestCase
{
    public function test_it_detects_field_differences_and_ignores_empty_api_values(): void
    {
        $current = [
            'nome_razao' => 'EMPRESA ABC LTDA',
            'endereco' => 'RUA DAS FLORES',
            'numero' => '100',
            'complemento' => 'SALA 1',
            'bairro' => 'CENTRO',
        ];

        $consulta = [
            'nome_razao' => 'EMPRESA ABC COMERCIO LTDA',
            'endereco' => 'AVENIDA BRASIL',
            'numero' => '250',
            'complemento' => null,
            'bairro' => 'CENTRO',
            'cep' => '01310-100',
        ];

        $diffs = PersonCnpjFieldDiff::differences($current, $consulta);
        $byField = collect($diffs)->keyBy('campo');

        $this->assertTrue($byField->has('nome_razao'));
        $this->assertTrue($byField->has('endereco'));
        $this->assertTrue($byField->has('numero'));
        $this->assertTrue($byField->has('cep'));
        $this->assertFalse($byField->has('complemento'));
        $this->assertFalse($byField->has('bairro'));

        $this->assertSame('EMPRESA ABC LTDA', $byField['nome_razao']['atual']);
        $this->assertSame('EMPRESA ABC COMERCIO LTDA', $byField['nome_razao']['novo']);
        $this->assertTrue($byField['nome_razao']['selected']);
    }

    public function test_it_normalizes_cep_and_phone_for_comparison(): void
    {
        $diffs = PersonCnpjFieldDiff::differences(
            [
                'cep' => '01310100',
                'fone1' => '1133334444',
            ],
            [
                'cep' => '01310-100',
                'fone1' => '(11) 3333-4444',
            ],
        );

        $this->assertSame([], $diffs);
    }

    public function test_it_returns_empty_when_nothing_changed(): void
    {
        $payload = [
            'nome_razao' => 'EMPRESA TESTE LTDA',
            'endereco' => 'RUA A',
            'numero' => '10',
        ];

        $this->assertSame([], PersonCnpjFieldDiff::differences($payload, $payload));
    }

    public function test_has_comparable_data_ignores_blank_defaults(): void
    {
        $this->assertFalse(PersonCnpjFieldDiff::hasComparableData([
            'cpf_cnpj' => '22.469.772/0001-00',
            'uf' => '',
            'regime_tributario' => '',
            'tipo_contribuinte' => 'nao_contribuinte',
        ]));

        $this->assertTrue(PersonCnpjFieldDiff::hasComparableData([
            'nome_razao' => 'EMPRESA TESTE LTDA',
        ]));
    }

    public function test_tipo_from_ie_rules(): void
    {
        $this->assertNull(PersonCnpjFieldDiff::tipoFromIe(null));
        $this->assertNull(PersonCnpjFieldDiff::tipoFromIe(''));
        $this->assertNull(PersonCnpjFieldDiff::tipoFromIe('-'));
        $this->assertSame('isento', PersonCnpjFieldDiff::tipoFromIe('ISENTO'));
        $this->assertSame('isento', PersonCnpjFieldDiff::tipoFromIe('isenta'));
        $this->assertSame('nao_contribuinte', PersonCnpjFieldDiff::tipoFromIe('NÃO CONTRIBUINTE'));
        $this->assertSame('contribuinte', PersonCnpjFieldDiff::tipoFromIe('258100168'));
    }

    public function test_enrich_sets_contribuinte_from_consulta_ie(): void
    {
        $enriched = PersonCnpjFieldDiff::enrichConsultaWithTipoContribuinte([
            'nome_razao' => 'EMPRESA TESTE LTDA',
            'rg_ie' => '258100168',
        ], [
            'tipo_contribuinte' => 'nao_contribuinte',
        ]);

        $this->assertSame('contribuinte', $enriched['tipo_contribuinte']);
    }

    public function test_enrich_fixes_incoherent_tipo_when_cadastro_has_ie_and_api_omits_ie(): void
    {
        $enriched = PersonCnpjFieldDiff::enrichConsultaWithTipoContribuinte([
            'nome_razao' => 'EMPRESA TESTE LTDA',
        ], [
            'rg_ie' => '258100168',
            'tipo_contribuinte' => 'nao_contribuinte',
        ]);

        $this->assertSame('contribuinte', $enriched['tipo_contribuinte']);

        $diffs = PersonCnpjFieldDiff::differences([
            'nome_razao' => 'EMPRESA TESTE LTDA',
            'rg_ie' => '258100168',
            'tipo_contribuinte' => 'nao_contribuinte',
        ], $enriched);

        $this->assertCount(1, $diffs);
        $this->assertSame('tipo_contribuinte', $diffs[0]['campo']);
        $this->assertSame('contribuinte', $diffs[0]['novo']);
        $this->assertSame('CONTRIBUINTE', PersonCnpjFieldDiff::formatDisplay('tipo_contribuinte', $diffs[0]['novo']));
    }

    public function test_enrich_does_not_presume_nao_contribuinte_without_ie(): void
    {
        $enriched = PersonCnpjFieldDiff::enrichConsultaWithTipoContribuinte([
            'nome_razao' => 'EMPRESA TESTE LTDA',
        ], [
            'tipo_contribuinte' => 'contribuinte',
        ]);

        $this->assertArrayNotHasKey('tipo_contribuinte', $enriched);
    }
}
