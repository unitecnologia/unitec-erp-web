<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use Carbon\Carbon;
use Tests\TestCase;

class ForcaVendasOrderTest extends TestCase
{
    public function test_data_abertura_uses_client_created_at(): void
    {
        $order = new ForcaVendasOrder([
            'client_created_at' => Carbon::parse('2026-07-01 14:30:00', 'UTC'),
            'received_at' => Carbon::parse('2026-07-02 09:00:00', 'UTC'),
        ]);

        $this->assertTrue($order->dataAberturaAt()->equalTo(
            Carbon::parse('2026-07-01 14:30:00', 'UTC'),
        ));
    }

    public function test_data_abertura_falls_back_to_received_at(): void
    {
        $order = new ForcaVendasOrder([
            'client_created_at' => null,
            'received_at' => Carbon::parse('2026-07-02 09:00:00', 'UTC'),
        ]);

        $this->assertTrue($order->dataAberturaAt()->equalTo(
            Carbon::parse('2026-07-02 09:00:00', 'UTC'),
        ));
    }
}
