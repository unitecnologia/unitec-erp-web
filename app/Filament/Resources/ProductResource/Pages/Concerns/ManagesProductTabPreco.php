<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\ProductTabelaPrecoService;
use Filament\Notifications\Notification;

trait ManagesProductTabPreco
{
    public bool $tabPrecoVarejo = false;

    public bool $tabPrecoAtacado = false;

    public bool $tabPrecoEspecial = false;

    public function updatedDataUsaTabPreco(mixed $value): void
    {
        if (! $value) {
            return;
        }

        if (! $this->tabPrecoVarejo && ! $this->tabPrecoAtacado && ! $this->tabPrecoEspecial) {
            $this->tabPrecoVarejo = true;
        }
    }

    protected function loadProductPriceTableItems(?Product $product = null): void
    {
        $this->tabPrecoVarejo = false;
        $this->tabPrecoAtacado = false;
        $this->tabPrecoEspecial = false;

        if (! $product) {
            return;
        }

        $svc = app(ProductTabelaPrecoService::class);
        $svc->carregarItens($product);

        // Carrega seleção mesmo com flag desligada (preservada no banco).
        foreach ($svc->niveisConfigurados($product) as $nivel) {
            match ($nivel) {
                ProductTabelaPrecoService::NIVEL_ATACADO => $this->tabPrecoAtacado = true,
                ProductTabelaPrecoService::NIVEL_ESPECIAL => $this->tabPrecoEspecial = true,
                default => $this->tabPrecoVarejo = true,
            };
        }
    }

    /**
     * @return list<string>
     */
    protected function selectedTabPrecoNiveis(): array
    {
        $niveis = [];

        if ($this->tabPrecoVarejo) {
            $niveis[] = ProductTabelaPrecoService::NIVEL_VAREJO;
        }

        if ($this->tabPrecoAtacado) {
            $niveis[] = ProductTabelaPrecoService::NIVEL_ATACADO;
        }

        if ($this->tabPrecoEspecial) {
            $niveis[] = ProductTabelaPrecoService::NIVEL_ESPECIAL;
        }

        return $niveis;
    }

    protected function assertTabPrecoSelectionValid(array $data): void
    {
        if (! ($data['usa_tab_preco'] ?? false)) {
            return;
        }

        if ($this->selectedTabPrecoNiveis() === []) {
            Notification::make()
                ->title('Selecione ao menos uma tabela (Varejo, Atacado ou Especial).')
                ->danger()
                ->send();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'usa_tab_preco' => 'Selecione ao menos uma tabela de preço (Varejo, Atacado ou Especial).',
            ]);
        }
    }

    protected function syncProductPriceTableItems(Product $product): void
    {
        // Flag desligada: não apaga as tabelas — só desativa o uso na venda.
        if (! ($product->usa_tab_preco ?? false)) {
            return;
        }

        app(ProductTabelaPrecoService::class)->syncNiveis(
            $product,
            $this->selectedTabPrecoNiveis(),
        );
    }
}
