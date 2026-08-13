<?php

namespace App\Filament\Concerns;

use App\Support\Erp\ErpUppercase;

trait NormalizesErpUppercaseFormData
{
    /** @var list<string> */
    private const ERP_UPPERCASE_PROPERTIES = [
        'contactMotivo',
        'contactDescricao',
        'localSearch',
        'profileNome',
        'prazosRapidos',
    ];

    /** @var list<string> */
    private const ERP_UPPERCASE_PREFIXES = [
        'data.',
        'form.',
        'bandeiraForm.',
        'maquininhaForm.',
    ];

    /**
     * Propriedades que guardam valores técnicos (enums de select/radio) e não podem ir para maiúsculas.
     *
     * @return list<string>
     */
    protected function erpUppercaseIgnoredProperties(): array
    {
        return [];
    }

    public function updated($propertyName, $value): void
    {
        if (! is_string($value)) {
            return;
        }

        if (in_array($propertyName, $this->erpUppercaseIgnoredProperties(), true)) {
            return;
        }

        $normalized = null;

        foreach (self::ERP_UPPERCASE_PREFIXES as $prefix) {
            if (str_starts_with($propertyName, $prefix)) {
                $field = (string) str($propertyName)->after($prefix);
                // Parcelas numéricas "30,60,90" — só sobe letras se houver.
                $leaf = (string) str($field)->afterLast('.');
                $normalized = ErpUppercase::normalizeFieldValue($leaf !== '' ? $leaf : $field, $value);
                break;
            }
        }

        if ($normalized === null && in_array($propertyName, self::ERP_UPPERCASE_PROPERTIES, true)) {
            $normalized = ErpUppercase::normalizeFieldValue($propertyName, $value);
        }

        if ($normalized !== null && $normalized !== $value) {
            data_set($this, $propertyName, $normalized);
        }
    }
}
