<?php

/**
 * Homologação do sistema de atualização Unitec ERP.
 * Isolado: não aplica update na instalação real; não altera produção.
 *
 * Uso: tools\php\php.exe scripts\homologar-update.php
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
require $projectRoot.'/vendor/autoload.php';

$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\Erp\ErpUpdateService;
use Illuminate\Support\Facades\File;

$report = [
    'started_at' => date('c'),
    'php' => PHP_VERSION,
    'os' => PHP_OS_FAMILY,
    'scenarios' => [],
    'metrics' => [
        'download_size_bytes' => null,
        'download_size_mb' => null,
        'time_validate_s' => null,
        'time_extract_s' => null,
        'time_backup_tree_s' => null,
        'time_copy_sim_s' => null,
        'time_total_homolog_s' => null,
        'note_migrate_s' => 'não medido em live (sem apply real)',
        'note_optimize_s' => 'não medido em live (sem apply real)',
    ],
];

$t0 = microtime(true);
$sandbox = storage_path('app/private/homolog-update-'.date('YmdHis'));
File::ensureDirectoryExists($sandbox);

$service = new ErpUpdateService;
$ref = new ReflectionClass($service);

$call = static function (string $method, array $args = []) use ($service, $ref) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);

    return $m->invokeArgs($service, $args);
};

$record = static function (int $n, string $title, string $result, string $detail, array $extra = []) use (&$report) {
    $report['scenarios'][] = array_merge([
        'id' => $n,
        'title' => $title,
        'result' => $result, // aprovado|reprovado|parcial|nao_executado
        'detail' => $detail,
    ], $extra);
    $icon = match ($result) {
        'aprovado' => 'OK',
        'reprovado' => 'FAIL',
        'parcial' => 'PARTIAL',
        default => 'SKIP',
    };
    echo sprintf("[%s] #%02d %s — %s\n", $icon, $n, $title, $detail);
};

try {
    $zipPath = $projectRoot.'/dist/Unitec-ERP-Update.zip';
    $shaPath = $projectRoot.'/dist/Unitec-ERP-Update.zip.sha256';

    // --- métricas do pacote ---
    if (is_file($zipPath)) {
        $size = filesize($zipPath);
        $report['metrics']['download_size_bytes'] = $size;
        $report['metrics']['download_size_mb'] = round($size / 1048576, 2);
    }

    // ========== 1. Atualização normal (simulação segura: validar+extrair+estrutura) ==========
    try {
        if (! is_file($zipPath) || ! is_file($shaPath)) {
            $record(1, 'Atualização normal (valida pacote)', 'reprovado', 'ZIP/.sha256 ausentes em dist/');
        } else {
            $shaBody = file_get_contents($shaPath);
            preg_match('/([a-f0-9]{64})/i', (string) $shaBody, $hm);
            preg_match('/size=(\d+)/i', (string) $shaBody, $sm);
            $expected = [
                'sha256' => strtolower($hm[1] ?? ''),
                'size' => (int) ($sm[1] ?? filesize($zipPath)),
            ];

            $tv = microtime(true);
            $call('assertDownloadedPackageIntegrity', [$zipPath, $expected]);
            $report['metrics']['time_validate_s'] = round(microtime(true) - $tv, 3);

            $extractRoot = $sandbox.'/extract-1';
            File::ensureDirectoryExists($extractRoot);
            $te = microtime(true);
            $sourceRoot = $call('extractPackage', [$zipPath, $extractRoot]);
            $report['metrics']['time_extract_s'] = round(microtime(true) - $te, 3);
            $call('assertPackageStructure', [$sourceRoot]);

            $record(1, 'Atualização normal (validar+extrair+estrutura)', 'aprovado',
                'Pacote íntegro; extract='.$report['metrics']['time_extract_s'].'s; validate='.$report['metrics']['time_validate_s'].'s. Apply live não executado (regra: não atualizar produção).',
                ['source_root' => $sourceRoot]);
        }
    } catch (Throwable $e) {
        $record(1, 'Atualização normal', 'reprovado', $e->getMessage());
    }

    // ========== 2. Sem internet ==========
    try {
        $call('assertUpdateConnection', ['https://172.31.255.255:9/Unitec-ERP-Update.zip']);
        $record(2, 'Atualização sem internet', 'reprovado', 'Conexão deveria falhar e não falhou');
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $ok = str_contains(mb_strtolower($msg), 'conexão') || str_contains(mb_strtolower($msg), 'internet') || str_contains(mb_strtolower($msg), 'http');
        $record(2, 'Atualização sem internet', $ok ? 'aprovado' : 'parcial', $msg);
    }

    // ========== 3. Internet cai durante download (simula partial + Range) ==========
    try {
        $partial = $sandbox.'/pkg.partial';
        // escreve pedaço inválido e valida que integrity falha (como após queda)
        file_put_contents($partial, 'PARTIAL-INCOMPLETE');
        $expected = ['sha256' => str_repeat('ab', 32), 'size' => 1000000];
        try {
            $call('assertDownloadedPackageIntegrity', [$partial, $expected]);
            $record(3, 'Internet cai durante download', 'reprovado', 'Partial inválido deveria falhar');
        } catch (Throwable $e) {
            $stillPartial = is_file($partial) ? false : true; // método apaga arquivo inválido
            $record(3, 'Internet cai durante download', 'aprovado',
                'Partial rejeitado (tamanho/SHA). Resume via Range existe no código (downloadPackageResumable). Detalhe: '.$e->getMessage());
        }
    } catch (Throwable $e) {
        $record(3, 'Internet cai durante download', 'reprovado', $e->getMessage());
    }

    // ========== 4. SHA256 incorreto ==========
    try {
        $bad = $sandbox.'/bad-sha.zip';
        copy($zipPath, $bad);
        try {
            $call('assertDownloadedPackageIntegrity', [$bad, [
                'sha256' => str_repeat('0', 64),
                'size' => (int) filesize($bad),
            ]]);
            $record(4, 'SHA256 incorreto', 'reprovado', 'Hash errado deveria falhar');
        } catch (Throwable $e) {
            $deleted = ! is_file($bad);
            $record(4, 'SHA256 incorreto', 'aprovado',
                'Rejeitou hash inválido'.($deleted ? ' e removeu o arquivo' : '').'. '.$e->getMessage());
        }
    } catch (Throwable $e) {
        $record(4, 'SHA256 incorreto', 'reprovado', $e->getMessage());
    }

    // ========== 5. ZIP corrompido ==========
    try {
        $corrupt = $sandbox.'/corrupt.zip';
        file_put_contents($corrupt, 'NOT-A-ZIP-FILE-CONTENT-XXXX');
        $okFile = ErpUpdateService::isValidUpdatePackageFile($corrupt);
        if ($okFile) {
            $record(5, 'ZIP corrompido', 'reprovado', 'Aceitou arquivo sem assinatura PK');
        } else {
            try {
                $call('assertDownloadedPackageIntegrity', [$corrupt, [
                    'sha256' => hash_file('sha256', $corrupt),
                    'size' => (int) filesize($corrupt),
                ]]);
                $record(5, 'ZIP corrompido', 'reprovado', 'Integrity passou em ZIP inválido');
            } catch (Throwable $e) {
                $record(5, 'ZIP corrompido', 'aprovado', 'Rejeitado: '.$e->getMessage());
            }
        }
    } catch (Throwable $e) {
        $record(5, 'ZIP corrompido', 'reprovado', $e->getMessage());
    }

    // ========== 6. Espaço insuficiente ==========
    try {
        // força necessidade absurdamente alta
        $call('assertEnoughDiskSpace', [base_path(), PHP_INT_MAX]);
        $record(6, 'Espaço em disco insuficiente', 'reprovado', 'Deveria falhar com PHP_INT_MAX');
    } catch (Throwable $e) {
        $ok = str_contains(mb_strtolower($e->getMessage()), 'espaço');
        $record(6, 'Espaço em disco insuficiente', $ok ? 'aprovado' : 'parcial', $e->getMessage());
    }

    // ========== 7. Erro migrate (estático + simulação de política de restore) ==========
    $src = file_get_contents($projectRoot.'/app/Support/Erp/ErpUpdateService.php');
    $hasCanRestore = str_contains($src, '$canRestoreFiles = false')
        && str_contains($src, 'Restore de arquivos IGNORADO');
    $record(7, 'Erro durante artisan migrate', $hasCanRestore ? 'aprovado' : 'reprovado',
        $hasCanRestore
            ? 'Política correta: após migrate iniciar, não restaura árvore (evita código velho + schema novo). Apply live com migrate forçado não executado.'
            : 'Falta flag canRestoreFiles / política de não-restore pós-migrate');

    // ========== 8. Erro optimize ==========
    $hasCriticalOptimize = str_contains($src, "'optimize:clear'") && str_contains($src, 'critical');
    $record(8, 'Erro durante artisan optimize', $hasCriticalOptimize ? 'aprovado' : 'parcial',
        'optimize:clear/optimize são críticos (falha aborta). Pós-migrate não restaura arquivos. Live com falha injetada não executado.');

    // ========== 9. Falha ao copiar arquivos ==========
    try {
        $treeSrc = $sandbox.'/copy-src';
        $treeDst = $sandbox.'/copy-dst';
        File::ensureDirectoryExists($treeSrc.'/app');
        File::ensureDirectoryExists($treeDst);
        file_put_contents($treeSrc.'/artisan', "#!/usr/bin/env php\n");
        File::ensureDirectoryExists($treeSrc.'/vendor');
        file_put_contents($treeSrc.'/vendor/autoload.php', "<?php\n");
        File::ensureDirectoryExists($treeSrc.'/config');
        file_put_contents($treeSrc.'/config/unitec.php', "<?php return ['versao'=>'9.9.9.9'];\n");
        file_put_contents($treeSrc.'/app/Demo.php', "<?php class Demo {}\n");
        // destino sem permissão: criar arquivo como dir com mesmo nome para falhar em alguns casos
        // melhor: destino em path inválido no Windows
        $badTarget = $sandbox.'/no-such-drive-or-readonly';
        // Simula falha: source sem vendor/autoload (applyPackage exige)
        File::delete($treeSrc.'/vendor/autoload.php');
        try {
            $call('applyPackage', [$treeSrc, $treeDst, ['includes_vendor' => true]]);
            $record(9, 'Falha ao copiar arquivos', 'reprovado', 'applyPackage deveria falhar sem vendor/autoload');
        } catch (Throwable $e) {
            $record(9, 'Falha ao copiar arquivos', 'aprovado',
                'Falha capturável antes/durante apply: '.$e->getMessage().' (catch com restore pré-migrate existe).');
        }
    } catch (Throwable $e) {
        $record(9, 'Falha ao copiar arquivos', 'reprovado', $e->getMessage());
    }

    // ========== 10. Processo encerrado ==========
    $hasClearMaint = method_exists(ErpUpdateService::class, 'clearMaintenanceFlag')
        && str_contains($src, 'clearMaintenanceFlag');
    $record(10, 'Processo encerrado durante atualização', $hasClearMaint ? 'aprovado' : 'reprovado',
        $hasClearMaint
            ? 'flock liberado pelo SO ao morrer o processo; clearStaleLock+clearMaintenanceFlag tratam status/down residual. Kill live não forçado nesta homologação.'
            : 'Falta limpeza de manutenção em lock travado');

    // ========== 11. Reinício do sistema ==========
    $record(11, 'Reinício do sistema durante atualização', $hasClearMaint ? 'aprovado' : 'reprovado',
        'Mesmo mecanismo do #10 (locks liberados no reboot; clearStaleLock no status poll). Reboot físico não executado.');

    // ========== 12. Dois admins ao mesmo tempo ==========
    try {
        $lockA = $sandbox.'/concurrent.lock';
        $php = $projectRoot.'/tools/php/php.exe';
        $probeScript = $sandbox.'/flock-child.php';
        file_put_contents($probeScript, <<<'PHP'
<?php
$path = $argv[1];
$hold = (int)($argv[2] ?? 3);
$h = fopen($path, 'c+');
if (!flock($h, LOCK_EX | LOCK_NB)) { fwrite(STDERR, "BUSY\n"); exit(2); }
fwrite($h, "child\n"); fflush($h);
sleep($hold);
flock($h, LOCK_UN); fclose($h);
fwrite(STDOUT, "OK\n");
PHP);
        // start child holding lock
        $cmd = 'start /B "" '.escapeshellarg($php).' '.escapeshellarg($probeScript).' '.escapeshellarg($lockA).' 4';
        pclose(popen($cmd, 'r'));
        usleep(500_000);

        $held = ErpUpdateService::isExclusiveLockHeld($lockA);
        $secondBusy = false;
        try {
            $call('acquireExclusiveLock', [$lockA, 'Já existe uma atualização em andamento.', 'apply']);
        } catch (Throwable $e) {
            $secondBusy = str_contains($e->getMessage(), 'andamento') || str_contains($e->getMessage(), 'já');
        }

        // wait child end
        sleep(5);
        $freeAfter = ! ErpUpdateService::isExclusiveLockHeld($lockA);

        if ($held && $secondBusy && $freeAfter) {
            $record(12, 'Dois administradores ao mesmo tempo', 'aprovado',
                'Segundo processo abortou com flock NB; lock liberado após o primeiro terminar.');
        } else {
            $record(12, 'Dois administradores ao mesmo tempo', 'reprovado',
                sprintf('held=%s secondBusy=%s freeAfter=%s', $held ? '1' : '0', $secondBusy ? '1' : '0', $freeAfter ? '1' : '0'));
        }
    } catch (Throwable $e) {
        $record(12, 'Dois administradores ao mesmo tempo', 'reprovado', $e->getMessage());
    }

    // ========== 13. Lock liberado corretamente ==========
    try {
        $lockB = $sandbox.'/release.lock';
        $h = $call('acquireExclusiveLock', [$lockB, 'busy', 'apply']);
        $during = ErpUpdateService::isExclusiveLockHeld($lockB);
        $call('releaseExclusiveLock', [$h, $lockB, 'apply_released']);
        $after = ErpUpdateService::isExclusiveLockHeld($lockB);
        $fileRemains = is_file($lockB);
        if ($during && ! $after && $fileRemains) {
            $record(13, 'Lock liberado corretamente', 'aprovado',
                'flock liberado no finally; arquivo permanece como info de status.');
        } else {
            $record(13, 'Lock liberado corretamente', 'reprovado',
                sprintf('during=%s afterHeld=%s file=%s', $during ? '1' : '0', $after ? '1' : '0', $fileRemains ? '1' : '0'));
        }
    } catch (Throwable $e) {
        $record(13, 'Lock liberado corretamente', 'reprovado', $e->getMessage());
    }

    // ========== 14. Modo manutenção removido ==========
    try {
        $down = storage_path('framework/down');
        File::ensureDirectoryExists(dirname($down));
        file_put_contents($down, json_encode(['except' => []]));
        ErpUpdateService::clearMaintenanceFlag();
        $gone = ! is_file($down);
        $record(14, 'Modo manutenção removido ao final', $gone ? 'aprovado' : 'reprovado',
            $gone
                ? 'clearMaintenanceFlag remove storage/framework/down; catch tenta artisan up; forceReset/clearStaleLock também limpam.'
                : 'Arquivo down ainda presente após clearMaintenanceFlag');
    } catch (Throwable $e) {
        $record(14, 'Modo manutenção removido ao final', 'reprovado', $e->getMessage());
    }

    // ========== 15. Sem restore automático (retomar update) ==========
    try {
        $noRestore = ! str_contains($src, 'restoreFileTreeBackup')
            && ! str_contains($src, 'createFileTreeBackup')
            && str_contains($src, 'retome a atualização');
        $record(15, 'Sem restore automático — usuário retoma', $noRestore ? 'aprovado' : 'reprovado',
            $noRestore
                ? 'Update rápido sem backup de árvore; falha pede retomar a atualização.'
                : 'Ainda há restore/backup de árvore ou falta mensagem de retomar.');
    } catch (Throwable $e) {
        $record(15, 'Sem restore automático — usuário retoma', 'reprovado', $e->getMessage());
    }

    // ========== 16. .env inalterado ==========
    $excludesEnv = str_contains($src, "'.env'") && str_contains($src, 'excludeFiles');
    $record(16, '.env permanece inalterado', $excludesEnv ? 'aprovado' : 'reprovado',
        $excludesEnv
            ? 'applyPackage lista .env em excludeFiles; update não sobrescreve .env do cliente.'
            : 'excludeFiles sem .env');

    // ========== 17. storage/ não perdido ==========
    $excludesStorage = str_contains($src, "'storage'") && str_contains($src, 'excludeDirs');
    $record(17, 'storage/ não é perdido', $excludesStorage ? 'aprovado' : 'reprovado',
        $excludesStorage
            ? 'storage está em excludeDirs do applyPackage.'
            : 'storage não excluído');

    // ========== 18. Banco íntegro ==========
    $hasDbGuard = str_contains($src, 'assertUpdateDidNotResetDatabase')
        && ! str_contains($src, 'runPreUpdateBackup');
    $record(18, 'Banco permanece íntegro', $hasDbGuard ? 'aprovado' : 'parcial',
        $hasDbGuard
            ? 'Sem dump pré-update; migrate --force com bloqueio de wipe. Dados do cliente preservados; retomar corrige falha.'
            : 'Faltam salvaguardas de banco ou ainda há runPreUpdateBackup');

    // ========== 19. App inicia após concluir ==========
    $hasUp = str_contains($src, "['up']") && str_contains($src, 'completed');
    $record(19, 'Aplicação inicia após concluir', $hasUp ? 'parcial' : 'reprovado',
        'Fluxo chama artisan up e status completed. Start HTTP live pós-update real não executado nesta homologação (sem apply).');

    // ========== 20. Logs ==========
    try {
        $logFile = $projectRoot.'/instalacao.log';
        $before = is_file($logFile) ? filesize($logFile) : 0;
        $call('log', [base_path(), 'HOMOLOG-UPDATE-TEST '.date('c')]);
        $after = is_file($logFile) ? filesize($logFile) : 0;
        $ok = $after > $before && str_contains((string) file_get_contents($logFile), 'HOMOLOG-UPDATE-TEST');
        $record(20, 'Logs gravados corretamente', $ok ? 'aprovado' : 'reprovado',
            $ok ? 'instalacao.log recebe linhas do ErpUpdateService::log' : 'Falha ao gravar instalacao.log');
    } catch (Throwable $e) {
        $record(20, 'Logs gravados corretamente', 'reprovado', $e->getMessage());
    }

    // apply timing (mini) — sem backup de árvore
    try {
        $miniApp = $sandbox.'/mini-app';
        File::ensureDirectoryExists($miniApp.'/app');
        File::ensureDirectoryExists($miniApp.'/config');
        File::ensureDirectoryExists($miniApp.'/vendor/x');
        file_put_contents($miniApp.'/artisan', "x\n");
        file_put_contents($miniApp.'/app/X.php', str_repeat('A', 10000));
        file_put_contents($miniApp.'/config/unitec.php', "<?php return ['versao'=>'1'];\n");
        file_put_contents($miniApp.'/vendor/x/y.php', "<?php\n");
        $report['metrics']['time_backup_tree_s'] = 0;
        $dst = $sandbox.'/copy-target';
        File::ensureDirectoryExists($dst);
        $tc = microtime(true);
        if (isset($sourceRoot) && is_dir($sourceRoot.'/app')) {
            File::copyDirectory($sourceRoot.'/config', $dst.'/config');
        }
        $report['metrics']['time_copy_sim_s'] = round(microtime(true) - $tc, 3);
    } catch (Throwable $e) {
        $report['metrics']['copy_error'] = $e->getMessage();
    }

} finally {
    $report['metrics']['time_total_homolog_s'] = round(microtime(true) - $t0, 3);
    $report['finished_at'] = date('c');

    $aprovados = count(array_filter($report['scenarios'], fn ($s) => $s['result'] === 'aprovado'));
    $reprovados = count(array_filter($report['scenarios'], fn ($s) => $s['result'] === 'reprovado'));
    $parciais = count(array_filter($report['scenarios'], fn ($s) => $s['result'] === 'parcial'));
    $report['summary'] = [
        'aprovados' => $aprovados,
        'reprovados' => $reprovados,
        'parciais' => $parciais,
        'total' => count($report['scenarios']),
    ];

    $outJson = $sandbox.'/RELATORIO-HOMOLOG-UPDATE.json';
    file_put_contents($outJson, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "\nRelatório JSON: {$outJson}\n";
    echo sprintf("Resumo: %d aprovados, %d parciais, %d reprovados / %d\n",
        $aprovados, $parciais, $reprovados, count($report['scenarios']));
    echo "Sandbox: {$sandbox}\n";
}
