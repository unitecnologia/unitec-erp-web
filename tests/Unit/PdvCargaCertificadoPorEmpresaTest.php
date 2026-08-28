<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Pdv\PdvCargaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdvCargaCertificadoPorEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fingerprint_diferente_por_empresa(): void
    {
        $empresaA = Empresa::query()->create([
            'nome' => 'EMPRESA CERT A '.random_int(1000, 9999),
            'ativo' => true,
        ]);
        $empresaB = Empresa::query()->create([
            'nome' => 'EMPRESA CERT B '.random_int(1000, 9999),
            'ativo' => true,
        ]);

        $relA = 'certificados/'.$empresaA->id.'/certificado.pfx';
        $relB = 'certificados/'.$empresaB->id.'/certificado.pfx';

        Storage::disk('local')->put($relA, 'pfx-empresa-a-'.uniqid('', true));
        Storage::disk('local')->put($relB, 'pfx-empresa-b-'.uniqid('', true));

        try {
            VendasParametro::forEmpresa((int) $empresaA->id)
                ->forceFill(['caminho_certificado' => $relA])
                ->save();
            VendasParametro::forEmpresa((int) $empresaB->id)
                ->forceFill(['caminho_certificado' => $relB])
                ->save();

            $svc = app(PdvCargaService::class);
            $fpA = $svc->certificadoFingerprint((int) $empresaA->id);
            $fpB = $svc->certificadoFingerprint((int) $empresaB->id);

            $this->assertNotNull($fpA);
            $this->assertNotNull($fpB);
            $this->assertNotSame($fpA, $fpB);
        } finally {
            Storage::disk('local')->delete([$relA, $relB]);
        }
    }
}
