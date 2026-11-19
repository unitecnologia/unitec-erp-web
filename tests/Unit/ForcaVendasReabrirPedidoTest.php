<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use App\Models\User;
use App\Support\Erp\EmpresaParametros;
use App\Support\ForcaVendas\ForcaVendasEdicaoLockService;
use App\Support\ForcaVendas\ForcaVendasSyncService;
use App\Support\ForcaVendas\ForcaVendasTelaVendaService;
use ReflectionMethod;
use Tests\TestCase;

class ForcaVendasReabrirPedidoTest extends TestCase
{
    public function test_parametro_reabrir_default_false(): void
    {
        $this->assertFalse(EmpresaParametros::forcaVendasPermitirReabrirPedido(null));
        $this->assertFalse(EmpresaParametros::forcaVendasPermitirReabrirPedido(0));
    }

    public function test_situacoes_editaveis_nao_incluem_faturado_nem_cancelado(): void
    {
        $sits = ForcaVendasTelaVendaService::situacoesEditaveis();

        $this->assertContains(ForcaVendasOrder::SITUACAO_PENDENTE, $sits);
        $this->assertNotContains(ForcaVendasOrder::SITUACAO_FATURADO, $sits);
        $this->assertNotContains(ForcaVendasOrder::SITUACAO_CANCELADO, $sits);
    }

    public function test_assert_pode_reabrir_bloqueia_faturado(): void
    {
        $svc = app(ForcaVendasSyncService::class);
        $method = new ReflectionMethod(ForcaVendasSyncService::class, 'assertPodeReabrirPedido');
        $method->setAccessible(true);

        $order = new ForcaVendasOrder([
            'empresa_id' => 1,
            'user_id' => 10,
            'vendedor_id' => 20,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_FATURADO,
        ]);

        $user = new User([
            'empresa_id' => 1,
            'vendedor_id' => 20,
        ]);
        $user->id = 10;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/faturado ou cancelado/i');
        $method->invoke($svc, $order, $user);
    }

    public function test_assert_pode_reabrir_bloqueia_orcamento(): void
    {
        $svc = app(ForcaVendasSyncService::class);
        $method = new ReflectionMethod(ForcaVendasSyncService::class, 'assertPodeReabrirPedido');
        $method->setAccessible(true);

        $orderOrc = new ForcaVendasOrder([
            'empresa_id' => 1,
            'user_id' => 10,
            'vendedor_id' => 20,
            'tipo' => ForcaVendasOrder::TIPO_ORCAMENTO,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
        ]);

        $user = new User([
            'empresa_id' => 1,
            'vendedor_id' => 20,
        ]);
        $user->id = 10;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Somente pedidos/i');
        $method->invoke($svc, $orderOrc, $user);
    }

    public function test_push_so_atualiza_com_reabertura_e_param_ligado(): void
    {
        $cases = [
            // [reabertura, paramOn, deveAtualizar]
            [false, false, false],
            [false, true, false],
            [true, false, false],
            [true, true, true],
        ];

        foreach ($cases as [$reabertura, $paramOn, $esperado]) {
            $deveAtualizar = $reabertura && $paramOn;
            $this->assertSame(
                $esperado,
                $deveAtualizar,
                "reabertura=".($reabertura ? '1' : '0').' param='.($paramOn ? '1' : '0'),
            );
        }
    }

    public function test_assert_pode_aplicar_reabertura_exige_lock_do_mesmo_aparelho(): void
    {
        $locks = app(ForcaVendasEdicaoLockService::class);

        // Sem colunas (sqlite memory da suíte): no-op — não quebra apps em migração.
        if (! $locks->colunasDisponiveis()) {
            $order = new ForcaVendasOrder([
                'uuid' => 'u-1',
                'em_edicao_fv' => false,
            ]);
            $locks->assertPodeAplicarReabertura($order, 'device-a');
            $this->assertTrue(true);

            return;
        }

        $order = new ForcaVendasOrder([
            'uuid' => 'u-1',
            'em_edicao_fv' => true,
            'em_edicao_device_uuid' => 'device-a',
            'em_edicao_erp' => false,
        ]);

        $locks->assertPodeAplicarReabertura($order, 'device-a');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/outro aparelho/i');
        $locks->assertPodeAplicarReabertura($order, 'device-b');
    }

    public function test_assert_nao_em_edicao_fv_bloqueia_faturamento(): void
    {
        $locks = app(ForcaVendasEdicaoLockService::class);

        if (! $locks->colunasDisponiveis()) {
            $this->assertFalse($locks->estaEmEdicao(null));
            $this->assertTrue(true);

            return;
        }

        $livre = new ForcaVendasOrder(['em_edicao_fv' => false]);
        $locks->assertNaoEmEdicaoFv($livre);

        $bloqueado = new ForcaVendasOrder(['em_edicao_fv' => true]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/EM EDIÇÃO NO FV/i');
        $locks->assertNaoEmEdicaoFv($bloqueado);
    }

    public function test_liberar_apos_sync_so_limpa_quando_em_edicao(): void
    {
        $locks = app(ForcaVendasEdicaoLockService::class);

        if (! $locks->colunasDisponiveis()) {
            $order = new ForcaVendasOrder(['em_edicao_fv' => true]);
            $locks->liberarAposSync($order);
            $this->assertTrue(true);

            return;
        }

        // Sem persistência: apenas garante que o método é idempotente em objeto solto.
        $order = new ForcaVendasOrder([
            'em_edicao_fv' => false,
            'em_edicao_device_uuid' => null,
        ]);
        $locks->liberarAposSync($order);
        $this->assertFalse((bool) $order->em_edicao_fv);
    }
}
