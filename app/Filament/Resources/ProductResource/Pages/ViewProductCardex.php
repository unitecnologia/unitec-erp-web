<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ProductCardexService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewProductCardex extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProductResource::class;

    protected static ?string $title = '';

    public string $cardexProdutoLabel = '';

    /** @var array<string, mixed> */
    public array $cardexData = [
        'compras' => [],
        'vendas' => [],
        'nfe' => [],
        'nfce' => [],
        'totais' => [
            'compras' => 'R$ 0,00',
            'vendas' => 'R$ 0,00',
            'nfe' => 'R$ 0,00',
            'nfce' => 'R$ 0,00',
            'total_vendas' => 'R$ 0,00',
        ],
    ];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        ErpScreen::set('Histórico de Movimentação');

        $this->loadCardex();
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-produtos-form-page',
            'erp-produtos-cardex-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.produtos.cardex.window'),
                View::make('filament.components.erp.produtos.cardex.footer'),
            ]);
    }

    public function refreshProductCardex(): void
    {
        $this->getRecord()->refresh();
        $this->loadCardex();

        Notification::make()
            ->title('Histórico atualizado.')
            ->success()
            ->send();
    }

    public function closeProductCardex(): void
    {
        ErpScreen::set(request()->query('return') === 'edit' ? 'Cadastro de Produtos' : 'Produtos');

        if (request()->query('return') === 'edit') {
            $this->redirect(ProductResource::getUrl('edit', ['record' => $this->getRecord()]));

            return;
        }

        $this->redirect(ProductResource::getUrl('index'));
    }

    protected function loadCardex(): void
    {
        $record = $this->getRecord();

        $this->cardexProdutoLabel = $record->codigo . ' — ' . $record->descricao;
        $this->cardexData = app(ProductCardexService::class)->forProduct($record);
    }
}
