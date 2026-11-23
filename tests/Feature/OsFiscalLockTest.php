<?php

namespace Tests\Feature;

use App\Filament\Resources\OrdemServicoResource\Pages\EditOrdemServico;
use App\Http\Controllers\Api\UnitecOs\OrdemServicoController;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Os\OsFiscalLock;
use App\Support\Erp\Os\OsReabrirService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Valida OsFiscalLock na base MySQL DEV (bkp) com rollback.
 * Rodar: tools\php\php.exe vendor\bin\phpunit -c phpunit.dev-mysql.xml --filter=OsFiscalLockTest
 */
class OsFiscalLockTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Garante MySQL DEV mesmo se algum bootstrap residual apontar SQLite.
        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.database' => env('DB_DATABASE', 'unitec_erp'),
            ]);
            DB::purge();
            DB::reconnect('mysql');
        }

        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            $this->markTestSkipped(
                'OsFiscalLockTest exige MySQL DEV. Driver atual: '.DB::getDriverName()
                .' db='.(string) DB::connection()->getDatabaseName()
            );
        }
    }

    public function test_bloqueia_alteracao_com_nfse_autorizada_e_nao_libera_os_sozinha(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);
        $this->nfse($os, Nfse::STATUS_AUTORIZADA);

        $motivo = OsFiscalLock::motivoBloqueioAlteracao($os);
        $this->assertNotNull($motivo);
        $this->assertStringContainsString('NFS-e emitida', (string) $motivo);

        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);
    }

    public function test_bloqueia_durante_transmissao_nfse_mesmo_com_status_aberta(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);
        $nfse = $this->nfse($os, Nfse::STATUS_ABERTA);
        $nfse->forceFill(['transmitindo_em' => now(), 'numero_nfse' => null])->save();

        $motivo = OsFiscalLock::motivoBloqueioAlteracao($os);
        $this->assertNotNull($motivo);
        $this->assertStringContainsString('transmissão', (string) $motivo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('transmissão');
        app(OsReabrirService::class)->reabrir($os->fresh());
    }

    public function test_nfe_por_ordem_servico_id_bloqueia_mesmo_sem_obs_legado(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);

        Nfe::query()->create([
            'empresa_id' => $os->empresa_id,
            'cliente_id' => $os->cliente_id,
            'ordem_servico_id' => $os->id,
            'numero' => (string) random_int(800000, 899999),
            'serie' => '1',
            'modelo' => '55',
            'data_emissao' => now()->toDateString(),
            'total' => 10,
            'subtotal' => 10,
            'total_itens' => 1,
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
            'cfop' => 5102,
            'finalidade' => '1',
            'movimento' => '1',
            'npedido' => 'OUTRO',
            'obs_contribuinte' => null,
        ]);

        $motivo = OsFiscalLock::motivoBloqueioAlteracao($os);
        $this->assertNotNull($motivo);
        $this->assertStringContainsString('NF-e emitida', (string) $motivo);
        $this->assertInstanceOf(Nfe::class, OsFiscalLock::nfeEmitida($os));
    }

    public function test_nfe_legado_sem_ordem_servico_id_ainda_bloqueia(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);
        $numero = ltrim(preg_replace('/\D/', '', (string) $os->numero) ?? '', '0');

        Nfe::query()->create([
            'empresa_id' => $os->empresa_id,
            'cliente_id' => $os->cliente_id,
            'ordem_servico_id' => null,
            'numero' => (string) random_int(700000, 799999),
            'serie' => '1',
            'modelo' => '55',
            'data_emissao' => now()->toDateString(),
            'total' => 10,
            'subtotal' => 10,
            'total_itens' => 1,
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
            'cfop' => 5102,
            'finalidade' => '1',
            'movimento' => '1',
            'npedido' => $numero,
            'obs_contribuinte' => 'NF-E ORIGINADA DA OS Nº '.$numero.'.',
        ]);

        $this->assertStringContainsString('NF-e emitida', (string) OsFiscalLock::motivoBloqueioAlteracao($os));
    }

    public function test_cancelamento_parcial_mantem_bloqueio(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);
        $nfse = $this->nfse($os, Nfse::STATUS_AUTORIZADA);

        Nfe::query()->create([
            'empresa_id' => $os->empresa_id,
            'cliente_id' => $os->cliente_id,
            'ordem_servico_id' => $os->id,
            'numero' => (string) random_int(600000, 699999),
            'serie' => '1',
            'modelo' => '55',
            'data_emissao' => now()->toDateString(),
            'total' => 10,
            'subtotal' => 10,
            'total_itens' => 1,
            'status' => Nfe::STATUS_TRANSMITIDA,
            'situacao' => Nfe::SITUACAO_TRANSMITIDA,
            'cfop' => 5102,
            'finalidade' => '1',
            'movimento' => '1',
        ]);

        $nfse->forceFill(['status' => Nfse::STATUS_CANCELADA])->save();

        $motivo = OsFiscalLock::motivoBloqueioAlteracao($os);
        $this->assertNotNull($motivo);
        $this->assertStringContainsString('NF-e emitida', (string) $motivo);

        $this->expectException(DomainException::class);
        app(OsReabrirService::class)->reabrir($os->fresh());
    }

    public function test_persist_os_nao_reabre_os_quando_documento_fiscal_aparece(): void
    {
        [$os, , $user] = $this->osBase(OrdemServico::SITUACAO_ABERTA);

        $lw = Livewire::actingAs($user)
            ->test(EditOrdemServico::class, ['record' => $os->id])
            ->assertOk();

        $os->forceFill(['situacao' => OrdemServico::SITUACAO_FINALIZADA])->save();
        $this->nfse($os->fresh(), Nfse::STATUS_AUTORIZADA);

        $lw->set('laudo', 'TENTATIVA DE ALTERACAO')
            ->call('gravarOs')
            ->assertNotified();

        $os->refresh();
        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->situacao);
        $this->assertNotSame('TENTATIVA DE ALTERACAO', (string) $os->laudo);
    }

    public function test_api_unitec_os_bloqueia_itens_em_os_faturada_com_nfse(): void
    {
        [$os, $peca, $user] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA, comTecnico: true);
        $this->nfse($os, Nfse::STATUS_AUTORIZADA);

        Sanctum::actingAs($user);

        $request = Request::create('/api/v1/unitec-os/ordens/'.$os->id, 'PUT', [
            'pecas' => [
                ['produto_id' => $peca->id, 'qtd' => 9, 'preco' => 1],
            ],
            'servico_realizado' => 'ALTERADO INDEVIDAMENTE',
        ]);
        $request->setUserResolver(static fn () => $user);

        try {
            app(OrdemServicoController::class)->update($request, (int) $os->id);
            $this->fail('API deveria bloquear mutação.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertNotEmpty($exception->errors()['os'] ?? []);
        }

        $os->refresh();
        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->situacao);
        $this->assertNotSame('ALTERADO INDEVIDAMENTE', (string) $os->laudo);
        $this->assertSame(1, $os->itens()->where('tipo', 'P')->count());
        $this->assertEqualsWithDelta(2.0, (float) $os->itens()->where('tipo', 'P')->value('qtd'), 0.001);
    }

    public function test_api_permite_alterar_os_aberta_sem_documento_fiscal(): void
    {
        [$os, $peca, $user] = $this->osBase(OrdemServico::SITUACAO_ABERTA, comTecnico: true);

        Sanctum::actingAs($user);

        $request = Request::create('/api/v1/unitec-os/ordens/'.$os->id, 'PUT', [
            'servico_realizado' => 'LAUDO OK',
            'observacoes' => 'OBS OK',
            'pecas' => [
                ['produto_id' => $peca->id, 'qtd' => 3, 'preco' => 20],
            ],
        ]);
        $request->setUserResolver(static fn () => $user);

        $response = app(OrdemServicoController::class)->update($request, (int) $os->id);
        $this->assertSame(200, $response->getStatusCode());

        $os->refresh();
        $this->assertSame('LAUDO OK', $os->laudo);
        $this->assertEqualsWithDelta(3.0, (float) $os->itens()->where('tipo', 'P')->value('qtd'), 0.001);
    }

    public function test_transporte_bloqueado_com_nfce_autorizada(): void
    {
        [$os, , $user] = $this->osBase(OrdemServico::SITUACAO_FINALIZADA);

        $pdv = PdvVenda::query()->create([
            'user_id' => $user->id,
            'person_id' => $os->cliente_id,
            'numero' => random_int(900000, 999999),
            'subtotal' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 10,
            'forma_pagamento' => 'DINHEIRO',
            'origem' => PdvVenda::ORIGEM_OS_NFCE_PECAS,
            'situacao' => 'F',
        ]);

        PdvVendaNfce::query()->create([
            'pdv_venda_id' => $pdv->id,
            'empresa_id' => $os->empresa_id,
            'ordem_servico_id' => $os->id,
            'operacao' => 'nfce_transmitir',
            'status' => PdvVendaNfce::STATUS_AUTORIZADA,
            'simulada' => false,
            'numero' => '55',
            'serie' => '1',
            'modelo' => '65',
            'chave' => str_repeat('1', 44),
            'autorizada_em' => now(),
        ]);

        $this->assertStringContainsString('NFC-e emitida', (string) OsFiscalLock::motivoBloqueioAlteracao($os));
    }

    public function test_os_sem_documento_fiscal_nao_e_bloqueada_pelo_lock(): void
    {
        [$os] = $this->osBase(OrdemServico::SITUACAO_ABERTA);

        $this->assertNull(OsFiscalLock::motivoBloqueioAlteracao($os));
        OsFiscalLock::assertPodeAlterar($os);
        $this->assertTrue(true);
    }

    /**
     * @return array{0: OrdemServico, 1: Product, 2: User}
     */
    private function osBase(string $situacao, bool $comTecnico = false): array
    {
        $suffix = (string) random_int(800000, 899999);

        $empresa = Empresa::query()->create([
            'codigo' => $suffix,
            'nome' => 'EMPRESA OS LOCK '.$suffix,
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
        ]);

        $vendedor = null;
        if ($comTecnico) {
            $vendedor = Vendedor::query()->create([
                'codigo' => $suffix,
                'nome' => 'TECNICO OS '.$suffix,
                'ativo' => true,
            ]);
        }

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'vendedor_id' => $vendedor?->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $cliente = Person::query()->create([
            'codigo' => 'CL'.$suffix,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS LOCK '.$suffix,
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $peca = Product::query()->create([
            'codigo' => 'PL'.$suffix,
            'descricao' => 'PECA OS LOCK '.$suffix,
            'unidade' => 'UN',
            'preco_venda' => 20,
            'estoque' => 10,
            'ativo' => true,
        ]);

        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'numero' => (string) (900000 + random_int(1, 99999)),
            'situacao' => $situacao,
            'cliente_id' => $cliente->id,
            'atendente_id' => $vendedor?->id,
            'total_geral' => 100,
            'total_produtos' => 40,
            'total_servicos' => 60,
        ])->save();

        foreach ([['P', $peca->id, 2, 20], ['S', null, 1, 60]] as [$tipo, $productId, $qtd, $preco]) {
            $item = new OrdemServicoItem();
            $item->forceFill([
                'ordem_servico_id' => $os->id,
                'empresa_id' => $empresa->id,
                'tipo' => $tipo,
                'product_id' => $productId,
                'funcionario_id' => $vendedor?->id,
                'qtd' => $qtd,
                'preco' => $preco,
                'total' => $qtd * $preco,
            ])->save();
        }

        return [$os->fresh(), $peca, $user];
    }

    private function nfse(OrdemServico $os, string $status): Nfse
    {
        $nfse = new Nfse();
        $nfse->forceFill([
            'empresa_id' => $os->empresa_id,
            'ordem_servico_id' => $os->id,
            'status' => $status,
            'numero_dps' => random_int(1000, 9999),
            'numero_nfse' => $status === Nfse::STATUS_ABERTA ? null : (string) random_int(100, 999),
            'tomador_nome' => 'CLIENTE OS',
            'competencia' => now()->toDateString(),
            'data_emissao' => now()->toDateString(),
            'serie_dps' => '1',
        ])->save();

        return $nfse;
    }
}
