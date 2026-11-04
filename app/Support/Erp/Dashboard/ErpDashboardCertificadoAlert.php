<?php

namespace App\Support\Erp\Dashboard;

use App\Models\VendasParametro;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class ErpDashboardCertificadoAlert
{
    /** Cache curto da validade parseada do PKCS#12 (segundos). */
    private const CACHE_TTL_SECONDS = 300;

    public static function resolveEmpresaId(): ?int
    {
        return \App\Support\Erp\ErpContext::currentEmpresaId();
    }

    /**
     * @return array{tone: string, title: string, time: string, featured?: bool}|null
     */
    public static function fromEmpresa(?int $empresaId = null): ?array
    {
        $empresaId ??= self::resolveEmpresaId();

        if (! $empresaId) {
            return null;
        }

        $params = VendasParametro::query()->find($empresaId);

        if ($params === null) {
            return null;
        }

        $path = NfeFiscalConfig::certificadoAbsolutePath($params);
        $senha = $params->safeSenhaCertificado();

        if ($path === null || ($senha === null && ! $params->hasStoredSenhaCertificado())) {
            return [
                'tone' => 'orange',
                'title' => 'Certificado A1 não configurado',
                'time' => 'Hoje',
                'featured' => true,
            ];
        }

        if ($senha === null) {
            return [
                'tone' => 'red',
                'title' => 'Senha do certificado precisa ser reconfigurada nas configurações fiscais',
                'time' => 'Hoje',
                'featured' => true,
            ];
        }

        $validade = self::resolveValidadeCached($empresaId, $params, $path, $senha);

        if ($validade === null) {
            return [
                'tone' => 'red',
                'title' => 'Certificado A1 não pôde ser lido',
                'time' => 'Hoje',
                'featured' => true,
            ];
        }

        $dias = (int) now()->startOfDay()->diffInDays($validade->copy()->startOfDay(), false);

        return [
            'tone' => self::alertTone($dias),
            'title' => self::alertTitle($dias),
            'time' => 'Hoje',
            'featured' => true,
        ];
    }

    /**
     * Invalida o cache de validade da empresa (ex.: após substituir o certificado).
     */
    public static function forgetCacheForEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        try {
            Cache::forget(self::cacheIndexKey($empresaId));
        } catch (Throwable) {
            // best-effort
        }
    }

    public static function alertTitle(int $dias): string
    {
        if ($dias < 0) {
            $expirados = abs($dias);

            return $expirados === 1
                ? 'Certificado A1 vencido há 1 dia'
                : "Certificado A1 vencido há {$expirados} dias";
        }

        if ($dias === 0) {
            return 'Certificado A1 vence hoje';
        }

        if ($dias === 1) {
            return 'Certificado A1 vence em 1 dia';
        }

        return "Certificado A1 vence em {$dias} dias";
    }

    public static function alertTone(int $dias): string
    {
        if ($dias < 0 || $dias <= 7) {
            return 'red';
        }

        if ($dias <= 30) {
            return 'yellow';
        }

        return 'green';
    }

    private static function resolveValidadeCached(
        int $empresaId,
        VendasParametro $params,
        string $path,
        string $senha,
    ): ?Carbon {
        $fingerprint = self::certFingerprint($params, $path, $senha);
        $cacheKey = self::cacheValueKey($empresaId, $fingerprint);
        $indexKey = self::cacheIndexKey($empresaId);

        try {
            $previous = Cache::get($indexKey);
            if (is_string($previous) && $previous !== '' && $previous !== $cacheKey) {
                Cache::forget($previous);
            }
        } catch (Throwable) {
            // segue sem índice
        }

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                // Cacheia sucesso e falha de leitura (evita reparse PKCS#12 a cada abertura).
                if (($cached['ok'] ?? true) === false) {
                    return null;
                }
                if (isset($cached['validade']) && is_string($cached['validade']) && $cached['validade'] !== '') {
                    return Carbon::parse($cached['validade'])->startOfDay();
                }
            }
        } catch (Throwable) {
            // recarrega do arquivo
        }

        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            self::rememberCertCache($cacheKey, $indexKey, ['ok' => false, 'validade' => null]);

            return null;
        }

        $result = NfeFiscalConfig::readPkcs12($contents, $senha);

        if (! ($result['ok'] ?? false)) {
            self::rememberCertCache($cacheKey, $indexKey, ['ok' => false, 'validade' => null]);

            return null;
        }

        try {
            $validade = Carbon::createFromFormat('d/m/Y', (string) $result['validade'])->startOfDay();
        } catch (Throwable) {
            self::rememberCertCache($cacheKey, $indexKey, ['ok' => false, 'validade' => null]);

            return null;
        }

        self::rememberCertCache($cacheKey, $indexKey, [
            'ok' => true,
            'validade' => $validade->toDateString(),
        ]);

        return $validade;
    }

    /**
     * @param  array{ok: bool, validade: ?string}  $payload
     */
    private static function rememberCertCache(string $cacheKey, string $indexKey, array $payload): void
    {
        try {
            Cache::put($cacheKey, $payload, self::CACHE_TTL_SECONDS);
            Cache::put($indexKey, $cacheKey, self::CACHE_TTL_SECONDS);
        } catch (Throwable) {
            // cache best-effort
        }
    }

    private static function certFingerprint(VendasParametro $params, string $path, string $senha): string
    {
        $mtime = @filemtime($path);
        $size = @filesize($path);
        $updated = (string) ($params->updated_at?->getTimestamp() ?? 0);
        $caminho = (string) ($params->caminho_certificado ?? '');
        $senhaFp = hash_hmac('sha256', $senha, (string) config('app.key'));

        return hash('sha256', implode('|', [
            $caminho,
            (string) ($mtime === false ? 0 : $mtime),
            (string) ($size === false ? 0 : $size),
            $updated,
            $senhaFp,
        ]));
    }

    private static function cacheIndexKey(int $empresaId): string
    {
        return 'erp.dash.cert.index.'.$empresaId;
    }

    private static function cacheValueKey(int $empresaId, string $fingerprint): string
    {
        return 'erp.dash.cert.v1.'.$empresaId.'.'.substr($fingerprint, 0, 32);
    }
}
