<?php

namespace App\Support\Erp\Tef;

use App\Models\Terminal;
use App\Support\Erp\Tef\Contracts\TefElectronicProvider;
use App\Support\Erp\Tef\Providers\ScopeTefElectronicProvider;
use App\Support\Erp\Tef\Providers\SuperTefElectronicProvider;
use RuntimeException;

/**
 * Resolve o adaptador TEF a partir do terminal (tef_extra.provedor).
 * Padrão: SuperTEF. Scope só quando explicitamente selecionado.
 */
final class TefProviderResolver
{
    public function __construct(
        private readonly ?SuperTefElectronicProvider $superTef = null,
        private readonly ?ScopeTefElectronicProvider $scope = null,
    ) {}

    public function forTerminal(?Terminal $terminal): TefElectronicProvider
    {
        $id = $this->providerIdForTerminal($terminal);

        return $this->make($id);
    }

    public function providerIdForTerminal(?Terminal $terminal): string
    {
        if ($terminal === null) {
            return TefProviderId::SUPERTEF;
        }

        $extra = is_array($terminal->tef_extra) ? $terminal->tef_extra : [];

        return TefProviderId::normalize((string) ($extra['provedor'] ?? TefProviderId::SUPERTEF));
    }

    public function make(string $providerId): TefElectronicProvider
    {
        return match (TefProviderId::normalize($providerId)) {
            TefProviderId::SCOPE => $this->scope ?? new ScopeTefElectronicProvider(),
            TefProviderId::SUPERTEF => $this->superTef ?? new SuperTefElectronicProvider(),
            default => throw new RuntimeException('Provedor TEF não suportado: '.$providerId),
        };
    }
}
