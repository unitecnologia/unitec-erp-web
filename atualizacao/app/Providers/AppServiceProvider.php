<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Support\Erp\ErpAccess;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('helpers.php');

        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Após update incompleto/disco cheio, sessions/views podem sumir e o ERP quebra no boot.
        \App\Support\Erp\ErpUpdateService::ensureFrameworkStorageDirectories();

        // PFX A1 antigos (RC2-40): OpenSSL 3 precisa do provider legacy.
        \App\Support\Erp\OpenSslLegacy::ensure();

        // Cookie de sessão amarrado à APP_KEY: reinstalar gera chave nova e o navegador
        // deixa de reutilizar cookie antigo (causa clássica de ERR_TOO_MANY_REDIRECTS).
        $appKey = (string) config('app.key');
        if ($appKey !== '') {
            config([
                'session.cookie' => 'unitec_'.substr(hash('sha256', $appKey), 0, 12),
            ]);
        }

        Event::listen(Logout::class, function (): void {
            ErpAccess::forgetSession();
        });

        Schema::defaultStringLength(191);

        if ($this->app->runningInConsole() || ! $this->app->bound('request')) {
            return;
        }

        $origin = $this->resolveRequestOrigin(request());

        if ($origin) {
            URL::useOrigin($origin);
        }
    }

    private function resolveRequestOrigin(Request $request): ?string
    {
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();

        if ($host === '') {
            return null;
        }

        // Só herda a porta do APP_URL (ex.: :8000) quando o host também é o do APP_URL.
        // Em Cloudflare Tunnel / proxy, injetar :8000 quebra CSS/JS/login no celular.
        $configured = parse_url((string) config('app.url')) ?: [];
        $configuredHost = strtolower((string) ($configured['host'] ?? ''));
        $configuredPort = (int) ($configured['port'] ?? 0);

        if (
            $configuredPort > 0
            && $configuredHost !== ''
            && strtolower($host) === $configuredHost
            && (! $port || in_array((int) $port, [80, 443], true))
        ) {
            $port = $configuredPort;
        }

        $origin = $scheme.'://'.$host;

        if ($port && ! in_array((int) $port, [80, 443], true)) {
            $origin .= ':'.$port;
        }

        return $origin;
    }
}
