<?php

namespace App\Support\Erp;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class ErpWarmService
{
    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     compiled: int,
     *     routes_total: int,
     *     routes_ok: int,
     *     routes_fail: int,
     *     elapsed_ms: int
     * }
     */
    public function warm(bool $compileFiles = true, bool $visitRoutes = true, int $routeLimit = 0): array
    {
        $started = hrtime(true);
        $compiled = 0;
        $routesOk = 0;
        $routesFail = 0;
        $routesTotal = 0;

        try {
            DB::select('SELECT 1');
        } catch (Throwable $e) {
            report($e);
        }

        if ($visitRoutes) {
            [$routesTotal, $routesOk, $routesFail] = $this->visitMenuRoutes($routeLimit);
        }

        if ($compileFiles) {
            $compiled = $this->compileApplicationFiles();
        }

        $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

        $ok = $compiled > 0 || $routesOk > 0;

        $parts = [];
        if ($compiled > 0) {
            $parts[] = "{$compiled} arquivos no OPcache";
        }
        if ($routesTotal > 0) {
            $parts[] = "{$routesOk}/{$routesTotal} telas aquecidas";
        }
        $parts[] = "{$elapsedMs} ms";

        return [
            'ok' => $ok,
            'message' => $ok
                ? 'Sistema aquecido ('.implode(' · ', $parts).').'
                : 'Não foi possível aquecer o sistema (confira OPcache e banco de dados).',
            'compiled' => $compiled,
            'routes_total' => $routesTotal,
            'routes_ok' => $routesOk,
            'routes_fail' => $routesFail,
            'elapsed_ms' => $elapsedMs,
        ];
    }

    /**
     * @return list<string>
     */
    public function collectWarmPaths(): array
    {
        $paths = ['/admin'];

        foreach (ErpMenu::allMenus() as $menu) {
            foreach ($menu['items'] as $item) {
                $path = $this->pathFromUrl((string) ($item['url'] ?? ''));
                if ($path !== null) {
                    $paths[] = $path;
                }
            }
        }

        foreach (ErpMenu::shortcuts() as $shortcut) {
            if (($shortcut['logout'] ?? false) || ($shortcut['disabled'] ?? false)) {
                continue;
            }

            $path = $this->pathFromUrl((string) ($shortcut['url'] ?? ''));
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        // Atalhos podem estar filtrados por permissão — incluir rotas principais conhecidas.
        foreach ($this->coreShortcutPaths() as $path) {
            $paths[] = $path;
        }

        $unique = [];
        foreach ($paths as $path) {
            if (! isset($unique[$path])) {
                $unique[$path] = true;
            }
        }

        return array_keys($unique);
    }

    /**
     * URLs prioritárias para prefetch no browser (menu do usuário logado).
     *
     * @return list<string>
     */
    public function collectPrefetchPathsForUser(?User $user, int $limit = 15): array
    {
        if ($user === null) {
            return [];
        }

        Auth::guard('web')->setUser($user);

        $paths = [];
        foreach (ErpMenu::mainMenus() as $menu) {
            foreach ($menu['items'] as $item) {
                $path = $this->pathFromUrl((string) ($item['url'] ?? ''));
                if ($path !== null) {
                    $paths[] = $path;
                }
            }
        }

        foreach (ErpMenu::shortcuts() as $shortcut) {
            if (($shortcut['logout'] ?? false) || ($shortcut['disabled'] ?? false)) {
                continue;
            }

            $path = $this->pathFromUrl((string) ($shortcut['url'] ?? ''));
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        $unique = [];
        foreach ($paths as $path) {
            if ($path === '/admin' || isset($unique[$path])) {
                continue;
            }

            $unique[$path] = true;
        }

        $list = array_keys($unique);

        if ($limit > 0 && count($list) > $limit) {
            $list = array_slice($list, 0, $limit);
        }

        return $list;
    }

    /**
     * @return array{0: int, 1: int, 2: int} total, ok, fail
     */
    protected function visitMenuRoutes(int $limit = 0): array
    {
        $paths = $this->collectWarmPaths();

        if ($limit > 0 && count($paths) > $limit) {
            $paths = array_slice($paths, 0, $limit);
        }

        $user = User::query()->where('name', 'USUARIO')->first()
            ?? User::query()->orderBy('id')->first();

        if ($user === null) {
            return [count($paths), 0, count($paths)];
        }

        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);

        $bootstrapRequest = Request::create((string) config('app.url', 'http://127.0.0.1:8765').'/admin', 'GET');
        app()->instance('request', $bootstrapRequest);

        $ok = 0;
        $fail = 0;

        foreach ($paths as $path) {
            try {
                Auth::guard('web')->login($user);

                $session = app('session.store');
                $session->start();

                $request = Request::create($path, 'GET');
                $request->setLaravelSession($session);
                $request->setUserResolver(fn () => Auth::guard('web')->user());

                $response = $kernel->handle($request);
                $status = $response->getStatusCode();
                $body = $response->getContent() ?: '';
                $kernel->terminate($request, $response);

                $hasError = $status >= 500
                    || str_contains($body, 'Whoops')
                    || str_contains($body, 'Server Error');

                if ($status >= 200 && $status < 500 && ! $hasError) {
                    $ok++;
                } else {
                    $fail++;
                }
            } catch (Throwable $e) {
                report($e);
                $fail++;
            }
        }

        return [count($paths), $ok, $fail];
    }

    protected function compileApplicationFiles(): int
    {
        if (! function_exists('opcache_compile_file')) {
            return 0;
        }

        $roots = [
            base_path('bootstrap/app.php'),
            base_path('bootstrap/providers.php'),
            app_path('Providers'),
            app_path('Filament'),
            app_path('Support/Erp'),
            app_path('Models'),
            app_path('Livewire'),
        ];

        $count = 0;

        foreach ($roots as $root) {
            if (is_file($root)) {
                if ($this->compileFile($root)) {
                    $count++;
                }

                continue;
            }

            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                if ($this->compileFile($file->getPathname())) {
                    $count++;
                }
            }
        }

        return $count;
    }

    protected function compileFile(string $file): bool
    {
        $resolved = realpath($file) ?: $file;

        foreach (get_included_files() as $included) {
            if ((realpath($included) ?: $included) === $resolved) {
                return false;
            }
        }

        try {
            return @opcache_compile_file($file);
        } catch (Throwable) {
            return false;
        }
    }

    protected function pathFromUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            return $path.'?'.$query;
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    protected function coreShortcutPaths(): array
    {
        return [
            '/admin/products',
            '/admin/people',
            '/admin/compras',
            '/admin/vendas',
            '/admin/orcamentos',
            '/admin/caixa',
            '/admin/pdv',
            '/admin/nfces',
            '/admin/nfes',
            '/admin/contas-receber',
            '/admin/contas-pagar',
        ];
    }
}
