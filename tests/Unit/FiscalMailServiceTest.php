<?php

namespace Tests\Unit;

use App\Support\Erp\Mail\FiscalMailService;
use PHPUnit\Framework\TestCase;

class FiscalMailServiceTest extends TestCase
{
    public function test_normalize_modo_defaults_to_api(): void
    {
        $this->assertSame(FiscalMailService::MODO_SMTP, FiscalMailService::normalizeModo(''));
        $this->assertSame(FiscalMailService::MODO_API, FiscalMailService::normalizeModo('api'));
        $this->assertSame(FiscalMailService::MODO_SMTP, FiscalMailService::normalizeModo('smtp'));
    }

    public function test_normalize_api_provider_defaults_to_brevo(): void
    {
        $this->assertSame(FiscalMailService::API_BREVO, FiscalMailService::normalizeApiProvider(''));
        $this->assertSame(FiscalMailService::API_BREVO, FiscalMailService::normalizeApiProvider('brevo'));
        $this->assertSame(FiscalMailService::API_BREVO, FiscalMailService::normalizeApiProvider('unknown'));
    }
}
