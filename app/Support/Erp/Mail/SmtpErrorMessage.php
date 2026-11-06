<?php

namespace App\Support\Erp\Mail;

use Throwable;

/**
 * Traduz falhas SMTP/Mailer para mensagem amigável ao operador.
 * Detalhes técnicos ficam nos logs (report); o código SMTP entra no texto para diagnóstico.
 */
final class SmtpErrorMessage
{
    public static function forClient(Throwable $exception): string
    {
        $raw = trim($exception->getMessage());
        $code = self::extractSmtpCode($raw);
        $guidance = self::guidanceFor($code, $raw);

        if ($code !== null) {
            return $guidance.' Código SMTP: '.$code.'.';
        }

        return $guidance;
    }

    private static function guidanceFor(?string $code, string $raw): string
    {
        $enhanced = self::enhancedStatus($code);
        $numeric = self::numericStatus($code);
        $haystack = mb_strtolower($raw);

        if ($enhanced === '4.1.2') {
            return 'O servidor de e-mail recusou o destinatário. Confira o e-mail do contador ou tente novamente mais tarde.';
        }

        return match ($enhanced) {
            '5.1.1', '5.1.10' => 'O endereço de e-mail do contador não foi encontrado. Confira o cadastro e tente novamente.',
            '5.1.2', '5.1.3' => 'O domínio do e-mail do contador não foi encontrado. Confira o endereço e tente novamente.',
            '5.1.8' => 'O servidor recusou o remetente. Verifique o e-mail configurado em Empresa → Parâmetros → E-mail.',
            '5.2.1' => 'A caixa de e-mail do contador não está aceitando mensagens. Peça para conferir a conta ou tente mais tarde.',
            '5.2.2', '4.2.2' => 'A caixa de e-mail do contador está cheia. Peça para liberar espaço ou tente mais tarde.',
            '5.3.4', '5.2.3' => 'O pacote é grande demais para o servidor de e-mail. Tente novamente mais tarde ou envie por outro meio.',
            '5.4.1', '4.4.1', '4.4.2' => 'Não foi possível entregar o e-mail neste momento. Tente novamente mais tarde.',
            '5.7.1', '5.7.0' => 'O servidor recusou o envio por política de segurança. Verifique as configurações de e-mail e o destinatário.',
            '5.7.8', '5.7.9' => 'Falha de autenticação no servidor de e-mail. Verifique usuário e senha em Empresa → Parâmetros → E-mail.',
            '4.7.0', '4.7.1' => 'O servidor de e-mail adiou o envio. Tente novamente mais tarde.',
            default => self::guidanceForNumeric($numeric, $haystack),
        };
    }

    private static function guidanceForNumeric(?int $numeric, string $haystack): string
    {
        return match ($numeric) {
            421 => 'O servidor de e-mail está temporariamente indisponível. Tente novamente mais tarde.',
            432, 454 => 'O servidor exigiu autenticação segura e a recusou. Verifique as configurações de e-mail.',
            450 => 'O servidor de e-mail recusou o destinatário temporariamente. Confira o e-mail do contador ou tente novamente mais tarde.',
            451 => 'O servidor de e-mail teve um erro temporário. Tente novamente mais tarde.',
            452 => 'O servidor de e-mail está sem espaço para receber a mensagem. Tente novamente mais tarde.',
            530 => 'O servidor de e-mail exige autenticação. Verifique as configurações em Empresa → Parâmetros → E-mail.',
            535 => 'Falha de autenticação no servidor de e-mail. Verifique usuário e senha em Empresa → Parâmetros → E-mail.',
            550 => 'O servidor recusou o destinatário. Confira o e-mail do contador e tente novamente.',
            551 => 'O servidor não reconheceu o destinatário. Confira o e-mail do contador.',
            552 => 'A caixa de e-mail do contador está cheia. Peça para liberar espaço ou tente mais tarde.',
            553 => 'O endereço de e-mail do contador foi recusado. Confira o cadastro e tente novamente.',
            554 => 'O servidor recusou o envio da mensagem. Verifique o e-mail do contador e as configurações de e-mail.',
            default => self::guidanceFromKeywords($haystack),
        };
    }

    private static function guidanceFromKeywords(string $haystack): string
    {
        if (str_contains($haystack, 'authenticat') || str_contains($haystack, '535')) {
            return 'Falha de autenticação no servidor de e-mail. Verifique usuário e senha em Empresa → Parâmetros → E-mail.';
        }

        if (str_contains($haystack, 'timed out') || str_contains($haystack, 'timeout')) {
            return 'O servidor de e-mail não respondeu a tempo. Tente novamente mais tarde.';
        }

        if (
            str_contains($haystack, 'connection could not be established')
            || str_contains($haystack, 'connection refused')
            || str_contains($haystack, 'could not connect')
            || str_contains($haystack, 'failed to connect')
        ) {
            return 'Não foi possível conectar ao servidor de e-mail. Verifique as configurações em Empresa → Parâmetros → E-mail.';
        }

        if (str_contains($haystack, 'certificate') || str_contains($haystack, 'ssl') || str_contains($haystack, 'tls')) {
            return 'Falha na conexão segura com o servidor de e-mail. Verifique as configurações de criptografia (SSL/TLS).';
        }

        return 'Não foi possível enviar o e-mail ao contador. Tente novamente. Se o problema continuar, verifique as configurações de e-mail.';
    }

    private static function extractSmtpCode(string $raw): ?string
    {
        if (preg_match('/\b(\d{3})\s+(\d\.\d\.\d)\b/', $raw, $match) === 1) {
            return $match[1].' '.$match[2];
        }

        if (preg_match('/got code ["\'](\d{3})["\']/i', $raw, $match) === 1) {
            $numeric = $match[1];
            if (preg_match('/\b(\d\.\d\.\d)\b/', $raw, $enhanced) === 1) {
                return $numeric.' '.$enhanced[1];
            }

            return $numeric;
        }

        if (preg_match('/\b([45]\d{2})\b/', $raw, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private static function enhancedStatus(?string $code): ?string
    {
        if ($code !== null && preg_match('/\b(\d\.\d\.\d)\b/', $code, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private static function numericStatus(?string $code): ?int
    {
        if ($code !== null && preg_match('/\b(\d{3})\b/', $code, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }
}
