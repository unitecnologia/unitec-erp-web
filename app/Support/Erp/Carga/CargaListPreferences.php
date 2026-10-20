<?php

namespace App\Support\Erp\Carga;

use App\Models\Carga;
use App\Models\ErpUserPreference;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSearchFieldSelection;
use Illuminate\Support\Facades\Auth;

/**
 * Preferências da listagem Carga / Romaneio (usuário + empresa).
 * Um único registro JSON — 1 SELECT no restore, 1 upsert no persist.
 */
final class CargaListPreferences
{
    public const KEY = 'cargas.list';

    /** @var list<string> */
    public const SORT_COLUMNS = [
        'numero', 'data', 'motorista', 'entregador', 'veiculo',
        'pedidos_count', 'valor_total', 'status',
    ];

    /** @var list<string> */
    public const SEARCH_COLUMNS = [
        'numero', 'data', 'motorista', 'entregador', 'veiculo',
        'pedidos_count', 'valor_total', 'status',
    ];

    /**
     * @return array<string, string>
     */
    public static function searchFieldLabels(): array
    {
        return [
            'numero' => 'Nº',
            'data' => 'DATA',
            'motorista' => 'MOTORISTA',
            'entregador' => 'ENTREGADOR',
            'veiculo' => 'VEÍCULO',
            'pedidos_count' => 'QTD. PEDIDOS',
            'valor_total' => 'VALOR TOTAL',
            'status' => 'STATUS',
        ];
    }

    /**
     * @return array{
     *     periodo_de: ?string,
     *     periodo_ate: ?string,
     *     status: string,
     *     per_page: int,
     *     sort_column: string,
     *     sort_direction: string,
     *     search_fields: list<string>
     * }|null
     */
    public static function load(?int $userId = null, ?int $empresaId = null): ?array
    {
        [$userId, $empresaId] = self::ids($userId, $empresaId);

        if ($userId <= 0 || $empresaId <= 0) {
            return null;
        }

        $raw = ErpUserPreference::getValue($userId, $empresaId, self::KEY);

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        return self::normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $prefs
     */
    public static function save(array $prefs, ?int $userId = null, ?int $empresaId = null): void
    {
        [$userId, $empresaId] = self::ids($userId, $empresaId);

        if ($userId <= 0 || $empresaId <= 0) {
            return;
        }

        $normalized = self::normalize($prefs);
        $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE);

        if (! is_string($payload) || $payload === '') {
            return;
        }

        // Coluna value VARCHAR(255).
        if (strlen($payload) > 255) {
            // Compacta labels longas mantendo o essencial.
            $compact = $normalized;
            $compact['sf'] = $compact['search_fields'];
            unset($compact['search_fields']);
            $payload = json_encode($compact, JSON_UNESCAPED_UNICODE) ?: $payload;

            if (strlen($payload) > 255) {
                return;
            }
        }

        ErpUserPreference::putValue($userId, $empresaId, self::KEY, $payload);
    }

    /**
     * @param  array<string, mixed>  $prefs
     * @return array{
     *     periodo_de: ?string,
     *     periodo_ate: ?string,
     *     status: string,
     *     per_page: int,
     *     sort_column: string,
     *     sort_direction: string,
     *     search_fields: list<string>
     * }
     */
    public static function normalize(array $prefs): array
    {
        $status = (string) ($prefs['status'] ?? 'todos');
        $allowedStatus = ['todos', Carga::STATUS_ABERTA, Carga::STATUS_FECHADA, Carga::STATUS_CANCELADA];
        if (! in_array($status, $allowedStatus, true)) {
            $status = 'todos';
        }

        $perPage = (int) ($prefs['per_page'] ?? 50);
        if (! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 50;
        }

        $sortColumn = (string) ($prefs['sort_column'] ?? 'numero');
        if (! in_array($sortColumn, self::SORT_COLUMNS, true)) {
            $sortColumn = 'numero';
        }

        $sortDirection = strtolower((string) ($prefs['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $rawFields = $prefs['search_fields'] ?? $prefs['sf'] ?? ['numero'];
        if (! is_array($rawFields)) {
            $rawFields = [(string) $rawFields];
        }

        $searchFields = ErpSearchFieldSelection::normalize(
            $rawFields,
            self::SEARCH_COLUMNS,
            'numero',
        );

        return [
            'periodo_de' => self::normalizeDate($prefs['periodo_de'] ?? null),
            'periodo_ate' => self::normalizeDate($prefs['periodo_ate'] ?? null),
            'status' => $status,
            'per_page' => $perPage,
            'sort_column' => $sortColumn,
            'sort_direction' => $sortDirection,
            'search_fields' => $searchFields,
        ];
    }

    private static function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function ids(?int $userId, ?int $empresaId): array
    {
        $userId = $userId ?? (int) (Auth::id() ?? 0);
        $empresaId = $empresaId ?? (int) (ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);

        return [$userId, $empresaId];
    }
}
