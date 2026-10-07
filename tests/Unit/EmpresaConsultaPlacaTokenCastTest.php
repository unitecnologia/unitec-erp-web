<?php

namespace Tests\Unit;

use App\Models\Empresa;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class EmpresaConsultaPlacaTokenCastTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_save_nao_quebra_quando_token_antigo_tem_mac_invalido(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA MAC',
            'ativo' => true,
        ]);

        $outraChave = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $ilegivel = $outraChave->encryptString('token-antigo');

        DB::table('empresas')->where('id', $empresa->id)->update([
            'param_consulta_placa_token' => $ilegivel,
        ]);

        $empresa->refresh();

        $this->assertNull($empresa->param_consulta_placa_token);

        $empresa->nome = 'EMPRESA GRAVADA';
        $empresa->save();

        $this->assertSame('EMPRESA GRAVADA', $empresa->fresh()->nome);
        $this->assertSame($ilegivel, $empresa->fresh()->getRawOriginal('param_consulta_placa_token'));
    }

    public function test_substitui_token_ilegivel_por_um_novo(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA TOKEN',
            'ativo' => true,
        ]);

        $outraChave = new Encrypter(random_bytes(32), 'AES-256-CBC');

        DB::table('empresas')->where('id', $empresa->id)->update([
            'param_consulta_placa_token' => $outraChave->encryptString('token-antigo'),
        ]);

        $empresa->refresh();
        $empresa->param_consulta_placa_token = 'voa_novo_token';
        $empresa->save();

        $empresa->refresh();

        $this->assertSame('voa_novo_token', $empresa->param_consulta_placa_token);
        $this->assertNotSame('voa_novo_token', $empresa->getRawOriginal('param_consulta_placa_token'));
    }
}
