<?php

namespace Tests\Unit;

use App\Support\Erp\WhatsApp\WhatsAppPhone;
use PHPUnit\Framework\TestCase;

class WhatsAppPhoneTest extends TestCase
{
    public function test_normalize_adds_mobile_nine_for_ten_digit_brazilian_cell(): void
    {
        $this->assertSame('5547996449859', WhatsAppPhone::normalize('4796449859'));
        $this->assertSame('5547996449859', WhatsAppPhone::normalize('(47) 9644-9859'));
    }

    public function test_normalize_keeps_eleven_digit_brazilian_cell(): void
    {
        $this->assertSame('5547996449859', WhatsAppPhone::normalize('47996449859'));
        $this->assertSame('5547984002117', WhatsAppPhone::normalize('(47) 98400-2117'));
    }

    public function test_normalize_does_not_add_nine_for_landline(): void
    {
        $this->assertSame('554733221100', WhatsAppPhone::normalize('4733221100'));
    }

    public function test_lookup_candidates_prefers_number_with_nine(): void
    {
        $candidates = WhatsAppPhone::lookupCandidates('47996449859');

        $this->assertSame(['5547996449859', '554796449859'], $candidates);
    }

    public function test_format_display_uses_mobile_nine(): void
    {
        $this->assertSame('(47) 99644-9859', WhatsAppPhone::formatDisplay('4796449859'));
        $this->assertSame('(47) 98400-2117', WhatsAppPhone::formatDisplay('47984002117'));
    }

    public function test_is_valid_mobile_requires_eleven_digits(): void
    {
        $this->assertTrue(WhatsAppPhone::isValidMobile(''));
        $this->assertTrue(WhatsAppPhone::isValidMobile('(47) 98400-2117'));
        $this->assertFalse(WhatsAppPhone::isValidMobile('(47) 8400-2117'));
        $this->assertFalse(WhatsAppPhone::isValidMobile('(47) 9840-0211'));
    }
}
