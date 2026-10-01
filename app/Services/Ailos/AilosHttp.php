<?php

namespace App\Services\Ailos;

use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Http\Client\PendingRequest;

/**
 * HTTP Ailos com CA bundle explícito (Windows/FrankenPHP sem curl.cainfo).
 */
final class AilosHttp
{
    public static function client(): PendingRequest
    {
        return LicencaHttpClient::make();
    }
}
