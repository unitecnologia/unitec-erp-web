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

        if (! $port || in_array($port, [80, 443], true)) {
            $configuredPort = (int) parse_url((string) config('app.url'), PHP_URL_PORT);

            if ($configuredPort > 0) {
                $port = $configuredPort;
            }
        }

        $origin = $scheme . '://' . $host;

        if ($port && ! in_array($port, [80, 443], true)) {
            $origin .= ':' . $port;
        }

        return $origin;
    }
}
