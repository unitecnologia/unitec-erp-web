<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvVendaEspera;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Persiste o cupom em espera sem gerar venda, estoque, financeiro ou NFC-e.
 */
final class PdvVendaEsperaService
{
    public const VERSION = 1;

    /**
     * @param  list<array<string, mixed>>  $cupomItens
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    public function buildSnapshot(array $cupomItens, array $contexto = []): array
    {
        return [
            'version' => self::VERSION,
            'saved_at' => Carbon::now()->toIso8601String(),
            'cupom_itens' => array_values($cupomItens),
            'contexto' => $contexto,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function encode(array $snapshot): string
    {
        return json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decode(PdvVendaEspera $espera): ?array
    {
        $decoded = json_decode((string) $espera->snapshot, true);

        if (! is_array($decoded)
            || (int) ($decoded['version'] ?? 0) !== self::VERSION
            || ! isset($decoded['cupom_itens'])
            || ! is_array($decoded['cupom_itens'])
            || $decoded['cupom_itens'] === []) {
            return null;
        }

        return $decoded;
    }

    public function nextSequencia(int $caixaSessaoId): int
    {
        return ((int) PdvVendaEspera::query()
            ->where('pdv_caixa_sessao_id', $caixaSessaoId)
            ->max('sequencia')) + 1;
    }

    /**
     * @return Collection<int, PdvVendaEspera>
     */
    public function pendentes(int $caixaSessaoId, int $userId): Collection
    {
        return PdvVendaEspera::query()
            ->where('pdv_caixa_sessao_id', $caixaSessaoId)
            ->where('user_id', $userId)
            ->latest('id')
            ->get();
    }
}
