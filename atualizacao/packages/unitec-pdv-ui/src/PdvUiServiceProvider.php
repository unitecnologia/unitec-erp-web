<?php

namespace Unitec\PdvUi;

use Illuminate\Support\ServiceProvider;
use Unitec\PdvUi\Console\PublishPdvUiAssetsCommand;

/**
 * Provider da UI compartilhada do PDV. Registra as views sob o namespace
 * `pdvui::` (mesmo arquivo consumido pelo ERP e pelo PDV offline) e o comando
 * de publicação dos assets (CSS/JS canônicos) para o public/ de cada app.
 */
class PdvUiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pdvui');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishPdvUiAssetsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../dist/css' => public_path('css'),
                __DIR__.'/../dist/js' => public_path('js'),
            ], 'pdv-ui-assets');
        }
    }

    /**
     * Caminho absoluto para os assets canônicos do pacote (dist/).
     */
    public static function distPath(string $path = ''): string
    {
        return __DIR__.'/../dist'.($path !== '' ? '/'.ltrim($path, '/') : '');
    }
}
