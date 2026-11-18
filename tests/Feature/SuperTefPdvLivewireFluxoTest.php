<?php

namespace Tests\Feature;

use App\Filament\Pages\Concerns\ManagesPdvSuperTef;
use App\Models\Empresa;
use App\Models\TefIntencao;
use App\Models\Terminal;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Tef\SuperTefApiClient;
use App\Support\Erp\Tef\SuperTefPaymentService;
use App\Support\Erp\Tef\SuperTefSofthouseToken;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercita o fluxo Livewire SuperTEF (abrir → enviar → poll pago → auto-finalizar)
 * sem montar o PdvPage completo.
 */
class SuperTefPdvLivewireFluxoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('unitec.supertef_pdv.enabled', true);
        Config::set('unitec.supertef_pdv.allow_local', true);
        Config::set('unitec.supertef_pdv.poll_seconds', 4);
        $this->criarSchemaMinimo();
        $this->app->instance(
            SuperTefPaymentService::class,
            new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste')),
        );
    }

    public function test_primeiro_abrir_so_expoe_modal_iniciando(): void
    {
        $terminal = $this->terminalPronto();
        $harness = $this->novoHarness($terminal, 1.0);

        $harness->call('abrirParaTeste');

        $harness->assertSet('finalizarSuperTefAberta', true)
            ->assertSet('finalizarSuperTefEnviando', true)
            ->assertSet('finalizarSuperTefStatus', 'Iniciando cobrança…')
            ->assertSet('finalizarSuperTefIntencaoId', null)
            ->assertSet('finalizarSuperTefPosLabel', '5665');

        $this->assertSame(0, TefIntencao::query()->count());
        Http::assertNothingSent();
    }

    public function test_enviar_pendente_depois_poll_pago_conclui_sem_segundo_concluir(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            $orderId = '';
            if ($request->method() === 'POST') {
                $orderId = (string) ($request['order_id'] ?? '');
            } else {
                $orderId = (string) (TefIntencao::query()->orderByDesc('id')->value('order_id') ?? '');
            }

            if ($request->method() === 'POST' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/pagamentos')) {
                return Http::response([
                    'payment_uniqueid' => 9001,
                    'payment_status' => 1,
                    'payment_message' => 'Pendente',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '1.00',
                        'order_id' => $orderId,
                        'transaction_type' => 1,
                        'installment_count' => 1,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [],
                ], 201);
            }

            static $gets = 0;
            if ($request->method() === 'GET' && str_contains($url, '/pagamentos/by-uniqueid/')) {
                $gets++;
                if ($gets === 1) {
                    return Http::response([
                        'payment_uniqueid' => 9001,
                        'payment_status' => 1,
                        'payment_message' => 'Pendente',
                        'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => $orderId],
                        'payment_data' => [],
                    ], 200);
                }

                return Http::response([
                    'payment_uniqueid' => 9001,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => $orderId],
                    'payment_data' => [
                        'nsu' => '111',
                        'authorization_code' => '222',
                        'brand' => 'VISA',
                    ],
                ], 200);
            }

            return Http::response(['error' => 'unexpected '.$url], 500);
        });

        $harness = $this->novoHarness($terminal, 1.0);
        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');

        $harness->assertSet('finalizarSuperTefAberta', true)
            ->assertSet('finalizarSuperTefEnviando', false)
            ->assertSet('finalizarSuperTefConfirmado', false)
            ->assertSet('finalizarSuperTefStatus', 'Aguardando pagamento.');

        $intencaoId = (int) $harness->get('finalizarSuperTefIntencaoId');
        $this->assertGreaterThan(0, $intencaoId);
        $this->assertSame(1, TefIntencao::query()->where('post_enviado', true)->count());

        // Libera o gap de poll.
        $harness->set('finalizarSuperTefLastPollTs', time() - 10);
        $harness->call('pollFinalizarSuperTef'); // ainda pendente
        $harness->assertSet('finalizarSuperTefConfirmado', false);

        $harness->set('finalizarSuperTefLastPollTs', time() - 10);
        $harness->call('pollEConcluirSePagoParaTeste'); // status 4 → wire:init conclusao

        $harness->assertSet('finalizarSuperTefConfirmado', true)
            ->assertSet('finalizarPedidoGravado', true)
            ->assertSet('finalizarSuperTefStatus', 'Pedido concluído');

        $intencao = TefIntencao::query()->find($intencaoId);
        $this->assertNotNull($intencao);
        $this->assertTrue($intencao->isPaga());
        $this->assertNotNull($intencao->pdv_venda_id);
        Http::assertSentCount(3); // 1 POST + 2 GET
    }

    public function test_falha_apos_aprovacao_preserva_intencao_sem_novo_post(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $harness = $this->novoHarness($terminal, 1.0, falharGravacao: true);
        $fp = $harness->instance()->pdvCupomFingerprintSuperTefPublico();

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'cupom_fingerprint' => $fp,
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 9002,
            'nsu' => '1',
            'autorizacao' => '2',
            'bandeira' => 'VISA',
            'pago_em' => now(),
        ])->save();

        $harness->set('finalizarSuperTefPendingOperacao', 'pedido');
        $harness->set('finalizarSuperTefIntencaoId', (int) $intencao->id);
        $harness->call('aplicarPagoParaTeste', (int) $intencao->id);

        $harness->assertSet('finalizarSuperTefConfirmado', true)
            ->assertSet('finalizarSuperTefAberta', true)
            ->assertSet('finalizarPedidoGravado', false)
            ->assertSet('finalizarSuperTefStatus', 'Pagamento aprovado.');

        $this->assertNotSame('', (string) $harness->get('finalizarSuperTefErro'));
        $fresh = $intencao->fresh();
        $this->assertTrue($fresh->isPaga());
        $this->assertNull($fresh->pdv_venda_id);
        $this->assertFalse($fresh->requerReconciliacao());
        Http::assertNothingSent();
    }

    public function test_valida_finalizar_rejeita_divergencia_de_valor_apos_aprovado(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $harness = $this->novoHarness($terminal, 1.0);
        $fp = $harness->instance()->pdvCupomFingerprintSuperTefPublico();

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'cupom_fingerprint' => $fp,
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 9010,
            'nsu' => '10',
            'autorizacao' => '20',
            'bandeira' => 'VISA',
            'pago_em' => now(),
        ])->save();

        $harness->set('finalizarSuperTefConfirmado', true);
        $harness->set('finalizarSuperTefIntencaoId', (int) $intencao->id);

        $ok = $harness->instance()->auditoriaValidaSuperTefFinalizar();
        $this->assertNull($ok);

        // Operador altera valor da linha após aprovação (sem nova cobrança).
        $pagamentos = $harness->get('finalizarPagamentos');
        $pagamentos[0]['valor'] = '9,99';
        $harness->set('finalizarPagamentos', $pagamentos);

        $erro = $harness->instance()->auditoriaValidaSuperTefFinalizar();
        $this->assertNotNull($erro);
        $this->assertStringContainsString('diverge', (string) $erro);
        Http::assertNothingSent();
    }

    public function test_fechar_janela_nao_cancela_cobranca_pendente(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-CLOSE-1',
            'cupom_fingerprint' => 'fp-close',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9101,
        ])->save();

        Http::fake();

        $harness = $this->novoHarness($terminal, 1.0);
        $harness->set('finalizarSuperTefAberta', true);
        $harness->set('finalizarSuperTefIntencaoId', (int) $intencao->id);
        $harness->set('finalizarSuperTefStatus', 'Aguardando pagamento.');
        $harness->call('cancelFinalizarSuperTef');

        $harness->assertSet('finalizarSuperTefAberta', false)
            ->assertSet('finalizarSuperTefIntencaoId', null);
        $fresh = $intencao->fresh();
        $this->assertSame(TefIntencao::STATUS_PENDENTE, $fresh->status);
        $this->assertSame(9101, (int) $fresh->payment_uniqueid);
        Http::assertNothingSent();
    }

    public function test_cancelar_pagamento_pendente_confirmado_libera_finalizacao(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-CANC-1',
            'cupom_fingerprint' => 'fp-canc',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9102,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos/by-uniqueid/9102' => Http::sequence()
                ->push([
                    'payment_uniqueid' => 9102,
                    'payment_status' => 1,
                    'payment_message' => 'Pendente',
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANC-1'],
                    'payment_data' => [],
                ], 200)
                ->push([
                    'payment_uniqueid' => 9102,
                    'payment_status' => 5,
                    'payment_message' => 'Cancelado',
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANC-1'],
                    'payment_data' => [],
                ], 200),
            'api.supertef.com.br/api/pagamentos/cancelar/9102' => Http::response([
                'payment_uniqueid' => 9102,
                'payment_status' => 5,
                'payment_message' => 'Cancelado',
                'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANC-1'],
                'payment_data' => [],
            ], 200),
        ]);

        $harness = $this->novoHarness($terminal, 1.0);
        $harness->set('finalizarSuperTefAberta', true);
        $harness->set('finalizarSuperTefIntencaoId', (int) $intencao->id);
        $harness->set('finalizarSuperTefStatus', 'Aguardando pagamento.');
        $harness->set('finalizarSuperTefEnviando', false);
        $harness->call('cancelarPagamentoSuperTef');

        $harness->assertSet('finalizarSuperTefAberta', false)
            ->assertSet('finalizarSuperTefIntencaoId', null)
            ->assertSet('finalizarSuperTefConfirmado', false);
        $this->assertSame(TefIntencao::STATUS_CANCELADA, $intencao->fresh()->status);
    }

    public function test_corrida_aprovacao_durante_cancelamento_preserva_pago(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        $harness = $this->novoHarness($terminal, 1.0);
        $fp = $harness->instance()->pdvCupomFingerprintSuperTefPublico();

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-RACE-1',
            'cupom_fingerprint' => $fp,
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9103,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos/by-uniqueid/9103' => Http::sequence()
                ->push([
                    'payment_uniqueid' => 9103,
                    'payment_status' => 1,
                    'payment_message' => 'Pendente',
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-RACE-1'],
                    'payment_data' => [],
                ], 200)
                ->push([
                    'payment_uniqueid' => 9103,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-RACE-1'],
                    'payment_data' => [
                        'nsu' => '77',
                        'authorization_code' => '88',
                        'brand' => 'VISA',
                    ],
                ], 200),
            'api.supertef.com.br/api/pagamentos/cancelar/9103' => Http::response([
                'error' => 'Já aprovado',
            ], 409),
        ]);

        $harness->set('finalizarSuperTefAberta', true);
        $harness->set('finalizarSuperTefIntencaoId', (int) $intencao->id);
        $harness->set('finalizarSuperTefStatus', 'Aguardando pagamento.');
        $harness->set('finalizarSuperTefPendingOperacao', 'pedido');
        $harness->call('cancelarEConcluirSePagoParaTeste');

        $harness->assertSet('finalizarSuperTefConfirmado', true)
            ->assertSet('finalizarPedidoGravado', true);
        $this->assertTrue($intencao->fresh()->isPaga());
        $this->assertNotNull($intencao->fresh()->pdv_venda_id);
    }

    public function test_f5_recupera_pendente_sem_novo_post(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $harness = $this->novoHarness($terminal, 1.0);
        $fp = $harness->instance()->pdvCupomFingerprintSuperTefPublico();

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'caixa_sessao_id' => 3,
            'order_id' => 'UNI-TEF-F5-1',
            'cupom_fingerprint' => $fp,
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9104,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos/by-uniqueid/9104' => Http::response([
                'payment_uniqueid' => 9104,
                'payment_status' => 1,
                'payment_message' => 'Pendente',
                'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-F5-1'],
                'payment_data' => [],
            ], 200),
        ]);

        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');

        $harness->assertSet('finalizarSuperTefIntencaoId', (int) $intencao->id)
            ->assertSet('finalizarSuperTefStatus', 'Aguardando pagamento.');
        $this->assertSame(1, TefIntencao::query()->count());
        Http::assertSentCount(1); // só GET, sem POST
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_fechar_e_trocar_cupom_mesmo_valor_nao_reusa_pendente(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $harness = $this->novoHarness($terminal, 10.0);
        $fpAtual = $harness->instance()->pdvCupomFingerprintSuperTefPublico();

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 10.0,
            'caixa_sessao_id' => 3,
            'order_id' => 'UNI-TEF-CUPOM-B',
            'cupom_fingerprint' => 'fp-cupom-anterior-diferente',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9201,
        ])->save();

        $this->assertNotSame('fp-cupom-anterior-diferente', $fpAtual);
        Http::fake();

        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');

        $harness->assertSet('finalizarPedidoGravado', false)
            ->assertSet('finalizarSuperTefConfirmado', false)
            ->assertSet('finalizarSuperTefResolverAberta', true)
            ->assertSet('finalizarSuperTefResolverIntencaoId', (int) $intencao->id);
        $this->assertSame(1, TefIntencao::query()->count());
        $this->assertSame(TefIntencao::STATUS_PENDENTE, $intencao->fresh()->status);
        $this->assertFalse($intencao->fresh()->requerReconciliacao());
        Http::assertNothingSent();
    }

    public function test_verificar_pendente_desatualizado_que_ja_esta_pago_na_api(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        $harness = $this->novoHarness($terminal, 2.0);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'caixa_sessao_id' => 3,
            'order_id' => 'UNI-TEF-STALE-PEND',
            'cupom_fingerprint' => 'fp-antigo',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9301,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos/by-uniqueid/9301' => Http::response([
                'payment_uniqueid' => 9301,
                'payment_status' => 4,
                'payment_message' => 'Pago',
                'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-STALE-PEND'],
                'payment_data' => ['nsu' => '1', 'authorization_code' => '2', 'brand' => 'VISA'],
            ], 200),
        ]);

        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');
        $harness->assertSet('finalizarSuperTefResolverAberta', true);
        $harness->call('verificarPagamentoAnteriorSuperTef');

        $harness->assertSet('finalizarSuperTefResolverVerificado', true);
        $this->assertTrue($harness->instance()->finalizarSuperTefResolverPodeTratarPago());
        $this->assertTrue($intencao->fresh()->isPaga());
        $this->assertNull($intencao->fresh()->pdv_venda_id);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_cancelar_cobranca_anterior_libera_pos_apos_confirmacao_api(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        $harness = $this->novoHarness($terminal, 2.0);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'caixa_sessao_id' => 3,
            'order_id' => 'UNI-TEF-CANC-ANT',
            'cupom_fingerprint' => 'fp-antigo-canc',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9302,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos/by-uniqueid/9302' => Http::sequence()
                ->push([
                    'payment_uniqueid' => 9302,
                    'payment_status' => 1,
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANC-ANT'],
                    'payment_data' => [],
                ], 200)
                ->push([
                    'payment_uniqueid' => 9302,
                    'payment_status' => 1,
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANC-ANT'],
                    'payment_data' => [],
                ], 200)
                ->push([
                    'payment_uniqueid' => 9302,
                    'payment_status' => 5,
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANC-ANT'],
                    'payment_data' => [],
                ], 200),
            'api.supertef.com.br/api/pagamentos/cancelar/9302' => Http::response([
                'payment_uniqueid' => 9302,
                'payment_status' => 5,
                'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANC-ANT'],
                'payment_data' => [],
            ], 200),
        ]);

        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');
        $harness->call('verificarPagamentoAnteriorSuperTef');
        $this->assertTrue($harness->instance()->finalizarSuperTefResolverPodeCancelar());
        $harness->call('solicitarCancelamentoPagamentoAnteriorSuperTef');
        $harness->assertSet('finalizarSuperTefResolverConfirmCancelar', true);
        $harness->call('confirmarCancelamentoPagamentoAnteriorSuperTef');

        $this->assertSame(TefIntencao::STATUS_CANCELADA, $intencao->fresh()->status);
        $this->assertFalse($service->posOcupada($terminal, 5665));
        $this->assertTrue($harness->instance()->finalizarSuperTefResolverPosLiberada());
        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_ends_with(parse_url($r->url(), PHP_URL_PATH) ?: '', '/pagamentos'));
    }

    public function test_verificar_ja_cancelado_na_api_atualiza_local_e_libera_pos(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        $harness = $this->novoHarness($terminal, 2.0);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-JA-CANC',
            'cupom_fingerprint' => 'fp-ja-canc',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9303,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos/by-uniqueid/9303' => Http::response([
                'payment_uniqueid' => 9303,
                'payment_status' => 5,
                'payment_message' => 'Cancelado',
                'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-JA-CANC'],
                'payment_data' => [],
            ], 200),
        ]);

        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');
        $harness->call('verificarPagamentoAnteriorSuperTef');

        $this->assertSame(TefIntencao::STATUS_CANCELADA, $intencao->fresh()->status);
        $this->assertFalse($service->posOcupada($terminal, 5665));
        $this->assertTrue($harness->instance()->finalizarSuperTefResolverPosLiberada());
    }

    public function test_falha_comunicacao_na_consulta_mantem_protecao(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        $harness = $this->novoHarness($terminal, 2.0);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-NET-FAIL',
            'cupom_fingerprint' => 'fp-net',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 9304,
        ])->save();

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });

        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');
        $harness->call('verificarPagamentoAnteriorSuperTef');

        $harness->assertSet('finalizarSuperTefResolverVerificado', true);
        $this->assertSame(TefIntencao::STATUS_PENDENTE, $intencao->fresh()->status);
        $this->assertTrue($service->posOcupada($terminal, 5665));
        $this->assertStringContainsString('Falha', (string) $harness->get('finalizarSuperTefResolverStatusLabel'));
    }

    public function test_pago_outro_cupom_abre_resolver_sem_nova_cobranca(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $harness = $this->novoHarness($terminal, 10.0);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 10.0,
            'caixa_sessao_id' => 3,
            'order_id' => 'UNI-TEF-PAGO-OUTRO',
            'cupom_fingerprint' => 'fp-cupom-fechado',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 9202,
            'pago_em' => now(),
            'nsu' => '1',
            'autorizacao' => '2',
            'bandeira' => 'VISA',
        ])->save();

        Http::fake();

        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');

        $fresh = $intencao->fresh();
        $this->assertFalse($fresh->requerReconciliacao());
        $this->assertNull($fresh->pdv_venda_id);
        $this->assertSame(1, TefIntencao::query()->count());
        $harness->assertSet('finalizarSuperTefResolverAberta', true)
            ->assertSet('finalizarSuperTefResolverIntencaoId', (int) $intencao->id)
            ->assertSet('finalizarSuperTefConfirmNovaCobranca', false)
            ->assertSet('finalizarSuperTefAberta', false)
            ->assertSet('finalizarSuperTefConfirmado', false)
            ->assertSet('finalizarPedidoGravado', false)
            ->assertSet('finalizarSuperTefNovaCobrancaAutorizada', false);
        Http::assertNothingSent();
    }

    public function test_voltar_confirmacao_nao_dispara_cobranca(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        $harness = $this->novoHarness($terminal, 10.0);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 10.0,
            'order_id' => 'UNI-TEF-PAGO-VOLTAR',
            'cupom_fingerprint' => 'fp-voltar',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 9206,
            'pago_em' => now(),
        ])->save();

        Http::fake();
        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');
        $harness->call('voltarConfirmacaoNovaCobrancaSuperTef');

        $harness->assertSet('finalizarSuperTefConfirmNovaCobranca', false)
            ->assertSet('finalizarSuperTefNovaCobrancaAutorizada', false);
        $this->assertFalse($intencao->fresh()->requerReconciliacao());
        $this->assertSame(1, TefIntencao::query()->count());
        Http::assertNothingSent();
    }

    public function test_iniciar_nova_cobranca_exige_confirmacao_explicita(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $harness = $this->novoHarness($terminal, 10.0);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 10.0,
            'caixa_sessao_id' => 3,
            'order_id' => 'UNI-TEF-PAGO-OUTRO-2',
            'cupom_fingerprint' => 'fp-cupom-fechado-2',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 9204,
            'pago_em' => now(),
        ])->save();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, '/by-uniqueid/9204')) {
                return Http::response([
                    'payment_uniqueid' => 9204,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => ['pos_id' => 5665, 'amount' => 10, 'order_id' => 'UNI-TEF-PAGO-OUTRO-2'],
                    'payment_data' => ['nsu' => '1', 'authorization_code' => '2', 'brand' => 'VISA'],
                ], 200);
            }
            if ($request->method() === 'POST' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/pagamentos')) {
                $orderId = (string) ($request['order_id'] ?? '');

                return Http::response([
                    'payment_uniqueid' => 9205,
                    'payment_status' => 1,
                    'payment_message' => 'Pendente',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '10.00',
                        'order_id' => $orderId,
                        'transaction_type' => 1,
                        'installment_count' => 1,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [],
                ], 201);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');
        $harness->assertSet('finalizarSuperTefResolverAberta', true);
        $harness->call('verificarPagamentoAnteriorSuperTef');
        $harness->call('tratarPagamentoAnteriorAprovadoSuperTef');
        $harness->assertSet('finalizarSuperTefConfirmNovaCobranca', true);

        // Confirmação explícita → marca recon, revalida POS, inicia cobrança.
        $harness->call('confirmarNovaCobrancaSuperTef');
        $harness->assertSet('finalizarSuperTefConfirmNovaCobranca', false)
            ->assertSet('finalizarSuperTefAberta', true)
            ->assertSet('finalizarSuperTefEnviando', true)
            ->assertSet('finalizarSuperTefNovaCobrancaAutorizada', true);

        $harness->call('enviarSuperTefPdvAgora');

        $this->assertTrue($intencao->fresh()->requerReconciliacao());
        $this->assertTrue($intencao->fresh()->temCienciaReconciliacao());
        $this->assertNull($intencao->fresh()->pdv_venda_id);
        $this->assertSame(2, TefIntencao::query()->count());
        $nova = TefIntencao::query()->where('id', '!=', $intencao->id)->first();
        $this->assertNotNull($nova);
        $this->assertSame($harness->instance()->pdvCupomFingerprintSuperTefPublico(), $nova->cupom_fingerprint);
        $this->assertSame(9205, (int) $nova->payment_uniqueid);
        $harness->assertSet('finalizarSuperTefNovaCobrancaAutorizada', false);

        // Próxima venda do mesmo cupom/valor: ciência já registrada → sem novo aviso.
        $harness2 = $this->novoHarness($terminal, 10.0);
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/pagamentos')) {
                $orderId = (string) ($request['order_id'] ?? '');

                return Http::response([
                    'payment_uniqueid' => 9206,
                    'payment_status' => 1,
                    'payment_message' => 'Pendente',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '10.00',
                        'order_id' => $orderId,
                        'transaction_type' => 1,
                        'installment_count' => 1,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [],
                ], 201);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });
        $harness2->call('abrirParaTeste');
        $harness2->call('enviarSuperTefPdvAgora');
        $harness2->assertSet('finalizarSuperTefConfirmNovaCobranca', false)
            ->assertSet('finalizarSuperTefResolverAberta', false)
            ->assertSet('finalizarSuperTefEnviando', false);
        $this->assertGreaterThan(0, (int) $harness2->get('finalizarSuperTefIntencaoId'));
        $this->assertNotSame((int) $intencao->id, (int) $harness2->get('finalizarSuperTefIntencaoId'));
    }

    public function test_pago_vinculado_outra_venda_nao_reutiliza(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        $harness = $this->novoHarness($terminal, 1.0);
        $fp = $harness->instance()->pdvCupomFingerprintSuperTefPublico();

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-USED-1',
            'cupom_fingerprint' => $fp,
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 9105,
            'pdv_venda_id' => 999,
            'pago_em' => now(),
        ])->save();

        $harness->set('finalizarSuperTefIntencaoId', (int) $intencao->id);
        $harness->call('aplicarPagoParaTeste', (int) $intencao->id);

        $harness->assertSet('finalizarPedidoGravado', false)
            ->assertSet('finalizarSuperTefConfirmado', false);
        $this->assertSame(999, (int) $intencao->fresh()->pdv_venda_id);
    }

    public function test_pago_orfao_sem_fingerprint_nao_cola_em_venda_nova(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-ORPHAN-LW',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 9003,
            'pago_em' => now(),
            'cupom_fingerprint' => null,
            'nsu' => '9',
            'autorizacao' => '8',
            'bandeira' => 'VISA',
        ])->save();

        $harness = $this->novoHarness($terminal, 1.0);
        $harness->set('finalizarSuperTefIntencaoId', (int) $intencao->id);
        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');

        $harness->assertSet('finalizarPedidoGravado', false)
            ->assertSet('finalizarSuperTefConfirmado', false)
            ->assertSet('finalizarSuperTefConfirmNovaCobranca', true)
            ->assertSet('finalizarSuperTefConfirmOcupanteId', (int) $intencao->id);
        $fresh = $intencao->fresh();
        $this->assertFalse($fresh->requerReconciliacao());
        $this->assertNull($fresh->pdv_venda_id);
        Http::assertNothingSent();
    }

    public function test_pago_com_fingerprint_do_cupom_pode_concluir(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        $harness = $this->novoHarness($terminal, 1.0);
        $fp = $harness->instance()->pdvCupomFingerprintSuperTefPublico();

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-FP-OK',
            'cupom_fingerprint' => $fp,
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 9004,
            'pago_em' => now(),
            'nsu' => '1',
            'autorizacao' => '2',
            'bandeira' => 'VISA',
        ])->save();

        $harness->set('finalizarSuperTefPendingOperacao', 'pedido');
        $harness->call('aplicarPagoParaTeste', (int) $intencao->id);

        $harness->assertSet('finalizarPedidoGravado', true)
            ->assertSet('finalizarSuperTefConfirmado', true);
        $this->assertSame(555, (int) $intencao->fresh()->pdv_venda_id);
    }

    public function test_credito_a_vista_envia_type_2_e_parcela_1(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => function ($request) {
                $orderId = (string) ($request['order_id'] ?? '');

                return Http::response([
                    'payment_uniqueid' => 9201,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '10.35',
                        'order_id' => $orderId,
                        'transaction_type' => 2,
                        'installment_count' => 1,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [
                        'nsu' => 'C1',
                        'authorization_code' => 'A1',
                        'brand' => 'MASTER',
                    ],
                ], 201);
            },
        ]);

        $harness = $this->novoHarness($terminal, 10.35, false, 'credito', 1);
        $harness->call('abrirParaTeste');
        $harness->assertSet('finalizarSuperTefModalidadeLabel', 'Crédito à vista');
        $harness->call('enviarEConcluirSePagoParaTeste');

        $intencao = TefIntencao::query()->orderByDesc('id')->first();
        $this->assertNotNull($intencao);
        $this->assertSame('2', (string) $intencao->transaction_type);
        $this->assertSame(1, (int) $intencao->installment_count);
        $this->assertSame(1, (int) $intencao->installment_type);
        $this->assertEqualsWithDelta(10.35, (float) $intencao->amount, 0.001);
        $this->assertTrue($intencao->isPaga());
        $this->assertSame('C1', (string) $intencao->nsu);
        $this->assertSame('A1', (string) $intencao->autorizacao);
        $this->assertSame('MASTER', (string) $intencao->bandeira);
        $harness->assertSet('finalizarPedidoGravado', true);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $data = $request->data();

            return (string) ($data['transaction_type'] ?? '') === '2'
                && (int) ($data['installment_count'] ?? 0) === 1
                && (float) ($data['amount'] ?? 0) === 10.35;
        });
    }

    public function test_credito_parcelado_3x_envia_installment_count(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => function ($request) {
                $orderId = (string) ($request['order_id'] ?? '');

                return Http::response([
                    'payment_uniqueid' => 9202,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '30.00',
                        'order_id' => $orderId,
                        'transaction_type' => 2,
                        'installment_count' => 3,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [
                        'nsu' => 'C3',
                        'authorization_code' => 'A3',
                        'brand' => 'VISA',
                    ],
                ], 201);
            },
        ]);

        $harness = $this->novoHarness($terminal, 30.0, false, 'credito', 3);
        $harness->call('abrirParaTeste');
        $harness->assertSet('finalizarSuperTefModalidadeLabel', 'Crédito 3x');
        $harness->call('enviarEConcluirSePagoParaTeste');

        $intencao = TefIntencao::query()->orderByDesc('id')->first();
        $this->assertNotNull($intencao);
        $this->assertSame('2', (string) $intencao->transaction_type);
        $this->assertSame(3, (int) $intencao->installment_count);
        $this->assertSame('C3', (string) $intencao->nsu);
        $this->assertSame('A3', (string) $intencao->autorizacao);
        $this->assertSame('VISA', (string) $intencao->bandeira);
        $harness->assertSet('finalizarPedidoGravado', true);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $data = $request->data();

            return (string) ($data['transaction_type'] ?? '') === '2'
                && (int) ($data['installment_count'] ?? 0) === 3
                && (int) ($data['installment_type'] ?? 0) === 1;
        });
    }

    public function test_credito_considerar_a_vista_forca_installment_1_sem_parcelas(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => function ($request) {
                $orderId = (string) ($request['order_id'] ?? '');

                return Http::response([
                    'payment_uniqueid' => 9301,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '15.50',
                        'order_id' => $orderId,
                        'transaction_type' => 2,
                        'installment_count' => 1,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [
                        'nsu' => 'AV1',
                        'authorization_code' => 'AV2',
                        'brand' => 'ELO',
                    ],
                ], 201);
            },
        ]);

        $harness = $this->novoHarness($terminal, 15.50, false, 'credito', 3);
        $harness->set('finalizarPagamentos.0.considerar_a_vista', true);
        $harness->set('finalizarPagamentos.0.supertef_parcelas', '3');
        $harness->set('finalizarPagamentos.0.supertef_condicao', 'fin:3');

        $harness->call('abrirParaTeste');
        $harness->assertSet('finalizarSuperTefModalidadeLabel', 'Crédito à vista');
        $harness->call('enviarEConcluirSePagoParaTeste');

        $intencao = TefIntencao::query()->orderByDesc('id')->first();
        $this->assertNotNull($intencao);
        $this->assertSame('2', (string) $intencao->transaction_type);
        $this->assertSame(1, (int) $intencao->installment_count);
        $this->assertSame(1, (int) $intencao->installment_type);
        $harness->assertSet('finalizarPedidoGravado', true);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $data = $request->data();

            return (string) ($data['transaction_type'] ?? '') === '2'
                && (int) ($data['installment_count'] ?? 0) === 1
                && (float) ($data['amount'] ?? 0) === 15.50;
        });
    }

    public function test_credito_financeiro_max_1_forca_installment_1_sem_overlay(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => function ($request) {
                $orderId = (string) ($request['order_id'] ?? '');

                return Http::response([
                    'payment_uniqueid' => 9303,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '22.00',
                        'order_id' => $orderId,
                        'transaction_type' => 2,
                        'installment_count' => 1,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [
                        'nsu' => 'M1',
                        'authorization_code' => 'M2',
                        'brand' => 'MASTER',
                    ],
                ], 201);
            },
        ]);

        $harness = $this->novoHarness($terminal, 22.0, false, 'credito', 3);
        $harness->set('finalizarPagamentos.0.considerar_a_vista', false);
        $harness->set('finalizarPagamentos.0.max_parcelas', 1);
        $harness->set('finalizarPagamentos.0.modo_prazo', 'financeiro');
        $harness->set('finalizarPagamentos.0.intervalo_parcelas', 30);
        $harness->set('finalizarPagamentos.0.supertef_parcelas', '3');
        $harness->set('finalizarPagamentos.0.supertef_condicao', 'fin:3');

        $harness->call('abrirParaTeste');
        $harness->assertSet('finalizarSuperTefModalidadeLabel', 'Crédito à vista');
        $harness->call('enviarEConcluirSePagoParaTeste');

        $intencao = TefIntencao::query()->orderByDesc('id')->first();
        $this->assertNotNull($intencao);
        $this->assertSame(1, (int) $intencao->installment_count);
        $harness->assertSet('finalizarPedidoGravado', true);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $data = $request->data();

            return (string) ($data['transaction_type'] ?? '') === '2'
                && (int) ($data['installment_count'] ?? 0) === 1
                && (float) ($data['amount'] ?? 0) === 22.0;
        });
    }

    public function test_credito_sem_considerar_a_vista_respeita_parcelas_escolhidas(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => function ($request) {
                $orderId = (string) ($request['order_id'] ?? '');

                return Http::response([
                    'payment_uniqueid' => 9302,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '40.00',
                        'order_id' => $orderId,
                        'transaction_type' => 2,
                        'installment_count' => 4,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [
                        'nsu' => 'P4',
                        'authorization_code' => 'A4',
                        'brand' => 'VISA',
                    ],
                ], 201);
            },
        ]);

        $harness = $this->novoHarness($terminal, 40.0, false, 'credito', 4);
        $harness->set('finalizarPagamentos.0.considerar_a_vista', false);
        $harness->call('abrirParaTeste');
        $harness->assertSet('finalizarSuperTefModalidadeLabel', 'Crédito 4x');
        $harness->call('enviarEConcluirSePagoParaTeste');

        $intencao = TefIntencao::query()->orderByDesc('id')->first();
        $this->assertNotNull($intencao);
        $this->assertSame(4, (int) $intencao->installment_count);
        $harness->assertSet('finalizarPedidoGravado', true);
    }

    public function test_credito_modo_tabela_nao_assume_unica_por_max_1(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => function ($request) {
                $orderId = (string) ($request['order_id'] ?? '');

                return Http::response([
                    'payment_uniqueid' => 9304,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => '50.00',
                        'order_id' => $orderId,
                        'transaction_type' => 2,
                        'installment_count' => 3,
                        'installment_type' => 1,
                    ],
                    'payment_data' => [
                        'nsu' => 'T3',
                        'authorization_code' => 'TA',
                        'brand' => 'ELO',
                    ],
                ], 201);
            },
        ]);

        $harness = $this->novoHarness($terminal, 50.0, false, 'credito', 3);
        $harness->set('finalizarPagamentos.0.considerar_a_vista', false);
        $harness->set('finalizarPagamentos.0.max_parcelas', 1);
        $harness->set('finalizarPagamentos.0.modo_prazo', 'tabela');
        $harness->set('finalizarPagamentos.0.supertef_parcelas', '3');
        $harness->set('finalizarPagamentos.0.supertef_condicao', 'tab:10');
        $harness->set('finalizarPagamentos.0.tabelas_prazo', [
            ['id' => 10, 'dias' => '30,60,90', 'ordem' => 1],
        ]);

        $harness->call('abrirParaTeste');
        // max=1 em tabela NÃO força à vista; respeita condição escolhida (3x).
        $harness->assertSet('finalizarSuperTefModalidadeLabel', 'Crédito 3x');
        $harness->call('enviarEConcluirSePagoParaTeste');

        $intencao = TefIntencao::query()->orderByDesc('id')->first();
        $this->assertNotNull($intencao);
        $this->assertSame('2', (string) $intencao->transaction_type);
        $this->assertSame(3, (int) $intencao->installment_count);
        $this->assertSame('T3', (string) $intencao->nsu);
        $this->assertSame('TA', (string) $intencao->autorizacao);
        $this->assertSame('ELO', (string) $intencao->bandeira);
        $harness->assertSet('finalizarPedidoGravado', true);
    }

    public function test_credito_e_debito_juntos_bloqueiam_cobranca(): void
    {
        $terminal = $this->terminalPronto();
        $this->gravarTokenSh($terminal);
        Http::fake();

        $harness = $this->novoHarness($terminal, 5.0, false, 'credito', 1);
        $harness->set('finalizarPagamentos', [
            [
                'forma' => 'SUPERTEF DEBITO',
                'valor' => '5,00',
                'usa_super_tef' => true,
                'tipo' => 'cartao_debito',
                'supertef_parcelas' => '1',
            ],
            [
                'forma' => 'SUPERTEF CREDITO',
                'valor' => '5,00',
                'usa_super_tef' => true,
                'tipo' => 'cartao_credito',
                'max_parcelas' => 12,
                'supertef_parcelas' => '2',
            ],
        ]);
        $harness->call('abrirParaTeste');
        $harness->call('enviarSuperTefPdvAgora');

        $harness->assertSet('finalizarSuperTefStatus', 'Erro');
        $this->assertStringContainsString('apenas uma linha SuperTEF', (string) $harness->get('finalizarSuperTefErro'));
        $this->assertSame(0, TefIntencao::query()->count());
        Http::assertNothingSent();
    }

    private function novoHarness(Terminal $terminal, float $valor, bool $falharGravacao = false, string $modalidade = 'debito', int $parcelas = 1)
    {
        return Livewire::test(SuperTefPdvHarness::class, [
            'terminalId' => (int) $terminal->id,
            'valorSuperTef' => $valor,
            'falharGravacao' => $falharGravacao,
            'modalidade' => $modalidade,
            'supertefParcelas' => $parcelas,
        ]);
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
            $table->text('param_tef_supertef_softhouse_token')->nullable();
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
            $table->unsignedInteger('pos_id');
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

    private function gravarTokenSh(Terminal $terminal): void
    {
        $empresa = Empresa::query()->find($terminal->empresa_id);
        $empresa->{SuperTefSofthouseToken::COLUMN} = 'token-sh-teste';
        $empresa->save();
    }

    private function terminalPronto(): Terminal
    {
        $empresa = Empresa::query()->create([
            'razao_social' => 'Empresa ST Livewire',
            'nome_fantasia' => 'ST',
            'cnpj' => sprintf('%014d', random_int(1, 99999999999999)),
        ]);

        $terminal = new Terminal();
        $terminal->forceFill([
            'empresa_id' => (int) $empresa->id,
            'nome' => 'PDV-LW',
            'usa_tef' => false,
            'tef_extra' => SuperTefTerminalConfig::writePayload([
                'ativo' => true,
                'pos_id' => 5665,
                'cliente_chave' => 'chave-dev',
            ]),
        ]);
        $terminal->save();

        return $terminal->fresh();
    }
}

class SuperTefPdvHarness extends Component
{
    use ManagesPdvSuperTef;

    public int $terminalId = 0;

    public float $valorSuperTef = 0;

    public string $modalidade = 'debito';

    public int $supertefParcelas = 1;

    public bool $falharGravacao = false;

    public bool $finalizarPedidoGravado = false;

    public ?string $pdvFinalizarOperacaoUnica = 'pedido';

    public ?string $finalizarPixPendingOperacao = null;

    public ?string $finalizarOperacaoPendente = null;

    /** @var array<int, array<string, mixed>> */
    public array $finalizarPagamentos = [];

    public ?int $caixaSessaoId = 3;

    /** @var array<int, array<string, mixed>> */
    public array $cupomItens = [];

    public function mount(int $terminalId, float $valorSuperTef, bool $falharGravacao = false, string $modalidade = 'debito', int $supertefParcelas = 1): void
    {
        $this->terminalId = $terminalId;
        $this->valorSuperTef = $valorSuperTef;
        $this->falharGravacao = $falharGravacao;
        $this->modalidade = $modalidade;
        $this->supertefParcelas = max(1, $supertefParcelas);
        $this->cupomItens = [[
            'product_id' => 10,
            'product_grade_id' => 0,
            'quantidade' => 1,
            'total' => $valorSuperTef,
        ]];
        $credito = $modalidade === 'credito';
        $pag = [
            'forma' => $credito ? 'SUPERTEF CREDITO' : 'SUPERTEF DEBITO',
            'valor' => ErpMoney::formatBr($valorSuperTef),
            'forma_id' => 0,
            'usa_super_tef' => true,
            'tipo' => $credito ? 'cartao_credito' : 'cartao_debito',
            'max_parcelas' => 12,
            'intervalo_parcelas' => 30,
            'modo_prazo' => 'financeiro',
            'considerar_a_vista' => false,
            'supertef_parcelas' => (string) $this->supertefParcelas,
        ];
        if ($credito) {
            $conds = [];
            for ($p = 1; $p <= 12; $p++) {
                $conds[] = [
                    'key' => 'fin:'.$p,
                    'parcelas' => $p,
                    'label' => $p === 1 ? 'À vista' : ($p.'x'),
                    'origem' => 'financeiro',
                    'tabela_id' => null,
                    'dias' => [],
                ];
            }
            $pag['supertef_condicoes'] = $conds;
            $pag['supertef_condicao'] = 'fin:'.$this->supertefParcelas;
        }
        $this->finalizarPagamentos = [$pag];
        $this->finalizarSuperTefPendingOperacao = 'pedido';
    }

    public function pdvCupomFingerprintSuperTefPublico(): string
    {
        return $this->pdvCupomFingerprintSuperTef();
    }

    public function finalizarTotalVendaValor(): float
    {
        return round($this->valorSuperTef, 2);
    }

    public function abrirParaTeste(): void
    {
        $this->abrirSuperTefPdv();
    }

    public function aplicarPagoParaTeste(int $intencaoId): void
    {
        $intencao = TefIntencao::query()->findOrFail($intencaoId);
        $this->aplicarSuperTefPago($intencao);
        // Espelha wire:init="concluirVendaAposSuperTefPago" (fora do poll).
        if ($this->finalizarSuperTefAguardandoConclusao) {
            $this->concluirVendaAposSuperTefPago();
        }
    }

    public function pollEConcluirSePagoParaTeste(): void
    {
        $this->pollFinalizarSuperTef();
        if ($this->finalizarSuperTefAguardandoConclusao) {
            $this->concluirVendaAposSuperTefPago();
        }
    }

    public function enviarEConcluirSePagoParaTeste(): void
    {
        $this->enviarSuperTefPdvAgora();
        if ($this->finalizarSuperTefAguardandoConclusao) {
            $this->concluirVendaAposSuperTefPago();
        }
    }

    public function cancelarEConcluirSePagoParaTeste(): void
    {
        $this->cancelarPagamentoSuperTef();
        if ($this->finalizarSuperTefAguardandoConclusao) {
            $this->concluirVendaAposSuperTefPago();
        }
    }

    public function pdvConfig(): object
    {
        $terminalId = $this->terminalId;

        return new class($terminalId)
        {
            public function __construct(private int $terminalId) {}

            public function usaSuperTefDebito(): bool
            {
                return true;
            }

            public function terminal(): ?Terminal
            {
                return Terminal::query()->find($this->terminalId);
            }

            public function perguntarImprimir(): bool
            {
                return true;
            }
        };
    }

    protected function executarFinalizarVendaPedido(bool $imprimir = true, bool $askPrintAfter = false): void
    {
        if ($this->falharGravacao) {
            throw new \RuntimeException('Falha simulada ao gravar pedido.');
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);
        if ($intencao) {
            $intencao->pdv_venda_id = 555;
            $intencao->pdv_venda_pagamento_id = 556;
            $intencao->save();
        }

        $this->finalizarPedidoGravado = true;
        $this->finalizarSuperTefAberta = false;
        $this->finalizarSuperTefStatus = 'Pedido concluído';
    }

    protected function confirmFinalizarComOperacao(string $operacao): void
    {
        $this->finalizarPedidoGravado = true;
    }

    protected function validarPreCondicoesFinalizarVenda(): bool
    {
        return true;
    }

    protected function notifyPdvError(string $titulo, ?string $detalhe = null): void
    {
        //
    }

    /** Expõe validação final para auditoria/testes (sem gravar venda). */
    public function auditoriaValidaSuperTefFinalizar(): ?string
    {
        return $this->validaSuperTefFinalizar();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
