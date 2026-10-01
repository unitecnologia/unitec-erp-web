<?php

namespace App\Services\Sicredi;

use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Http\Client\PendingRequest;

/**
 * HTTP Sicredi com CA bundle explícito (Windows/FrankenPHP sem curl.cainfo).
 */
final class SicrediHttp
{
    public static function client(): PendingRequest
    {
        return LicencaHttpClient::make();
    }
}
