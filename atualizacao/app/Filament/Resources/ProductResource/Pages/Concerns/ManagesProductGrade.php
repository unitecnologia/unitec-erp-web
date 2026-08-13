<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Models\ProductGrade;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;

trait ManagesProductGrade
{
    /** @var array<int, array<string, mixed>> */
    public array $gradeRows = [];

    public ?int $selectedGradeIndex = null;

    protected function loadProductGrades(?Product $product = null): void
    {
        if (! $product) {
            $this->gradeRows = [];
            $this->selectedGradeIndex = null;

            return;
        }

        $this->gradeRows = $product->grades()
            ->orderBy('id')
            ->get()
            ->map(fn (ProductGrade $grade): array => [
                'id' => $grade->id,
                'descricao' => $grade->descricao,
                'tamanho' => $grade->tamanho ?? '',
                'qtd' => $this->formatBrDecimal($grade->qtd, 3),
                'preco' => $this->formatBrDecimal($grade->preco, 2),
                'preco_atacado' => $this->formatBrDecimal($grade->preco_atacado, 2),
            ])
            ->values()
            ->all();
    }

    public function selectGradeRow(int $index): void
    {
        if (isset($this->gradeRows[$index])) {
            $this->selectedGradeIndex = $index;
        }
    }

    public function addGradeRow(): void
    {
        $this->gradeRows[] = [
            'id' => null,
            'descricao' => '',
            'tamanho' => '',
            'qtd' => '0,000',
            'preco' => '0,00',
            'preco_atacado' => '0,00',
        ];

        $this->selectedGradeIndex = count($this->gradeRows) - 1;
        $this->dispatch('erp-masks-refresh');
    }

    public function deleteGradeRow(): void
    {
        if ($this->selectedGradeIndex === null || ! isset($this->gradeRows[$this->selectedGradeIndex])) {
            Notification::make()
                ->title('Selecione uma linha da grade.')
                ->warning()
                ->send();

            return;
        }

        unset($this->gradeRows[$this->selectedGradeIndex]);
        $this->gradeRows = array_values($this->gradeRows);
        $this->selectedGradeIndex = null;
    }

    public function gradeRowsTotalQty(): float
    {
        return collect($this->gradeRows)
            ->sum(fn (array $row): float => $this->parseBrDecimal($row['qtd'] ?? 0, 3));
    }

    protected function syncProductGrades(Product $product): void
    {
        if (! ($product->is_grade ?? false)) {
            $product->grades()->delete();

            return;
        }

        $ids = [];

        foreach ($this->gradeRows as $row) {
            $descricao = ErpUppercase::normalizeFieldValue('descricao', trim((string) ($row['descricao'] ?? '')));

            if ($descricao === '') {
                continue;
            }

            $attributes = [
                'descricao' => $descricao,
                'tamanho' => ErpUppercase::normalizeFieldValue('descricao', trim((string) ($row['tamanho'] ?? ''))) ?: null,
                'qtd' => $this->parseBrDecimal($row['qtd'] ?? 0, 3),
                'preco' => $this->parseBrDecimal($row['preco'] ?? 0, 2),
                'preco_atacado' => $this->parseBrDecimal($row['preco_atacado'] ?? 0, 2),
            ];

            if (filled($row['id'] ?? null)) {
                ProductGrade::query()->whereKey($row['id'])->update($attributes);
                $ids[] = (int) $row['id'];
            } else {
                $created = $product->grades()->create($attributes);
                $ids[] = $created->id;
            }
        }

        $product->grades()->whereNotIn('id', $ids)->delete();
    }
}
