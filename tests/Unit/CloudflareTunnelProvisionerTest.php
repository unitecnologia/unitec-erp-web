<?php

namespace Tests\Unit;

use App\Support\Erp\Cloudflare\CloudflareTunnelProvisioner;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class CloudflareTunnelProvisionerTest extends TestCase
{
    public function test_ensure_dns_creates_cname_when_missing(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/*/dns_records*' => Http::sequence()
                ->push(['success' => true, 'result' => []], 200)
                ->push(['success' => true, 'result' => ['id' => 'dns1']], 200),
        ]);

        $this->invokeEnsureDns(
            hostname: 'novaloja.unierp.uk',
            tunnelId: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        );

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/dns_records')
                && ($request['content'] ?? null) === 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee.cfargotunnel.com';
        });
    }

    public function test_ensure_dns_keeps_cname_already_on_same_tunnel(): void
    {
        $tunnelId = '11111111-2222-3333-4444-555555555555';

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/*/dns_records*' => Http::response([
                'success' => true,
                'result' => [[
                    'id' => 'rec-same',
                    'type' => 'CNAME',
                    'name' => 'loja.unierp.uk',
                    'content' => $tunnelId.'.cfargotunnel.com',
                    'proxied' => true,
                ]],
            ], 200),
        ]);

        $this->invokeEnsureDns(hostname: 'loja.unierp.uk', tunnelId: $tunnelId);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
        Http::assertNotSent(fn ($request) => in_array($request->method(), ['POST', 'PATCH', 'PUT'], true));
    }

    public function test_ensure_dns_refuses_to_steal_cname_from_other_live_tunnel(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, '/dns_records')) {
                return Http::response([
                    'success' => true,
                    'result' => [[
                        'id' => 'rec-other',
                        'type' => 'CNAME',
                        'name' => 'convenienciazerograultda.unierp.uk',
                        'content' => 'db074b6b-8883-4a46-8e3e-396631893f55.cfargotunnel.com',
                        'proxied' => true,
                    ]],
                ], 200);
            }

            if ($request->method() === 'GET' && str_contains($url, '/cfd_tunnel/')) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => 'db074b6b-8883-4a46-8e3e-396631893f55',
                        'name' => 'unitec-1-nortesulcomercial-57f0ab',
                        'deleted_at' => '0001-01-01T00:00:00Z',
                    ],
                ], 200);
            }

            return Http::response(['success' => false], 500);
        });

        try {
            $this->invokeEnsureDns(
                hostname: 'convenienciazerograultda.unierp.uk',
                tunnelId: '0c0d3559-7e4f-4783-b94e-cfd5cb2b4cd8',
            );
            $this->fail('Expected RuntimeException when CNAME belongs to another tunnel.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('já pertence a outro túnel', $e->getMessage());
            $this->assertStringContainsString('não altera DNS de outro cliente', $e->getMessage());
        }

        Http::assertNotSent(fn ($request) => in_array($request->method(), ['POST', 'PATCH', 'PUT'], true));
    }

    public function test_ensure_dns_reclaims_cname_when_old_tunnel_was_deleted(): void
    {
        $newTunnelId = '0c0d3559-7e4f-4783-b94e-cfd5cb2b4cd8';

        Http::fake(function ($request) use ($newTunnelId) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, '/dns_records')) {
                return Http::response([
                    'success' => true,
                    'result' => [[
                        'id' => 'rec-dangling',
                        'type' => 'CNAME',
                        'name' => 'convenienciazerograultda.unierp.uk',
                        'content' => '602ebeac-bd5b-4947-b900-106afb42d5ff.cfargotunnel.com',
                        'proxied' => true,
                    ]],
                ], 200);
            }

            if ($request->method() === 'GET' && str_contains($url, '/cfd_tunnel/')) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => '602ebeac-bd5b-4947-b900-106afb42d5ff',
                        'name' => 'unitec-1-alencardeoliveira-960f19',
                        'deleted_at' => '2026-09-28T10:11:39.317372Z',
                    ],
                ], 200);
            }

            if ($request->method() === 'PATCH' && str_contains($url, '/dns_records/rec-dangling')) {
                return Http::response([
                    'success' => true,
                    'result' => ['id' => 'rec-dangling', 'content' => $newTunnelId.'.cfargotunnel.com'],
                ], 200);
            }

            return Http::response(['success' => false], 500);
        });

        $this->invokeEnsureDns(
            hostname: 'convenienciazerograultda.unierp.uk',
            tunnelId: $newTunnelId,
        );

        Http::assertSent(function ($request) use ($newTunnelId) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/dns_records/rec-dangling')
                && ($request['content'] ?? null) === $newTunnelId.'.cfargotunnel.com';
        });
    }

    public function test_ensure_dns_reclaims_cname_when_previous_tunnel_id_matches(): void
    {
        $oldTunnelId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $newTunnelId = '11111111-2222-3333-4444-555555555555';

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/*/dns_records*' => Http::sequence()
                ->push([
                    'success' => true,
                    'result' => [[
                        'id' => 'rec-own',
                        'type' => 'CNAME',
                        'name' => 'loja.unierp.uk',
                        'content' => $oldTunnelId.'.cfargotunnel.com',
                        'proxied' => true,
                    ]],
                ], 200)
                ->push(['success' => true, 'result' => ['id' => 'rec-own']], 200),
        ]);

        $this->invokeEnsureDns(
            hostname: 'loja.unierp.uk',
            tunnelId: $newTunnelId,
            previousTunnelId: $oldTunnelId,
        );

        Http::assertSent(function ($request) use ($newTunnelId) {
            return $request->method() === 'PATCH'
                && ($request['content'] ?? null) === $newTunnelId.'.cfargotunnel.com';
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/cfd_tunnel/'));
    }

    public function test_write_local_files_only_contains_this_tunnel_and_hostname(): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'unitec-cf-'.bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        try {
            $tunnelId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
            $hostname = 'alencardeoliveira.unierp.uk';

            $this->invokeWriteLocalFiles(
                programData: $dir,
                tunnelId: $tunnelId,
                accountId: 'acct',
                tunnelSecret: base64_encode(random_bytes(16)),
                hostname: $hostname,
                localService: 'http://127.0.0.1:8765',
            );

            $yml = file_get_contents($dir.DIRECTORY_SEPARATOR.'config.yml');
            $this->assertIsString($yml);
            $this->assertStringContainsString('tunnel: '.$tunnelId, $yml);
            $this->assertStringContainsString('hostname: '.$hostname, $yml);
            $this->assertStringContainsString('httpHostHeader: '.$hostname, $yml);
            $this->assertStringContainsString('service: http://127.0.0.1:8765', $yml);
            $this->assertStringNotContainsString('recantodosvieiras', $yml);
            $this->assertStringNotContainsString('convenienciazerograultda', $yml);
            $this->assertStringNotContainsString('602ebeac-bd5b-4947-b900-106afb42d5ff', $yml);
            $this->assertFileExists($dir.DIRECTORY_SEPARATOR.$tunnelId.'.json');
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /**
     * @param  array{api_token?: string, account_id?: string, zone_id?: string, base_domain?: string}  $creds
     */
    private function invokeEnsureDns(
        string $hostname,
        string $tunnelId,
        array $creds = [],
        string $previousTunnelId = '',
    ): void {
        $provisioner = new CloudflareTunnelProvisioner;
        $method = new ReflectionMethod(CloudflareTunnelProvisioner::class, 'ensureDnsCname');
        $method->setAccessible(true);
        $method->invoke($provisioner, array_merge([
            'api_token' => 'test-token',
            'account_id' => 'acc',
            'zone_id' => 'zone',
            'base_domain' => 'unierp.uk',
        ], $creds), $hostname, $tunnelId, $previousTunnelId);
    }

    private function invokeWriteLocalFiles(
        string $programData,
        string $tunnelId,
        string $accountId,
        ?string $tunnelSecret,
        string $hostname,
        string $localService,
    ): void {
        $provisioner = new CloudflareTunnelProvisioner;
        $method = new ReflectionMethod(CloudflareTunnelProvisioner::class, 'writeLocalFiles');
        $method->setAccessible(true);
        $method->invoke(
            $provisioner,
            $programData,
            $tunnelId,
            $accountId,
            $tunnelSecret,
            $hostname,
            $localService,
        );
    }
}
