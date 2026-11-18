<?php

namespace App\Support\Erp\Tef\Scope;

use App\Models\Terminal;
use App\Support\Erp\Tef\TefProviderId;

/**
 * Gate do Scope TEF. Padrão: desligado.
 * Integração real depende do SDK/documentação oficial da Conexão Tech.
 */
final class ScopeTefPdvGate
{
    public static function featureEnabled(): bool
    {
        if (! filter_var(config('unitec.scope_tef_pdv.enabled', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        if (! self::sdkAvailable()) {
            return false;
        }

        if (filter_var(config('unitec.scope_tef_pdv.allow_local', true), FILTER_VALIDATE_BOOL)
            && app()->environment('local')) {
            return true;
        }

        return filter_var(config('unitec.scope_tef_pdv.enabled', false), FILTER_VALIDATE_BOOL)
            && self::sdkAvailable();
    }

    /**
     * SDK Conexão Tech ainda não entregue — nunca inventar DLL/endpoints.
     */
    public static function sdkAvailable(): bool
    {
        return filter_var(config('unitec.scope_tef_pdv.sdk_available', false), FILTER_VALIDATE_BOOL);
    }

    public static function activationBlockedReason(): string
    {
        if (! self::sdkAvailable()) {
            return 'Scope TEF aguarda SDK e documentação oficial da Conexão Tech. Adaptador permanece desativado.';
        }

        if (! filter_var(config('unitec.scope_tef_pdv.enabled', false), FILTER_VALIDATE_BOOL)) {
            return 'Scope TEF desabilitado (UNITEC_SCOPE_TEF_PDV=false).';
        }

        return '';
    }

    public static function terminalPronto(?Terminal $terminal): bool
    {
        if ($terminal === null || ! self::featureEnabled()) {
            return false;
        }

        $extra = is_array($terminal->tef_extra) ? $terminal->tef_extra : [];
        if (TefProviderId::normalize((string) ($extra['provedor'] ?? '')) !== TefProviderId::SCOPE) {
            return false;
        }

        $cfg = ScopeTefTerminalConfig::read($terminal, withSecret: true);

        return $cfg['ativo']
            && $cfg['empresa_codigo'] !== ''
            && $cfg['loja_codigo'] !== ''
            && $cfg['terminal_codigo'] !== ''
            && $cfg['credencial_configured']
            && filled($cfg['credencial']);
    }

    public static function pollSeconds(): int
    {
        return max(4, (int) config('unitec.scope_tef_pdv.poll_seconds', 4));
    }
}
