<?php

namespace App\Support\Erp;

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
            $response = Http::timeout(6)->get("https://viacep.com.br/ws/{$digits}/json/");
        } catch (\Throwable) {
            throw new RuntimeException('Não foi possível consultar o CEP. Verifique a conexão e tente novamente.');
        }

        if (! $response->ok()) {
            throw new RuntimeException('Serviço de CEP indisponível no momento. Tente novamente.');
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

    protected function formatCep(string $digits): string
    {
        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
    }
}
