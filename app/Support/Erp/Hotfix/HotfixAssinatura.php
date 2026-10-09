<?php

namespace App\Support\Erp\Hotfix;

/**
 * Autenticidade do pacote: RSA-SHA256 via ext-openssl (presente no PHP distribuído; não depende de sodium).
 *
 * A linha "sig=" do Unitec-ERP-Hotfix.zip.sha256 assina versão + SHA256 + tamanho do ZIP,
 * e o ZIP (com o hotfix.json) só é aceito se bater com esse hash. A chave privada fica
 * fora do repositório, no PC que gera os pacotes (scripts/criar-hotfix.ps1).
 *
 * Sem chave pública configurada, nenhum hotfix é aceito.
 */
final class HotfixAssinatura
{
    /** Mais de uma chave permite rotação: publique com a nova só depois que os clientes tiverem as duas. */
    private const CHAVES_PUBLICAS = [
        <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAlBExv8u7AHI232reWath
pZAXrS+QGmex3B9BcPwtN4QTrILuguuk1v+2yeOXLYulkin1hCfKwiH6J3UUzFay
3e0IOm9vAYAHm+R5H5ID1oLfcbiEGiSiwigs3nn7lFSO3DMec42Q2bZVga89NfId
o2zCGFtMLZgfL756xt/d5PqxUVACrpcvtedtNYl4zeRCyFHWAudikOCY8WptxUad
3vkCGQHVucU7XcCPJC5V2ARCTX31OTVTm16dSnV3qjRTiWuIuhiuQe362QxEaWVR
QnMcZeVGv92PZFHTPdut+G8F923W9V0+UGohB+VyahIb7v/8PetMDjVIMJQ+FWDg
jL8DWVhZtUSiJifbMHrBfQo+NZd2tzxBgyscxtS96Pr+knfDbzdzQw8KaAm5ZGW1
GUQZBPcCN0QwYxTqmDL/s6VrA58C3BhNLe1/dayZ0UGy2KEDtvjfJcBVQbzgg3bE
fBcq8uHoSf0SLStBhzZYqmiqxmwB99KfYLZNm0hDn/ZZAgMBAAE=
-----END PUBLIC KEY-----
PEM,
    ];

    public static function mensagem(string $versao, string $sha256, int $size): string
    {
        return "unitec-hotfix-v1\nversao={$versao}\nsha256=".strtolower($sha256)."\nsize={$size}\n";
    }

    public static function configurada(): bool
    {
        return self::CHAVES_PUBLICAS !== [] && function_exists('openssl_verify');
    }

    public static function valida(string $versao, string $sha256, int $size, string $assinaturaBase64): bool
    {
        if (! self::configurada()) {
            return false;
        }

        $assinatura = base64_decode(trim($assinaturaBase64), true);

        if ($assinatura === false || $assinatura === '') {
            return false;
        }

        $mensagem = self::mensagem($versao, $sha256, $size);

        foreach (self::CHAVES_PUBLICAS as $pem) {
            $chave = openssl_pkey_get_public($pem);

            if ($chave !== false && openssl_verify($mensagem, $assinatura, $chave, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }
}
