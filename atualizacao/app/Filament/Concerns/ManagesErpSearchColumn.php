<?php

namespace App\Filament\Concerns;

trait ManagesErpSearchColumn
{
    /**
     * @return list<string>
     */
    abstract protected function erpAllowedSearchColumns(): array;

    protected function erpDefaultSearchColumn(): string
    {
        $allowed = $this->erpAllowedSearchColumns();

        return $allowed[0] ?? 'codigo';
    }

    protected function erpSearchColumnSessionKey(): string
    {
        return 'erp_search_column_'.str_replace('\\', '_', static::class);
    }

    protected function erpNormalizeSearchColumn(mixed $value): string
    {
        $allowed = $this->erpAllowedSearchColumns();

        return in_array($value, $allowed, true) ? (string) $value : $this->erpDefaultSearchColumn();
    }

    protected function erpRestoreSearchColumnFromSession(): void
    {
        if (! request()->has('campo')) {
            $this->searchColumn = (string) session(
                $this->erpSearchColumnSessionKey(),
                $this->erpDefaultSearchColumn()
            );
        }

        $this->searchColumn = $this->erpNormalizeSearchColumn($this->searchColumn);
    }

    public function setSearchColumn(string $column): void
    {
        $this->searchColumn = $this->erpNormalizeSearchColumn($column);

        session([$this->erpSearchColumnSessionKey() => $this->searchColumn]);

        $this->localSearch = '';
        $this->erpAfterSearchColumnChanged();
    }

    public function updatedSearchColumn(): void
    {
        $this->setSearchColumn($this->searchColumn);
    }

    protected function erpAfterSearchColumnChanged(): void
    {
        if (method_exists($this, 'clearListSelection')) {
            $this->clearListSelection();
        }

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }
}
