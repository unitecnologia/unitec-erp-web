<?php

namespace App\Support\ContadorCloud;

use App\Models\Contador;
use App\Models\Empresa;
use App\Support\Erp\DocumentoBrasileiroValidator;
use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Http\Client\Response;

final class ContadorCloudPairingClient
{
    /**
     * Vínculo automático direto no portal:
     * POST https://unitecnologiasc.com.br/api/portal/vinculos/auto
     *
     * @return array{ok: bool, message: string, data: ?array, code: ?string, http_status: ?int}
     */
    public function vincularAutomatico(
        Empresa $empresa,
        Contador $contador,
        ?string $portalBaseUrl = null,
    ): array {
        $secret = trim((string) config('contador-cloud.auto_vinculo_secret', ''));

        if ($secret === '') {
            return $this->fail(
                'auto_secret_ausente',
                'Integração com o Portal do Contador indisponível (configuração interna). Contate o suporte Unitec.',
                503,
            );
        }

        $cnpjContador = $this->formatCnpj((string) $contador->cnpj_cpf);

        if ($cnpjContador === null) {
            $digits = DocumentoBrasileiroValidator::digits((string) $contador->cnpj_cpf);
            $message = strlen($digits) === 11
                ? 'Informe o CNPJ do escritório contábil. CPF não é aceito no Portal do Contador.'
                : 'CNPJ do escritório contábil inválido. Confira os dígitos verificadores.';

            return $this->fail('cnpj_contador_invalido', $message, 400);
        }

        $payload = $this->buildEmpresaPayload($empresa);
        $payload['cnpjContador'] = $cnpjContador;
        $payload['contadorCnpj'] = $cnpjContador;
        $payload['nomeContador'] = mb_strtoupper(trim((string) $contador->nome), 'UTF-8');
        $payload['contadorNome'] = $payload['nomeContador'];

        if (filled($contador->email)) {
            $email = trim((string) $contador->email);
            $payload['emailContador'] = $email;
            $payload['contadorEmail'] = $email;
        }

        $endpoint = ContadorCloudHttpHelper::pairingAutoUrl((string) ($portalBaseUrl ?? ''));
        $timeout = (int) config('contador-cloud.default_timeout', 30);

        try {
            $response = LicencaHttpClient::make()
                ->timeout($timeout)
                ->withToken($secret)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            return $this->interpretVinculoResponse($response);
        } catch (\Throwable $exception) {
            return $this->fail(
                'exception',
                'Não foi possível contactar o portal: '.$exception->getMessage(),
                null,
            );
        }
    }

    /**
     * @return array{ok: bool, message: string, data: ?array, code: ?string, http_status: ?int}
     */
    private function interpretVinculoResponse(Response $response): array
    {
        $status = $response->status();
        $json = ContadorCloudHttpHelper::isJsonApiResponse($response) ? $response->json() : null;
        $errorCode = is_array($json) ? (string) ($json['error'] ?? $json['code'] ?? '') : '';

        if (in_array($status, [200, 201], true) && is_array($json)) {
            $credenciais = is_array($json['credenciais'] ?? null)
                ? $json['credenciais']
                : (is_array($json['data']['credenciais'] ?? null) ? $json['data']['credenciais'] : null);

            if ($credenciais === null) {
                return $this->fail('sem_credenciais', 'Portal não devolveu credenciais no vínculo automático.', $status, is_array($json) ? $json : null);
            }

            $json['status'] = 'authorized';
            $json['credenciais'] = $credenciais;

            if (blank($credenciais['token'] ?? null)) {
                return $this->fail(
                    'sem_token',
                    'Portal respondeu sem token (possível falha após criar o vínculo, ex.: SMTP). Não considere conectado — tente novamente depois.',
                    $status,
                    $json,
                );
            }

            if (blank($credenciais['empresaId'] ?? null)) {
                return $this->fail('sem_empresa_id', 'Portal autorizou, mas não enviou o ID da empresa na nuvem.', $status, $json);
            }

            return [
                'ok' => true,
                'message' => 'Empresa vinculada automaticamente ao Portal do Contador.',
                'data' => $json,
                'code' => 'authorized',
                'http_status' => $status,
            ];
        }

        if ($status === 400) {
            $details = $this->formatValidationDetails($json);

            return $this->fail(
                $errorCode !== '' ? $errorCode : 'validacao',
                'Pendências cadastrais no vínculo'
                    .($details !== '' ? ': '.$details : '. Verifique nome, e-mail e CNPJ do contador.'),
                $status,
                is_array($json) ? $json : null,
            );
        }

        if (in_array($status, [401, 403], true)) {
            return $this->fail(
                'auto_secret_rejeitado',
                'Falha na autenticação da integração com o portal. Contate o suporte Unitec.',
                $status,
                is_array($json) ? $json : null,
            );
        }

        if ($status === 409) {
            return $this->fail(
                $errorCode !== '' ? $errorCode : 'conflito',
                'Contador ou empresa inativa no portal, ou empresa já vinculada a outro escritório. '
                    .$this->extractErrorMessage($json),
                $status,
                is_array($json) ? $json : null,
            );
        }

        if ($status === 503 || $status >= 500) {
            return $this->fail(
                $errorCode !== '' ? $errorCode : 'indisponivel',
                'Portal indisponível ou configuração pendente (ex.: SMTP). '
                    .'Se o contador acabou de ser criado, o vínculo pode existir sem token — tente novamente após a correção. '
                    .$this->extractErrorMessage($json),
                $status,
                is_array($json) ? $json : null,
            );
        }

        if ($status === 404 || ! ContadorCloudHttpHelper::isJsonApiResponse($response)) {
            return $this->fail(
                'auto_indisponivel',
                'Portal ainda não disponibilizou o vínculo automático nesta URL.',
                $status,
            );
        }

        $error = $this->extractErrorMessage($json);

        return $this->fail(
            $errorCode !== '' ? $errorCode : 'http_'.$status,
            $error !== ''
                ? 'Portal respondeu: '.$error
                : 'Portal respondeu com status '.$status.'.',
            $status,
            is_array($json) ? $json : null,
        );
    }

    /**
     * @return array{ok: bool, message: string, data: ?array, code: ?string, http_status: ?int}
     */
    private function fail(string $code, string $message, ?int $httpStatus, ?array $data = null): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'data' => $data,
            'code' => $code,
            'http_status' => $httpStatus,
        ];
    }

    /**
     * @param  mixed  $json
     */
    private function extractErrorMessage(mixed $json): string
    {
        if (! is_array($json)) {
            return '';
        }

        return trim((string) ($json['error'] ?? $json['message'] ?? ''));
    }

    /**
     * @param  mixed  $json
     */
    private function formatValidationDetails(mixed $json): string
    {
        if (! is_array($json)) {
            return $this->extractErrorMessage($json);
        }

        $parts = [];

        if (is_array($json['details'] ?? null)) {
            foreach ($json['details'] as $detail) {
                if (! is_array($detail)) {
                    continue;
                }

                $path = is_array($detail['path'] ?? null) ? implode('.', $detail['path']) : (string) ($detail['path'] ?? '');
                $msg = trim((string) ($detail['message'] ?? ''));

                if ($msg === '') {
                    continue;
                }

                $parts[] = $path !== '' ? $path.': '.$msg : $msg;
            }
        }

        $base = $this->extractErrorMessage($json);

        if ($base !== '') {
            array_unshift($parts, $base);
        }

        return implode('; ', array_unique($parts));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmpresaPayload(Empresa $empresa): array
    {
        return [
            'cnpj' => $this->formatCnpj((string) $empresa->cnpj) ?? $this->onlyDigits((string) $empresa->cnpj),
            'razaoSocial' => mb_strtoupper(trim((string) ($empresa->razao_social ?: $empresa->nome)), 'UTF-8'),
            'nomeFantasia' => mb_strtoupper(trim((string) ($empresa->fantasia ?: $empresa->nome)), 'UTF-8'),
            'ie' => trim((string) ($empresa->ie ?? '')),
            'email' => trim((string) ($empresa->email ?? '')),
            'cidade' => trim((string) ($empresa->cidade ?? '')),
            'uf' => strtoupper(trim((string) ($empresa->uf ?? ''))),
            'erpOrigem' => 'unitec-erp-web',
            'erpEmpresaId' => (string) $empresa->getKey(),
            'sistemaContabil' => trim((string) ($empresa->param_portal_contador_sistema_contabil ?? '')),
        ];
    }

    /**
     * Aceita somente CNPJ válido (tamanho + dígitos verificadores). CPF retorna null.
     */
    private function formatCnpj(string $value): ?string
    {
        $digits = DocumentoBrasileiroValidator::digits($value);

        if (! DocumentoBrasileiroValidator::isValidCnpj($digits)) {
            return null;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: null;
    }

    private function onlyDigits(string $value): string
    {
        return DocumentoBrasileiroValidator::digits($value);
    }
}
