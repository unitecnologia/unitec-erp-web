<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\Nfse\NfseDpsBuilder;
use Tests\TestCase;

class NfseDpsBuilderTest extends TestCase
{
    public function test_monta_dps_somente_com_snapshots_da_nota(): void
    {
        $empresa = new Empresa([
            'cnpj' => '00.000.000/0001-91',
            'razao_social' => 'PRESTADOR GRAVADO',
            'fantasia' => 'PRESTADOR',
            'im' => '12345',
            'cidade' => 'CAMBORIU',
            'cidade_codigo' => '4203204',
            'uf' => 'SC',
        ]);
        $empresa->id = 1;

        $nota = new Nfse([
            'status' => Nfse::STATUS_ABERTA,
            'serie_dps' => '1',
            'numero_dps' => 4,
            'competencia' => '2026-09-01',
            'data_emissao' => '2026-09-13',
            'tomador_nome' => 'TOMADOR DA NOTA',
            'tomador_cpf_cnpj' => '000.000.000-00',
            'tomador_email' => 'snapshot@nota.local',
            'tomador_cidade' => 'ITAJAÍ',
            'tomador_cidade_codigo' => '4208203',
            'tomador_uf' => 'SC',
            'municipio_prestacao_codigo' => '4208203',
            'municipio_prestacao_nome' => 'ITAJAÍ',
            'municipio_prestacao_uf' => 'SC',
            'valor_servicos' => '100.00',
            'desconto' => '0.00',
            'iss' => '0.00',
            'total' => '100.00',
        ]);
        $nota->setRelation('empresa', $empresa);
        $nota->setRelation('tomador', new Person([
            'nome_razao' => 'CLIENTE ATUAL',
            'email' => 'cadastro-atual@cliente.local',
            'cidade_codigo' => '9999999',
        ]));

        $item = new NfseItem([
            'codigo' => '10733',
            'descricao' => 'SERVIÇO DA NOTA',
            'unidade' => 'UN',
            'quantidade' => '1.000',
            'valor' => '100.00',
            'total' => '100.00',
            'c_trib_nac' => '010701',
            'c_nbs' => '114011000',
            'c_trib_mun' => '001',
            'c_ind_op' => '100301',
        ]);
        $item->setRelation('product', new Product([
            'c_trib_nac' => '999999',
            'c_nbs' => '000000000',
            'descricao' => 'CADASTRO ATUAL',
        ]));
        $nota->setRelation('itens', collect([$item]));

        $dps = app(NfseDpsBuilder::class)->montar($nota);

        $this->assertSame('1', $dps['dps']['serie']);
        $this->assertSame(4, $dps['dps']['numero']);
        $this->assertSame('2026-09', $dps['dps']['competencia']);
        $this->assertSame('PRESTADOR GRAVADO', $dps['prestador']['nome']);
        $this->assertSame('4203204', $dps['prestador']['cidade_codigo']);
        $this->assertSame('TOMADOR DA NOTA', $dps['tomador']['nome']);
        $this->assertSame('snapshot@nota.local', $dps['tomador']['email']);
        $this->assertSame('4208203', $dps['tomador']['cidade_codigo']);
        $this->assertSame('4208203', $dps['municipio_prestacao']['codigo']);
        $this->assertSame('ITAJAÍ', $dps['municipio_prestacao']['nome']);
        $this->assertSame('100.00', $dps['valores']['total']);
        $this->assertSame('SERVIÇO DA NOTA', $dps['servicos'][0]['descricao']);
        $this->assertSame('010701', $dps['servicos'][0]['cTribNac']);
        $this->assertSame('114011000', $dps['servicos'][0]['cNBS']);
        $this->assertSame('001', $dps['servicos'][0]['cTribMun']);
        $this->assertSame('100301', $dps['servicos'][0]['cIndOp']);
        $this->assertArrayNotHasKey('ibs', $dps);
        $this->assertArrayNotHasKey('cbs', $dps);
        $this->assertArrayNotHasKey('xml', $dps);
    }
}
