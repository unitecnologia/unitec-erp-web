<?php

namespace Tests\Unit;

use App\Support\Erp\RequestOrigin;
use Illuminate\Http\Request;
use Tests\TestCase;

class RequestOriginTest extends TestCase
{
    public function test_local_request_keeps_loopback_port(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8765/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => '8765',
            ],
        );

        $this->assertSame('http://127.0.0.1:8765', RequestOrigin::resolve($request));
    }

    public function test_public_host_uses_request_host(): void
    {
        $request = Request::create(
            'https://alencardeoliveira.unierp.uk/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => 'alencardeoliveira.unierp.uk',
                'SERVER_NAME' => 'alencardeoliveira.unierp.uk',
                'SERVER_PORT' => '443',
                'HTTPS' => 'on',
            ],
        );

        $this->assertSame('https://alencardeoliveira.unierp.uk', RequestOrigin::resolve($request));
    }

    public function test_tunnel_forwarded_headers_use_public_https_origin(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8765/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => '8765',
                'HTTP_X_FORWARDED_HOST' => 'nortesulcomercial.unierp.uk',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );

        $this->assertSame(
            'https://nortesulcomercial.unierp.uk',
            RequestOrigin::resolve($request),
        );
    }

    public function test_forwarded_host_list_uses_first_value(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8765/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_PORT' => '8765',
                'HTTP_X_FORWARDED_HOST' => 'loja.unierp.uk, other.example',
                'HTTP_X_FORWARDED_PROTO' => 'https, http',
            ],
        );

        $this->assertSame('https://loja.unierp.uk', RequestOrigin::resolve($request));
    }

    public function test_does_not_append_local_port_when_forwarded_host_has_no_port(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8765/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_PORT' => '8765',
                'HTTP_X_FORWARDED_HOST' => 'cliente.unierp.uk',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );

        $origin = RequestOrigin::resolve($request);

        $this->assertSame('https://cliente.unierp.uk', $origin);
        $this->assertStringNotContainsString(':8765', (string) $origin);
    }

    public function test_loopback_with_only_forwarded_proto_uses_public_app_url(): void
    {
        config(['app.url' => 'https://alencardeoliveira.unierp.uk']);

        $request = Request::create(
            'http://127.0.0.1:8765/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => '8765',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );

        $this->assertSame('https://alencardeoliveira.unierp.uk', RequestOrigin::resolve($request));
    }

    public function test_loopback_without_forwarded_keeps_local_even_when_app_url_is_public(): void
    {
        config(['app.url' => 'https://alencardeoliveira.unierp.uk']);

        $request = Request::create(
            'http://127.0.0.1:8765/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => '8765',
            ],
        );

        $this->assertSame('http://127.0.0.1:8765', RequestOrigin::resolve($request));
    }

    public function test_loopback_host_without_port_uses_server_port(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8765/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => '8765',
            ],
        );

        // Simula Caddy `{http.request.host}` (hostname sem porta) + SERVER_PORT real.
        $request->headers->set('HOST', '127.0.0.1');
        $request->server->set('HTTP_HOST', '127.0.0.1');
        $request->server->set('SERVER_PORT', '8765');

        $this->assertSame('http://127.0.0.1:8765', RequestOrigin::resolve($request));
    }

    public function test_lan_host_without_port_uses_server_port(): void
    {
        $request = Request::create(
            'http://192.168.0.52:8765/admin/login',
            'GET',
            server: [
                'HTTP_HOST' => '192.168.0.52:8765',
                'SERVER_NAME' => '192.168.0.52',
                'SERVER_PORT' => '8765',
            ],
        );

        $request->headers->set('HOST', '192.168.0.52');
        $request->server->set('HTTP_HOST', '192.168.0.52');
        $request->server->set('SERVER_PORT', '8765');

        $this->assertSame('http://192.168.0.52:8765', RequestOrigin::resolve($request));
    }

    public function test_public_unierp_host_with_server_port_8765_does_not_leak_local_port(): void
    {
        $request = Request::create(
            'https://alencardeoliveira.unierp.uk/gestor',
            'GET',
            server: [
                'HTTP_HOST' => 'alencardeoliveira.unierp.uk',
                'SERVER_NAME' => 'alencardeoliveira.unierp.uk',
                'SERVER_PORT' => '8765',
                'HTTPS' => 'on',
            ],
        );

        $origin = RequestOrigin::resolve($request);

        $this->assertSame('https://alencardeoliveira.unierp.uk', $origin);
        $this->assertStringNotContainsString(':8765', (string) $origin);
    }

    public function test_cloudflare_tunnel_forwarded_port_8765_is_ignored_for_public_host(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8765/gestor',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => '8765',
                'HTTP_X_FORWARDED_HOST' => 'alencardeoliveira.unierp.uk',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '8765',
            ],
        );

        $origin = RequestOrigin::resolve($request);

        $this->assertSame('https://alencardeoliveira.unierp.uk', $origin);
        $this->assertStringNotContainsString(':8765', (string) $origin);
    }

    public function test_public_app_url_with_8765_does_not_leak_port_on_public_host(): void
    {
        config(['app.url' => 'https://alencardeoliveira.unierp.uk:8765']);

        $request = Request::create(
            'https://alencardeoliveira.unierp.uk/gestor',
            'GET',
            server: [
                'HTTP_HOST' => 'alencardeoliveira.unierp.uk',
                'SERVER_NAME' => 'alencardeoliveira.unierp.uk',
                'SERVER_PORT' => '443',
                'HTTPS' => 'on',
            ],
        );

        $origin = RequestOrigin::resolve($request);

        $this->assertSame('https://alencardeoliveira.unierp.uk', $origin);
        $this->assertStringNotContainsString(':8765', (string) $origin);
    }

    public function test_loopback_forwarded_proto_strips_8765_from_public_app_url(): void
    {
        config(['app.url' => 'https://cliente.unierp.uk:8765']);

        $request = Request::create(
            'http://127.0.0.1:8765/gestor',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_NAME' => '127.0.0.1',
                'SERVER_PORT' => '8765',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );

        $origin = RequestOrigin::resolve($request);

        $this->assertSame('https://cliente.unierp.uk', $origin);
        $this->assertStringNotContainsString(':8765', (string) $origin);
    }

    public function test_zerograu_public_host_never_gets_local_port(): void
    {
        $request = Request::create(
            'https://convenienciazerograultda.unierp.uk/gestor',
            'GET',
            server: [
                'HTTP_HOST' => 'convenienciazerograultda.unierp.uk',
                'SERVER_PORT' => '8765',
                'HTTPS' => 'on',
            ],
        );

        $this->assertSame(
            'https://convenienciazerograultda.unierp.uk',
            RequestOrigin::resolve($request),
        );
    }

    public function test_rfc7239_forwarded_host_is_used_when_xfh_missing(): void
    {
        $request = Request::create(
            'http://127.0.0.1:8765/gestor/login',
            'GET',
            server: [
                'HTTP_HOST' => '127.0.0.1:8765',
                'SERVER_PORT' => '8765',
                'HTTP_FORWARDED' => 'for=1.2.3.4;proto=https;host=convenienciazerograultda.unierp.uk',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );

        $this->assertSame(
            'https://convenienciazerograultda.unierp.uk',
            RequestOrigin::resolve($request),
        );
    }

    public function test_loopback_absolute_url_becomes_relative_for_browser(): void
    {
        $this->assertSame(
            '/gestor',
            RequestOrigin::toBrowserUrl('https://127.0.0.1:8765/gestor'),
        );
        $this->assertSame(
            '/gestor/login',
            RequestOrigin::toBrowserUrl('http://localhost:8765/gestor/login'),
        );
        $this->assertSame(
            'https://convenienciazerograultda.unierp.uk/gestor',
            RequestOrigin::toBrowserUrl('https://convenienciazerograultda.unierp.uk/gestor'),
        );
        $this->assertSame('/gestor', RequestOrigin::toBrowserUrl('/gestor'));
    }
}
