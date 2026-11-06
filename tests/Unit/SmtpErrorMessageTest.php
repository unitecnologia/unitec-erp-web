<?php

namespace Tests\Unit;

use App\Support\Erp\Mail\SmtpErrorMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;

class SmtpErrorMessageTest extends TestCase
{
    public function test_maps_450_4_1_2_to_check_contador_email(): void
    {
        $exception = new TransportException(
            'Expected response code "250" but got code "450", with message "450 4.1.2 : Recipient address rejected: Domain not found".'
        );

        $message = SmtpErrorMessage::forClient($exception);

        $this->assertStringContainsString('Confira o e-mail do contador', $message);
        $this->assertStringContainsString('tente novamente mais tarde', $message);
        $this->assertStringContainsString('Código SMTP: 450 4.1.2', $message);
        $this->assertStringNotContainsString('Expected response code', $message);
        $this->assertStringNotContainsString('Recipient address rejected', $message);
        $this->assertStringNotContainsString('TransportException', $message);
    }

    public function test_maps_known_smtp_codes(): void
    {
        $cases = [
            [
                'Expected response code "250" but got code "550", with message "550 5.1.1 User unknown".',
                'não foi encontrado',
                '550 5.1.1',
            ],
            [
                'Expected response code "250" but got code "550", with message "550 5.1.2 Domain not found".',
                'domínio do e-mail do contador',
                '550 5.1.2',
            ],
            [
                'Expected response code "235" but got code "535", with message "535 5.7.8 Error: authentication failed".',
                'autenticação',
                '535 5.7.8',
            ],
            [
                'Expected response code "250" but got code "421", with message "421 Service not available".',
                'temporariamente indisponível',
                '421',
            ],
            [
                'Expected response code "250" but got code "452", with message "452 Insufficient system storage".',
                'sem espaço',
                '452',
            ],
            [
                'Expected response code "250" but got code "552", with message "552 Mailbox full".',
                'está cheia',
                '552',
            ],
            [
                'Expected response code "250" but got code "553", with message "553 mailbox name not allowed".',
                'foi recusado',
                '553',
            ],
            [
                'Expected response code "250" but got code "554", with message "554 5.7.1 Message rejected".',
                'política de segurança',
                '554 5.7.1',
            ],
            [
                'Expected response code "250" but got code "451", with message "451 Local error in processing".',
                'erro temporário',
                '451',
            ],
            [
                'Expected response code "250" but got code "530", with message "530 Authentication required".',
                'exige autenticação',
                '530',
            ],
            [
                'Expected response code "250" but got code "450", with message "450 Try again later".',
                'recusou o destinatário temporariamente',
                '450',
            ],
        ];

        foreach ($cases as [$raw, $expectedSnippet, $expectedCode]) {
            $message = SmtpErrorMessage::forClient(new TransportException($raw));

            $this->assertStringContainsStringIgnoringCase($expectedSnippet, $message, $raw);
            $this->assertStringContainsString('Código SMTP: '.$expectedCode, $message, $raw);
            $this->assertStringNotContainsString('Expected response code', $message, $raw);
        }
    }

    public function test_unknown_smtp_code_is_generic_and_keeps_code(): void
    {
        $exception = new TransportException(
            'Expected response code "250" but got code "418", with message "418 I\'m a teapot".'
        );

        $message = SmtpErrorMessage::forClient($exception);

        $this->assertStringContainsString('Não foi possível enviar o e-mail ao contador', $message);
        $this->assertStringContainsString('Código SMTP: 418', $message);
        $this->assertStringNotContainsString('teapot', $message);
        $this->assertStringNotContainsString('Expected response code', $message);
    }

    public function test_connection_timeout_without_smtp_code(): void
    {
        $exception = new TransportException('Connection to "smtp.example.com" timed out.');

        $message = SmtpErrorMessage::forClient($exception);

        $this->assertStringContainsString('não respondeu a tempo', $message);
        $this->assertStringNotContainsString('smtp.example.com', $message);
        $this->assertStringNotContainsString('timed out', $message);
    }

    public function test_connection_refused_without_smtp_code(): void
    {
        $exception = new TransportException('Connection could not be established with host "smtp.example.com": Connection refused');

        $message = SmtpErrorMessage::forClient($exception);

        $this->assertStringContainsString('Não foi possível conectar ao servidor de e-mail', $message);
        $this->assertStringNotContainsString('smtp.example.com', $message);
        $this->assertStringNotContainsString('Connection could not be established', $message);
    }

    public function test_unknown_non_smtp_exception_is_generic_and_hides_raw_message(): void
    {
        $exception = new RuntimeException('SQLSTATE[HY000]: Symfony\\Component\\Mailer internals leaked');

        $message = SmtpErrorMessage::forClient($exception);

        $this->assertSame(
            'Não foi possível enviar o e-mail ao contador. Tente novamente. Se o problema continuar, verifique as configurações de e-mail.',
            $message
        );
        $this->assertStringNotContainsString('SQLSTATE', $message);
        $this->assertStringNotContainsString('Symfony', $message);
    }

    public function test_does_not_claim_send_succeeded(): void
    {
        $message = SmtpErrorMessage::forClient(new TransportException(
            'Expected response code "250" but got code "450", with message "450 4.1.2 Domain not found".'
        ));

        $this->assertStringNotContainsStringIgnoringCase('enviado', $message);
        $this->assertStringNotContainsStringIgnoringCase('sucesso', $message);
        $this->assertStringNotContainsStringIgnoringCase('concluído', $message);
    }
}
