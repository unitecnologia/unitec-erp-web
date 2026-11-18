<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Terminal;
use App\Models\TefIntencao;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Erp\Tef\SuperTefApiClient;
use App\Support\Erp\Tef\SuperTefPaymentService;
use App\Support\Erp\Tef\SuperTefPdvGate;
use App\Support\Erp\Tef\SuperTefSofthouseToken;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema mínimo (sem MigratesSqliteMemory): o migrate :memory: do projeto
 * ainda quebra em migration legada (entregas_devices / information_schema).
 */
class SuperTefPaymentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('unitec.supertef_pdv.enabled', true);
        Config::set('unitec.supertef_pdv.allow_local', true);
        $this->criarSchemaMinimo();
    }

    public function test_cria_intencao_antes_do_post_e_nao_reenvia(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);

        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.35,
        ]);

        $this->assertSame(TefIntencao::STATUS_CRIADA, $intencao->status);
        $this->assertFalse($intencao->post_enviado);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => Http::response([
                'payment_uniqueid' => 1001,
                'payment_status' => 1,
                'payment_message' => 'Solicitado',
                'payment_order' => [
                    'pos_id' => 5665,
                    'amount' => '1.35',
                    'order_id' => $intencao->order_id,
                    'transaction_type' => 1,
                    'installment_count' => 1,
                    'installment_type' => 1,
                ],
                'payment_data' => [],
            ], 201),
        ]);

        $envio1 = $service->enviarCobranca($intencao, $terminal);
        $this->assertTrue($envio1['intencao']->post_enviado);
        $this->assertSame(1001, (int) $envio1['intencao']->payment_uniqueid);
        $this->assertSame(TefIntencao::STATUS_PENDENTE, $envio1['intencao']->status);

        $envio2 = $service->enviarCobranca($envio1['intencao'], $terminal);
        $this->assertStringContainsString('já enviado', $envio2['message']);
        Http::assertSentCount(1);
    }

    public function test_bloqueia_segunda_intencao_na_mesma_pos(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));

        $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pendente');
        $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 2.0,
        ]);
    }

    public function test_rejeita_pagamento_de_outra_pos(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-TEST-POS',
        ]);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => Http::response([
                'payment_uniqueid' => 55,
                'payment_status' => 4,
                'payment_message' => 'Pago',
                'payment_order' => [
                    'pos_id' => 9999,
                    'amount' => '1',
                    'order_id' => 'UNI-TEF-TEST-POS',
                ],
                'payment_data' => ['nsu' => '1', 'authorization_code' => '2', 'brand' => 'VISA'],
            ], 201),
        ]);

        $envio = $service->enviarCobranca($intencao, $terminal);
        $this->assertSame(TefIntencao::STATUS_INDETERMINADA, $envio['intencao']->status);
        $this->assertFalse($envio['intencao']->isPaga());
    }

    public function test_timeout_marca_indeterminada_sem_segundo_post(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });

        $envio = $service->enviarCobranca($intencao, $terminal);
        $this->assertTrue($envio['transport_error']);
        $this->assertTrue($envio['intencao']->post_enviado);
        $this->assertSame(TefIntencao::STATUS_INDETERMINADA, $envio['intencao']->status);

        $again = $service->enviarCobranca($envio['intencao'], $terminal);
        $this->assertStringContainsString('já enviado', $again['message']);
    }

    public function test_erro_api_apos_post_fica_indeterminada_e_ocupa_pos(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $fp = hash('sha256', 'cupom-falha-pos');
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 2.5,
            'cupom_fingerprint' => $fp,
        ]);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => Http::response([
                'message' => 'POS offline',
            ], 422),
        ]);

        $envio = $service->enviarCobranca($intencao, $terminal);
        $this->assertTrue($envio['intencao']->post_enviado);
        $this->assertSame(TefIntencao::STATUS_INDETERMINADA, $envio['intencao']->status);
        $this->assertTrue($service->posOcupada($terminal, 5665));
        $this->assertStringContainsString('Não reenvie', $envio['message']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pendente nesta POS');
        $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 2.5,
            'cupom_fingerprint' => hash('sha256', 'outro-cupom'),
        ]);
    }

    public function test_falha_legado_com_post_enviado_ainda_ocupa_pos_e_reaproveita_mesmo_cupom(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $fp = hash('sha256', 'cupom-legado-falha');

        $legado = TefIntencao::query()->create([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal_id' => (int) $terminal->id,
            'provedor' => 'supertef',
            'order_id' => 'UNI-TEF-LEGADO-FALHA',
            'cupom_fingerprint' => $fp,
            'pos_id' => 5665,
            'amount' => 3.0,
            'transaction_type' => '1',
            'installment_count' => 1,
            'installment_type' => 1,
            'status' => TefIntencao::STATUS_FALHA,
            'post_enviado' => true,
            'requer_reconciliacao' => false,
        ]);

        $this->assertTrue($service->posOcupada($terminal, 5665));
        $reuso = $service->encontrarReaproveitavel($terminal, 3.0, null, $fp);
        $this->assertNotNull($reuso);
        $this->assertSame($legado->id, $reuso->id);
    }

    public function test_assert_pertence_rejeita_outra_empresa(): void
    {
        $terminalA = $this->terminalComSuperTef(5665, 'chave-a');
        $terminalB = $this->terminalComSuperTef(5665, 'chave-b');
        $this->gravarTokenSh($terminalA);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminalA->empresa_id,
            'terminal' => $terminalA,
            'amount' => 1.0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('terminal/empresa');
        $service->consultar($intencao, $terminalB);
    }

    public function test_gate_desligado_em_producao_sem_flag(): void
    {
        Config::set('unitec.supertef_pdv.enabled', false);
        Config::set('unitec.supertef_pdv.allow_local', false);
        $this->assertFalse(SuperTefPdvGate::featureEnabled());
    }

    public function test_gate_habilita_por_flag_sem_depender_de_app_env(): void
    {
        Config::set('unitec.supertef_pdv.enabled', true);
        Config::set('unitec.supertef_pdv.allow_local', false);
        $this->assertTrue(SuperTefPdvGate::featureEnabled());
    }

    public function test_operacao_pedido_nao_e_fiscal(): void
    {
        $this->assertFalse(\App\Support\Erp\Pdv\PdvFinalizarOperacao::isFiscal(
            \App\Support\Erp\Pdv\PdvFinalizarOperacao::PEDIDO
        ));
    }

    public function test_helper_identifica_supertef_debito(): void
    {
        $this->assertTrue(PdvFinalizarPagamentosHelper::isFormaSuperTefDebito([
            'forma' => 'SUPERTEF DEBITO',
            'tipo' => 'cartao_debito',
            'usa_super_tef' => true,
        ]));

        $this->assertFalse(PdvFinalizarPagamentosHelper::isFormaSuperTefDebito([
            'forma' => 'POS DEBITO',
            'tipo' => 'cartao_debito',
            'usa_super_tef' => false,
        ]));
    }

    public function test_helper_identifica_supertef_credito_e_parcelas(): void
    {
        $credito = [
            'forma' => 'SUPERTEF CREDITO',
            'tipo' => 'cartao_credito',
            'usa_super_tef' => true,
            'max_parcelas' => 12,
            'modo_prazo' => 'financeiro',
            'supertef_condicao' => 'fin:3',
            'supertef_parcelas' => '3',
        ];

        $this->assertTrue(PdvFinalizarPagamentosHelper::isFormaSuperTef($credito));
        $this->assertTrue(PdvFinalizarPagamentosHelper::isFormaSuperTefCredito($credito));
        $this->assertFalse(PdvFinalizarPagamentosHelper::isFormaSuperTefDebito($credito));
        $this->assertFalse(PdvFinalizarPagamentosHelper::isFormaCartaoContasReceber($credito + [
            'aparece_contas_receber' => true,
            'tipo_movimento' => 'contas_receber',
        ]));
        $this->assertSame(3, PdvFinalizarPagamentosHelper::superTefParcelas($credito, 12));

        $condsFin = PdvFinalizarPagamentosHelper::condicoesSuperTefCredito([
            'modo_prazo' => 'financeiro',
            'max_parcelas' => 4,
            'intervalo_parcelas' => 30,
        ], 12);
        $this->assertCount(4, $condsFin);
        $this->assertSame('fin:1', $condsFin[0]['key']);
        $this->assertSame('À vista', $condsFin[0]['label']);
        $this->assertSame(4, $condsFin[3]['parcelas']);

        $condsTab = PdvFinalizarPagamentosHelper::condicoesSuperTefCredito([
            'modo_prazo' => 'tabela',
            'max_parcelas' => 12,
            'tabelas_prazo' => [
                ['id' => 10, 'dias' => '30,60,90'],
                ['id' => 11, 'dias' => '0'],
                ['id' => 12, 'dias' => '30,60,90,120,150,180,210,240,270,300,330,360,390'], // 13 > teto 12
            ],
        ], 12);
        $this->assertCount(2, $condsTab);
        $this->assertSame(3, $condsTab[0]['parcelas']);
        $this->assertSame('tab:10', $condsTab[0]['key']);
        $this->assertSame(1, $condsTab[1]['parcelas']);
        $this->assertSame(3, PdvFinalizarPagamentosHelper::superTefParcelas([
            'modo_prazo' => 'tabela',
            'supertef_condicao' => 'tab:10',
            'tabelas_prazo' => [['id' => 10, 'dias' => '30,60,90']],
        ], 12));
    }

    public function test_cria_intencao_credito_parcelado_com_centavos(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));

        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 12.34,
            'transaction_type' => '2',
            'installment_count' => 4,
            'installment_type' => 1,
            'cupom_fingerprint' => 'fp-credito-4x',
        ]);

        $this->assertSame('2', (string) $intencao->transaction_type);
        $this->assertSame(4, (int) $intencao->installment_count);
        $this->assertSame(1, (int) $intencao->installment_type);
        $this->assertEqualsWithDelta(12.34, (float) $intencao->amount, 0.001);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => Http::response([
                'payment_uniqueid' => 4400,
                'payment_status' => 1,
                'payment_message' => 'Solicitado',
                'payment_order' => [
                    'pos_id' => 5665,
                    'amount' => '12.34',
                    'order_id' => $intencao->order_id,
                    'transaction_type' => 2,
                    'installment_count' => 4,
                    'installment_type' => 1,
                ],
                'payment_data' => [],
            ], 201),
        ]);

        $envio = $service->enviarCobranca($intencao, $terminal);
        $this->assertTrue($envio['intencao']->post_enviado);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $data = $request->data();

            return (string) ($data['transaction_type'] ?? '') === '2'
                && (int) ($data['installment_count'] ?? 0) === 4
                && (float) ($data['amount'] ?? 0) === 12.34
                && (int) ($data['pos_id'] ?? 0) === 5665;
        });
    }

    public function test_consulta_paga_persiste_acquirer_cnpj(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));

        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 20.00,
            'transaction_type' => '2',
            'installment_count' => 1,
            'installment_type' => 1,
            'cupom_fingerprint' => 'fp-acq-cnpj',
        ]);

        Http::fake([
            'api.supertef.com.br/api/pagamentos' => Http::response([
                'payment_uniqueid' => 5501,
                'payment_status' => 1,
                'payment_message' => 'Solicitado',
                'payment_order' => [
                    'pos_id' => 5665,
                    'amount' => '20.00',
                    'order_id' => $intencao->order_id,
                    'transaction_type' => 2,
                    'installment_count' => 1,
                    'installment_type' => 1,
                ],
                'payment_data' => [],
            ], 201),
            'api.supertef.com.br/api/pagamentos/by-uniqueid/5501' => Http::response([
                'payment_uniqueid' => 5501,
                'payment_status' => 4,
                'payment_message' => 'Pago',
                'payment_order' => [
                    'pos_id' => 5665,
                    'amount' => '20.00',
                    'order_id' => $intencao->order_id,
                    'transaction_type' => 2,
                    'installment_count' => 1,
                    'installment_type' => 1,
                ],
                'payment_data' => [
                    'nsu' => 'N9',
                    'authorization_code' => 'A9',
                    'brand' => 'VISA',
                    'acquirer_cnpj' => '01.027.058/0001-91',
                ],
            ], 200),
        ]);

        $service->enviarCobranca($intencao, $terminal);
        $paga = $service->consultar($intencao->fresh(), $terminal);

        $this->assertTrue($paga->isPaga());
        $this->assertSame('01027058000191', (string) $paga->acquirer_cnpj);
        $this->assertSame('01027058000191', $paga->acquirerCnpjFiscal());
        $this->assertSame('N9', (string) $paga->nsu);
        $this->assertSame('A9', (string) $paga->autorizacao);
        $this->assertSame('VISA', (string) $paga->bandeira);
    }

    public function test_pdv_config_usa_tef_legado_continua_false(): void
    {
        $this->assertFalse(\App\Support\Erp\Pdv\PdvConfig::make()->usaTef());
    }

    public function test_paga_sem_venda_nao_reusa_em_venda_nova_e_bloqueia_pos(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));

        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-REUSE-1',
            'cupom_fingerprint' => 'fp-original',
        ]);
        $intencao->status = TefIntencao::STATUS_PAGA;
        $intencao->post_enviado = true;
        $intencao->payment_uniqueid = 777;
        $intencao->save();

        $this->assertTrue($service->posOcupada($terminal, 5665));
        // Paga NÃO entra no reaproveitamento automático (evita colar em venda errada).
        $this->assertNull($service->encontrarReaproveitavel($terminal, 1.0, null, 'fp-original'));
        $this->assertNull($service->encontrarReaproveitavel($terminal, 1.0, null, 'fp-outro-cupom'));
        $this->assertNull($service->encontrarReaproveitavel($terminal, 1.0)); // sem fingerprint
        $this->assertFalse($intencao->fresh()->podeVincularAoCupom('fp-outro-cupom'));
        $this->assertTrue($intencao->fresh()->podeVincularAoCupom('fp-original'));

        $this->expectException(\RuntimeException::class);
        $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
        ]);
    }

    public function test_pendente_mesmo_valor_outro_fingerprint_nao_reaproveita(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));

        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 10.0,
            'caixa_sessao_id' => 3,
            'order_id' => 'UNI-TEF-FP-PEND-1',
            'cupom_fingerprint' => 'fp-cupom-a',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 4401,
        ])->save();

        // Mesmo valor + mesma POS + mesmo caixa, cupom diferente → NÃO reaproveita.
        $this->assertNull($service->encontrarReaproveitavel($terminal, 10.0, 3, 'fp-cupom-b'));
        $this->assertNull($service->encontrarReaproveitavel($terminal, 10.0, 3, ''));
        $this->assertNull($service->encontrarReaproveitavel($terminal, 10.0, 3, null));

        // Mesmo cupom → reaproveita.
        $reuso = $service->encontrarReaproveitavel($terminal, 10.0, 3, 'fp-cupom-a');
        $this->assertNotNull($reuso);
        $this->assertSame((int) $intencao->id, (int) $reuso->id);

        $ocupante = $service->intencaoOcupanteNaPos($terminal, 5665);
        $this->assertNotNull($ocupante);
        $this->assertSame('fp-cupom-a', $ocupante->cupom_fingerprint);
        $this->assertTrue($service->posOcupada($terminal, 5665));
    }

    public function test_reconciliacao_admin_libera_pos_sem_vincular(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));

        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-ORPHAN-1',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 888,
            'pdv_venda_id' => null,
            'cupom_fingerprint' => null,
        ])->save();

        $service->marcarReconciliacaoAdministrativa($intencao, 'órfão sem fingerprint');
        $fresh = $intencao->fresh();
        $this->assertTrue($fresh->requerReconciliacao());
        $this->assertFalse($fresh->podeVincularAoCupom('qualquer'));
        $this->assertFalse($service->posOcupada($terminal, 5665));
        $this->assertNull($service->encontrarReaproveitavel($terminal, 1.0, null, 'fp-nova-venda'));

        $nova = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'cupom_fingerprint' => 'fp-nova-venda',
        ]);
        $this->assertNotSame((int) $intencao->id, (int) $nova->id);
        $this->assertNull($fresh->fresh()->pdv_venda_id);
    }

    public function test_ciencia_reconciliacao_nao_reexibe_mesmo_orfao(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));

        $orfao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-CIENCIA-1',
            'cupom_fingerprint' => 'fp-antigo',
        ]);
        $orfao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 801,
            'pago_em' => now(),
        ])->save();

        $this->assertNotNull($service->pagamentoAprovadoIncompativelNaPos($terminal, 5665, 'fp-novo'));
        $service->marcarCienciaReconciliacao($orfao);
        $fresh = $orfao->fresh();
        $this->assertTrue($fresh->requerReconciliacao());
        $this->assertTrue($fresh->temCienciaReconciliacao());
        $this->assertNull($fresh->pdv_venda_id);
        $this->assertNull($service->pagamentoAprovadoIncompativelNaPos($terminal, 5665, 'fp-novo'));
        $this->assertFalse($service->posOcupada($terminal, 5665));

        // Nova intenção órfã sem ciência → volta a exigir aviso.
        $novoOrfao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 3.0,
            'order_id' => 'UNI-TEF-CIENCIA-2',
            'cupom_fingerprint' => 'fp-outro',
        ]);
        $novoOrfao->forceFill([
            'status' => TefIntencao::STATUS_PAGA,
            'post_enviado' => true,
            'payment_uniqueid' => 802,
            'pago_em' => now(),
        ])->save();
        $this->assertSame((int) $novoOrfao->id, (int) $service->pagamentoAprovadoIncompativelNaPos($terminal, 5665, 'fp-novo')->id);
    }

    public function test_cancelar_pendente_confirmado_via_get(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-CANCEL-OK',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 501,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos/by-uniqueid/501' => Http::sequence()
                ->push([
                    'payment_uniqueid' => 501,
                    'payment_status' => 1,
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANCEL-OK'],
                    'payment_data' => [],
                ], 200)
                ->push([
                    'payment_uniqueid' => 501,
                    'payment_status' => 5,
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANCEL-OK'],
                    'payment_data' => [],
                ], 200),
            'api.supertef.com.br/api/pagamentos/cancelar/501' => Http::response([
                'payment_uniqueid' => 501,
                'payment_status' => 5,
                'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANCEL-OK'],
                'payment_data' => [],
            ], 200),
        ]);

        $res = $service->cancelarPendente($intencao, $terminal);
        $this->assertTrue($res['ok']);
        $this->assertFalse($res['already_paid']);
        $this->assertFalse($res['unknown']);
        $this->assertSame(TefIntencao::STATUS_CANCELADA, $res['intencao']->status);
    }

    public function test_cancelar_timeout_mantem_indeterminada(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-CANCEL-TO',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 502,
        ])->save();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/by-uniqueid/502')) {
                return Http::response([
                    'payment_uniqueid' => 502,
                    'payment_status' => 1,
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANCEL-TO'],
                    'payment_data' => [],
                ], 200);
            }
            if (str_contains($request->url(), '/cancelar/502')) {
                throw new \Illuminate\Http\Client\ConnectionException('timeout cancel');
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $res = $service->cancelarPendente($intencao, $terminal);
        $this->assertFalse($res['ok']);
        $this->assertTrue($res['unknown']);
        $this->assertSame(TefIntencao::STATUS_INDETERMINADA, $res['intencao']->status);
    }

    public function test_cancelar_status_5_sem_contexto_nao_confirma(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-CANCEL-BAD',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 503,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos/by-uniqueid/503' => Http::sequence()
                ->push([
                    'payment_uniqueid' => 503,
                    'payment_status' => 1,
                    'payment_order' => ['pos_id' => 5665, 'amount' => 1, 'order_id' => 'UNI-TEF-CANCEL-BAD'],
                    'payment_data' => [],
                ], 200)
                ->push([
                    // GET pós-PUT sem uniqueid/order compatível → indeterminada
                    'payment_uniqueid' => 999999,
                    'payment_status' => 5,
                    'payment_order' => ['pos_id' => 9999, 'amount' => 1, 'order_id' => 'OUTRO'],
                    'payment_data' => [],
                ], 200),
            'api.supertef.com.br/api/pagamentos/cancelar/503' => Http::response([
                'payment_status' => 5,
                // sem payment_uniqueid / order compatível
            ], 200),
        ]);

        $res = $service->cancelarPendente($intencao, $terminal);
        $this->assertFalse($res['ok']);
        $this->assertTrue($res['unknown']);
        $this->assertSame(TefIntencao::STATUS_INDETERMINADA, $res['intencao']->status);
    }

    public function test_isolamento_entre_empresas_e_pos(): void
    {
        $terminalA = $this->terminalComSuperTef(5665, 'chave-a');
        $terminalB = $this->terminalComSuperTef(7777, 'chave-b');
        $this->gravarTokenSh($terminalA);
        $this->gravarTokenSh($terminalB);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));

        $intA = $service->criarIntencao([
            'empresa_id' => (int) $terminalA->empresa_id,
            'terminal' => $terminalA,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-ISO-A',
        ]);
        $intA->forceFill([
            'status' => TefIntencao::STATUS_PENDENTE,
            'post_enviado' => true,
            'payment_uniqueid' => 601,
        ])->save();

        $this->assertTrue($service->posOcupada($terminalA, 5665));
        $this->assertFalse($service->posOcupada($terminalB, 7777));
        $this->assertNull($service->encontrarReaproveitavel($terminalB, 1.0, null, 'fp-qualquer'));

        $intB = $service->criarIntencao([
            'empresa_id' => (int) $terminalB->empresa_id,
            'terminal' => $terminalB,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-ISO-B',
        ]);
        $this->assertNotSame((int) $intA->id, (int) $intB->id);
        $this->assertSame((int) $terminalB->empresa_id, (int) $intB->empresa_id);
        $this->assertSame(7777, (int) $intB->pos_id);
    }

    public function test_recuperacao_por_order_id_sem_repost(): void
    {
        $terminal = $this->terminalComSuperTef(5665, 'chave-dev');
        $this->gravarTokenSh($terminal);
        $service = new SuperTefPaymentService(new SuperTefApiClient('token-sh-teste'));
        $intencao = $service->criarIntencao([
            'empresa_id' => (int) $terminal->empresa_id,
            'terminal' => $terminal,
            'amount' => 1.0,
            'order_id' => 'UNI-TEF-REC-1',
        ]);
        $intencao->forceFill([
            'status' => TefIntencao::STATUS_INDETERMINADA,
            'post_enviado' => true,
            'payment_uniqueid' => null,
        ])->save();

        Http::fake([
            'api.supertef.com.br/api/pagamentos*' => Http::response([
                'data' => [[
                    'payment_uniqueid' => 701,
                    'payment_status' => 4,
                    'payment_message' => 'Pago',
                    'payment_order' => [
                        'pos_id' => 5665,
                        'amount' => 1,
                        'order_id' => 'UNI-TEF-REC-1',
                    ],
                    'payment_data' => [
                        'nsu' => '1',
                        'authorization_code' => '2',
                        'brand' => 'VISA',
                    ],
                ]],
            ], 200),
        ]);

        $rec = $service->recuperarPorOrderId($intencao, $terminal);
        $this->assertTrue($rec->isPaga());
        $this->assertSame(701, (int) $rec->payment_uniqueid);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
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

    private function terminalComSuperTef(int $posId, string $chave): Terminal
    {
        $empresa = Empresa::query()->create([
            'razao_social' => 'Empresa SuperTEF Test',
            'nome_fantasia' => 'SuperTEF',
            'cnpj' => sprintf('%014d', random_int(1, 99999999999999)),
        ]);

        $terminal = new Terminal();
        $terminal->forceFill([
            'empresa_id' => (int) $empresa->id,
            'nome' => 'PDV-ST-'.random_int(1000, 9999),
            'usa_tef' => false,
            'tef_extra' => SuperTefTerminalConfig::writePayload([
                'ativo' => true,
                'pos_id' => $posId,
                'cliente_chave' => $chave,
            ]),
        ]);
        $terminal->save();

        return $terminal->fresh();
    }
}
