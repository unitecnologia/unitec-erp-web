<?php

namespace Tests\Unit;

use App\Filament\Pages\PermissoesPage;
use App\Models\ErpProfile;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Schema mínimo: CAIXA (is_system) deve permitir editar/salvar permissões;
 * ADMINISTRADOR permanece travado; sem permissão manage não acessa a tela.
 */
class PermissoesPerfilCaixaEditavelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
    }

    public function test_caixa_sistema_nao_fica_locked_e_administrador_fica(): void
    {
        $caixa = ErpProfile::query()->create([
            'nome' => 'CAIXA',
            'descricao' => 'Operador',
            'is_system' => true,
        ]);
        $admin = ErpProfile::query()->create([
            'nome' => 'ADMINISTRADOR',
            'descricao' => 'Total',
            'is_system' => true,
        ]);

        $page = new PermissoesPage;
        $capture = new ReflectionMethod(PermissoesPage::class, 'captureProfile');
        $capture->setAccessible(true);

        $caixaState = $capture->invoke($page, (int) $caixa->id);
        $adminState = $capture->invoke($page, (int) $admin->id);

        $this->assertFalse((bool) ($caixaState['locked'] ?? true));
        $this->assertFalse((bool) ($caixaState['fullAccess'] ?? true));
        $this->assertTrue((bool) ($caixaState['system'] ?? false));

        $this->assertTrue((bool) ($adminState['locked'] ?? false));
        $this->assertTrue((bool) ($adminState['fullAccess'] ?? false));
        $this->assertTrue((bool) ($adminState['system'] ?? false));
    }

    public function test_salvar_permissoes_do_caixa_persiste_e_recarrega(): void
    {
        $adminUser = $this->usuarioComAcesso();
        Auth::login($adminUser);

        $caixa = ErpProfile::query()->create([
            'nome' => 'CAIXA',
            'descricao' => 'Operador',
            'is_system' => true,
        ]);
        ErpAccess::syncProfilePermissions($caixa, ['pdv.access']);

        Livewire::test(PermissoesPage::class)
            ->call('selectProfile', (int) $caixa->id)
            ->assertSet('permissionLocked', false)
            ->assertSet('permissionFullAccess', false)
            ->assertSet('profileIsSystem', true)
            ->call('savePermissionKeys', ['pdv.access', 'pdv.print', 'vendas.access'])
            ->assertNotSet('permissionLocked', true);

        $keys = DB::table('erp_profile_permissions')
            ->where('erp_profile_id', $caixa->id)
            ->orderBy('permission_key')
            ->pluck('permission_key')
            ->all();

        $this->assertSame(['pdv.access', 'pdv.print', 'vendas.access'], $keys);

        $page = new PermissoesPage;
        $capture = new ReflectionMethod(PermissoesPage::class, 'captureProfile');
        $capture->setAccessible(true);
        $reloaded = $capture->invoke($page, (int) $caixa->id);

        $this->assertEqualsCanonicalizing(
            ['pdv.access', 'pdv.print', 'vendas.access'],
            $reloaded['keys'] ?? [],
        );
        $this->assertFalse((bool) ($reloaded['locked'] ?? true));
    }

    public function test_administrador_nao_salva_permissoes(): void
    {
        $adminUser = $this->usuarioComAcesso();
        Auth::login($adminUser);

        $admin = ErpProfile::query()->create([
            'nome' => 'ADMINISTRADOR',
            'descricao' => 'Total',
            'is_system' => true,
        ]);
        ErpAccess::syncProfilePermissions($admin, ['pdv.access']);

        Livewire::test(PermissoesPage::class)
            ->call('selectProfile', (int) $admin->id)
            ->assertSet('permissionLocked', true)
            ->assertSet('permissionFullAccess', true)
            ->call('savePermissionKeys', ['pdv.print']);

        $keys = DB::table('erp_profile_permissions')
            ->where('erp_profile_id', $admin->id)
            ->pluck('permission_key')
            ->all();

        $this->assertSame(['pdv.access'], $keys);
    }

    public function test_sem_permissao_manage_nao_acessa_pagina(): void
    {
        $user = User::query()->create([
            'name' => 'SEM ACESSO',
            'email' => 'sem-'.uniqid('', true).'@test.local',
            'password' => 'secret',
            'is_admin' => false,
            'ativo' => true,
        ]);
        Auth::login($user);

        $this->assertFalse(PermissoesPage::canAccess());
    }

    public function test_js_desabilita_checks_quando_locked(): void
    {
        $js = (string) file_get_contents(public_path('js/erp-permissoes.js'));
        $this->assertStringContainsString('input.disabled = locked || fullAccess', $js);
        $this->assertStringContainsString("button.disabled = locked || fullAccess", $js);
    }

    private function usuarioComAcesso(): User
    {
        $user = User::query()->create([
            'name' => 'ADMIN TESTE',
            'email' => 'admin-'.uniqid('', true).'@test.local',
            'password' => 'secret',
            'is_admin' => true,
            'ativo' => true,
        ]);

        return $user;
    }

    private function criarSchemaMinimo(): void
    {
        foreach ([
            'erp_profile_permissions',
            'user_permissions',
            'users',
            'erp_profiles',
            'cache',
            'cache_locks',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('erp_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('erp_profile_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('erp_profile_id');
            $table->string('permission_key');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->unsignedBigInteger('erp_profile_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('permission_key');
            $table->timestamps();
        });

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }
}
