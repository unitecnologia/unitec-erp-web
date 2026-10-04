<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Support\Erp\ErpMoney;
use App\Support\Erp\Orcamento\OrcamentoPrecoDivergenciaService;
use Filament\Notifications\Notification;

trait ManagesOrcamentoPrecoDivergencia
{
    protected function sincronizarPrecosComCadastro(bool $notify = false): int
    {
        if ($this->orcamentoReadOnly()) {
            return 0;
        }

        $divergencias = app(OrcamentoPrecoDivergenciaService::class)->detectar($this->itens);

        if ($divergencias === []) {
            return 0;
        }

        $itens = $this->itens;

        foreach ($divergencias as $divergencia) {
            $index = (int) $divergencia['index'];

            if (! isset($itens[$index])) {
                continue;
            }

            $itens[$index]['preco_unitario'] = ErpMoney::formatBr((float) $divergencia['preco_atual']);
            $itens[$index] = $this->recalcItemRowData($itens[$index]);
        }

        $this->itens = $itens;
        $this->recalcHeaderFromItens();

        $atualizados = count($divergencias);

        if ($notify && $atualizados > 0) {
            Notification::make()
                ->title('Preços atualizados')
                ->body($atualizados . ' item(ns) recalculados com os valores atuais do cadastro.')
                ->success()
                ->send();
        }

        return $atualizados;
    }

    protected function marcarDivergenciaPrecoCadastro(): void
    {
        if ($this->orcamentoReadOnly() || $this->itens === []) {
            return;
        }

        $divergencias = app(OrcamentoPrecoDivergenciaService::class)->detectar($this->itens);

        if ($divergencias === []) {
            return;
        }

        $itens = $this->itens;

        foreach ($divergencias as $divergencia) {
            $index = (int) $divergencia['index'];

            if (! isset($itens[$index])) {
                continue;
            }

            $itens[$index]['preco_cadastro_atual'] = ErpMoney::formatBr((float) $divergencia['preco_atual']);
        }

        $this->itens = $itens;
    }
}
