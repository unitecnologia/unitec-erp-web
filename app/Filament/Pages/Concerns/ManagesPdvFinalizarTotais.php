<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVenda;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvNfceCupomPrinter;

trait ManagesPdvFinalizarTotais
{
    public function finalizarSubtotalValor(): float
    {
        return $this->cupomTotalValor();
    }

    public function finalizarDescontoValor(): float
    {
        if (! $this->pdvConfig()->habilitarDescontoVenda()) {
            return 0.0;
        }

        return ErpMoney::parseBr($this->finalizarForm['desconto_venda'] ?? '0');
    }

    public function finalizarAcrescimoValor(): float
    {
        if (! $this->pdvConfig()->habilitarAcrescimoVenda()) {
            return 0.0;
        }

        return ErpMoney::parseBr($this->finalizarForm['acrescimo_venda'] ?? '0');
    }

    public function finalizarTotalVendaValor(): float
    {
        return max(0, round(
            $this->finalizarSubtotalValor()
            - $this->finalizarDescontoValor()
            + $this->finalizarAcrescimoValor(),
            2,
        ));
    }

    public function getPdvHabilitarDescontoVendaProperty(): bool
    {
        return $this->pdvConfig()->habilitarDescontoVenda();
    }

    public function getPdvHabilitarAcrescimoVendaProperty(): bool
    {
        return $this->pdvConfig()->habilitarAcrescimoVenda();
    }

    public function getFinalizarSubtotalProperty(): string
    {
        return ErpMoney::formatBr($this->finalizarSubtotalValor());
    }

    protected function validateFinalizarTotais(): bool
    {
        $subtotal = $this->finalizarSubtotalValor();
        $desconto = $this->finalizarDescontoValor();
        $acrescimo = $this->finalizarAcrescimoValor();

        if ($desconto < 0 || $acrescimo < 0) {
            $this->notifyPdvError('Desconto ou acréscimo inválido.');

            return false;
        }

        if ($desconto > $subtotal) {
            $this->notifyPdvError('Desconto maior que o subtotal da venda.');

            return false;
        }

        $maxPct = $this->pdvConfig()->descontoMaximo();

        if ($maxPct > 0 && $desconto > 0) {
            $maxDesconto = round($subtotal * $maxPct / 100, 2);

            if ($desconto > $maxDesconto) {
                $this->notifyPdvError(
                    'Desconto maior que o máximo permitido (' . ErpMoney::formatBr($maxPct) . '%).'
                );

                return false;
            }
        }

        $maxAcrescimoPct = $this->pdvConfig()->acrescimoMaximo();

        if ($maxAcrescimoPct > 0 && $acrescimo > 0) {
            $maxAcrescimo = round($subtotal * $maxAcrescimoPct / 100, 2);

            if ($acrescimo > $maxAcrescimo) {
                $this->notifyPdvError(
                    'Acréscimo maior que o máximo permitido (' . ErpMoney::formatBr($maxAcrescimoPct) . '%).'
                );

                return false;
            }
        }

        if ($this->finalizarTotalVendaValor() <= 0) {
            $this->notifyPdvError('Total da venda inválido.');

            return false;
        }

        return true;
    }

    public function updatedFinalizarFormDescontoVenda(): void
    {
        if (! $this->pdvConfig()->habilitarDescontoVenda()) {
            return;
        }

        $this->syncFinalizarPagamentoPadrao();
    }

    public function updatedFinalizarFormAcrescimoVenda(): void
    {
        if (! $this->pdvConfig()->habilitarAcrescimoVenda()) {
            return;
        }

        $this->syncFinalizarPagamentoPadrao();
    }

    protected function syncFinalizarPagamentoPadrao(): void
    {
        if (! $this->pdvConfig()->pagamentoPadraoDinheiro()) {
            return;
        }

        $total = ErpMoney::formatBr($this->finalizarTotalVendaValor());
        $pagamentos = $this->finalizarPagamentos;
        $pagamentos[0]['valor'] = $total;
        $this->finalizarPagamentos = $pagamentos;
    }

    protected function imprimirCupomPosVenda(int $vendaId, int $copias = 1): void
    {
        $venda = PdvVenda::query()->find($vendaId);

        if (PdvNfceCupomPrinter::isNfceSimulada($venda)) {
            $this->imprimirNfceCupomPosVenda($vendaId, $copias);

            return;
        }

        $url = route('erp.reports.pdv-cupom', ['venda' => $vendaId, 'auto' => 1]);
        $payload = json_encode(['url' => $url, 'copias' => max(1, $copias)], JSON_THROW_ON_ERROR);
        $this->js('window.ErpPdvPrint?.openCupom(' . $payload . ')');
    }

    protected function imprimirNfceCupomPosVenda(int $vendaId, int $copias = 1): void
    {
        $this->js(PdvNfceCupomPrinter::livewireOpenJs($vendaId, $copias));
    }
}
