<?php

namespace App\Support\Gestor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logs de diagnóstico do login/sessão do painel Gestor.
 * Nunca registra senha nem campos sensíveis.
 */
final class GestorLoginDiag
{
    private const SENSITIVE_KEYS = [
        'password',
        'login_senha',
        'senha',
        'senha_app_forca_vendas',
        'credentials',
        'remember_token',
    ];

    public static function enabled(?Request $request = null): bool
    {
        try {
            if (filament()->getCurrentPanel()?->getId() === 'gestor') {
                return true;
            }
        } catch (Throwable) {
            // Filament ainda não resolvido neste request.
        }

        $request ??= request();
        if (! $request instanceof Request) {
            return false;
        }

        return $request->is('gestor') || $request->is('gestor/*');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $event, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }

        foreach (self::SENSITIVE_KEYS as $key) {
            unset($context[$key]);
        }

        // warning: LOG_LEVEL atual do ERP é "warning" — info seria descartado.
        Log::warning('gestor.login.'.$event, $context + [
            'panel' => 'gestor',
            'path' => request()?->path(),
            'ip' => request()?->ip(),
        ]);
    }
}
