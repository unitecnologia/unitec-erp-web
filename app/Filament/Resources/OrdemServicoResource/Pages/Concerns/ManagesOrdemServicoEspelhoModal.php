<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\OrdemServico;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Os\OrdemServicoEspelhoService;
use Filament\Notifications\Notification;

trait ManagesOrdemServicoEspelhoModal
{
    public bool $osEspelhoOpen = false;

    public string $osEspelhoTab = 'resumo';

    /** @var array<string, mixed> */
    public array $osEspelho = [];

    public function openOrdemServicoEspelho(int $ordemId): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        $ordem = OrdemServico::query()
            ->when($empresaId !== null, fn ($query) => $query->where(function ($query) use ($empresaId): void {
                $query->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
            }))
            ->find($ordemId);

        if (! $ordem instanceof OrdemServico) {
            Notification::make()
                ->title('Ordem de serviço não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $this->osEspelho = app(OrdemServicoEspelhoService::class)->build($ordem);
        $this->osEspelhoTab = 'resumo';
        $this->osEspelhoOpen = true;
    }

    public function closeOrdemServicoEspelho(): void
    {
        $this->osEspelhoOpen = false;
        $this->osEspelhoTab = 'resumo';
        $this->osEspelho = [];
    }

    public function setOrdemServicoEspelhoTab(string $tab): void
    {
        if (! in_array($tab, ['resumo', 'itens', 'midias', 'financeiro', 'historico'], true)) {
            return;
        }

        $this->osEspelhoTab = $tab;
    }
}
