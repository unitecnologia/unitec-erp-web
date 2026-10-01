<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Concerns\EmbedsInPdvOverlay;
use App\Filament\Resources\ProductResource;
use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpContext;
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
    use EmbedsInPdvOverlay;
    use InteractsWithRecord;

    protected static string $resource = ProductResource::class;

    protected static ?string $title = '';

    public string $cardexProdutoLabel = '';

    public string $cardexPeriodoDe = '';

    public string $cardexPeriodoAte = '';

    public bool $cardexPrestador = false;

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
        'resumo' => [
            'e_medio' => '0,000',
            'ult_compra' => '0,00',
            'ult_compra_anterior' => '0,00',
        ],
    ];

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        ErpScreen::set('Histórico de Movimentação');

        $hoje = now();
        $this->cardexPeriodoDe = $hoje->copy()->startOfMonth()->toDateString();
        $this->cardexPeriodoAte = $hoje->copy()->endOfMonth()->toDateString();

        $this->loadCardex();
    }

    public function updatedCardexPeriodoDe(): void
    {
        $this->carregarPeriodoCardex();
    }

    public function updatedCardexPeriodoAte(): void
    {
        $this->carregarPeriodoCardex();
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
        $classes = [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-produtos-form-page',
            'erp-produtos-cardex-page',
        ];

        if ($this->embedsInPdv) {
            $classes[] = 'erp-pdv-embed';
        }

        return $classes;
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
            $this->redirect($this->urlWithPdvEmbed(ProductResource::getUrl('edit', ['record' => $this->getRecord()])));

            return;
        }

        $this->redirect($this->urlWithPdvEmbed(ProductResource::getUrl('index')));
    }

    protected function loadCardex(): void
    {
        $record = $this->getRecord();

        $this->cardexProdutoLabel = $record->codigo . ' — ' . $record->descricao;
        $this->cardexPrestador = EmpresaModulos::empresaPrestadorServicos(ErpContext::currentEmpresa());
        $this->cardexData = app(ProductCardexService::class)->forProduct(
            $record,
            $this->cardexPrestador,
            $this->cardexPeriodoDe,
            $this->cardexPeriodoAte,
        );
    }

    protected function carregarPeriodoCardex(): void
    {
        $this->cardexPeriodoDe = $this->dataPeriodo($this->cardexPeriodoDe) ?? now()->startOfMonth()->toDateString();
        $this->cardexPeriodoAte = $this->dataPeriodo($this->cardexPeriodoAte) ?? now()->endOfMonth()->toDateString();
        $this->loadCardex();
    }

    protected function dataPeriodo(string $valor): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        if (! $data instanceof \DateTimeImmutable || $data->format('Y-m-d') !== $valor) {
            return null;
        }

        return $valor;
    }
}
