<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Support\Erp\ZeraEstoqueNegativoService;
use Filament\Notifications\Notification;

trait ManagesEmpresaBloquearEstoqueNegativo
{
    public bool $zerarEstoqueNegativoModalOpen = false;

    public int $zerarEstoqueNegativoModalCount = 0;

    public function toggleBloquearEstoqueNegativo(): void
    {
        $enabled = filter_var(
            $this->data['param_geral_bloquear_estoque_negativo'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );

        if ($enabled) {
            $this->data['param_geral_bloquear_estoque_negativo'] = false;
            $this->zerarEstoqueNegativoModalOpen = false;

            return;
        }

        $count = (new ZeraEstoqueNegativoService())->countNegativos();

        if ($count === 0) {
            $this->data['param_geral_bloquear_estoque_negativo'] = true;

            return;
        }

        $this->zerarEstoqueNegativoModalCount = $count;
        $this->zerarEstoqueNegativoModalOpen = true;
    }

    public function confirmZerarEstoqueNegativoModal(): void
    {
        $count = (new ZeraEstoqueNegativoService())->zerarTodos();

        $this->data['param_geral_bloquear_estoque_negativo'] = true;
        $this->zerarEstoqueNegativoModalOpen = false;
        $this->zerarEstoqueNegativoModalCount = 0;

        Notification::make()
            ->title('Estoque negativo zerado')
            ->body($count.' produto(s) ajustado(s). Bloqueio de estoque negativo ativado.')
            ->success()
            ->send();
    }

    public function cancelZerarEstoqueNegativoModal(): void
    {
        $this->zerarEstoqueNegativoModalOpen = false;
        $this->zerarEstoqueNegativoModalCount = 0;
        $this->data['param_geral_bloquear_estoque_negativo'] = false;
    }

    public function handleZerarEstoqueNegativoModalEscape(): void
    {
        $this->cancelZerarEstoqueNegativoModal();
    }
}
