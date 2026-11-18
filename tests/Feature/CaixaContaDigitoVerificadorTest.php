<?php

namespace Tests\Feature;

use App\Filament\Resources\CaixaContaResource\Pages\ListCaixaContas;
use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Garante que DV "0" não é descartado no cadastro de Conta Caixa/BANCO.
 */
class CaixaContaDigitoVerificadorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    public function test_grava_e_reabre_dv_agencia_e_conta_igual_a_zero(): void
    {
        [$empresa, $admin] = $this->seedEmpresaAdmin();

        Livewire::test(ListCaixaContas::class)
            ->call('createContaCaixa')
            ->set('form.codigo', 7001)
            ->set('form.nome', 'BANCO DV ZERO')
            ->set('form.eh_bancaria', true)
            ->set('form.tipo', CaixaConta::TIPO_BANCO)
            ->set('form.ativo', true)
            ->set('form.banco_codigo', '085')
            ->set('form.agencia', '0109')
            ->set('form.agencia_dv', '0')
            ->set('form.conta_numero', '48346')
            ->set('form.conta_dv', '0')
            ->set('form.conta_tipo_bancaria', CaixaConta::CONTA_CORRENTE)
            ->set('form.titular_nome', 'TESTE TITULAR')
            ->set('form.titular_documento', '12345678901')
            ->call('saveContaCaixa')
            ->assertHasNoErrors();

        $conta = CaixaConta::query()->where('nome', 'BANCO DV ZERO')->first();
        $this->assertNotNull($conta);
        $this->assertSame('0', (string) $conta->agencia_dv);
        $this->assertSame('0', (string) $conta->conta_dv);

        Livewire::test(ListCaixaContas::class)
            ->set('highlightedRecordId', (int) $conta->id)
            ->call('editContaCaixa')
            ->assertSet('form.agencia_dv', '0')
            ->assertSet('form.conta_dv', '0');
    }

    private function createMinimalSchema(): void
    {
        Schema::dropIfExists('caixa_contas');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120);
            $table->string('fantasia', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('name');
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('caixa_contas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('BANCO');
            $table->string('situacao', 20)->default('aberto');
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->string('banco_codigo', 10)->nullable();
            $table->string('banco_nome', 80)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('agencia_dv', 4)->nullable();
            $table->string('conta_numero', 30)->nullable();
            $table->string('conta_dv', 4)->nullable();
            $table->string('conta_tipo_bancaria', 20)->nullable();
            $table->string('titular_nome', 120)->nullable();
            $table->string('titular_documento', 20)->nullable();
            $table->unsignedBigInteger('ultimo_usuario_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array{0: Empresa, 1: User}
     */
    private function seedEmpresaAdmin(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => 'DV0',
            'nome' => 'EMPRESA DV',
            'fantasia' => 'DV',
            'ativo' => true,
        ]);

        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        return [$empresa, $admin];
    }
}
