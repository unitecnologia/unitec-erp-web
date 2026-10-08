<?php

namespace App\Support\Fiscal;

use App\Models\PdvVendaNfce;
use App\Models\Terminal;
use App\Models\VendasParametro;
use Illuminate\Support\Facades\DB;

/**
 * Série NFC-e por caixa (aba PDVs Offline). O número vem do contador único por
 * empresa/série/ambiente (NfceNumeracao); o caminho por terminal só vale enquanto a
 * migration do contador não foi aplicada. O incremento usa conexão separada para
 * sobreviver ao rollback da venda (539).
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

    /**
     * @param  int|null  $ambiente  1 = produção, 2 = homologação (PdvVendaNfce); null = todos (legado)
     */
    public static function ultimoNumero(int $empresaId, ?string $serie, ?int $ambiente = null): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        $ultimo = PdvVendaNfce::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('serie', self::seriesEquivalentes($serie))
            ->when($ambiente !== null, fn ($q) => $q->where('ambiente', $ambiente)->where(fn ($s) => $s->where('simulada', false)->orWhereNull('simulada')))
            ->max('numero');

        if ($ultimo === null) {
            return null;
        }

        return (int) $ultimo;
    }

    /**
     * Menor número que o caixa pode usar na série/ambiente configurados (exibição, carga do PDV
     * offline, validação do ajuste manual). Com o contador único: maior entre o contador e tudo
     * que já foi usado (NFC-e gravadas e livro) no ambiente atual; contadores antigos do terminal
     * só pesam enquanto a série ainda não tem contador (já incluídos em NfceNumeracao::proximo).
     */
    public static function proximoPiso(?Terminal $terminal, ?VendasParametro $parametros): int
    {
        $empresaId = (int) ($terminal?->empresa_id ?: $parametros?->empresa_id ?: 0);
        $serie = self::serieEfetiva($terminal, $parametros);

        if ($empresaId > 0 && $parametros !== null && NfceNumeracao::disponivel()) {
            $serieInt = self::serieEfetivaInt($terminal, $parametros);
            $ambiente = NfceNumeracao::ambiente($parametros);

            return max(
                NfceNumeracao::proximo($empresaId, $serieInt, $parametros),
                NfceNumeracao::ultimoUsado($empresaId, $serieInt, $ambiente) + 1,
                (self::ultimoNumero($empresaId, $serie, $ambiente) ?? 0) + 1,
            );
        }

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
        $empresaId = (int) ($terminal?->empresa_id ?: $parametros->empresa_id);

        if ($empresaId > 0 && NfceNumeracao::disponivel()) {
            $temTerminal = $terminal !== null && $terminal->exists && $terminal->getKey() !== null;
            $serie = self::serieEfetivaInt($terminal, $parametros);
            $numero = NfceNumeracao::consumir($empresaId, $serie, $parametros, $temTerminal ? (int) $terminal->getKey() : null);

            if ($temTerminal && (int) ($terminal->numeracao_inicial ?? 0) <= $numero) {
                $terminal->setAttribute('numeracao_inicial', $numero + 1);
                $terminal->setAttribute('usar_numero_inicial', true);
            }

            if (self::mesmaSerie((string) $serie, (string) $parametros->serie) && (int) ($parametros->numero ?? 0) <= $numero) {
                $parametros->setAttribute('numero', $numero + 1);
            }

            return $numero;
        }

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

            // Outros caixas na mesma série (lidos após a trava dos parâmetros, que serializa a empresa).
            $outrosCaixas = 1;
            foreach (Terminal::on($connection)->where('empresa_id', $empresaId)->whereKeyNot($terminalId)->get(['id', 'empresa_id', 'serie', 'numeracao_inicial']) as $outro) {
                if (self::mesmaSerie(self::serieEfetiva($outro, $paramsRow), $serie)) {
                    $outrosCaixas = max($outrosCaixas, (int) ($outro->numeracao_inicial ?: 1));
                }
            }

            $atual = max($armazenado, $pisoUltimo, $empresaNumero, $outrosCaixas);
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

    /**
     * @param  int|null  $serie  série do número conflitante (ex.: chave citada na 539); padrão = série do caixa
     */
    public static function ensureNumeroPeloMenos(?Terminal $terminal, int $minimo, ?VendasParametro $parametros, ?int $serie = null): void
    {
        $minimo = max(1, $minimo);
        $empresaId = (int) ($terminal?->empresa_id ?: $parametros?->empresa_id ?: 0);

        if ($parametros !== null && $empresaId > 0 && NfceNumeracao::disponivel()) {
            $temTerminal = $terminal !== null && $terminal->exists && $terminal->getKey() !== null;
            $serieTerminal = self::serieEfetivaInt($terminal, $parametros);
            $serie ??= $serieTerminal;

            NfceNumeracao::garantirPeloMenos(
                $empresaId,
                $serie,
                $parametros,
                $minimo,
                $temTerminal && $serie === $serieTerminal ? (int) $terminal->getKey() : null,
            );

            return;
        }

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

    public static function sequenciaConnectionName(): string
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
