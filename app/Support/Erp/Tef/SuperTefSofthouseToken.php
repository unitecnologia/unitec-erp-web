<?php

namespace App\Support\Erp\Tef;

use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\Schema;

/**
 * Token Bearer da software house SuperTEF.
 *
 * É da instalação (softhouse UNI), não da loja. Por isso o mesmo valor cifrado
 * é espelhado em todas as empresas do banco: qualquer contexto de empresa
 * encontra o token, e o dump SQL do backup já o leva.
 *
 * Isolamento de loja fica em terminais.tef_extra (cliente_chave + pos_id por PDV).
 */
final class SuperTefSofthouseToken
{
    public const COLUMN = 'param_tef_supertef_softhouse_token';

    public static function columnExists(): bool
    {
        return Schema::hasColumn('empresas', self::COLUMN);
    }

    public static function get(?int $empresaId = null): string
    {
        if (! self::columnExists()) {
            return '';
        }

        $empresa = self::resolveEmpresa($empresaId);
        $token = trim((string) ($empresa?->{self::COLUMN} ?? ''));

        if ($token !== '') {
            return $token;
        }

        // Instalação multiempresa: se a empresa atual ainda não tem o espelho, usa qualquer uma.
        $fallback = Empresa::query()
            ->whereNotNull(self::COLUMN)
            ->where(self::COLUMN, '!=', '')
            ->orderBy('id')
            ->first();

        return trim((string) ($fallback?->{self::COLUMN} ?? ''));
    }

    public static function isConfigured(?int $empresaId = null): bool
    {
        return self::get($empresaId) !== '';
    }

    /**
     * Grava o token em todas as empresas (mesmo cipher).
     * String vazia + keepExisting=true não altera o valor atual.
     */
    public static function put(?string $token, bool $keepExisting = true, ?int $empresaId = null): void
    {
        if (! self::columnExists()) {
            throw new \RuntimeException('Coluna do token SuperTEF ausente. Rode as migrations em DEV.');
        }

        $token = trim((string) $token);

        if ($token === '') {
            if ($keepExisting) {
                return;
            }

            Empresa::query()->update([self::COLUMN => null]);

            return;
        }

        // Cast SafeEncryptedString cifra no set; update em massa precisa do valor já cifrado.
        $empresa = self::resolveEmpresa($empresaId) ?? Empresa::query()->orderBy('id')->first();

        if ($empresa === null) {
            throw new \RuntimeException('Nenhuma empresa cadastrada para gravar o token SuperTEF.');
        }

        $empresa->{self::COLUMN} = $token;
        $empresa->save();

        $cipher = $empresa->getAttributes()[self::COLUMN] ?? null;

        if (! is_string($cipher) || $cipher === '') {
            return;
        }

        Empresa::query()
            ->whereKeyNot($empresa->id)
            ->update([self::COLUMN => $cipher]);
    }

    private static function resolveEmpresa(?int $empresaId): ?Empresa
    {
        $empresaId ??= ErpContext::currentEmpresaId();

        if ($empresaId) {
            $empresa = Empresa::query()->find($empresaId);
            if ($empresa) {
                return $empresa;
            }
        }

        return Empresa::query()->orderBy('id')->first();
    }
}
