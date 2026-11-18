<?php

namespace App\Support\Erp\Tef\Scope;

use App\Models\Terminal;
use App\Support\Erp\Tef\Data\TefProviderResult;
use App\Support\Erp\Tef\TefProviderId;

/**
 * Diagnóstico de instalação Scope — sem chamar API/DLL inventada.
 */
final class ScopeTefDiagnostico
{
    public static function run(Terminal $terminal): TefProviderResult
    {
        $extra = is_array($terminal->tef_extra) ? $terminal->tef_extra : [];
        $provedor = TefProviderId::normalize((string) ($extra['provedor'] ?? TefProviderId::SUPERTEF));
        $cfg = ScopeTefTerminalConfig::read($terminal, withSecret: false);

        $checks = [
            'provedor_scope' => $provedor === TefProviderId::SCOPE,
            'sdk_available' => ScopeTefPdvGate::sdkAvailable(),
            'feature_enabled' => filter_var(config('unitec.scope_tef_pdv.enabled', false), FILTER_VALIDATE_BOOL),
            'config_ativo' => (bool) $cfg['ativo'],
            'empresa_codigo' => $cfg['empresa_codigo'] !== '',
            'loja_codigo' => $cfg['loja_codigo'] !== '',
            'terminal_codigo' => $cfg['terminal_codigo'] !== '',
            'credencial' => (bool) $cfg['credencial_configured'],
            'servico_host' => $cfg['servico_host'] !== '',
        ];

        $faltando = [];
        foreach ($checks as $nome => $ok) {
            if (! $ok) {
                $faltando[] = $nome;
            }
        }

        $meta = [
            'provider' => TefProviderId::SCOPE,
            'ambiente' => $cfg['ambiente'],
            'servico_host' => $cfg['servico_host'],
            'servico_porta' => $cfg['servico_porta'],
            'checks' => $checks,
            'faltando' => $faltando,
            'sdk_dependency' => 'Conexão Tech Scope TEF SDK + documentação oficial (pendente)',
            'blocked_reason' => ScopeTefPdvGate::activationBlockedReason(),
        ];

        if (! ScopeTefPdvGate::sdkAvailable()) {
            return new TefProviderResult(
                ok: false,
                message: 'Diagnóstico Scope: SDK Conexão Tech indisponível. Configuração pode ser preparada; cobranças permanecem bloqueadas.',
                operational: false,
                meta: $meta,
            );
        }

        if ($faltando !== []) {
            return TefProviderResult::failure(
                'Diagnóstico Scope: configuração incompleta ('.implode(', ', $faltando).').',
                meta: $meta,
            );
        }

        return TefProviderResult::success('Diagnóstico Scope: configuração local completa.', meta: $meta);
    }
}
