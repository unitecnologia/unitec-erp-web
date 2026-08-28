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
        if ($this->isBloquearEstoqueNegativoAtivo()) {
            $this->aplicarBloquearEstoqueNegativo(false);
            $this->zerarEstoqueNegativoModalOpen = false;

            return;
        }

        $count = (new ZeraEstoqueNegativoService())->countNegativos();

        if ($count === 0) {
            $this->aplicarBloquearEstoqueNegativo(true);

            return;
        }

        $this->zerarEstoqueNegativoModalCount = $count;
        $this->zerarEstoqueNegativoModalOpen = true;
    }

    public function confirmZerarEstoqueNegativoModal(): void
    {
        $count = (new ZeraEstoqueNegativoService())->zerarTodos();

        $this->aplicarBloquearEstoqueNegativo(true);
        $this->zerarEstoqueNegativoModalOpen = false;
        $this->zerarEstoqueNegativoModalCount = 0;

        Notification::make()
            ->title('Estoque negativo zerado')
            ->body($count.' produto(s) ajustado(s).')
            ->success()
            ->send();
    }

    public function cancelZerarEstoqueNegativoModal(): void
    {
        $this->zerarEstoqueNegativoModalOpen = false;
        $this->zerarEstoqueNegativoModalCount = 0;
        $this->aplicarBloquearEstoqueNegativo(false);
    }

    public function handleZerarEstoqueNegativoModalEscape(): void
    {
        $this->cancelZerarEstoqueNegativoModal();
    }

    public function isBloquearEstoqueNegativoAtivo(): bool
    {
        return filter_var(
            $this->data['param_geral_bloquear_estoque_negativo'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    private function aplicarBloquearEstoqueNegativo(bool $ativo): void
    {
        // Reatribui o array inteiro: nested set em $this->data[...] o Livewire não detecta.
        $this->data = array_merge($this->data ?? [], [
            'param_geral_bloquear_estoque_negativo' => $ativo,
        ]);

        if (method_exists($this, 'safeFillEmpresaForm')) {
            $this->safeFillEmpresaForm();
        }
    }
}
