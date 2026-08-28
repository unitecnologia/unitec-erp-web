<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Terminal;
use App\Models\User;
use App\Models\VendasParametro;
use App\Support\Pdv\PdvCargaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PdvCargaNfceSequenciaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_carga_envia_proximo_numero_da_tela_nfc_e_por_caixa(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA CARGA NFCE '.random_int(1000, 9999),
            'ativo' => true,
        ]);

        $pdv1 = Terminal::query()->create([
            ...Terminal::defaultAttributes($empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'PDV1',
            'numero_logico_terminal' => 1,
            'serie' => '1',
            'numeracao_inicial' => 1,
            'usar_numero_inicial' => true,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
        ]);

        VendasParametro::forEmpresa((int) $empresa->id)->forceFill([
            'serie' => '1',
            'numero' => 1,
        ])->save();

        $user = User::factory()->create(['empresa_id' => $empresa->id]);
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);
        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'numero' => 18,
            'subtotal' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 10,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
        ]);
        PdvVendaNfce::query()->create([
            'pdv_venda_id' => $venda->id,
            'empresa_id' => $empresa->id,
            'operacao' => 'nfce',
            'modelo' => '65',
            'serie' => '1',
            'numero' => 18,
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
        ]);

        $payload = app(PdvCargaService::class)->buildPull(null, (int) $empresa->id, 'PDV1', 0, 10);
        $caixa = $payload['empresa']['pdv_terminal'] ?? [];

        $this->assertSame((int) $pdv1->id, (int) ($caixa['id'] ?? 0));
        $this->assertSame('1', (string) ($caixa['serie'] ?? ''));
        $this->assertGreaterThanOrEqual(19, (int) ($caixa['numero_inicial'] ?? 0));
        $this->assertTrue((bool) ($caixa['usar_numero_inicial'] ?? false));
        $this->assertSame('1', (string) ($payload['empresa']['pdv_terminal_serie'] ?? ''));
    }
}
