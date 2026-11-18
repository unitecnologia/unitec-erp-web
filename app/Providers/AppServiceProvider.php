<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Services\Ailos\AilosAuthService;
use App\Services\Ailos\AilosCobrancaAuth;
use App\Support\Erp\Boleto\Api\BoletoApi;
use App\Support\Erp\Boleto\Api\Drivers\AilosBoletoDriver;
use App\Support\Erp\Boleto\Api\Drivers\SicrediBoletoDriver;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpFilamentNotification;
use App\Support\Erp\Nfse\NfseSefinEnvio;
use App\Support\Erp\Nfse\NfseSefinHttp;
use App\Support\Erp\WindowsSafeFilesystem;
use Filament\Notifications\Notification;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Auth\Events\Logout;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('helpers.php');

        // Windows: packages.php / views compiladas usam rename(); code:5 derruba o boot.
        $this->app->singleton('files', fn () => new WindowsSafeFilesystem);
        $this->app->singleton(Filesystem::class, fn ($app) => $app->make('files'));

        // Pail fica em dont-discover (producao). Em DEV so registra se o pacote estiver no disco.
        // Nao usar class_exists() cego: autoload do Composer fatala se a pasta foi removida.
        $pailProvider = 'Laravel\\Pail\\PailServiceProvider';
        $pailFile = base_path('vendor/laravel/pail/src/PailServiceProvider.php');
        if (is_file($pailFile) && class_exists($pailProvider)) {
            $this->app->register($pailProvider);
        }

        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(Notification::class, ErpFilamentNotification::class);
        $this->app->bind(NfseSefinEnvio::class, NfseSefinHttp::class);
        $this->app->bind(AilosCobrancaAuth::class, AilosAuthService::class);

        $this->app->singleton(BoletoApi::class, function ($app): BoletoApi {
            return new BoletoApi([
                $app->make(AilosBoletoDriver::class),
                $app->make(SicrediBoletoDriver::class),
            ]);
        });
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
    }
}
