<?php

namespace Tests\Feature;

use App\Filament\Pages\Concerns\ManagesPdvReimprimirNfce;
use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\User;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Schema mínimo (sem MigratesSqliteMemory): migrate :memory: quebra em migration legada.
 */
class PdvReimprimirNfceLivewireTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErpContext::clearMemo();
        $this->criarSchemaMinimo();
    }

    public function test_open_com_autorizada_abre_modal_e_confirm_chama_impressao(): void
    {
        [$empresa, $sessao, $user, $venda] = $this->cenarioAutorizada();
        $this->actingAs($user);
        session(['erp_empresa_id' => (int) $empresa->id]);
        ErpContext::clearMemo();

        $harness = $this->novoHarness((int) $sessao->id);
        $harness->call('openReimprimirNfceModal')
            ->assertSet('activeModal', 'reimprimir_nfce')
            ->assertSet('selectedReimprimirNfceIndex', 0)
            ->assertCount('reimprimirNfceResults', 1);

        $harness->call('confirmReimprimirNfce')
            ->assertSet('activeModal', null)
            ->assertSet('printedVendaId', (int) $venda->id)
            ->assertSet('printCalls', 1);

        $this->assertSame(1, PdvVendaNfce::query()->count());
        $this->assertSame(PdvVendaNfce::STATUS_AUTORIZADA, PdvVendaNfce::query()->value('status'));
        $this->assertSame((int) $venda->total, (int) PdvVenda::query()->value('total'));
    }

    public function test_open_sem_nfce_nao_abre_modal(): void
    {
        [$empresa, $sessao, $user] = $this->caixaPronto();
        $this->actingAs($user);
        session(['erp_empresa_id' => (int) $empresa->id]);
        ErpContext::clearMemo();

        $this->novoHarness((int) $sessao->id)
            ->call('openReimprimirNfceModal')
            ->assertSet('activeModal', null)
            ->assertSet('printedVendaId', null)
            ->assertCount('reimprimirNfceResults', 0);
    }

    public function test_rejeitada_nao_abre_como_autorizada(): void
    {
        [$empresa, $sessao, $user] = $this->caixaPronto();
        $this->vendaComNfce($sessao, $user, $empresa, 7, PdvVendaNfce::STATUS_REJEITADA);
        $this->actingAs($user);
        session(['erp_empresa_id' => (int) $empresa->id]);
        ErpContext::clearMemo();

        $this->novoHarness((int) $sessao->id)
            ->call('openReimprimirNfceModal')
            ->assertSet('activeModal', null)
            ->assertCount('reimprimirNfceResults', 0)
            ->assertSet('printedVendaId', null);
    }

    public function test_botao_e_atalho_mesma_rotina_reimpressao_repetida_sem_duplicar(): void
    {
        [$empresa, $sessao, $user, $venda] = $this->cenarioAutorizada();
        $this->actingAs($user);
        session(['erp_empresa_id' => (int) $empresa->id]);
        ErpContext::clearMemo();

        $harness = $this->novoHarness((int) $sessao->id);
        $harness->call('openReimprimirNfceModal')->call('confirmReimprimirNfce');
        $harness->call('openReimprimirNfceModal')->call('confirmReimprimirNfce');

        $harness->assertSet('printCalls', 2)
            ->assertSet('printedVendaId', (int) $venda->id);
        $this->assertSame(1, PdvVendaNfce::query()->count());
        $this->assertSame(PdvVendaNfce::STATUS_AUTORIZADA, (string) PdvVendaNfce::query()->value('status'));
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('pdv_venda_nfce');
        Schema::dropIfExists('pdv_vendas');
        Schema::dropIfExists('pdv_caixa_sessoes');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('empresa_id');
            $table->decimal('valor_abertura', 12, 2)->default(0);
            $table->timestamp('aberto_em')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('acrescimo', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('forma_pagamento')->nullable();
            $table->boolean('fiscal')->default(false);
            $table->string('situacao', 1)->default('F');
            $table->string('vendedor_nome')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_venda_nfce', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('operacao')->nullable();
            $table->string('modelo', 2)->nullable();
            $table->string('serie')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->string('cnf')->nullable();
            $table->string('chave', 44)->nullable();
            $table->string('protocolo')->nullable();
            $table->string('status')->nullable();
            $table->boolean('simulada')->default(false);
            $table->longText('xml')->nullable();
            $table->text('qr_code_conteudo')->nullable();
            $table->timestamp('autorizada_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array{0:Empresa,1:PdvCaixaSessao,2:User,3:PdvVenda}
     */
    private function cenarioAutorizada(): array
    {
        [$empresa, $sessao, $user] = $this->caixaPronto();
        $venda = $this->vendaComNfce($sessao, $user, $empresa, 5, PdvVendaNfce::STATUS_AUTORIZADA, [
            'chave' => str_repeat('4', 44),
            'protocolo' => 'ABC123',
        ]);

        return [$empresa, $sessao, $user, $venda];
    }

    /**
     * @return array{0:Empresa,1:PdvCaixaSessao,2:User}
     */
    private function caixaPronto(): array
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA NFCE LW',
            'ativo' => true,
        ]);
        $user = User::query()->create([
            'empresa_id' => $empresa->id,
            'name' => 'Operador',
            'email' => 'lw-'.uniqid('', true).'@test.local',
            'password' => 'secret',
        ]);
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        return [$empresa, $sessao, $user];
    }

    /**
     * @param  array<string, mixed>  $nfceExtra
     */
    private function vendaComNfce(
        PdvCaixaSessao $sessao,
        User $user,
        Empresa $empresa,
        int $numero,
        string $status,
        array $nfceExtra = [],
    ): PdvVenda {
        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'numero' => $numero,
            'subtotal' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 10,
            'forma_pagamento' => 'DINHEIRO',
            'fiscal' => true,
            'situacao' => 'F',
            'vendedor_nome' => 'OPERADOR',
        ]);

        PdvVendaNfce::query()->create(array_merge([
            'pdv_venda_id' => $venda->id,
            'empresa_id' => $empresa->id,
            'operacao' => 'nfce_transmitir',
            'modelo' => '65',
            'serie' => '1',
            'numero' => $numero,
            'status' => $status,
            'simulada' => false,
            'autorizada_em' => $status === PdvVendaNfce::STATUS_AUTORIZADA ? now() : null,
        ], $nfceExtra));

        return $venda->fresh(['nfce']);
    }

    private function novoHarness(int $sessaoId)
    {
        return Livewire::test(PdvReimprimirNfceHarness::class, [
            'caixaSessaoId' => $sessaoId,
            'caixaAberto' => true,
        ]);
    }
}

class PdvReimprimirNfceHarness extends Component
{
    use ManagesPdvReimprimirNfce;

    public bool $caixaAberto = true;

    public ?int $caixaSessaoId = null;

    public ?string $activeModal = null;

    public ?int $printedVendaId = null;

    public int $printCalls = 0;

    public function mount(int $caixaSessaoId, bool $caixaAberto = true): void
    {
        $this->caixaSessaoId = $caixaSessaoId;
        $this->caixaAberto = $caixaAberto;
    }

    public function render(): string
    {
        return '<div></div>';
    }

    public function openPdvModal(string $modal): void
    {
        $this->activeModal = $modal;
    }

    public function closePdvModal(): void
    {
        $this->activeModal = null;
    }

    protected function notifyPdvError(string $title, ?string $body = null): void
    {
        //
    }

    protected function pdvConfig(): object
    {
        return new class
        {
            public function viasImpressao(): int
            {
                return 1;
            }
        };
    }

    protected function imprimirNfceCupomPosVenda(int $vendaId, int $copias = 1): void
    {
        $this->printedVendaId = $vendaId;
        $this->printCalls++;
    }
}
