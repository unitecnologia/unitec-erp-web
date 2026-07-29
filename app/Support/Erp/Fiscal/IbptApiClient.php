<?php

namespace App\Support\Erp\Fiscal;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class IbptApiClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $cnpj,
        private readonly ?string $baseUrl = null,
        private readonly ?int $timeout = null,
    ) {}

    public static function make(?string $token = null, ?string $cnpj = null): self
    {
        return new self(
            token: trim((string) ($token ?: config('ibpt.token'))),
            cnpj: preg_replace('/\D/', '', (string) $cnpj) ?? '',
        );
    }

    /**
     * Token da empresa (Imposto Padrão) tem prioridade sobre IBPT_TOKEN do .env.
     */
    public static function forEmpresa(?Empresa $empresa): self
    {
        $token = trim((string) ($empresa?->param_imp_ibpt_token ?: ''));

        return self::make(
            token: $token !== '' ? $token : null,
            cnpj: (string) ($empresa?->cnpj ?? ''),
        );
    }

    public function hasCredentials(): bool
    {
        return $this->token !== '' && strlen($this->cnpj) === 14;
    }

    public function tokenMasked(): string
    {
        if ($this->token === '') {
            return '';
        }

        if (strlen($this->token) <= 8) {
            return str_repeat('*', strlen($this->token));
        }

        return substr($this->token, 0, 4).'…'.substr($this->token, -4);
    }

    /**
     * @param  array{
     *     codigo: string,
     *     uf: string,
     *     ex?: int|string,
     *     descricao: string,
     *     unidade_medida: string,
     *     valor: float|int|string,
     *     gtin: string,
     *     codigo_interno?: string
     * }  $params
     * @return array<string, mixed>
     */
    public function produto(array $params): array
    {
        $this->assertCredentials();

        $query = [
            'token' => $this->token,
            'cnpj' => $this->cnpj,
            'codigo' => preg_replace('/\D/', '', (string) $params['codigo']) ?? '',
            'uf' => strtoupper(trim((string) $params['uf'])),
            'ex' => (int) ($params['ex'] ?? 0),
            'descricao' => (string) $params['descricao'],
            'unidadeMedida' => (string) $params['unidade_medida'],
            'valor' => $this->formatValor($params['valor'] ?? 0),
            'gtin' => (string) ($params['gtin'] !== '' ? $params['gtin'] : 'SEM GTIN'),
        ];

        if (filled($params['codigo_interno'] ?? null)) {
            $query['codigoInterno'] = (string) $params['codigo_interno'];
        }

        return $this->get('produtos', $query);
    }

    /**
     * @param  array{
     *     codigo: string,
     *     uf: string,
     *     descricao: string,
     *     unidade_medida: string,
     *     valor: float|int|string
     * }  $params
     * @return array<string, mixed>
     */
    public function servico(array $params): array
    {
        $this->assertCredentials();

        return $this->get('servicos', [
            'token' => $this->token,
            'cnpj' => $this->cnpj,
            'codigo' => preg_replace('/\D/', '', (string) $params['codigo']) ?? '',
            'uf' => strtoupper(trim((string) $params['uf'])),
            'descricao' => (string) $params['descricao'],
            'unidadeMedida' => (string) $params['unidade_medida'],
            'valor' => $this->formatValor($params['valor'] ?? 0),
        ]);
    }

    /**
     * Converte resposta da API no formato de fiscal_ibpt_itens.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function toIbptItem(array $payload): array
    {
        $ncm = preg_replace('/\D/', '', (string) ($payload['Codigo'] ?? $payload['codigo'] ?? '')) ?? '';
        $ex = (string) ($payload['EX'] ?? $payload['Ex'] ?? $payload['ex'] ?? '0');
        $tipo = (string) ($payload['Tipo'] ?? $payload['tipo'] ?? '');
        $versao = (string) ($payload['Versao'] ?? $payload['versao'] ?? '');
        $chave = (string) ($payload['Chave'] ?? $payload['chave'] ?? '');
        $fonte = (string) ($payload['Fonte'] ?? $payload['fonte'] ?? '');
        $descricao = (string) ($payload['Descricao'] ?? $payload['descricao'] ?? '');

        return [
            'ncm' => Str::limit($ncm, 10, ''),
            'ex_tipi' => $ex === '' ? '0' : Str::limit($ex, 4, ''),
            'tipo' => $tipo === '' ? null : Str::limit($tipo, 1, ''),
            'descricao' => $descricao === '' ? null : Str::limit($descricao, 500, ''),
            'aliq_nacional' => $this->toFloat($payload['Nacional'] ?? $payload['nacional'] ?? 0),
            'aliq_importado' => $this->toFloat($payload['Importado'] ?? $payload['importado'] ?? 0),
            'aliq_estadual' => $this->toFloat($payload['Estadual'] ?? $payload['estadual'] ?? 0),
            'aliq_municipal' => $this->toFloat($payload['Municipal'] ?? $payload['municipal'] ?? 0),
            'vigencia_inicio' => $this->parseBrDate((string) ($payload['VigenciaInicio'] ?? $payload['vigenciaInicio'] ?? '')),
            'vigencia_fim' => $this->parseBrDate((string) ($payload['VigenciaFim'] ?? $payload['vigenciaFim'] ?? '')),
            'chave' => $chave === '' ? null : Str::limit($chave, 80, ''),
            'versao' => $versao === '' ? null : Str::limit($versao, 40, ''),
            'fonte' => $fonte === '' ? null : Str::limit($fonte, 80, ''),
        ];
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query): array
    {
        $url = ($this->baseUrl ?: (string) config('ibpt.base_url')).'/'.ltrim($endpoint, '/');
        $timeout = $this->timeout ?: (int) config('ibpt.timeout', 30);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get($url, $query);

        if (! $response->successful()) {
            $body = trim($response->body());
            throw new \RuntimeException(
                'IBPT API HTTP '.$response->status().($body !== '' ? ': '.Str::limit($body, 240) : '')
            );
        }

        $json = $response->json();

        if (is_array($json) && array_is_list($json)) {
            if ($json === []) {
                throw new \RuntimeException('IBPT API retornou lista vazia.');
            }

            $first = $json[0];

            if (! is_array($first)) {
                throw new \RuntimeException('IBPT API retornou formato inesperado.');
            }

            return $first;
        }

        if (! is_array($json)) {
            throw new \RuntimeException('IBPT API retornou formato inesperado.');
        }

        if (isset($json['Message']) || isset($json['message']) || isset($json['erro']) || isset($json['Erro'])) {
            $msg = (string) ($json['Message'] ?? $json['message'] ?? $json['erro'] ?? $json['Erro']);
            throw new \RuntimeException($msg !== '' ? $msg : 'IBPT API retornou erro.');
        }

        return $json;
    }

    private function assertCredentials(): void
    {
        if ($this->token === '') {
            throw new \RuntimeException('Token IBPT não informado.');
        }

        if (strlen($this->cnpj) !== 14) {
            throw new \RuntimeException('CNPJ da empresa inválido para consulta IBPT.');
        }
    }

    private function formatValor(float|int|string $valor): string
    {
        if (is_string($valor)) {
            $valor = str_replace(['.', ','], ['', '.'], preg_replace('/[^\d,.\-]/', '', $valor) ?? '0');
        }

        return number_format((float) $valor, 2, '.', '');
    }

    private function toFloat(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', $value) ?? '0');
        }

        return round((float) $value, 2);
    }

    private function parseBrDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        return null;
    }
}
