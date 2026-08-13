<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductSerial;
use App\Support\Erp\ErpMoney;

trait ManagesPdvSerial
{
    public string $pdvSerialSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $pdvSerialResults = [];

    public ?int $selectedPdvSerialIndex = null;

    public ?int $pdvPendingGradeId = null;

    public ?string $pdvPendingDescricao = null;

    protected function openPdvSerialModal(
        Product $product,
        float $quantidade,
        float $preco,
        ?int $gradeId = null,
        ?string $descricao = null,
    ): void {
        $this->pdvPendingProductId = $product->id;
        $this->pdvPendingQuantidade = $quantidade;
        $this->pdvPendingPreco = $preco;
        $this->pdvPendingGradeId = $gradeId;
        $this->pdvPendingDescricao = $descricao;
        $this->pdvSerialSearch = '';
        $this->refreshPdvSerialResults();
        $this->openPdvModal('serial');
        $this->dispatch('erp-pdv-focus-serial');
    }

    public function refreshPdvSerialResults(): void
    {
        if (! $this->pdvPendingProductId) {
            $this->pdvSerialResults = [];

            return;
        }

        $term = trim($this->pdvSerialSearch);
        $like = '%' . $term . '%';

        $query = ProductSerial::query()
            ->where('product_id', $this->pdvPendingProductId)
            ->where('situacao', 'DISPONIVEL');

        if ($term !== '') {
            $query->where('numero_serie', 'like', $like);
        }

        $this->pdvSerialResults = $query
            ->orderBy('numero_serie')
            ->limit(50)
            ->get()
            ->map(fn (ProductSerial $serial): array => [
                'serial_id' => $serial->id,
                'numero_serie' => $serial->numero_serie,
            ])
            ->values()
            ->all();

        $this->selectedPdvSerialIndex = $this->pdvSerialResults === [] ? null : 0;
    }

    public function updatedPdvSerialSearch(): void
    {
        $this->pdvSerialSearch = mb_strtoupper($this->pdvSerialSearch, 'UTF-8');
        $this->refreshPdvSerialResults();
    }

    public function selectPdvSerialRow(int $index): void
    {
        if (isset($this->pdvSerialResults[$index])) {
            $this->selectedPdvSerialIndex = $index;
        }
    }

    public function movePdvSerialSelection(int $delta): void
    {
        if ($this->pdvSerialResults === []) {
            return;
        }

        $count = count($this->pdvSerialResults);
        $index = ($this->selectedPdvSerialIndex ?? 0) + $delta;
        $this->selectedPdvSerialIndex = max(0, min($count - 1, $index));
    }

    public function confirmPdvSerial(): void
    {
        $product = $this->pdvPendingProductId
            ? Product::query()->find($this->pdvPendingProductId)
            : null;

        if (! $product) {
            $this->closePdvModal();
            $this->resetPdvPendingLaunch();

            return;
        }

        $index = $this->selectedPdvSerialIndex;
        $serial = ($index !== null && isset($this->pdvSerialResults[$index]))
            ? $this->pdvSerialResults[$index]
            : null;

        if (! $serial) {
            $this->notifyPdvError('Selecione um número de série disponível.');

            return;
        }

        $quantidade = 1.0;
        $preco = (float) ($this->pdvPendingPreco ?? $product->preco_venda);
        $gradeId = $this->pdvPendingGradeId;
        $descricao = $this->pdvPendingDescricao ?? $product->descricao;
        $serialId = (int) ($serial['serial_id'] ?? 0);

        $this->closePdvModal();
        $this->resetPdvPendingLaunch();
        $this->pdvPendingGradeId = null;
        $this->pdvPendingDescricao = null;

        $this->confirmAddProduct($product, $quantidade, $preco, $gradeId, $serialId, $descricao);
    }

    public function cancelPdvSerial(): void
    {
        $this->closePdvModal();
        $this->resetPdvPendingLaunch();
        $this->pdvPendingGradeId = null;
        $this->pdvPendingDescricao = null;
        $this->dispatch('erp-pdv-focus-search');
    }
}
