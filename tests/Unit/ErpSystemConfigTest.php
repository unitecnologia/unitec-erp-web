<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\ErpSystemConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ErpSystemConfigTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_url_prioriza_banco_sobre_env(): void
    {
        $empresa = Empresa::query()->create([
            'codigo' => 9901,
            'nome' => 'EMPRESA CONFIG TESTE',
            'fantasia' => 'EMPRESA CONFIG',
            'ativo' => true,
            'param_update_download_url' => 'https://dropbox.example/Unitec-ERP-Update.zip?dl=1',
        ]);

        $this->assertSame(
            'https://dropbox.example/Unitec-ERP-Update.zip?dl=1',
            ErpSystemConfig::updateDownloadUrl($empresa->id),
        );
    }

    public function test_backup_status_vem_do_banco(): void
    {
        $empresa = Empresa::query()->create([
            'codigo' => 9902,
            'nome' => 'EMPRESA BACKUP TESTE',
            'fantasia' => 'EMPRESA BACKUP',
            'ativo' => true,
            'param_backup_ultimo_status' => 'failed',
            'param_backup_ultimo_em' => '24/05/2026 16:00',
        ]);

        $this->assertSame('failed', ErpSystemConfig::backupLastStatus($empresa->id));
        $this->assertSame('24/05/2026 16:00', ErpSystemConfig::backupLastAt($empresa->id));
    }
}
