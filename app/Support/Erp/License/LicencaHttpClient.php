<?php

namespace App\Support\Erp\License;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Unitec\FiscalEngine\Util\CaBundleResolver;

/**
 * HTTP para o portal Unitec com CA bundle explícito.
 * FrankenPHP/Windows muitas vezes não herda curl.cainfo do php.ini do CLI.
 */
final class LicencaHttpClient
{
    /**
     * @param  array<string, mixed>  $options
     */
    public static function make(array $options = []): PendingRequest
    {
        return Http::withOptions(self::options($options));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function options(array $options = []): array
    {
        CaBundleResolver::setProjectRoot(base_path());
        $ca = CaBundleResolver::resolve();

        if ($ca !== null && ! array_key_exists('verify', $options)) {
            $options['verify'] = $ca;
        }

        return $options;
    }
}
