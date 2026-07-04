<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\ErpProfile;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpPermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UnitecInitialSeeder extends Seeder
{
    public function run(): void
    {
        Empresa::query()->updateOrCreate(
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

        Terminal::query()->updateOrCreate(
            [
                'empresa_id' => 1,
                'nome' => 'CAIXA-1',
            ],
            [
                ...Terminal::defaultAttributes(1),
                'nome' => 'CAIXA-1',
                'velocidade' => 9600,
                'porta' => 'RAW:IMPRESSORA',
                'exibe_f3' => true,
                'exibe_f4' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'usuario@unitecnologia.local'],
            [
                'name' => 'USUARIO',
                'password' => Hash::make('01'),
                'senha' => '01',
                'empresa_id' => 1,
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
}
