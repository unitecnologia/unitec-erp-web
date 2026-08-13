<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

trait ManagesProductExitConfirm
{
    public bool $productExitConfirmOpen = false;

    public ?string $productFormBaselineHash = null;

    public function confirmProductExitWithoutSaving(): void
    {
        $this->productExitConfirmOpen = false;
        $this->leaveProductForm();
    }

    public function dismissProductExitConfirm(): void
    {
        $this->productExitConfirmOpen = false;
    }

    protected function captureProductFormBaseline(): void
    {
        $this->productFormBaselineHash = $this->hashProductFormStateForDirtyCheck();
        $this->productExitConfirmOpen = false;
    }

    protected function productFormHasUnsavedChanges(): bool
    {
        if ($this->productFormBaselineHash === null) {
            return false;
        }

        return $this->hashProductFormStateForDirtyCheck() !== $this->productFormBaselineHash;
    }

    protected function hashProductFormStateForDirtyCheck(): string
    {
        $payload = [
            'data' => $this->normalizeProductStateForDirtyCheck($this->data ?? []),
            'grades' => $this->gradeRows ?? [],
            'compositions' => $this->compositionRows ?? [],
            'priceTable' => $this->priceTableRows ?? [],
            'imeis' => $this->imeiRows ?? [],
            'foto' => (string) ($this->data['foto_path'] ?? ''),
            'pendingFoto' => (string) ($this->pendingProductFotoUrl ?? ''),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeProductStateForDirtyCheck(array $data): array
    {
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = $value ? '1' : '0';

                continue;
            }

            if ($value === null) {
                $data[$key] = '';

                continue;
            }

            if (is_scalar($value)) {
                $data[$key] = trim((string) $value);
            }
        }

        return $data;
    }
}
