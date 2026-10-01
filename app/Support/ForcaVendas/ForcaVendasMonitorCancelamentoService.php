<?php

namespace App\Support\ForcaVendas;

use App\Models\ForcaVendasOrder;
use App\Models\Pedido;
use App\Models\Venda;
use App\Support\Erp\Boleto\Api\BoletoApi;
use App\Support\Erp\Vendas\EstornarVendaService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cancelamento do Monitor: pendente só encerra o pedido; faturado estorna
 * depois das travas e, se houver boleto bancário, depois da baixa confirmada.
 */
final class ForcaVendasMonitorCancelamentoService
{
    public function __construct(
        private readonly ForcaVendasFaturamentoService $faturamento = new ForcaVendasFaturamentoService(),
        private readonly EstornarVendaService $estorno = new EstornarVendaService(),
    ) {}

    /**
     * @throws ForcaVendasCancelamentoBoletoPendenteException
     * @throws RuntimeException
     */
    public function cancelar(
        ForcaVendasOrder $order,
        string $motivo,
        string $numero,
        bool $boletoAutorizado,
        ?callable $aoAvancar = null,
    ): void {
        $avancar = function (string $nome) use ($aoAvancar): void {
            if ($aoAvancar !== null) {
                $aoAvancar($nome);
            }
        };

        $avancar('Validando pedido');

        if ($order->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
            throw new RuntimeException('Pedido já está cancelado.');
        }

        if (! $order->venda_id) {
            $this->cancelarSemVenda($order);
            $avancar('Finalizando pedido');

            return;
        }

        $venda = Venda::query()->find($order->venda_id);

        if (! $venda instanceof Venda) {
            throw new RuntimeException('Pedido sem venda gerada para cancelar.');
        }

        if ($venda->status === Venda::STATUS_CANCELADO) {
            throw new RuntimeException('Pedido já está cancelado.');
        }

        $this->estorno->assertSemImpedimentoFiscalOuDevolucao($venda);
        $this->faturamento->garantirEntregaNaoExpedida($venda);

        $avancar('Verificando financeiro');
        $this->faturamento->garantirTitulosNaoRecebidos($order);

        $avancar('Verificando boleto bancário');
        $boletos = $this->faturamento->boletosBancariosAtivos($order);

        if ($boletos->isNotEmpty() && ! $boletoAutorizado) {
            throw new ForcaVendasCancelamentoBoletoPendenteException($numero);
        }

        if ($boletos->isNotEmpty()) {
            $avancar('Solicitando baixa do boleto');
            $api = app(BoletoApi::class);

            foreach ($boletos as $boleto) {
                $api->baixarComConfirmacao($boleto);
            }
        }

        $resultado = $this->estorno->fromVenda(
            $venda,
            $motivo,
            EstornarVendaService::ORIGEM_MONITOR_FV,
            aoAvancar: $avancar,
        );

        if ($resultado->alreadyCancelled) {
            throw new RuntimeException('Pedido já está cancelado.');
        }
    }

    /**
     * Estorna o faturamento com as mesmas travas do cancelamento e devolve
     * o pedido para pendente, editável e faturável.
     *
     * @return array{sem_venda: bool, tem_boleto: bool}
     *
     * @throws ForcaVendasCancelamentoBoletoPendenteException
     * @throws RuntimeException
     */
    public function reabrir(
        ForcaVendasOrder $order,
        string $numero,
        bool $boletoAutorizado,
        ?callable $aoAvancar = null,
    ): array {
        $avancar = function (string $nome) use ($aoAvancar): void {
            if ($aoAvancar !== null) {
                $aoAvancar($nome);
            }
        };

        $avancar('Validando pedido');

        if ($order->situacao === ForcaVendasOrder::SITUACAO_PENDENTE && ! $order->venda_id) {
            throw new RuntimeException('Pedido já está pendente.');
        }

        $venda = $order->venda_id ? Venda::query()->find($order->venda_id) : null;
        $vendaViva = $venda instanceof Venda && $venda->status !== Venda::STATUS_CANCELADO;

        if (! $vendaViva) {
            $this->reabrirSemEstorno($order, $avancar);

            return ['sem_venda' => true, 'tem_boleto' => false];
        }

        $this->faturamento->garantirEntregaNaoExpedida($venda);
        $this->faturamento->garantirTitulosNaoRecebidos($order);

        $avancar('Verificando documentos fiscais');
        $this->estorno->assertSemImpedimentoFiscalOuDevolucao($venda);

        $avancar('Verificando boleto bancário');
        $boletos = $this->faturamento->boletosBancariosAtivos($order);

        if ($boletos->isNotEmpty() && ! $boletoAutorizado) {
            throw new ForcaVendasCancelamentoBoletoPendenteException($numero);
        }

        if ($boletos->isNotEmpty()) {
            $avancar('Baixando boleto no banco');
            $api = app(BoletoApi::class);

            foreach ($boletos as $boleto) {
                $api->baixarComConfirmacao($boleto);
            }
        }

        $this->faturamento->estornar(
            $order,
            'Reabertura do pedido no Monitor de Vendas.',
            $avancar,
            true,
        );

        return ['sem_venda' => false, 'tem_boleto' => $boletos->isNotEmpty()];
    }

    /**
     * Pedido já sem venda viva: só volta para pendente e recria a reserva.
     *
     * @param  callable(string): void  $avancar
     */
    private function reabrirSemEstorno(ForcaVendasOrder $order, callable $avancar): void
    {
        DB::transaction(function () use ($order, $avancar): void {
            /** @var ForcaVendasOrder|null $locked */
            $locked = ForcaVendasOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked instanceof ForcaVendasOrder) {
                throw new RuntimeException('Pedido não encontrado.');
            }

            if ($locked->situacao === ForcaVendasOrder::SITUACAO_PENDENTE && ! $locked->venda_id) {
                throw new RuntimeException('Pedido já está pendente.');
            }

            $venda = $locked->venda_id ? Venda::query()->whereKey((int) $locked->venda_id)->lockForUpdate()->first() : null;

            if ($venda instanceof Venda && $venda->status !== Venda::STATUS_CANCELADO) {
                throw new RuntimeException('Pedido mudou durante a reabertura. Tente novamente.');
            }

            $avancar('Recriando reserva');
            $locked->loadMissing('pedido.itens.product', 'user');

            if ($locked->tipo === ForcaVendasOrder::TIPO_PEDIDO && $locked->pedido && $locked->user) {
                (new \App\Support\Erp\EstoqueReservaService())->reservarPedido($locked, $locked->pedido, $locked->user);
            }

            $avancar('Reabrindo pedido');
            $locked->forceFill([
                'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
                'venda_id' => null,
                'confirmed_at' => null,
                'faturado_at' => null,
                'canceled_at' => null,
            ])->save();

            $locked->load('pedido');

            if ($locked->pedido && $locked->pedido->status !== Pedido::STATUS_ABERTO) {
                $locked->pedido->update(['status' => Pedido::STATUS_ABERTO]);
            }
        });
    }

    /**
     * Pedido ainda sem venda: só situação e status do DAV. Sem estoque, caixa ou financeiro.
     */
    private function cancelarSemVenda(ForcaVendasOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            /** @var ForcaVendasOrder|null $locked */
            $locked = ForcaVendasOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked instanceof ForcaVendasOrder) {
                throw new RuntimeException('Pedido não encontrado.');
            }

            if ($locked->venda_id) {
                throw new RuntimeException('Pedido mudou durante o cancelamento. Tente novamente.');
            }

            if ($locked->situacao === ForcaVendasOrder::SITUACAO_CANCELADO) {
                throw new RuntimeException('Pedido já está cancelado.');
            }

            $locked->load('pedido');

            if ($locked->pedido && $locked->pedido->status !== Pedido::STATUS_CANCELADO) {
                $locked->pedido->update(['status' => Pedido::STATUS_CANCELADO]);
            }

            $locked->forceFill([
                'situacao' => ForcaVendasOrder::SITUACAO_CANCELADO,
                'canceled_at' => now(),
            ])->save();
        });
    }
}
