<?php

namespace App\Support\Erp;

final class ProductLocalizacao
{
    /**
     * @return array{c: string, m: string, p: string, g: string}
     */
    public static function parse(?string $value): array
    {
        $parts = [
            'c' => '',
            'm' => '',
            'p' => '',
            'g' => '',
        ];

        if (blank($value)) {
            return $parts;
        }

        $value = trim($value);

        if (preg_match('/\bC:(\d+)/i', $value, $match)) {
            $parts['c'] = $match[1];
        }

        if (preg_match('/\bM:(\d+)/i', $value, $match)) {
            $parts['m'] = $match[1];
        }

        if (preg_match('/\bP:(\d+)/i', $value, $match)) {
            $parts['p'] = $match[1];
        }

        if (preg_match('/\bG:(\d+)/i', $value, $match)) {
            $parts['g'] = $match[1];
        }

        return $parts;
    }

    public static function corredorFromLocalizacao(?string $localizacao): string
    {
        return self::parse($localizacao)['c'];
    }

    public static function corredorLabel(?string $localizacao): string
    {
        $corredor = self::corredorFromLocalizacao($localizacao);

        return $corredor !== '' ? 'CORREDOR ' . $corredor : 'SEM CORREDOR';
    }

    public static function isStructured(?string $value): bool
    {
        if (blank($value)) {
            return false;
        }

        return (bool) preg_match('/C:\d+/i', trim($value));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function expandIntoForm(array $data): array
    {
        $parsed = self::parse($data['localizacao'] ?? null);

        $data['loc_corredor'] = $parsed['c'];
        $data['loc_modulo'] = $parsed['m'];
        $data['loc_prateleira'] = $parsed['p'];
        $data['loc_gaveta'] = $parsed['g'];

        $localizacao = trim((string) ($data['localizacao'] ?? ''));

        if ($localizacao !== '' && ! self::isStructured($localizacao)) {
            $data['loc_legado'] = $localizacao;
        } else {
            $data['loc_legado'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function collapseFromForm(array $data): array
    {
        $formatted = self::format(
            $data['loc_corredor'] ?? null,
            $data['loc_modulo'] ?? null,
            $data['loc_prateleira'] ?? null,
            $data['loc_gaveta'] ?? null,
        );

        if ($formatted !== null) {
            $data['localizacao'] = $formatted;
        } elseif (filled($data['loc_legado'] ?? null) && ! self::hasStructuredParts($data)) {
            $data['localizacao'] = trim((string) $data['loc_legado']);
        } elseif (! self::isStructured($data['localizacao'] ?? null)) {
            $data['localizacao'] = null;
        }

        unset(
            $data['loc_corredor'],
            $data['loc_modulo'],
            $data['loc_prateleira'],
            $data['loc_gaveta'],
            $data['loc_legado'],
        );

        return $data;
    }

    public static function format(
        mixed $corredor,
        mixed $modulo,
        mixed $prateleira,
        mixed $gaveta,
    ): ?string {
        $segments = [];

        if (self::filledPart($corredor)) {
            $segments[] = 'C:' . self::normalizePart($corredor);
        }

        if (self::filledPart($modulo)) {
            $segments[] = 'M:' . self::normalizePart($modulo);
        }

        if (self::filledPart($prateleira)) {
            $segments[] = 'P:' . self::normalizePart($prateleira);
        }

        if (self::filledPart($gaveta)) {
            $segments[] = 'G:' . self::normalizePart($gaveta);
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function previewFromForm(array $data): ?string
    {
        return self::format(
            $data['loc_corredor'] ?? null,
            $data['loc_modulo'] ?? null,
            $data['loc_prateleira'] ?? null,
            $data['loc_gaveta'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function hasStructuredParts(array $data): bool
    {
        return self::filledPart($data['loc_corredor'] ?? null)
            || self::filledPart($data['loc_modulo'] ?? null)
            || self::filledPart($data['loc_prateleira'] ?? null)
            || self::filledPart($data['loc_gaveta'] ?? null);
    }

    protected static function filledPart(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' && (int) $normalized > 0;
    }

    protected static function normalizePart(mixed $value): int
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === '') {
            return 0;
        }

        return max(0, min(99, (int) substr($digits, 0, 2)));
    }

    public static function resolveFromEntregaItem(?string $itemLocalizacao, ?string $productLocalizacao = null): string
    {
        if (filled($itemLocalizacao)) {
            return trim((string) $itemLocalizacao);
        }

        return trim((string) ($productLocalizacao ?? ''));
    }

    /**
     * Ordenação bipagem: corredor, módulo, prateleira, gaveta, descrição, código, quantidade.
     */
    public static function compareForBipagemSort(
        ?string $localA,
        ?string $localB,
        string $descricaoA,
        string $descricaoB,
        ?string $codigoA,
        ?string $codigoB,
        float $quantidadeA,
        float $quantidadeB,
    ): int {
        foreach (['c', 'm', 'p', 'g'] as $segment) {
            $cmp = self::bipagemPartSortValue(self::parse($localA)[$segment])
                <=> self::bipagemPartSortValue(self::parse($localB)[$segment]);

            if ($cmp !== 0) {
                return $cmp;
            }
        }

        $legacyA = trim((string) ($localA ?? ''));
        $legacyB = trim((string) ($localB ?? ''));

        if ($legacyA !== '' && $legacyB !== '' && ! self::isStructured($legacyA) && ! self::isStructured($legacyB)) {
            $legacyCmp = strcasecmp($legacyA, $legacyB);

            if ($legacyCmp !== 0) {
                return $legacyCmp;
            }
        }

        $descCmp = strcasecmp($descricaoA, $descricaoB);

        if ($descCmp !== 0) {
            return $descCmp;
        }

        $codCmp = strcmp((string) ($codigoA ?? ''), (string) ($codigoB ?? ''));

        if ($codCmp !== 0) {
            return $codCmp;
        }

        return $quantidadeA <=> $quantidadeB;
    }

    protected static function bipagemPartSortValue(string $part): int
    {
        $part = trim($part);

        if ($part === '') {
            return PHP_INT_MAX;
        }

        return (int) $part;
    }
}
