<?php

namespace Tests\Unit;

use App\Support\Erp\Atualizacao\AtualizacaoApplyService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Cobertura da correção 6.4.1.218 (manifesto Pail / health gate helpers).
 * Não aplica update completo — valida as rotinas isoladas.
 */
class AtualizacaoApplyPackageManifestTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'unitec-apply-'.bin2hex(random_bytes(4));
        mkdir($this->tmp.'/bootstrap/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmp);
        parent::tearDown();
    }

    #[Test]
    public function delete_package_discovery_caches_remove_packages_and_services(): void
    {
        $packages = $this->tmp.'/bootstrap/cache/packages.php';
        $services = $this->tmp.'/bootstrap/cache/services.php';
        file_put_contents($packages, "<?php return [];\n");
        file_put_contents($services, "<?php return [];\n");

        $this->invoke('deletePackageDiscoveryCaches', [$this->tmp]);

        $this->assertFileDoesNotExist($packages);
        $this->assertFileDoesNotExist($services);
    }

    #[Test]
    public function find_forbidden_dev_markers_detecta_pail(): void
    {
        $manifest = <<<'PHP'
<?php
return [
    'packages' => [
        'laravel/pail' => ['providers' => ['Laravel\\Pail\\PailServiceProvider']],
    ],
    'providers' => [
        'Laravel\\Pail\\PailServiceProvider',
    ],
    'eager' => [],
    'deferred' => [],
];
PHP;
        file_put_contents($this->tmp.'/bootstrap/cache/packages.php', $manifest);

        $hits = $this->invoke('findForbiddenDevMarkersInManifest', [$this->tmp]);

        $this->assertNotEmpty($hits);
        $this->assertTrue(
            collect($hits)->contains(fn ($h) => str_contains((string) $h, 'Pail') || str_contains((string) $h, 'laravel/pail'))
        );
    }

    #[Test]
    public function find_forbidden_dev_markers_limpo_retorna_vazio(): void
    {
        $manifest = <<<'PHP'
<?php
return [
    'packages' => [
        'filament/filament' => ['providers' => []],
    ],
    'providers' => [
        'Filament\\FilamentServiceProvider',
    ],
    'eager' => [],
    'deferred' => [],
];
PHP;
        file_put_contents($this->tmp.'/bootstrap/cache/packages.php', $manifest);

        $hits = $this->invoke('findForbiddenDevMarkersInManifest', [$this->tmp]);

        $this->assertSame([], $hits);
    }

    #[Test]
    public function probe_health_retorna_estrutura_esperada(): void
    {
        $result = $this->invoke('probeHealth', ['6.4.1.218']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('health_version', $result);
        $this->assertIsBool($result['ok']);
    }

    #[Test]
    public function parse_health_payload_extrai_status_e_version(): void
    {
        $parsed = $this->invoke('parseHealthPayload', [
            '{"status":"ok","version":"6.4.1.218"}',
        ]);

        $this->assertSame('ok', $parsed['status']);
        $this->assertSame('6.4.1.218', $parsed['version']);
    }

    #[Test]
    public function parse_health_payload_rejeita_json_invalido(): void
    {
        $this->assertSame([], $this->invoke('parseHealthPayload', ['not-json']));
    }

    /**
     * @param  list<mixed>  $args
     */
    private function invoke(string $method, array $args): mixed
    {
        $service = new AtualizacaoApplyService;
        $ref = new ReflectionMethod(AtualizacaoApplyService::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
