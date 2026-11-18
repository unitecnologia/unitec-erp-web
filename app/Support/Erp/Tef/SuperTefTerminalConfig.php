<?php

namespace App\Support\Erp\Tef;

use App\Models\Terminal;
use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Configuração SuperTEF por terminal em terminais.tef_extra (JSON LONGTEXT).
 * A chave do cliente fica cifrada; o browser só recebe flags de “configurado”.
 *
 * Crédito (API SuperTEF):
 * - transaction_type = 2
 * - installment_type 1 = parcelamento loja (homologado / padrão)
 * - installment_type 3 = administradora/portador (documentado; desabilitado até homologação)
 */
final class SuperTefTerminalConfig
{
    public const VERSION = 1;

    public const PROVIDER_SUPERTEF = 'supertef';

    /** Parcelamento pela loja (API). */
    public const INSTALLMENT_TYPE_LOJA = 1;

    /** Parcelamento pela administradora/portador (API) — só após homologação. */
    public const INSTALLMENT_TYPE_ADMINISTRADORA = 3;

    public const CREDITO_MAX_PARCELAS_DEFAULT = 12;

    public const CREDITO_MAX_PARCELAS_LIMITE = 18;

    /**
     * @return array{
     *   ativo: bool,
     *   provedor: string,
     *   pos_id: int|null,
     *   cliente_chave_configured: bool,
     *   cliente_chave: string|null,
     *   credito_max_parcelas: int,
     *   credito_installment_type: int
     * }
     */
    public static function read(Terminal $terminal, bool $withSecret = false): array
    {
        $raw = $terminal->tef_extra;
        $data = is_array($raw) ? $raw : [];

        $chaveCipher = trim((string) ($data['cliente_chave_cipher'] ?? ''));
        $chavePlain = null;

        if ($chaveCipher !== '') {
            try {
                $chavePlain = Crypt::decryptString($chaveCipher);
            } catch (DecryptException) {
                $chavePlain = null;
            }
        }

        $pos = $data['pos_id'] ?? null;
        $posId = is_numeric($pos) ? (int) $pos : null;
        if ($posId !== null && $posId <= 0) {
            $posId = null;
        }

        return [
            'ativo' => (bool) ($data['ativo'] ?? false),
            'provedor' => self::normalizeProvider((string) ($data['provedor'] ?? self::PROVIDER_SUPERTEF)),
            'pos_id' => $posId,
            'cliente_chave_configured' => is_string($chavePlain) && trim($chavePlain) !== '',
            'cliente_chave' => $withSecret && is_string($chavePlain) ? trim($chavePlain) : null,
            'credito_max_parcelas' => self::normalizeMaxParcelas($data['credito_max_parcelas'] ?? null),
            'credito_installment_type' => self::normalizeInstallmentType($data['credito_installment_type'] ?? null),
        ];
    }

    /**
     * @param  array{
     *   ativo?: bool,
     *   provedor?: string,
     *   pos_id?: int|string|null,
     *   cliente_chave?: string|null,
     *   keep_cliente_chave?: bool,
     *   credito_max_parcelas?: int|string|null,
     *   credito_installment_type?: int|string|null
     * }  $input
     * @return array<string, mixed>
     */
    public static function writePayload(array $input, ?Terminal $existing = null): array
    {
        $atual = $existing ? self::read($existing, withSecret: true) : [
            'ativo' => false,
            'provedor' => self::PROVIDER_SUPERTEF,
            'pos_id' => null,
            'cliente_chave_configured' => false,
            'cliente_chave' => null,
            'credito_max_parcelas' => self::CREDITO_MAX_PARCELAS_DEFAULT,
            'credito_installment_type' => self::INSTALLMENT_TYPE_LOJA,
        ];

        $ativo = (bool) ($input['ativo'] ?? false);
        $provedor = self::normalizeProvider((string) ($input['provedor'] ?? $atual['provedor']));
        $posRaw = $input['pos_id'] ?? $atual['pos_id'];
        $posId = is_numeric($posRaw) ? (int) $posRaw : null;
        if ($posId !== null && $posId <= 0) {
            $posId = null;
        }

        $novaChave = trim((string) ($input['cliente_chave'] ?? ''));
        $keep = (bool) ($input['keep_cliente_chave'] ?? true);
        $chaveFinal = $novaChave !== ''
            ? $novaChave
            : ($keep ? (string) ($atual['cliente_chave'] ?? '') : '');

        $payload = [
            'v' => self::VERSION,
            'ativo' => $ativo,
            'provedor' => $provedor,
            'pos_id' => $posId,
            'credito_max_parcelas' => self::normalizeMaxParcelas(
                $input['credito_max_parcelas'] ?? $atual['credito_max_parcelas']
            ),
            'credito_installment_type' => self::normalizeInstallmentType(
                $input['credito_installment_type'] ?? $atual['credito_installment_type']
            ),
        ];

        if ($chaveFinal !== '') {
            $payload['cliente_chave_cipher'] = Crypt::encryptString($chaveFinal);
        }

        return $payload;
    }

    public static function normalizeProvider(string $provider): string
    {
        // Aceita Scope para cadastro multiprovedor; desconhecido → SuperTEF (legado seguro).
        return TefProviderId::normalize($provider) === TefProviderId::SCOPE
            ? TefProviderId::SCOPE
            : self::PROVIDER_SUPERTEF;
    }

    public static function normalizeMaxParcelas(mixed $value): int
    {
        $n = is_numeric($value) ? (int) $value : self::CREDITO_MAX_PARCELAS_DEFAULT;

        return max(1, min(self::CREDITO_MAX_PARCELAS_LIMITE, $n));
    }

    /**
     * Somente installment_type=1 (loja) liberado até homologação do tipo 3.
     */
    public static function normalizeInstallmentType(mixed $value): int
    {
        $n = is_numeric($value) ? (int) $value : self::INSTALLMENT_TYPE_LOJA;

        if ($n === self::INSTALLMENT_TYPE_ADMINISTRADORA) {
            // Documentado na API, mas desabilitado no ERP até homologação.
            return self::INSTALLMENT_TYPE_LOJA;
        }

        return self::INSTALLMENT_TYPE_LOJA;
    }
}
