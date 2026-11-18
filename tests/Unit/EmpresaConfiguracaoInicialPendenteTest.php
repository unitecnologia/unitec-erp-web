<?php

namespace Tests\Unit;

use App\Models\Empresa;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmpresaConfiguracaoInicialPendenteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        Schema::dropIfExists('empresas');
        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('pessoa_tipo')->nullable();
            $table->string('razao_social')->nullable();
            $table->string('cnpj')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('configuracao_inicial_concluida')->default(false);
            $table->timestamps();
        });
    }

    public function test_empresa_provisoria_id_1_mantem_primeiro_acesso_pendente(): void
    {
        $this->insertEmpresaProvisoria(concluida: false);

        $this->assertTrue(Empresa::query()->exists());
        $this->assertTrue(Empresa::configuracaoInicialPendente());
    }

    public function test_apos_concluir_configuracao_inicial_nao_fica_pendente(): void
    {
        $this->insertEmpresaProvisoria(concluida: true, razao: 'CLIENTE TESTE LTDA', cnpj: '22469772000100');

        $this->assertFalse(Empresa::configuracaoInicialPendente());
    }

    private function insertEmpresaProvisoria(bool $concluida, ?string $razao = null, ?string $cnpj = null): void
    {
        $now = now();

        DB::table('empresas')->insert([
            'id' => 1,
            'codigo' => 1,
            'pessoa_tipo' => 'juridica',
            'ativo' => 1,
            'configuracao_inicial_concluida' => $concluida ? 1 : 0,
            'razao_social' => $razao,
            'cnpj' => $cnpj,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
