<?php

namespace App\Support\Erp;

/**
 * Ícones do menu superior sem o componente Filament (SVG inline pesado + Blade caro).
 * Lê o mesmo Heroicon outline do pacote blade-heroicons — visual equivalente.
 */
final class ErpMenuIcons
{
    /** @var array<string, string> */
    private static array $innerCache = [];

    public static function stem(string $icon): string
    {
        $icon = trim($icon);

        if (str_starts_with($icon, 'heroicon-')) {
            return substr($icon, strlen('heroicon-'));
        }

        return $icon;
    }

    public static function svg(string $icon, ?string $colorClass = null): string
    {
        $stem = self::stem($icon);
        $inner = self::innerHtml($stem);
        $class = trim('erp-menu-bar__icon '.($colorClass ?? ''));

        if ($inner === '') {
            return '';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="'
            .e($class)
            .'">'
            .$inner
            .'</svg>';
    }

    private static function innerHtml(string $stem): string
    {
        if (array_key_exists($stem, self::$innerCache)) {
            return self::$innerCache[$stem];
        }

        if (! preg_match('/^[a-z]+-[a-z0-9-]+$/', $stem)) {
            return self::$innerCache[$stem] = '';
        }

        $path = base_path('vendor/blade-ui-kit/blade-heroicons/resources/svg/'.$stem.'.svg');

        if (! is_file($path)) {
            return self::$innerCache[$stem] = '';
        }

        $raw = (string) file_get_contents($path);

        if ($raw === '') {
            return self::$innerCache[$stem] = '';
        }

        if (! preg_match_all('/<path\b[^>]*>/', $raw, $matches)) {
            return self::$innerCache[$stem] = '';
        }

        return self::$innerCache[$stem] = implode('', $matches[0]);
    }
}
