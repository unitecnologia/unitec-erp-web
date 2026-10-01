<?php

namespace App\Support\Erp;

use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CepLookupService
{
    /**
     * @return array{cep: string, endereco: string, bairro: string, cidade_nome: string, uf: string, cidade_codigo: string}
     */
    public function lookup(string $cep): array
    {
        $digits = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($digits) !== 8) {
            throw new RuntimeException('Informe um CEP completo com 8 dígitos.');
        }

        try {
            $response = $this->http()->get("https://viacep.com.br/ws/{$digits}/json/");
        } catch (\Throwable) {
            return $this->lookupFromAwesomeApi($digits, viaCepHttpError: false);
        }

        if (! $response->ok()) {
            return $this->lookupFromAwesomeApi($digits, viaCepHttpError: true);
        }

        $data = $response->json();

        if (! is_array($data) || ($data['erro'] ?? false)) {
            throw new RuntimeException('CEP não encontrado.');
        }

        $cidadeCodigo = preg_replace('/\D/', '', (string) ($data['ibge'] ?? '')) ?? '';

        if (! self::isValidIbgeCode($cidadeCodigo)) {
            throw new RuntimeException('CEP encontrado, mas o código IBGE do município não foi retornado.');
        }

        return [
            'cep' => $this->formatCep($digits),
            'endereco' => mb_strtoupper((string) ($data['logradouro'] ?? ''), 'UTF-8'),
            'bairro' => mb_strtoupper((string) ($data['bairro'] ?? ''), 'UTF-8'),
            'cidade_nome' => mb_strtoupper((string) ($data['localidade'] ?? ''), 'UTF-8'),
            'uf' => mb_strtoupper((string) ($data['uf'] ?? ''), 'UTF-8'),
            'cidade_codigo' => $cidadeCodigo,
        ];
    }

    public static function isValidIbgeCode(?string $code): bool
    {
        $digits = preg_replace('/\D/', '', (string) $code) ?? '';

        return strlen($digits) === 7;
    }

    /**
     * HTTP de saída com CA bundle explícito (FrankenPHP/Windows não herda curl.cainfo).
     */
    protected function http(): PendingRequest
    {
        return Http::withOptions(LicencaHttpClient::options())
            ->timeout(6)
            ->connectTimeout(4)
            ->acceptJson();
    }

    /**
     * @return array{cep: string, endereco: string, bairro: string, cidade_nome: string, uf: string, cidade_codigo: string}
     */
    protected function lookupFromAwesomeApi(string $digits, bool $viaCepHttpError): array
    {
        try {
            $response = $this->http()->get("https://cep.awesomeapi.com.br/json/{$digits}");
        } catch (\Throwable) {
            throw new RuntimeException('Não foi possível consultar o CEP. Verifique a conexão e tente novamente.');
        }

        if (! $response->ok()) {
            throw new RuntimeException(
                $viaCepHttpError
                    ? 'Serviço de CEP indisponível no momento. Tente novamente.'
                    : 'Não foi possível consultar o CEP. Verifique a conexão e tente novamente.'
            );
        }

        $data = $response->json();

        if (! is_array($data) || ($data['erro'] ?? false)) {
            throw new RuntimeException(
                $viaCepHttpError
                    ? 'Serviço de CEP indisponível no momento. Tente novamente.'
                    : 'Não foi possível consultar o CEP. Verifique a conexão e tente novamente.'
            );
        }

        $cidadeCodigo = preg_replace('/\D/', '', (string) ($data['city_ibge'] ?? '')) ?? '';

        if (! self::isValidIbgeCode($cidadeCodigo)) {
            throw new RuntimeException('CEP encontrado, mas o código IBGE do município não foi retornado.');
        }

        return [
            'cep' => $this->formatCep($digits),
            'endereco' => mb_strtoupper((string) ($data['address'] ?? ''), 'UTF-8'),
            'bairro' => mb_strtoupper((string) ($data['district'] ?? ''), 'UTF-8'),
            'cidade_nome' => mb_strtoupper((string) ($data['city'] ?? ''), 'UTF-8'),
            'uf' => mb_strtoupper((string) ($data['state'] ?? ''), 'UTF-8'),
            'cidade_codigo' => $cidadeCodigo,
        ];
    }

    protected function formatCep(string $digits): string
    {
        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
    }
}
