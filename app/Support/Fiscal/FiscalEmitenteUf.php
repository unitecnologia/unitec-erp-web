<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\VendasParametro;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * UF do emitente para operações fiscais NF-e/NFC-e.
 * Não usa default silencioso para SC: evita transmitir PR com endpoints/mensagens errados.
 *
 * Prioridade: parâmetros fiscais (`vendas_parametros.uf`) → cadastro da empresa.
 * Memo por request (atributos do Request) evita SELECT repetido e não vaza entre
 * requisições no FrankenPHP; a chave é empresa_id (empresas diferentes não se misturam).
 */
final class FiscalEmitenteUf
{
    private const REQUEST_MEMO_KEY = 'fiscal.emitente_uf.memo';

    /** Fallback só quando não há Request (console/queue no mesmo processo). */
    /** @var array<int, ?string> */
    private static array $ufMemo = [];

    public static function tryResolve(?Empresa $empresa, ?VendasParametro $parametros = null): ?string
    {
        $empresaId = (int) ($empresa?->id ?? 0);

        if ($parametros !== null) {
            $uf = self::normalizeUf($parametros->uf ?: $empresa?->uf);

            if ($empresaId > 0) {
                self::memoPut($empresaId, $uf);
            }

            return $uf;
        }

        if ($empresaId > 0 && self::memoHas($empresaId)) {
            return self::memoGet($empresaId);
        }

        $paramsUf = $empresaId > 0
            ? VendasParametro::query()->whereKey($empresaId)->value('uf')
            : null;

        $uf = self::normalizeUf($paramsUf ?: $empresa?->uf);

        if ($empresaId > 0) {
            self::memoPut($empresaId, $uf);
        }

        return $uf;
    }

    public static function resolve(?Empresa $empresa, ?VendasParametro $parametros = null): string
    {
        $uf = self::tryResolve($empresa, $parametros);

        if ($uf === null) {
            throw new FiscalEngineException(
                'UF do emitente não configurada. Informe a UF da empresa ou dos parâmetros fiscais antes da operação.'
            );
        }

        return $uf;
    }

    /** Rótulo amigável para overlays (“Paraná”, “Santa Catarina”, ou a sigla). */
    public static function sefazOrigemRotulo(?string $uf): string
    {
        return match (strtoupper(trim((string) $uf))) {
            'PR' => 'Paraná',
            'SC' => 'Santa Catarina',
            '' => 'SEFAZ',
            default => strtoupper(trim((string) $uf)),
        };
    }

    /** Limpa memo (troca de empresa / testes / fim de processo console). */
    public static function clearMemo(): void
    {
        self::$ufMemo = [];

        if (app()->bound('request')) {
            request()->attributes->remove(self::REQUEST_MEMO_KEY);
        }
    }

    private static function normalizeUf(mixed $uf): ?string
    {
        $normalized = strtoupper(trim((string) $uf));

        return $normalized !== '' ? $normalized : null;
    }

    private static function memoHas(int $empresaId): bool
    {
        return array_key_exists($empresaId, self::memoBag());
    }

    private static function memoGet(int $empresaId): ?string
    {
        return self::memoBag()[$empresaId] ?? null;
    }

    private static function memoPut(int $empresaId, ?string $uf): void
    {
        if (app()->bound('request')) {
            $bag = self::memoBag();
            $bag[$empresaId] = $uf;
            request()->attributes->set(self::REQUEST_MEMO_KEY, $bag);

            return;
        }

        self::$ufMemo[$empresaId] = $uf;
    }

    /**
     * @return array<int, ?string>
     */
    private static function memoBag(): array
    {
        if (app()->bound('request')) {
            $bag = request()->attributes->get(self::REQUEST_MEMO_KEY);

            return is_array($bag) ? $bag : [];
        }

        return self::$ufMemo;
    }
}
