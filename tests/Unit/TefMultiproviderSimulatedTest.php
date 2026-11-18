<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Terminal;
use App\Models\TefIntencao;
use App\Support\Erp\Tef\Data\TefChargeCommand;
use App\Support\Erp\Tef\Providers\ScopeTefElectronicProvider;
use App\Support\Erp\Tef\Scope\ScopeTefPdvGate;
use App\Support\Erp\Tef\Scope\ScopeTefTerminalConfig;
use App\Support\Erp\Tef\Simulation\SimulatedTefProvider;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use App\Support\Erp\Tef\TefIntentionLifecycle;
use App\Support\Erp\Tef\TefProviderId;
use App\Support\Erp\Tef\TefProviderResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Etapa 1 multiprovedor: simulador + Scope desativado + isolamento.
 * Schema mínimo (sem MigratesSqliteMemory) — igual ao SuperTEF.
 */
class TefMultiproviderSimulatedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('unitec.scope_tef_pdv.enabled', false);
        Config::set('unitec.scope_tef_pdv.sdk_available', false);
        $this->criarSchemaMinimo();
    }

    public function test_aprovacao_e_vinculo_a_venda(): void
    {
        [$empresaA, $terminalA] = $this->empresaETerminal(1);
        $provider = new SimulatedTefProvider();
        $provider->setDefaultScenario('approve');

        $intencao = $provider->createIntention([
            'empresa_id' => (int) $empresaA->id,
            'terminal' => $terminalA,
            'amount' => 10.50,
            'cupom_fingerprint' => hash('sha256', 'cupom-a'),
            'pos_id' => 1,
        ]);

        $envio = $provider->sendCharge($intencao, $terminalA);
        $this->assertTrue($envio->ok);
        $this->assertTrue($envio->intention->isPaga());
        $this->assertSame('SIM-NSU', $envio->intention->nsu);

        $linked = (new TefIntentionLifecycle())->linkToSale($envio->intention, 9001, 10);
        $this->assertSame(9001, (int) $linked->pdv_venda_id);
        $this->assertTrue($linked->podeVincularAoCupom(hash('sha256', 'cupom-a')) === false); // já vinculada
    }

    public function test_recusa(): void
    {
        [, $terminal] = $this->empresaETerminal(1);
        $provider = new SimulatedTefProvider();
        $intencao = $provider->createIntention([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 5,
            'pos_id' => 1,
        ]);
        $provider->setScenarioForOrder($intencao->order_id, 'decline');

        $res = $provider->sendCharge($intencao, $terminal);
        $this->assertFalse($res->ok);
        $this->assertSame(TefIntencao::STATUS_FALHA, $res->intention->status);
    }

    public function test_cancelamento_pendente(): void
    {
        [, $terminal] = $this->empresaETerminal(1);
        $provider = new SimulatedTefProvider();
        $intencao = $provider->createIntention([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 7,
            'pos_id' => 1,
        ]);
        $provider->setScenarioForOrder($intencao->order_id, 'pending_confirm');
        $provider->sendCharge($intencao, $terminal);

        $cancel = $provider->cancel($intencao->fresh(), $terminal);
        $this->assertTrue($cancel->ok);
        $this->assertSame(TefIntencao::STATUS_CANCELADA, $cancel->intention->status);
    }

    public function test_timeout_e_recuperacao_sem_reenvio(): void
    {
        [, $terminal] = $this->empresaETerminal(1);
        $provider = new SimulatedTefProvider();
        $intencao = $provider->createIntention([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 3.33,
            'pos_id' => 1,
        ]);
        $provider->setScenarioForOrder($intencao->order_id, 'timeout');

        $envio = $provider->sendCharge($intencao, $terminal);
        $this->assertTrue($envio->transportError);
        $this->assertSame(TefIntencao::STATUS_INDETERMINADA, $envio->intention->status);
        $this->assertSame(1, $provider->sendCountFor($intencao->order_id));

        $retry = $provider->sendCharge($envio->intention, $terminal);
        $this->assertTrue($retry->duplicatePrevented);
        $this->assertSame(1, $provider->sendCountFor($intencao->order_id));

        $provider->setScenarioForOrder($intencao->order_id, 'approve');
        $poll = $provider->poll($envio->intention->fresh(), $terminal);
        $this->assertTrue($poll->intention->isPaga());
    }

    public function test_perda_de_conexao(): void
    {
        [, $terminal] = $this->empresaETerminal(1);
        $provider = new SimulatedTefProvider();
        $intencao = $provider->createIntention([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 2,
            'pos_id' => 1,
        ]);

        $res = $provider->sendCharge($intencao, $terminal, new TefChargeCommand(meta: ['scenario' => 'disconnect']));
        $this->assertTrue($res->transportError);
        $this->assertSame(TefIntencao::STATUS_INDETERMINADA, $res->intention->status);
    }

    public function test_repeticao_mesma_solicitacao_nao_reenvia(): void
    {
        [, $terminal] = $this->empresaETerminal(1);
        $provider = new SimulatedTefProvider();
        $fp = hash('sha256', 'mesmo-cupom');
        $life = new TefIntentionLifecycle();

        $i1 = $provider->createIntention([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 9.99,
            'cupom_fingerprint' => $fp,
            'pos_id' => 2,
        ]);
        $provider->sendCharge($i1, $terminal, new TefChargeCommand(meta: ['scenario' => 'pending_confirm']));

        $reuse = $life->findReusableOpen($terminal, SimulatedTefProvider::PROVIDER_ID, 9.99, $fp, 2);
        $this->assertNotNull($reuse);
        $this->assertSame($i1->id, $reuse->id);

        $again = $provider->sendCharge($reuse, $terminal);
        $this->assertTrue($again->duplicatePrevented);
        $this->assertSame(1, $provider->sendCountFor($i1->order_id));
    }

    public function test_falha_apos_aprovacao_mantem_pago_sem_venda(): void
    {
        [, $terminal] = $this->empresaETerminal(1);
        $provider = new SimulatedTefProvider();
        $intencao = $provider->createIntention([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 15,
            'pos_id' => 1,
            'cupom_fingerprint' => hash('sha256', 'fa'),
        ]);
        $provider->setScenarioForOrder($intencao->order_id, 'fail_after_approve');

        $res = $provider->sendCharge($intencao, $terminal);
        $this->assertFalse($res->ok);
        $this->assertTrue($res->intention->isPaga());
        $this->assertNull($res->intention->pdv_venda_id);
        $this->assertTrue(
            (new TefIntentionLifecycle())->slotOccupied($terminal, SimulatedTefProvider::PROVIDER_ID, 1)
        );
    }

    public function test_confirmacao_ou_desfazimento_pendente(): void
    {
        [, $terminal] = $this->empresaETerminal(1);
        $provider = new SimulatedTefProvider();
        $intencao = $provider->createIntention([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 4,
            'pos_id' => 1,
        ]);
        $provider->setScenarioForOrder($intencao->order_id, 'pending_undo');
        $provider->sendCharge($intencao, $terminal);

        $cancel = $provider->cancel($intencao->fresh(), $terminal);
        // pendente_confirm path — pending_undo after send is PENDENTE, cancel works.
        // For indeterminada + pending_undo via timeout:
        $i2 = $provider->createIntention([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 4.5,
            'pos_id' => 3,
        ]);
        $provider->setScenarioForOrder($i2->order_id, 'timeout');
        $provider->sendCharge($i2, $terminal);
        $pend = $provider->cancel($i2->fresh(), $terminal);
        $this->assertFalse($pend->ok);
        $this->assertTrue((bool) ($pend->meta['pending_undo'] ?? false));

        $this->assertTrue($cancel->ok || $cancel->intention->status === TefIntencao::STATUS_CANCELADA);
    }

    public function test_isolamento_entre_empresas_e_terminais(): void
    {
        [$emp1, $t1] = $this->empresaETerminal(1);
        [$emp2, $t2] = $this->empresaETerminal(2);
        $provider = new SimulatedTefProvider();
        $life = new TefIntentionLifecycle();

        $a = $provider->createIntention([
            'empresa_id' => (int) $emp1->id,
            'terminal' => $t1,
            'amount' => 1,
            'pos_id' => 10,
            'cupom_fingerprint' => hash('sha256', 'e1'),
        ]);
        $provider->sendCharge($a, $t1, new TefChargeCommand(meta: ['scenario' => 'pending_confirm']));

        $this->assertTrue($life->slotOccupied($t1, SimulatedTefProvider::PROVIDER_ID, 10));
        $this->assertFalse($life->slotOccupied($t2, SimulatedTefProvider::PROVIDER_ID, 10));

        $b = $provider->createIntention([
            'empresa_id' => (int) $emp2->id,
            'terminal' => $t2,
            'amount' => 1,
            'pos_id' => 10,
            'cupom_fingerprint' => hash('sha256', 'e2'),
        ]);
        $this->assertNotSame($a->id, $b->id);
        $this->assertSame((int) $emp2->id, (int) $b->empresa_id);

        $this->expectException(\RuntimeException::class);
        $provider->poll($a, $t2);
    }

    public function test_scope_adapter_desativado_sem_sdk(): void
    {
        $this->assertFalse(ScopeTefPdvGate::sdkAvailable());
        $this->assertFalse(ScopeTefPdvGate::featureEnabled());

        $scope = new ScopeTefElectronicProvider();
        $this->assertFalse($scope->isOperational());

        [, $terminal] = $this->empresaETerminal(1);
        $terminal->tef_extra = ScopeTefTerminalConfig::mergeIntoExtra(
            SuperTefTerminalConfig::writePayload([
                'ativo' => false,
                'provedor' => TefProviderId::SCOPE,
            ]),
            [
                'ativo' => false,
                'empresa_codigo' => 'E1',
                'loja_codigo' => 'L1',
                'terminal_codigo' => 'T1',
                'credencial' => 'segredo-scope',
            ]
        );
        $terminal->save();

        $diag = $scope->diagnose($terminal->fresh());
        $this->assertFalse($diag->operational);
        $this->assertStringContainsString('SDK', $diag->message);

        $res = $scope->sendCharge(new TefIntencao(), $terminal);
        $this->assertFalse($res->ok);
        $this->assertFalse($res->operational);
    }

    public function test_resolver_escolhe_provedor_do_terminal(): void
    {
        [, $terminal] = $this->empresaETerminal(1);
        $terminal->tef_extra = SuperTefTerminalConfig::writePayload([
            'ativo' => false,
            'provedor' => TefProviderId::SCOPE,
        ]);
        $terminal->save();

        $resolver = new TefProviderResolver();
        $this->assertSame(TefProviderId::SCOPE, $resolver->providerIdForTerminal($terminal->fresh()));
        $this->assertSame(TefProviderId::SCOPE, $resolver->forTerminal($terminal->fresh())->id());

        $terminal->tef_extra = SuperTefTerminalConfig::writePayload([
            'ativo' => true,
            'provedor' => TefProviderId::SUPERTEF,
            'pos_id' => 1,
            'cliente_chave' => 'x',
        ]);
        $terminal->save();
        $this->assertSame(TefProviderId::SUPERTEF, $resolver->providerIdForTerminal($terminal->fresh()));
    }

    public function test_sanitize_remove_dados_de_cartao(): void
    {
        $life = new TefIntentionLifecycle();
        $clean = $life->sanitizeRaw([
            'nsu' => '123',
            'pan' => '4111111111111111',
            'cvv' => '123',
            'payment_data' => [
                'brand' => 'VISA',
                'track2' => 'secret',
            ],
        ]);

        $this->assertSame('123', $clean['nsu']);
        $this->assertArrayNotHasKey('pan', $clean);
        $this->assertArrayNotHasKey('cvv', $clean);
        $this->assertSame('VISA', $clean['payment_data']['brand']);
        $this->assertArrayNotHasKey('track2', $clean['payment_data']);
    }

    /**
     * @return array{0: Empresa, 1: Terminal}
     */
    private function empresaETerminal(int $seed): array
    {
        $empresa = Empresa::query()->create([
            'razao_social' => 'Empresa TEF '.$seed,
            'nome_fantasia' => 'TEF '.$seed,
            'cnpj' => sprintf('%014d', 10000000000000 + $seed),
        ]);

        $terminal = new Terminal();
        $terminal->forceFill([
            'empresa_id' => (int) $empresa->id,
            'nome' => 'PDV-'.$seed,
            'usa_tef' => false,
            'tef_extra' => SuperTefTerminalConfig::writePayload([
                'ativo' => false,
                'provedor' => TefProviderId::SUPERTEF,
            ]),
        ]);
        $terminal->save();

        return [$empresa, $terminal->fresh()];
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('tef_intencoes');
        Schema::dropIfExists('terminais');
        Schema::dropIfExists('empresas');

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj')->nullable();
            $table->timestamps();
        });

        Schema::create('terminais', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('nome', 80)->nullable();
            $table->boolean('usa_tef')->default(false);
            $table->longText('tef_extra')->nullable();
            $table->timestamps();
        });

        Schema::create('tef_intencoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('terminal_id');
            $table->unsignedBigInteger('caixa_sessao_id')->nullable();
            $table->unsignedBigInteger('pdv_venda_id')->nullable();
            $table->unsignedBigInteger('pdv_venda_pagamento_id')->nullable();
            $table->boolean('requer_reconciliacao')->default(false);
            $table->timestamp('ciencia_reconciliacao_em')->nullable();
            $table->string('provedor', 20)->default('supertef');
            $table->string('order_id', 64)->unique();
            $table->string('cupom_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('payment_uniqueid')->nullable();
            $table->string('provider_external_id', 80)->nullable();
            $table->unsignedInteger('pos_id')->default(0);
            $table->decimal('amount', 12, 2);
            $table->string('transaction_type', 8)->default('1');
            $table->unsignedTinyInteger('installment_count')->default(1);
            $table->unsignedTinyInteger('installment_type')->default(1);
            $table->string('status', 24)->default('criada');
            $table->boolean('post_enviado')->default(false);
            $table->timestamp('post_enviado_em')->nullable();
            $table->timestamp('last_poll_at')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->string('nsu', 40)->nullable();
            $table->string('autorizacao', 40)->nullable();
            $table->string('bandeira', 40)->nullable();
            $table->string('acquirer_cnpj', 14)->nullable();
            $table->string('payment_message', 191)->nullable();
            $table->json('raw_last')->nullable();
            $table->timestamps();
        });
    }
}
