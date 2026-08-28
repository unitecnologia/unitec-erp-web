<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Terminal;
use App\Support\Pdv\PdvCargaService;
use App\Support\Pdv\PdvOfflineTerminalLookup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PdvOfflineTerminalLookupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pdv1_nao_casa_com_erp1_numero_zero(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA LOOKUP '.random_int(1000, 9999),
            'ativo' => true,
        ]);

        $erp1 = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'ERP1',
            'numero_logico_terminal' => 0,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
        ]);

        $pdv1 = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'PDV1',
            'numero_logico_terminal' => 1,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
        ]);

        $foundPdv = PdvOfflineTerminalLookup::find((int) $empresa->id, 'PDV1');
        $foundNumero = PdvOfflineTerminalLookup::find((int) $empresa->id, '1');

        $this->assertNotNull($foundPdv);
        $this->assertSame((int) $pdv1->id, (int) $foundPdv->id);
        $this->assertNotSame((int) $erp1->id, (int) $foundPdv->id);
        $this->assertSame((int) $pdv1->id, (int) $foundNumero->id);

        $payload = app(PdvCargaService::class)->buildPull(null, (int) $empresa->id, 'PDV1', 0, 10);
        $this->assertSame((int) $pdv1->id, (int) ($payload['empresa']['pdv_terminal']['id'] ?? 0));
    }
}
