<?php

namespace App\Filament\Resources\OrcamentoResource\Pages;

use App\Filament\Resources\OrcamentoResource;
use App\Filament\Resources\OrcamentoResource\Pages\Concerns\ErpOrcamentoFormPage;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\CreateRecord;

class CreateOrcamento extends CreateRecord
{
    use ErpOrcamentoFormPage;

    protected static string $resource = OrcamentoResource::class;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Lançamento de Orçamento');
        $this->initializeOrcamentoFormDefaults();

        // Novo orçamento: cursor em Razão Social (exceto retorno de atalho já com cliente/produto).
        if ($this->clienteId === null
            && blank($this->itemCodigoInput)
            && blank(trim($this->itemProdutoSearch))
        ) {
            $this->dispatch('orc-focus-cliente');
            $this->dispatch('erp-orcamento-focus-cliente');
        }
    }

    protected function getRedirectUrl(): string
    {
        return OrcamentoResource::getUrl('index');
    }
}
