<?php

namespace App\Support\Fiscal;

use App\Models\PdvVendaNfce;
use App\Models\Terminal;
use App\Models\VendasParametro;
use Illuminate\Support\Facades\DB;

/**
 * Série e próximo número NFC-e por caixa (aba PDVs Offline).
 * O incremento usa conexão separada para sobreviver ao rollback da venda (539).
 */
final class NfceTerminalSequencia
{
    /**
     * @return list<string>
     */
    public static function seriesEquivalentes(?string $serie): array
    {
        $serie = trim((string) $serie);
        if ($serie === '') {
            $serie = '1';
        }

        $semZeros = ltrim($serie, '0') ?: '0';

        return array_values(array_unique([
            $serie,
            $semZeros,
            str_pad($semZeros, 3, '0', STR_PAD_LEFT),
        ]));
    }

    public static function mesmaSerie(?string $a, ?string $b): bool
    {
        $na = ltrim(trim((string) $a), '0') ?: '0';
        $nb = ltrim(trim((string) $b), '0') ?: '0';

        return $na === $nb;
    }

    public static function serieEfetiva(?Terminal $terminal, ?VendasParametro $parametros): string
    {
        $serie = trim((string) ($terminal?->serie ?: ''));
        if ($serie !== '') {
            return $serie;
        }

        $serie = trim((string) ($parametros?->serie ?: ''));

        return $serie !== '' ? $serie : '1';
    }

    public static function serieEfetivaInt(?Terminal $terminal, ?VendasParametro $parametros): int
    {
        return (int) ltrim(self::serieEfetiva($terminal, $parametros), '0') ?: 1;
    }

    public static function ultimoNumero(int $empresaId, ?string $serie): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        $ultimo = PdvVendaNfce::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('serie', self::seriesEquivalentes($serie))
            ->max('numero');

        if ($ultimo === null) {
            return null;
        }

        return (int) $ultimo;
    }

    public static function proximoPiso(?Terminal $terminal, ?VendasParametro $parametros): int
    {
        $empresaId = (int) ($terminal?->empresa_id ?: $parametros?->empresa_id ?: 0);
        $serie = self::serieEfetiva($terminal, $parametros);
        $ultimo = $empresaId > 0 ? self::ultimoNumero($empresaId, $serie) : null;
        $pisoUltimo = $ultimo !== null ? $ultimo + 1 : 1;
        $armazenado = max(1, (int) ($terminal?->numeracao_inicial ?: 1));
        $empresaNumero = 1;

        if ($parametros && self::mesmaSerie($serie, (string) $parametros->serie)) {
            $empresaNumero = max(1, (int) ($parametros->numero ?: 1));
        }

        return max($armazenado, $pisoUltimo, $empresaNumero);
    }

    public static function consume(?Terminal $terminal, VendasParametro $parametros): int
    {
        if ($terminal === null || ! $terminal->exists || $terminal->getKey() === null) {
            return $parametros->consumeNumero();
        }

        $connection = self::sequenciaConnectionName();
        $terminalId = (int) $terminal->getKey();
        $empresaId = (int) ($terminal->empresa_id ?: $parametros->empresa_id);

        $numero = DB::connection($connection)->transaction(function () use ($connection, $terminalId, $empresaId, $parametros): int {
            $row = Terminal::on($connection)
                ->whereKey($terminalId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return $parametros->consumeNumero();
            }

            $paramsRow = VendasParametro::on($connection)
                ->whereKey($empresaId)
                ->lockForUpdate()
                ->first() ?? $parametros;

            $serie = self::serieEfetiva($row, $paramsRow);
            $ultimo = self::ultimoNumero($empresaId, $serie);
            $pisoUltimo = $ultimo !== null ? $ultimo + 1 : 1;
            $armazenado = max(1, (int) ($row->numeracao_inicial ?: 1));
            $empresaNumero = 1;

            if (self::mesmaSerie($serie, (string) ($paramsRow->serie ?? ''))) {
                $empresaNumero = max(1, (int) ($paramsRow->numero ?: 1));
            }

            $atual = max($armazenado, $pisoUltimo, $empresaNumero);
            $proximo = $atual + 1;

            $row->newQuery()
                ->whereKey($row->getKey())
                ->update([
                    'numeracao_inicial' => $proximo,
                    'usar_numero_inicial' => true,
                ]);

            if (self::mesmaSerie($serie, (string) ($paramsRow->serie ?? ''))) {
                $paramsRow->newQuery()
                    ->whereKey($paramsRow->getKey())
                    ->update(['numero' => $proximo]);
            }

            return $atual;
        });

        $terminal->setAttribute('numeracao_inicial', $numero + 1);
        $terminal->setAttribute('usar_numero_inicial', true);

        if (self::mesmaSerie(self::serieEfetiva($terminal, $parametros), (string) $parametros->serie)) {
            $parametros->setAttribute('numero', $numero + 1);
        }

        return $numero;
    }

    public static function ensureNumeroPeloMenos(?Terminal $terminal, int $minimo, ?VendasParametro $parametros): void
    {
        $minimo = max(1, $minimo);

        if ($terminal === null || ! $terminal->exists || $terminal->getKey() === null) {
            $parametros?->ensureNumeroPeloMenos($minimo);

            return;
        }

        $connection = self::sequenciaConnectionName();
        $terminalId = (int) $terminal->getKey();
        $empresaId = (int) ($terminal->empresa_id ?: $parametros?->empresa_id);

        DB::connection($connection)->transaction(function () use ($connection, $terminalId, $empresaId, $minimo, $parametros): void {
            $row = Terminal::on($connection)
                ->whereKey($terminalId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $parametros?->ensureNumeroPeloMenos($minimo);

                return;
            }

            $atual = max(1, (int) ($row->numeracao_inicial ?: 1));
            if ($atual < $minimo) {
                $row->newQuery()
                    ->whereKey($row->getKey())
                    ->update(['numeracao_inicial' => $minimo]);
            }

            $paramsRow = $parametros !== null
                ? VendasParametro::on($connection)->whereKey($empresaId)->lockForUpdate()->first()
                : null;

            if ($paramsRow && self::mesmaSerie(self::serieEfetiva($row, $paramsRow), (string) $paramsRow->serie)) {
                $empresaAtual = max(1, (int) ($paramsRow->numero ?: 1));
                if ($empresaAtual < $minimo) {
                    $paramsRow->newQuery()
                        ->whereKey($paramsRow->getKey())
                        ->update(['numero' => $minimo]);
                }
            }
        });

        if ((int) ($terminal->numeracao_inicial ?? 1) < $minimo) {
            $terminal->setAttribute('numeracao_inicial', $minimo);
        }

        if (
            $parametros
            && self::mesmaSerie(self::serieEfetiva($terminal, $parametros), (string) $parametros->serie)
            && (int) ($parametros->numero ?? 1) < $minimo
        ) {
            $parametros->setAttribute('numero', $minimo);
        }
    }

    private static function sequenciaConnectionName(): string
    {
        $default = (string) config('database.default');
        $driver = (string) config("database.connections.{$default}.driver");

        if ($driver === 'sqlite') {
            $database = (string) config("database.connections.{$default}.database");
            if ($database === ':memory:' || str_contains($database, 'mode=memory')) {
                return $default;
            }
        }

        $seq = $default.'_fiscal_seq';

        if (! config()->has("database.connections.{$seq}")) {
            config(["database.connections.{$seq}" => config("database.connections.{$default}")]);
        }

        return $seq;
    }
}
