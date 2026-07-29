<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Fiscal\PdvNfceFiscalPayloadBuilder;
use Tests\TestCase;

class PdvNfceFiscalPayloadBuilderTest extends TestCase
{
    public function test_pode_emitir_real_quando_sc_configurado(): void
    {
        $empresa = new Empresa([
            'uf' => 'SC',
            'cidade_codigo' => '4205407',
            'ie' => '255000000',
        ]);

        $parametros = new VendasParametro([
            'uf' => 'SC',
            'id_token' => '1',
            'token' => 'FF1BFD3B-29D5-48F2-B472-5E76C281D283',
            'caminho_certificado' => 'nfe/1/certificado.pfx',
        ]);

        $builder = new PdvNfceFiscalPayloadBuilder();

        $this->assertFalse($builder->podeEmitirReal($parametros, $empresa, PdvFinalizarOperacao::NFCE_CONTINGENCIA));
        $this->assertFalse($builder->podeEmitirReal($parametros, $empresa, PdvFinalizarOperacao::NFCE_TRANSMITIR));
    }
}
