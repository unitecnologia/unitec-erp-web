<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVenda;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Nfce\NfceImpressaoFiscal;
use App\Support\Erp\Pdv\PdvNfceCupomPrinter;

trait ManagesPdvFinalizarTotais
{
    public function finalizarSubtotalValor(): float
    {
        return $this->cupomTotalValor();
    }

    /**
     * Desconto/acréscimo vindos do documento importado (pedido/orçamento) valem mesmo com
     * o campo desabilitado nos parâmetros: já foram concedidos no documento de origem.
     */
    public function finalizarDescontoValor(): float
    {
        if (! $this->pdvConfig()->habilitarDescontoVenda()) {
            return $this->importDescontoVenda();
        }

        return ErpMoney::parseBr($this->finalizarForm['desconto_venda'] ?? '0');
    }

    public function finalizarAcrescimoValor(): float
    {
        if (! $this->pdvConfig()->habilitarAcrescimoVenda()) {
            return $this->importAcrescimoVenda();
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

        if ($maxPct > 0 && $desconto > 0 && $desconto > $this->importDescontoVenda() + 0.004) {
            $maxDesconto = round($subtotal * $maxPct / 100, 2);

            if ($desconto > $maxDesconto) {
                $this->notifyPdvError(
                    'Desconto maior que o máximo permitido (' . ErpMoney::formatBr($maxPct) . '%).'
                );

                return false;
            }
        }

        $maxAcrescimoPct = $this->pdvConfig()->acrescimoMaximo();

        if ($maxAcrescimoPct > 0 && $acrescimo > 0 && $acrescimo > $this->importAcrescimoVenda() + 0.004) {
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

    /** Sem $copias usa o Nº de vias do terminal (pedido: no mínimo 2 se "pedido em duas vias"). */
    protected function imprimirCupomPosVenda(int $vendaId, ?int $copias = null): void
    {
        $venda = PdvVenda::query()->find($vendaId);

        if (PdvNfceCupomPrinter::imprimeComoNfce($venda)) {
            $this->imprimirNfceCupomPosVenda($vendaId, $copias ?? $this->pdvConfig()->viasImpressao());

            return;
        }

        $copias ??= $this->pdvConfig()->viasPedido();
        $url = route('erp.reports.pdv-cupom', ['venda' => $vendaId, 'auto' => 1]);
        $payload = json_encode(
            PdvNfceCupomPrinter::printPayload($url, $copias, $vendaId),
            JSON_THROW_ON_ERROR,
        );
        $this->js('(function (payload) {
            if (window.ErpPrint?.openCupom) {
                window.ErpPrint.openCupom(payload);
                return;
            }
            window.ErpPdvPrint?.openCupom(payload);
        })(' . $payload . ')');
    }

    protected function imprimirNfceCupomPosVenda(int $vendaId, int $copias = 1): void
    {
        $venda = PdvVenda::query()->with('nfce')->find($vendaId);

        if ($venda && ($bloqueio = NfceImpressaoFiscal::motivoBloqueio($venda->nfce)) !== null) {
            $this->notifyPdvError('Impressão fiscal bloqueada.', $bloqueio);

            return;
        }

        $this->js(PdvNfceCupomPrinter::livewireOpenJs($vendaId, $copias));
    }
}
