<?php

namespace App\Support\Erp\Tef;

use App\Models\Terminal;
use App\Support\Erp\Tef\TefProviderId;

/**
 * Gate DEV: SuperTEF no PDV não sobe para cliente sem flag explícita.
 * Em APP_ENV=local (esta máquina) libera se o terminal estiver configurado.
 */
final class SuperTefPdvGate
{
    public static function featureEnabled(): bool
    {
        if (filter_var(config('unitec.supertef_pdv.enabled', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        if (! filter_var(config('unitec.supertef_pdv.allow_local', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return app()->environment('local');
    }

    public static function terminalPronto(?Terminal $terminal): bool
    {
        if ($terminal === null || ! self::featureEnabled()) {
            return false;
        }

        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: true);

        // Multiprovedor: SuperTEF só quando o terminal está explicitamente nele.
        if (TefProviderId::normalize((string) $cfg['provedor']) !== TefProviderId::SUPERTEF) {
            return false;
        }

        return $cfg['ativo']
            && $cfg['pos_id'] !== null
            && $cfg['pos_id'] > 0
            && filled($cfg['cliente_chave'])
            && SuperTefSofthouseToken::get() !== '';
    }

    public static function pollSeconds(): int
    {
        return max(4, (int) config('unitec.supertef_pdv.poll_seconds', 4));
    }
}
