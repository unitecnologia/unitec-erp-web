<?php

namespace App\Support\Erp\Audit;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Helpers compactos para logs essenciais (diff / snapshot / redação).
 * Não grava sozinho — callers usam ErpOperacaoLogService dentro da transação.
 */
final class ErpOperacaoAuditoria
{
    /** @var list<string> */
    private const CAMPOS_SENSIVEIS = [
        'password',
        'senha',
        'senha_certificado',
        'proxy_senha',
        'email_senha',
        'email_api_key',
        'nfse_ws_senha',
        'token',
        'csc',
        'id_token',
        'resp_tecnico_csrt',
        'param_consulta_placa_token',
        'param_tef_supertef_softhouse_token',
        'param_meli_access_token',
        'param_meli_refresh_token',
        'cliente_chave',
        'softhouse_token',
        'tef_extra',
        'remember_token',
        'caminho_certificado',
    ];

    /**
     * @param  list<string>  $antes
     * @param  list<string>  $depois
     * @return array{added: list<string>, removed: list<string>}
     */
    public static function diffListas(array $antes, array $depois): array
    {
        $antes = array_values(array_unique($antes));
        $depois = array_values(array_unique($depois));
        sort($antes);
        sort($depois);

        return [
            'added' => array_values(array_diff($depois, $antes)),
            'removed' => array_values(array_diff($antes, $depois)),
        ];
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     * @return array<string, array{de: mixed, para: mixed}>
     */
    public static function diffCampos(array $antes, array $depois, int $maxCampos = 40): array
    {
        $keys = array_values(array_unique([...array_keys($antes), ...array_keys($depois)]));
        sort($keys);

        $mudancas = [];
        foreach ($keys as $key) {
            if (self::ehSensivel($key)) {
                $de = array_key_exists($key, $antes) ? self::mascaraValor($antes[$key]) : null;
                $para = array_key_exists($key, $depois) ? self::mascaraValor($depois[$key]) : null;
                if ($de !== $para) {
                    $mudancas[$key] = ['de' => $de, 'para' => $para];
                }

                continue;
            }

            $de = $antes[$key] ?? null;
            $para = $depois[$key] ?? null;

            if (self::valoresIguais($de, $para)) {
                continue;
            }

            $mudancas[$key] = [
                'de' => self::normalizarValor($de),
                'para' => self::normalizarValor($para),
            ];

            if (count($mudancas) >= $maxCampos) {
                break;
            }
        }

        return $mudancas;
    }

    public static function ehSensivel(string $campo): bool
    {
        $campo = mb_strtolower($campo, 'UTF-8');

        foreach (self::CAMPOS_SENSIVEIS as $sensivel) {
            if ($campo === $sensivel || str_contains($campo, $sensivel)) {
                return true;
            }
        }

        return str_contains($campo, 'senha')
            || str_contains($campo, 'password')
            || str_contains($campo, 'token')
            || str_contains($campo, 'secret')
            || str_contains($campo, 'pfx')
            || str_contains($campo, 'certificado') && str_contains($campo, 'senha');
    }

    public static function mascaraValor(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '(vazio)';
        }

        return '(alterado)';
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<string>  $campos
     * @return array<string, mixed>
     */
    public static function somente(array $attrs, array $campos): array
    {
        $out = [];
        foreach ($campos as $campo) {
            if (array_key_exists($campo, $attrs)) {
                $out[$campo] = self::ehSensivel($campo)
                    ? self::mascaraValor($attrs[$campo])
                    : self::normalizarValor($attrs[$campo]);
            }
        }

        return $out;
    }

    public static function atorAtual(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Campos da empresa relevantes para rastreabilidade (sem secrets).
     *
     * @return list<string>
     */
    public static function camposEmpresaCriticos(): array
    {
        return [
            'codigo', 'razao_social', 'fantasia', 'cnpj', 'ativo', 'tipo_atividade',
            'param_modulo_pdv', 'param_modulo_ordens_servico', 'param_modulo_logistica',
            'param_modulo_rh', 'param_modulo_mercado_livre',
            'param_pdv_checar_limite_cliente', 'param_pdv_habilitar_desconto',
            'param_pdv_permitir_desconto_item', 'param_bloquear_estoque_negativo',
            'param_pdv_atalho_concluir_venda', 'param_financeiro_aguarde',
        ];
    }

    /**
     * Campos fiscais auditáveis (valores secretos são mascarados no diff).
     *
     * @return list<string>
     */
    public static function camposConfigFiscais(): array
    {
        return [
            'uf', 'ambiente', 'numero_nfe', 'serie_nfe', 'aguardar', 'intervalo', 'tentativas',
            'id_token', 'token', 'versao_qrcode', 'dfe_ultimo_nsu',
            'nfse_provedor', 'nfse_ambiente', 'nfse_serie_dps', 'nfse_proximo_dps',
            'nfse_serie_rps', 'nfse_proximo_rps', 'nfse_tipo_rps', 'nfse_aliquota_iss',
            'nfse_ws_usuario', 'nfse_ws_senha', 'nfse_url_producao', 'nfse_url_homologacao',
            'senha_certificado', 'proxy_senha', 'email_senha', 'email_api_key',
            'caminho_certificado', 'email_api_provedor',
        ];
    }

    /**
     * @return list<string>
     */
    public static function camposTerminal(): array
    {
        return [
            'nome', 'empresa_id', 'ativo', 'eh_caixa', 'ip', 'porta', 'modelo',
            'numero_loja', 'numero_logico_terminal', 'serie_nfce', 'usa_tef',
            'categoria_licenca', 'velocidade', 'exibe_f5',
        ];
    }

    private static function valoresIguais(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (string) $a === (string) $b;
        }

        return self::normalizarValor($a) === self::normalizarValor($b);
    }

    private static function normalizarValor(mixed $valor): mixed
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (is_bool($valor)) {
            return $valor;
        }

        if (is_scalar($valor) || $valor === null) {
            return $valor;
        }

        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }

        return (string) $valor;
    }
}
