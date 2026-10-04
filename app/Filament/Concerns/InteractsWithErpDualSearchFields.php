<?php

namespace App\Filament\Concerns;

use App\Support\Erp\ErpSearchFieldSelection;
use Livewire\Attributes\Url;

trait InteractsWithErpDualSearchFields
{
    /** @var list<string> */
    public array $searchFieldsActive = [];

    #[Url(as: 'campos', except: '')]
    public string $searchFieldsQuery = '';

    /**
     * @param  list<string>  $allowed
     */
    protected function erpRestoreDualSearchFields(array $allowed, string $sessionKey): void
    {
        $fallback = in_array($this->searchColumn, $allowed, true)
            ? $this->searchColumn
            : (string) ($allowed[0] ?? '');

        if ($this->searchFieldsQuery !== '') {
            $this->erpApplyDualSearchFields(
                ErpSearchFieldSelection::normalize(
                    array_map('trim', explode(',', $this->searchFieldsQuery)),
                    $allowed,
                    $fallback,
                ),
                $sessionKey,
            );

            return;
        }

        if (! request()->has('campo')) {
            $stored = session($sessionKey);

            if (is_array($stored)) {
                $this->erpApplyDualSearchFields(
                    ErpSearchFieldSelection::normalize($stored, $allowed, $fallback),
                    $sessionKey,
                );

                return;
            }
        }

        $this->erpApplyDualSearchFields(
            ErpSearchFieldSelection::normalize([$fallback], $allowed, $fallback),
            $sessionKey,
            false,
        );
    }

    /**
     * @param  list<string>  $allowed
     */
    protected function erpToggleDualSearchField(string $column, array $allowed, string $sessionKey): bool
    {
        $next = ErpSearchFieldSelection::toggle(
            $this->searchFieldsActive !== [] ? $this->searchFieldsActive : [$this->searchColumn],
            $column,
            $allowed,
            $this->searchColumn,
        );

        if ($next === null) {
            $this->skipRender();

            return false;
        }

        $this->erpApplyDualSearchFields($next, $sessionKey);

        return true;
    }

    /**
     * @param  list<string>  $fields
     */
    protected function erpApplyDualSearchFields(array $fields, string $sessionKey, bool $persist = true): void
    {
        $this->searchFieldsActive = $fields;
        $this->searchColumn = $fields[array_key_last($fields)] ?? $this->searchColumn;
        $this->searchFieldsQuery = count($fields) > 1 ? implode(',', $fields) : '';

        if ($persist) {
            session([$sessionKey => $fields]);
        }
    }
}
