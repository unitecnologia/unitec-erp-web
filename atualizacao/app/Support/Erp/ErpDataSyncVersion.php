<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\Cache;

/**
 * Versão leve por canal (produtos, pessoas, …) para sincronizar listagens em rede.
 * Estações só recarregam a grade quando o número muda.
 */
final class ErpDataSyncVersion
{
    public const CHANNEL_PRODUCTS = 'products';

    public const CHANNEL_SALES = 'sales';

    public const CHANNEL_QUOTES = 'quotes';

    public const CHANNEL_PEOPLE = 'people';

    private const CACHE_PREFIX = 'erp_data_sync_v:';

    public static function current(string $channel): string
    {
        $channel = self::normalizeChannel($channel);
        $key = self::cacheKey($channel);
        $value = Cache::get($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fresh = self::newVersion();
        Cache::forever($key, $fresh);

        return $fresh;
    }

    public static function bump(string $channel): string
    {
        $channel = self::normalizeChannel($channel);
        $fresh = self::newVersion();
        Cache::forever(self::cacheKey($channel), $fresh);

        return $fresh;
    }

    private static function cacheKey(string $channel): string
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        return self::CACHE_PREFIX.$empresaId.':'.$channel;
    }

    private static function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return $channel !== '' ? $channel : 'default';
    }

    private static function newVersion(): string
    {
        return sprintf('%d-%s', (int) (microtime(true) * 1000), bin2hex(random_bytes(3)));
    }
}
