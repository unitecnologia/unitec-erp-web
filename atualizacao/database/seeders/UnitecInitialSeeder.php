<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\ErpProfile;
use App\Models\User;
use App\Support\Erp\EmpresaVendaProntaBootstrap;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpPermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UnitecInitialSeeder extends Seeder
{
    public function run(): void
    {
        // Primeiro acesso: sem empresa. O usuário cadastra a empresa após o login.
        // Dados demo (empresa/produtos/etc.) ficam em DemoDatabaseSeeder.
        // Unidades/grupo/marca já ficam prontos para cadastro de produto e PDV.
        // Tabelas fiscais oficiais (CFOP + cClassTrib + IBPT + ICMS) vão no padrão de instalação.
        $this->call(ProductAuxiliarySeeder::class);
        $this->call(FiscalTabelasPadraoSeeder::class);

        User::query()->updateOrCreate(
            ['name' => 'USUARIO'],
            [
                'password' => Hash::make('01'),
                'senha' => '01',
                'empresa_id' => null,
                'is_admin' => true,
                'ativo' => true,
            ],
        );

        $adminProfile = ErpProfile::query()->updateOrCreate(
            ['nome' => 'ADMINISTRADOR'],
            [
                'descricao' => 'Acesso total (modelo)',
                'is_system' => true,
            ],
        );

        ErpAccess::syncProfilePermissions($adminProfile, ErpPermissionCatalog::allKeys());

        ErpProfile::query()->updateOrCreate(
            ['nome' => 'CAIXA'],
            [
                'descricao' => 'Operador de caixa e vendas',
                'is_system' => true,
            ],
        );

        $caixaProfile = ErpProfile::query()->where('nome', 'CAIXA')->first();

        if ($caixaProfile) {
            ErpAccess::syncProfilePermissions($caixaProfile, [
                'pdv.access',
                'pdv.print',
                'vendas.access',
                'vendas.print',
                'pessoas.access',
                'produtos.access',
            ]);
        }
    }

    /**
     * Dados de empresa demo (opcional — DemoDatabaseSeeder).
     */
    public static function seedDemoEmpresa(): Empresa
    {
        $empresa = Empresa::query()->updateOrCreate(
            ['id' => 1],
            [
                'codigo' => 1,
                'nome' => 'UNITECHNOLOGIA SISTEMAS',
                'fantasia' => 'UNITECHNOLOGIA SISTEMAS',
                'razao_social' => 'ALENCAR DE OLIVEIRA',
                'pessoa_tipo' => 'juridica',
                'cidade' => 'BALNEÁRIO CAMBORIÚ',
                'cidade_codigo' => '4202008',
                'cnpj' => '22469772000100',
                'ie' => '258100168',
                'cnae' => '5819100',
                'regime_tributario' => 'normal',
                'cep' => '88337040',
                'endereco' => 'RUA DOM DANIEL',
                'numero' => '269',
                'bairro' => 'VILA REAL',
                'uf' => 'SC',
                'pais_codigo' => '1058',
                'pais' => 'BRASIL',
                'telefone' => '47984002117',
                'cnpj_representante' => '00000000000000',
                'tipo_atividade' => 'informatica',
                'ativo' => true,
                ...\App\Support\Erp\EmpresaParametros::defaultFormValues(),
            ],
        );

        $user = User::query()->where('name', 'USUARIO')->first();

        if ($user) {
            $user->forceFill(['empresa_id' => $empresa->id])->save();

            if (! $user->empresas()->whereKey($empresa->id)->exists()) {
                $user->empresas()->attach($empresa->id);
            }
        }

        EmpresaVendaProntaBootstrap::forEmpresa($empresa, $user);

        return $empresa;
    }
}
