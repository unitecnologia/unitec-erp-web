<?php

namespace App\Support\Erp\Tef;

/**
 * Identificadores estáveis dos provedores TEF eletrônicos do UNI ERP.
 * SuperTEF permanece o único operacional até a documentação oficial do Scope.
 */
final class TefProviderId
{
    public const SUPERTEF = 'supertef';

    public const SCOPE = 'scope';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SUPERTEF, self::SCOPE];
    }

    public static function normalize(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            self::SCOPE => self::SCOPE,
            // Apenas suíte de testes (SimulatedTefProvider) — nunca expor na UI.
            'simulated' => 'simulated',
            self::SUPERTEF => self::SUPERTEF,
            default => self::SUPERTEF,
        };
    }

    public static function label(string $provider): string
    {
        return match (self::normalize($provider)) {
            self::SCOPE => 'Scope TEF (Conexão Tech)',
            default => 'SuperTEF',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForUi(): array
    {
        return [
            self::SUPERTEF => self::label(self::SUPERTEF),
            self::SCOPE => self::label(self::SCOPE).' — em preparação',
        ];
    }
}
