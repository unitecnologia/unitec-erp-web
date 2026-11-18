<?php

namespace App\Support\Erp\Tef\Scope;

use App\Models\Terminal;
use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Configuração Scope TEF (Conexão Tech) em terminais.tef_extra.scope.
 *
 * Credenciais cifradas. Campos de identificação preparados; endpoints/DLL
 * oficiais ainda NÃO inventados — aguardam documentação da Conexão Tech.
 */
final class ScopeTefTerminalConfig
{
    public const VERSION = 1;

    public const AMBIENTE_HOMOLOGACAO = 'homologacao';

    public const AMBIENTE_PRODUCAO = 'producao';

    /**
     * @return array{
     *   ativo: bool,
     *   ambiente: string,
     *   empresa_codigo: string,
     *   loja_codigo: string,
     *   terminal_codigo: string,
     *   servico_host: string,
     *   servico_porta: int|null,
     *   credencial_configured: bool,
     *   credencial: string|null
     * }
     */
    public static function read(Terminal $terminal, bool $withSecret = false): array
    {
        $raw = is_array($terminal->tef_extra) ? $terminal->tef_extra : [];
        $data = is_array($raw['scope'] ?? null) ? $raw['scope'] : [];

        $cipher = trim((string) ($data['credencial_cipher'] ?? ''));
        $plain = null;
        if ($cipher !== '') {
            try {
                $plain = Crypt::decryptString($cipher);
            } catch (DecryptException) {
                $plain = null;
            }
        }

        $porta = $data['servico_porta'] ?? null;
        $portaInt = is_numeric($porta) ? (int) $porta : null;
        if ($portaInt !== null && ($portaInt <= 0 || $portaInt > 65535)) {
            $portaInt = null;
        }

        return [
            'ativo' => (bool) ($data['ativo'] ?? false),
            'ambiente' => self::normalizeAmbiente((string) ($data['ambiente'] ?? self::AMBIENTE_HOMOLOGACAO)),
            'empresa_codigo' => trim((string) ($data['empresa_codigo'] ?? '')),
            'loja_codigo' => trim((string) ($data['loja_codigo'] ?? '')),
            'terminal_codigo' => trim((string) ($data['terminal_codigo'] ?? '')),
            'servico_host' => self::normalizeHost((string) ($data['servico_host'] ?? '127.0.0.1')),
            'servico_porta' => $portaInt,
            'credencial_configured' => is_string($plain) && trim($plain) !== '',
            'credencial' => $withSecret && is_string($plain) ? trim($plain) : null,
        ];
    }

    /**
     * Mescla bloco Scope em payload tef_extra já montado (SuperTEF + provedor).
     *
     * @param  array<string, mixed>  $tefExtra
     * @param  array{
     *   ativo?: bool,
     *   ambiente?: string,
     *   empresa_codigo?: string,
     *   loja_codigo?: string,
     *   terminal_codigo?: string,
     *   servico_host?: string,
     *   servico_porta?: int|string|null,
     *   credencial?: string|null,
     *   keep_credencial?: bool
     * }  $input
     * @return array<string, mixed>
     */
    public static function mergeIntoExtra(array $tefExtra, array $input, ?Terminal $existing = null): array
    {
        $atual = $existing ? self::read($existing, withSecret: true) : [
            'ativo' => false,
            'ambiente' => self::AMBIENTE_HOMOLOGACAO,
            'empresa_codigo' => '',
            'loja_codigo' => '',
            'terminal_codigo' => '',
            'servico_host' => '127.0.0.1',
            'servico_porta' => null,
            'credencial_configured' => false,
            'credencial' => null,
        ];

        $novaCred = trim((string) ($input['credencial'] ?? ''));
        $keep = (bool) ($input['keep_credencial'] ?? true);
        $credFinal = $novaCred !== ''
            ? $novaCred
            : ($keep ? (string) ($atual['credencial'] ?? '') : '');

        $portaRaw = $input['servico_porta'] ?? $atual['servico_porta'];
        $porta = is_numeric($portaRaw) ? (int) $portaRaw : null;
        if ($porta !== null && ($porta <= 0 || $porta > 65535)) {
            $porta = null;
        }

        $scope = [
            'v' => self::VERSION,
            'ativo' => (bool) ($input['ativo'] ?? false),
            'ambiente' => self::normalizeAmbiente((string) ($input['ambiente'] ?? $atual['ambiente'])),
            'empresa_codigo' => trim((string) ($input['empresa_codigo'] ?? $atual['empresa_codigo'])),
            'loja_codigo' => trim((string) ($input['loja_codigo'] ?? $atual['loja_codigo'])),
            'terminal_codigo' => trim((string) ($input['terminal_codigo'] ?? $atual['terminal_codigo'])),
            'servico_host' => self::normalizeHost((string) ($input['servico_host'] ?? $atual['servico_host'])),
            'servico_porta' => $porta,
        ];

        if ($credFinal !== '') {
            $scope['credencial_cipher'] = Crypt::encryptString($credFinal);
        }

        $tefExtra['scope'] = $scope;

        if (($tefExtra['provedor'] ?? null) === TefProviderId::SCOPE) {
            // Scope nunca usa usa_tef legado; ativo do bloco Scope é a fonte.
            $tefExtra['ativo'] = (bool) $scope['ativo'];
        }

        return $tefExtra;
    }

    public static function normalizeAmbiente(string $ambiente): string
    {
        $ambiente = strtolower(trim($ambiente));

        return $ambiente === self::AMBIENTE_PRODUCAO
            ? self::AMBIENTE_PRODUCAO
            : self::AMBIENTE_HOMOLOGACAO;
    }

    public static function normalizeHost(string $host): string
    {
        $host = trim($host);
        if ($host === '' || strlen($host) > 120) {
            return '127.0.0.1';
        }

        // Apenas host/IP local preparado — sem URL inventada de API remota.
        return $host;
    }

    /**
     * Slot numérico estável para ocupação (não é POS SuperTEF).
     */
    public static function slotIdFromTerminalCodigo(string $terminalCodigo): int
    {
        $terminalCodigo = trim($terminalCodigo);
        if ($terminalCodigo === '') {
            return 0;
        }

        if (ctype_digit($terminalCodigo)) {
            $n = (int) $terminalCodigo;

            return $n > 0 ? $n : 0;
        }

        return (int) sprintf('%u', crc32($terminalCodigo)) % 2000000000;
    }
}
