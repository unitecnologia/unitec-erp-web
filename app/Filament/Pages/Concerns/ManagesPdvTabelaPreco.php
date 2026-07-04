<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PriceTable;
use App\Models\Product;
use Filament\Notifications\Notification;

trait ManagesPdvTabelaPreco
{
    public ?int $pdvPriceTableId = null;

    /** @var array<int, array<string, mixed>> */
    public array $tabelaPrecoResults = [];

    public ?int $selectedTabelaPrecoIndex = null;

    protected function loadPdvPriceTableFromSession(): void
    {
        if (! $this->pdvConfig()->habilitarTabelaPreco()) {
            $this->pdvPriceTableId = null;

            return;
        }

        $sessionId = session('erp.pdv.price_table_id');

        if (filled($sessionId)) {
            $this->pdvPriceTableId = (int) $sessionId;

            return;
        }

        $this->pdvPriceTableId = PriceTable::query()
            ->where('ativo', true)
            ->where('codigo', '1')
            ->value('id');
    }

    public function getPdvHabilitarTabelaPrecoProperty(): bool
    {
        return $this->pdvConfig()->habilitarTabelaPreco();
    }

    public function getPdvTabelaPrecoLabelProperty(): string
    {
        if (! $this->pdvHabilitarTabelaPreco) {
            return '';
        }

        $table = $this->pdvPriceTableId
            ? PriceTable::query()->find($this->pdvPriceTableId)
            : null;

        if (! $table) {
            return 'PADRAO';
        }

        return mb_strtoupper(trim($table->codigo . ' - ' . $table->descricao), 'UTF-8');
    }

    public function openTabelaPrecoModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if (! $this->pdvConfig()->habilitarTabelaPreco()) {
            $this->notifyPdvError('Tabela de preço não habilitada nos parâmetros.');

            return;
        }

        $this->refreshTabelaPrecoResults();
        $this->openPdvModal('tabela_preco');
        $this->dispatch('erp-pdv-focus-tabela-preco');
    }

    public function refreshTabelaPrecoResults(): void
    {
        $this->tabelaPrecoResults = PriceTable::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get()
            ->map(fn (PriceTable $table): array => [
                'price_table_id' => $table->id,
                'codigo' => $table->codigo,
                'descricao' => mb_strtoupper($table->descricao, 'UTF-8'),
            ])
            ->values()
            ->all();

        $selectedIndex = null;

        foreach ($this->tabelaPrecoResults as $index => $row) {
            if ((int) ($row['price_table_id'] ?? 0) === (int) $this->pdvPriceTableId) {
                $selectedIndex = $index;
                break;
            }
        }

        $this->selectedTabelaPrecoIndex = $selectedIndex ?? ($this->tabelaPrecoResults === [] ? null : 0);
    }

    public function selectTabelaPrecoRow(int $index): void
    {
        if (isset($this->tabelaPrecoResults[$index])) {
            $this->selectedTabelaPrecoIndex = $index;
        }
    }

    public function moveTabelaPrecoSelection(int $delta): void
    {
        if ($this->tabelaPrecoResults === []) {
            return;
        }

        $count = count($this->tabelaPrecoResults);
        $index = ($this->selectedTabelaPrecoIndex ?? 0) + $delta;
        $this->selectedTabelaPrecoIndex = max(0, min($count - 1, $index));
    }

    public function confirmTabelaPreco(): void
    {
        $index = $this->selectedTabelaPrecoIndex;

        if ($index === null || ! isset($this->tabelaPrecoResults[$index])) {
            $this->notifyPdvError('Selecione uma tabela de preço.');

            return;
        }

        $tableId = (int) ($this->tabelaPrecoResults[$index]['price_table_id'] ?? 0);

        if ($tableId <= 0) {
            $this->notifyPdvError('Tabela de preço inválida.');

            return;
        }

        $this->pdvPriceTableId = $tableId;
        session(['erp.pdv.price_table_id' => $tableId]);
        $this->recheckCupomPricesForTable();
        $this->closePdvModal();

        Notification::make()
            ->title('Tabela de preço alterada.')
            ->body($this->pdvTabelaPrecoLabel)
            ->success()
            ->send();

        $this->dispatch('erp-pdv-focus-search');
    }

    protected function recheckCupomPricesForTable(): void
    {
        if ($this->cupomItens === []) {
            return;
        }

        $priceService = $this->pdvPriceService();

        foreach ($this->cupomItens as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()->find($productId);

            if (! $product) {
                continue;
            }

            $quantidade = (float) ($item['quantidade'] ?? 1);
            $preco = $priceService->resolvePrecoVenda($product, $quantidade);
            $this->cupomItens[$index]['preco'] = $preco;
            $this->cupomItens[$index]['total'] = round($quantidade * $preco, 2);
        }

        $this->persistCupomToSession();
    }

    public function cancelTabelaPreco(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
