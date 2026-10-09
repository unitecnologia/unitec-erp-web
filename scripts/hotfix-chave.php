<?php

/*
 * Chave de assinatura dos hotfixes (somente no PC que gera os pacotes).
 *
 *   php scripts/hotfix-chave.php gerar   <arquivo-privado.pem>
 *       Cria o par RSA-3072 (não sobrescreve) e imprime a chave PÚBLICA para colar em
 *       app/Support/Erp/Hotfix/HotfixAssinatura.php (CHAVES_PUBLICAS).
 *
 *   php scripts/hotfix-chave.php publica <arquivo-privado.pem>
 *   php scripts/hotfix-chave.php assinar <arquivo-privado.pem> <versao> <sha256> <size>
 *       Imprime a assinatura base64 (linha "sig=" do .sha256).
 *
 * A chave privada nunca vai para o repositório, para o pacote oficial nem para o cliente.
 */

require __DIR__.'/../app/Support/Erp/Hotfix/HotfixAssinatura.php';

use App\Support\Erp\Hotfix\HotfixAssinatura;

function falhar(string $mensagem): never
{
    fwrite(STDERR, $mensagem.PHP_EOL);
    exit(1);
}

function opensslConfig(): array
{
    foreach ([getenv('OPENSSL_CONF') ?: '', __DIR__.'/../tools/php/extras/ssl/openssl.cnf', dirname(PHP_BINARY).'/extras/ssl/openssl.cnf'] as $cnf) {
        if ($cnf !== '' && is_file($cnf)) {
            return ['config' => $cnf];
        }
    }

    return [];
}

function chavePrivada(string $arquivo)
{
    $chave = is_file($arquivo) ? openssl_pkey_get_private((string) file_get_contents($arquivo)) : false;

    if ($chave === false) {
        falhar('Chave privada inválida ou inexistente: '.$arquivo);
    }

    return $chave;
}

if (! extension_loaded('openssl')) {
    falhar('Extensão openssl não carregada.');
}

$acao = $argv[1] ?? '';
$arquivo = $argv[2] ?? '';

if ($arquivo === '') {
    falhar('Uso: php scripts/hotfix-chave.php gerar|publica|assinar <arquivo-privado.pem> [...]');
}

switch ($acao) {
    case 'gerar':
        if (file_exists($arquivo)) {
            falhar('Já existe: '.$arquivo.' (não sobrescrevo).');
        }

        $config = opensslConfig() + ['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
        $chave = openssl_pkey_new($config);

        if ($chave === false || ! openssl_pkey_export($chave, $pem, null, opensslConfig())) {
            falhar('Falha ao gerar a chave: '.openssl_error_string());
        }

        if (! is_dir(dirname($arquivo))) {
            mkdir(dirname($arquivo), 0700, true);
        }

        file_put_contents($arquivo, $pem);
        echo openssl_pkey_get_details($chave)['key'];
        break;

    case 'publica':
        echo openssl_pkey_get_details(chavePrivada($arquivo))['key'];
        break;

    case 'assinar':
        [$versao, $sha256, $size] = [$argv[3] ?? '', strtolower($argv[4] ?? ''), (int) ($argv[5] ?? 0)];

        if ($versao === '' || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1 || $size <= 0) {
            falhar('Uso: assinar <arquivo-privado.pem> <versao> <sha256> <size>');
        }

        if (! openssl_sign(HotfixAssinatura::mensagem($versao, $sha256, $size), $assinatura, chavePrivada($arquivo), OPENSSL_ALGO_SHA256)) {
            falhar('Falha ao assinar: '.openssl_error_string());
        }

        echo base64_encode($assinatura);
        break;

    default:
        falhar('Ação desconhecida: '.$acao);
}
