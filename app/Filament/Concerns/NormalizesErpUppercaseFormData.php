<?php

namespace App\Filament\Concerns;

use App\Support\Erp\ErpUppercase;

trait NormalizesErpUppercaseFormData
{
    /** @var list<string> */
    private const ERP_UPPERCASE_PROPERTIES = [
        'contactMotivo',
        'contactDescricao',
    ];

    public function updated($propertyName, $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $normalized = null;

        if (str_starts_with($propertyName, 'data.')) {
            $field = (string) str($propertyName)->after('data.');
            $normalized = ErpUppercase::normalizeFieldValue($field, $value);
        } elseif (in_array($propertyName, self::ERP_UPPERCASE_PROPERTIES, true)) {
            $normalized = ErpUppercase::normalizeFieldValue($propertyName, $value);
        }

        if ($normalized !== null && $normalized !== $value) {
            data_set($this, $propertyName, $normalized);
        }
    }
}
