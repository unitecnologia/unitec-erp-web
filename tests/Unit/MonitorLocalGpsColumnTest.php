<?php

namespace Tests\Unit;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\ForcaVendasOrder;
use Tests\TestCase;

class MonitorLocalGpsColumnTest extends TestCase
{
    public function test_google_maps_url_com_coordenadas_validas(): void
    {
        $order = new ForcaVendasOrder([
            'latitude' => '-27.0099811',
            'longitude' => '-48.6290570',
        ]);

        $url = ForcaVendasMonitorResource::googleMapsUrl($order);

        $this->assertSame(
            'https://www.google.com/maps?q='.rawurlencode('-27.0099811,-48.6290570'),
            $url,
        );
    }

    public function test_google_maps_url_null_sem_coordenadas(): void
    {
        $order = new ForcaVendasOrder([
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->assertNull(ForcaVendasMonitorResource::googleMapsUrl($order));
    }

    public function test_google_maps_url_null_com_coordenada_parcial(): void
    {
        $soLat = new ForcaVendasOrder(['latitude' => '-27.01', 'longitude' => null]);
        $soLng = new ForcaVendasOrder(['latitude' => null, 'longitude' => '-48.62']);

        $this->assertNull(ForcaVendasMonitorResource::googleMapsUrl($soLat));
        $this->assertNull(ForcaVendasMonitorResource::googleMapsUrl($soLng));
    }

    public function test_google_maps_url_null_fora_da_faixa(): void
    {
        $order = new ForcaVendasOrder([
            'latitude' => '91',
            'longitude' => '-48.62',
        ]);

        $this->assertNull(ForcaVendasMonitorResource::googleMapsUrl($order));
    }

    public function test_google_maps_url_aceita_zero_zero(): void
    {
        $order = new ForcaVendasOrder([
            'latitude' => '0',
            'longitude' => '0',
        ]);

        $this->assertSame(
            'https://www.google.com/maps?q='.rawurlencode('0.0000000,0.0000000'),
            ForcaVendasMonitorResource::googleMapsUrl($order),
        );
    }
}
