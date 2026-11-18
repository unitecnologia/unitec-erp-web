<?php

namespace Tests\Unit\Pix;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use App\Support\Pix\PixContaBancariaVinculo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PixContaBancariaVinculoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
    }

    public function test_campo_no_catalogo_pix(): void
    {
        $fields = EmpresaParametros::pixFields();

        $this->assertArrayHasKey(EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID, $fields);
        $this->assertSame('Conta Bancária Vinculada', $fields[EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID]['label']);
        $this->assertSame('integer', $fields[EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID]['type']);
        $this->assertNull($fields[EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID]['default']);
        $this->assertTrue(Schema::hasColumn('empresas', EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID));
    }

    public function test_lista_somente_banco_ativo_da_empresa(): void
    {
        [$empresaA, $empresaB] = $this->duasEmpresas();
        $bancoA = $this->conta($empresaA, CaixaConta::TIPO_BANCO, true, 11, 'BANCO A');
        $this->conta($empresaA, CaixaConta::TIPO_BANCO, false, 12, 'BANCO INATIVO');
        $this->conta($empresaA, CaixaConta::TIPO_PDV, true, 13, 'PDV A');
        $this->conta($empresaB, CaixaConta::TIPO_BANCO, true, 14, 'BANCO B');

        $opcoes = PixContaBancariaVinculo::opcoesParaEmpresa((int) $empresaA->id);

        $this->assertCount(1, $opcoes);
        $this->assertSame((int) $bancoA->id, $opcoes[0]['id']);
        $this->assertStringContainsString('BANCO A', $opcoes[0]['label']);
    }

    public function test_pix_desligado_grava_sem_conta(): void
    {
        $empresa = $this->duasEmpresas()[0];

        $empresa->update([
            'param_pix_habilitar' => false,
            EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID => null,
        ]);

        $fresh = $empresa->fresh();
        $this->assertFalse((bool) $fresh->param_pix_habilitar);
        $this->assertNull($fresh->{EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID});
    }

    public function test_pix_ligado_sem_conta_nao_grava(): void
    {
        $empresa = $this->duasEmpresas()[0];

        $this->expectException(ValidationException::class);

        $empresa->update([
            'param_pix_habilitar' => true,
            EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID => null,
        ]);
    }

    public function test_pix_ligado_com_conta_valida_grava(): void
    {
        $empresa = $this->duasEmpresas()[0];
        $banco = $this->conta($empresa, CaixaConta::TIPO_BANCO, true, 21, 'CONTA PIX');

        $empresa->update([
            'param_pix_habilitar' => true,
            EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID => $banco->id,
        ]);

        $fresh = $empresa->fresh();
        $this->assertTrue((bool) $fresh->param_pix_habilitar);
        $this->assertSame((int) $banco->id, (int) $fresh->{EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID});
    }

    public function test_pix_ligado_rejeita_conta_de_outra_empresa_ou_pdv(): void
    {
        [$empresaA, $empresaB] = $this->duasEmpresas();
        $bancoB = $this->conta($empresaB, CaixaConta::TIPO_BANCO, true, 31, 'BANCO B');
        $pdvA = $this->conta($empresaA, CaixaConta::TIPO_PDV, true, 32, 'PDV A');

        try {
            $empresaA->update([
                'param_pix_habilitar' => true,
                EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID => $bancoB->id,
            ]);
            $this->fail('Conta de outra empresa deveria ser rejeitada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID, $e->errors());
        }

        try {
            $empresaA->update([
                'param_pix_habilitar' => true,
                EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID => $pdvA->id,
            ]);
            $this->fail('Conta PDV deveria ser rejeitada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID, $e->errors());
        }
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('caixa_contas');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('param_pix_habilitar')->default(false);
            $table->unsignedBigInteger(EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID)->nullable();
            $table->timestamps();
        });

        Schema::create('caixa_contas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('BANCO');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * @return array{0: Empresa, 1: Empresa}
     */
    private function duasEmpresas(): array
    {
        $empresaA = Empresa::query()->create([
            'nome' => 'EMP PIX A',
            'ativo' => true,
        ]);
        $empresaB = Empresa::query()->create([
            'nome' => 'EMP PIX B',
            'ativo' => true,
        ]);

        return [$empresaA, $empresaB];
    }

    private function conta(Empresa $empresa, string $tipo, bool $ativo, int $codigo, string $nome): CaixaConta
    {
        return CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => $codigo,
            'nome' => $nome,
            'tipo' => $tipo,
            'ativo' => $ativo,
        ]);
    }
}
