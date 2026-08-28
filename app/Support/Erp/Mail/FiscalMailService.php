<?php

namespace App\Support\Erp\Mail;

use App\Mail\OrcamentoEmail;
use App\Models\Empresa;
use App\Models\VendasParametro;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FiscalMailService
{
    public const MODO_SMTP = 'smtp';

    public const MODO_API = 'api';

    public const API_BREVO = 'brevo';

    /**
     * @return array<string, string>
     */
    public static function apiProviderLabels(): array
    {
        return [
            self::API_BREVO => 'Brevo',
        ];
    }

    public static function normalizeModo(string $modo): string
    {
        return $modo === self::MODO_API ? self::MODO_API : self::MODO_SMTP;
    }

    public static function normalizeApiProvider(string $provider): string
    {
        return array_key_exists($provider, self::apiProviderLabels())
            ? $provider
            : self::API_BREVO;
    }

    /**
     * @param  list<array{path: string, name: string}>  $fileAttachments
     */
    public static function sendForEmpresa(
        int $empresaId,
        string $to,
        string $messageBody,
        string $subjectLine,
        array $fileAttachments = [],
        ?string $fromAddress = null,
        ?string $fromName = null,
    ): void {
        $params = VendasParametro::forEmpresa($empresaId);
        $form = NfeFiscalConfig::toFormArray($params);
        $empresa = Empresa::query()->find($empresaId);
        $modo = self::normalizeModo((string) ($form['email_modo'] ?? self::MODO_SMTP));

        // No SMTP o remetente deve ser o usuário autenticado no servidor.
        if ($modo === self::MODO_SMTP) {
            $smtpUser = trim((string) ($form['email_user'] ?? ''));
            if (filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
                $fromAddress = $smtpUser;
            }
        }

        $resolvedFromAddress = $fromAddress ?: self::resolveSenderEmail($form, $empresa);
        $resolvedFromName = trim((string) ($fromName ?: $empresa?->nome ?: config('mail.from.name', 'Uni Sistemas')));

        self::send(
            $to,
            new OrcamentoEmail(
                messageBody: $messageBody,
                subjectLine: $subjectLine,
                fileAttachments: $fileAttachments,
                fromAddress: $resolvedFromAddress,
                fromName: $resolvedFromName,
            ),
            $form,
            $params,
            $resolvedFromName,
        );
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public static function send(string $to, OrcamentoEmail $mailable, array $form, VendasParametro $params, ?string $fromName = null): void
    {
        if (self::normalizeModo((string) ($form['email_modo'] ?? self::MODO_SMTP)) === self::MODO_API) {
            self::sendViaApi($to, $mailable, $form, $params, $fromName);

            return;
        }

        NfeFiscalConfig::applySmtpMailConfig($form, $params);

        if (blank($mailable->fromAddress)) {
            $fromUser = trim((string) ($form['email_user'] ?? ''));
            if (filter_var($fromUser, FILTER_VALIDATE_EMAIL)) {
                $mailable->fromAddress = $fromUser;
            }
        }

        Mail::to($to)->send($mailable);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array{ok: bool, message: string}
     */
    public static function testEmail(array $form, VendasParametro $params, string $to, ?Empresa $empresa = null): array
    {
        $to = trim($to);

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Informe um e-mail de destino válido.'];
        }

        if (self::normalizeModo((string) ($form['email_modo'] ?? self::MODO_SMTP)) === self::MODO_API) {
            return self::testApiEmail($form, $params, $to, $empresa);
        }

        return NfeFiscalConfig::testSmtpEmail($form, $params, $to, $empresa?->nome);
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public static function resolveSenderEmail(array $form, ?Empresa $empresa): string
    {
        $configured = trim((string) ($form['email_user'] ?? ''));

        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $empresaEmail = trim((string) ($empresa?->email ?? ''));

        if ($empresaEmail !== '' && filter_var($empresaEmail, FILTER_VALIDATE_EMAIL)) {
            return $empresaEmail;
        }

        throw new \RuntimeException('Configure o e-mail remetente em Empresa → Parâmetros → E-mail ou no cadastro da empresa.');
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public static function resolveApiKey(array $form, VendasParametro $params): ?string
    {
        if (filled($form['email_api_key'] ?? '')) {
            return trim((string) $form['email_api_key']);
        }

        $stored = trim((string) ($params->email_api_key ?? ''));

        return $stored !== '' ? $stored : null;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    protected static function sendViaApi(string $to, OrcamentoEmail $mailable, array $form, VendasParametro $params, ?string $fromName): void
    {
        $apiKey = self::resolveApiKey($form, $params);
        $fromEmail = $mailable->fromAddress ?: self::resolveSenderEmail($form, null);

        if ($apiKey === null || $apiKey === '') {
            throw new \RuntimeException('Configure a API Key em Empresa → Parâmetros → E-mail.');
        }

        $fromName = trim((string) ($mailable->fromName ?: $fromName ?: 'Uni Sistemas'));
        $attachments = self::buildApiAttachments($mailable->fileAttachments);

        match (self::normalizeApiProvider((string) ($form['email_api_provedor'] ?? self::API_BREVO))) {
            self::API_BREVO => self::sendBrevo($apiKey, $fromEmail, $fromName, $to, $mailable->subjectLine, $mailable->messageBody, $attachments),
            default => self::sendBrevo($apiKey, $fromEmail, $fromName, $to, $mailable->subjectLine, $mailable->messageBody, $attachments),
        };
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array{ok: bool, message: string}
     */
    protected static function testApiEmail(array $form, VendasParametro $params, string $to, ?Empresa $empresa): array
    {
        $apiKey = self::resolveApiKey($form, $params);
        $provider = self::normalizeApiProvider((string) ($form['email_api_provedor'] ?? self::API_BREVO));

        if ($apiKey === null || $apiKey === '') {
            return ['ok' => false, 'message' => 'Informe a API Key ou salve uma chave nas configurações.'];
        }

        try {
            $fromEmail = self::resolveSenderEmail($form, $empresa);
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        $subject = trim((string) ($form['email_assunto'] ?? ''));

        if ($subject === '') {
            $subject = 'Teste de e-mail — Uni Sistemas';
        }

        $body = "Este é um e-mail de teste enviado pelas configurações de E-mail da Empresa no Uni Sistemas.\n\n"
            .'Provedor: '.(self::apiProviderLabels()[$provider] ?? $provider)."\n"
            ."Remetente: {$fromEmail}\n"
            .'Data/hora: '.now()->format('d/m/Y H:i:s');

        try {
            self::sendBrevo(
                $apiKey,
                $fromEmail,
                $empresa?->nome ?: 'Uni Sistemas',
                $to,
                $subject,
                $body,
                [],
            );

            return ['ok' => true, 'message' => "E-mail de teste enviado para {$to} via API Brevo."];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Falha ao enviar: '.$exception->getMessage()];
        }
    }

    /**
     * @param  list<array{filename: string, content: string}>  $attachments
     */
    protected static function sendBrevo(
        string $apiKey,
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $body,
        array $attachments,
    ): void {
        $payload = [
            'sender' => [
                'name' => $fromName,
                'email' => $fromEmail,
            ],
            'to' => [[
                'email' => $to,
            ]],
            'subject' => $subject,
            'textContent' => $body,
        ];

        if ($attachments !== []) {
            $payload['attachment'] = array_map(
                fn (array $attachment): array => [
                    'name' => $attachment['filename'],
                    'content' => $attachment['content'],
                ],
                $attachments,
            );
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'accept' => 'application/json',
        ])
            ->timeout(30)
            ->post('https://api.brevo.com/v3/smtp/email', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(self::httpErrorMessage($response->json(), $response->body()));
        }
    }

    /**
     * @param  list<array{path: string, name: string}>  $fileAttachments
     * @return list<array{filename: string, content: string}>
     */
    protected static function buildApiAttachments(array $fileAttachments): array
    {
        $attachments = [];

        foreach ($fileAttachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');

            if (! is_file($path)) {
                continue;
            }

            $attachments[] = [
                'filename' => (string) ($attachment['name'] ?? basename($path)),
                'content' => base64_encode((string) file_get_contents($path)),
            ];
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected static function httpErrorMessage(?array $json, string $fallback): string
    {
        if (is_array($json)) {
            $message = trim((string) ($json['message'] ?? $json['error'] ?? ''));

            if ($message !== '') {
                return $message;
            }
        }

        return trim($fallback) !== '' ? trim($fallback) : 'Erro desconhecido na API de e-mail.';
    }
}
