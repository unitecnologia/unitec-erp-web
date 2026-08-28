<?php

namespace Tests\Unit;

use App\Support\Erp\License\LicencaSnapshot;
use PHPUnit\Framework\TestCase;

class LicencaSnapshotDeviceQuotaTest extends TestCase
{
    public function test_preserva_limites_do_portal_no_cache(): void
    {
        $snapshot = LicencaSnapshot::fromArray([
            'status' => 'ativo',
            'quantidade_computadores' => 3,
            'quantidade_telefones' => 2,
        ]);

        $cached = LicencaSnapshot::fromArray($snapshot->toArray(), true);

        $this->assertSame(3, $cached->quantidadeComputadores);
        $this->assertSame(2, $cached->quantidadeTelefones);
        $this->assertTrue($cached->fromCache);
    }

    public function test_limite_nulo_permanece_sem_limite(): void
    {
        $snapshot = LicencaSnapshot::fromArray([
            'status' => 'ativo',
            'quantidade_computadores' => null,
            'quantidade_telefones' => 'null',
        ]);

        $this->assertNull($snapshot->quantidadeComputadores);
        $this->assertNull($snapshot->quantidadeTelefones);
    }
}
