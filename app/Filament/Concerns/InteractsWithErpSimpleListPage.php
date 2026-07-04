<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithErpSimpleListPage
{
    abstract protected function erpSimpleListSearchInput(): string;

    abstract protected function erpSimpleListCreateMethod(): string;

    abstract protected function erpSimpleListEditMethod(): string;

    abstract protected function erpSimpleListDeleteMethod(): string;

    protected function buildSimpleListKeyboardConfig(): array
    {
        return [
            'searchInput' => $this->erpSimpleListSearchInput(),
            'create' => $this->erpSimpleListCreateMethod(),
            'edit' => $this->erpSimpleListEditMethod(),
            'delete' => $this->erpSimpleListDeleteMethod(),
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
            ],
        ];
    }

    protected function applySimpleLocalSearch(Builder $query, string $term, array $columns, string $defaultColumn = 'codigo'): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $columns, true)
            ? $this->searchColumn
            : $defaultColumn;

        $like = '%' . $term . '%';

        if ($column === 'codigo') {
            if (is_numeric($term)) {
                $query->whereKey((int) $term);

                return;
            }

            if ($query->getConnection()->getDriverName() === 'sqlite') {
                $query->whereRaw('CAST(id AS TEXT) LIKE ?', [$like]);

                return;
            }

            $query->whereRaw('CAST(id AS CHAR) LIKE ?', [$like]);

            return;
        }

        $query->where($column, 'like', $like);
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function deleteSimpleRecord(string $modelClass, string $notificationTitle): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $modelClass::query()->whereKey($recordId)->delete();

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title($notificationTitle)
            ->success()
            ->send();
    }
}
