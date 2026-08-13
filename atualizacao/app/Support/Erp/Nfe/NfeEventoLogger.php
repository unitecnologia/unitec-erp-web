<?php

namespace App\Support\Erp\Nfe;

use App\Models\NfeEvento;
use Carbon\CarbonInterface;

final class NfeEventoLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function registrar(
        int $nfeId,
        string $tipo,
        string $titulo,
        ?string $descricao = null,
        ?string $destinatario = null,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        ?array $metadata = null,
        ?int $userId = null,
        ?CarbonInterface $createdAt = null,
    ): NfeEvento {
        return NfeEvento::query()->create([
            'nfe_id' => $nfeId,
            'user_id' => $userId ?? auth()->id(),
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'destinatario' => $destinatario,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'metadata' => $metadata,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
