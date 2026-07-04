<?php

namespace App\Support\Erp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CnpjLookupService
{
    private const CACHE_TTL_DAYS = 30;

    /**
     * @return array<string, string|null>
     */
    public function fetch(string $cnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            throw new RuntimeException('Informe um CNPJ completo com 14 dígitos.');
        }

        if (! $this->isValidCnpj($cnpj)) {
            throw new RuntimeException('CNPJ inválido.');
        }

        return Cache::remember(
            "erp.cnpj.lookup.{$cnpj}",
            now()->addDays(self::CACHE_TTL_DAYS),
            fn (): array => $this->fetchFromProviders($cnpj),
        );
    }

    /**
     * @return array<string, string|null>
     */
    protected function fetchFromProviders(string $cnpj): array
    {
        $merged = [];

        $merged = $this->mergeFields($merged, $this->fetchOpenCnpj($cnpj));
        $merged = $this->mergeFields($merged, $this->fetchBrasilApi($cnpj));

        if (blank($merged['rg_ie'] ?? null)) {
            $merged = $this->mergeFields($merged, $this->fetchCnpjWs($cnpj));
        }

        if (blank($merged['nome_razao'] ?? null)) {
            throw new RuntimeException('CNPJ não encontrado.');
        }

        return $this->normalizeMappedFields($merged);
    }

    /**
     * @return array<string, string|null>
     */
    protected function fetchOpenCnpj(string $cnpj): array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get("https://api.opencnpj.org/{$cnpj}");
        } catch (RequestException) {
            return [];
        }

        if ($response->status() === 404 || ! $response->successful()) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $this->mapOpenCnpj($payload);
    }

    /**
     * @return array<string, string|null>
     */
    protected function fetchBrasilApi(string $cnpj): array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");
        } catch (RequestException) {
            return [];
        }

        if ($response->status() === 404 || ! $response->successful()) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $this->mapBrasilApi($payload);
    }

    /**
     * @return array<string, string|null>
     */
    protected function fetchCnpjWs(string $cnpj): array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get("https://publica.cnpj.ws/cnpj/{$cnpj}");
        } catch (RequestException) {
            return [];
        }

        if ($response->status() === 404 || ! $response->successful()) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $this->mapCnpjWs($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    protected function mapOpenCnpj(array $data): array
    {
        $logradouro = trim(implode(' ', array_filter([
            $data['tipo_logradouro'] ?? null,
            $data['logradouro'] ?? null,
        ])));

        $phones = collect($data['telefones'] ?? [])
            ->filter(fn (mixed $phone): bool => is_array($phone) && ($phone['is_fax'] ?? false) !== true)
            ->values();

        $mapped = [
            'cpf_cnpj' => $this->formatCnpj((string) ($data['cnpj'] ?? '')),
            'nome_razao' => $this->normalizeRazaoSocial((string) ($data['razao_social'] ?? '')),
            'apelido_fantasia' => Str::upper(trim((string) ($data['nome_fantasia'] ?? ''))),
            'cep' => $this->formatCep((string) ($data['cep'] ?? '')),
            'endereco' => Str::upper($logradouro),
            'numero' => trim((string) ($data['numero'] ?? '')),
            'complemento' => Str::upper(trim((string) ($data['complemento'] ?? ''))),
            'bairro' => Str::upper(trim((string) ($data['bairro'] ?? ''))),
            'cidade_nome' => Str::upper(trim((string) ($data['municipio'] ?? ''))),
            'uf' => Str::upper(trim((string) ($data['uf'] ?? ''))),
            'email' => Str::lower(trim((string) ($data['email'] ?? ''))),
            'fone1' => $this->formatPhoneParts($phones->get(0)),
            'fone2' => $this->formatPhoneParts($phones->get(1)),
        ];

        if (in_array(Str::upper((string) ($data['opcao_simples'] ?? '')), ['S', 'SIM', 'TRUE', '1'], true)) {
            $mapped['regime_tributario'] = 'simples';
        }

        return $this->filterFilledFields($mapped);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    protected function mapBrasilApi(array $data): array
    {
        $logradouro = trim(implode(' ', array_filter([
            $data['descricao_tipo_de_logradouro'] ?? null,
            $data['logradouro'] ?? null,
        ])));

        $mapped = [
            'cpf_cnpj' => $this->formatCnpj((string) ($data['cnpj'] ?? '')),
            'nome_razao' => $this->normalizeRazaoSocial((string) ($data['razao_social'] ?? '')),
            'apelido_fantasia' => Str::upper(trim((string) ($data['nome_fantasia'] ?? ''))),
            'cep' => $this->formatCep((string) ($data['cep'] ?? '')),
            'endereco' => Str::upper($logradouro),
            'numero' => trim((string) ($data['numero'] ?? '')),
            'complemento' => Str::upper(trim((string) ($data['complemento'] ?? ''))),
            'bairro' => Str::upper(trim((string) ($data['bairro'] ?? ''))),
            'cidade_codigo' => (string) ($data['codigo_municipio_ibge'] ?? $data['codigo_municipio'] ?? ''),
            'cidade_nome' => Str::upper(trim((string) ($data['municipio'] ?? ''))),
            'uf' => Str::upper(trim((string) ($data['uf'] ?? ''))),
            'email' => Str::lower(trim((string) ($data['email'] ?? ''))),
            'fone1' => $this->formatApiPhone($data['ddd_telefone_1'] ?? null),
            'fone2' => $this->formatApiPhone($data['ddd_telefone_2'] ?? null),
        ];

        $inscricaoEstadual = $this->extractInscricaoEstadual($data);

        if (filled($inscricaoEstadual)) {
            $mapped['rg_ie'] = $inscricaoEstadual;
        }

        if (($data['opcao_pelo_simples'] ?? false) === true) {
            $mapped['regime_tributario'] = 'simples';
        }

        return $this->filterFilledFields($mapped);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    protected function mapCnpjWs(array $data): array
    {
        /** @var array<string, mixed> $estabelecimento */
        $estabelecimento = $data['estabelecimento'] ?? [];

        $logradouro = trim(implode(' ', array_filter([
            $estabelecimento['tipo_logradouro'] ?? null,
            $estabelecimento['logradouro'] ?? null,
        ])));

        /** @var array<string, mixed>|null $cidade */
        $cidade = $estabelecimento['cidade'] ?? null;

        /** @var array<string, mixed>|null $estado */
        $estado = $estabelecimento['estado'] ?? null;

        $uf = Str::upper(trim((string) ($estado['sigla'] ?? '')));

        $mapped = [
            'cpf_cnpj' => $this->formatCnpj((string) ($estabelecimento['cnpj'] ?? $data['cnpj_raiz'] ?? '')),
            'nome_razao' => $this->normalizeRazaoSocial((string) ($data['razao_social'] ?? '')),
            'apelido_fantasia' => Str::upper(trim((string) ($estabelecimento['nome_fantasia'] ?? ''))),
            'cep' => $this->formatCep((string) ($estabelecimento['cep'] ?? '')),
            'endereco' => Str::upper($logradouro),
            'numero' => trim((string) ($estabelecimento['numero'] ?? '')),
            'complemento' => Str::upper(trim((string) ($estabelecimento['complemento'] ?? ''))),
            'bairro' => Str::upper(trim((string) ($estabelecimento['bairro'] ?? ''))),
            'cidade_codigo' => (string) ($cidade['ibge_id'] ?? ''),
            'cidade_nome' => Str::upper(trim((string) ($cidade['nome'] ?? ''))),
            'uf' => $uf,
            'email' => Str::lower(trim((string) ($estabelecimento['email'] ?? ''))),
            'fone1' => $this->formatPhoneParts([
                'ddd' => $estabelecimento['ddd1'] ?? null,
                'numero' => $estabelecimento['telefone1'] ?? null,
            ]),
            'fone2' => $this->formatPhoneParts([
                'ddd' => $estabelecimento['ddd2'] ?? null,
                'numero' => $estabelecimento['telefone2'] ?? null,
            ]),
            'rg_ie' => $this->extractInscricaoEstadualFromCnpjWs($estabelecimento, $uf),
        ];

        if (($data['simples']['simples'] ?? null) === 'Sim') {
            $mapped['regime_tributario'] = 'simples';
        }

        return $this->filterFilledFields($mapped);
    }

    /**
     * @param  array<string, mixed>  $estabelecimento
     */
    protected function extractInscricaoEstadualFromCnpjWs(array $estabelecimento, string $uf): ?string
    {
        /** @var array<int, array<string, mixed>> $inscricoes */
        $inscricoes = $estabelecimento['inscricoes_estaduais'] ?? [];

        foreach ($inscricoes as $inscricao) {
            if (($inscricao['ativo'] ?? false) !== true) {
                continue;
            }

            /** @var array<string, mixed>|null $estado */
            $estado = $inscricao['estado'] ?? null;
            $sigla = Str::upper(trim((string) ($estado['sigla'] ?? '')));

            if (filled($uf) && filled($sigla) && $sigla !== $uf) {
                continue;
            }

            $value = trim((string) ($inscricao['inscricao_estadual'] ?? ''));

            if (filled($value) && ! in_array(Str::upper($value), ['ISENTO', 'ISENTA', 'N/A', 'NA'], true)) {
                return Str::upper($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $base
     * @param  array<string, string|null>  $incoming
     * @return array<string, string|null>
     */
    protected function mergeFields(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (filled($value) && blank($base[$key] ?? null)) {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param  array<string, string|null>  $mapped
     * @return array<string, string|null>
     */
    protected function normalizeMappedFields(array $mapped): array
    {
        if (filled($mapped['nome_razao'] ?? null)) {
            $mapped['nome_razao'] = $this->normalizeRazaoSocial((string) $mapped['nome_razao']);
        }

        return $this->filterFilledFields($mapped);
    }

    /**
     * @param  array<string, string|null>  $mapped
     * @return array<string, string|null>
     */
    protected function filterFilledFields(array $mapped): array
    {
        return array_map(
            fn (?string $value): ?string => filled($value) ? $value : null,
            $mapped,
        );
    }

    protected function normalizeRazaoSocial(string $razao): string
    {
        $razao = Str::upper(trim($razao));

        $razao = preg_replace('/^\d{2}\.\d{3}\.\d{3}\s+/', '', $razao) ?? $razao;
        $razao = preg_replace('/^\d{8}\s+/', '', $razao) ?? $razao;

        return trim($razao);
    }

    /**
     * @param  array<string, mixed>|null  $phone
     */
    protected function formatPhoneParts(mixed $phone): ?string
    {
        if (! is_array($phone)) {
            return null;
        }

        $ddd = preg_replace('/\D/', '', (string) ($phone['ddd'] ?? ''));
        $numero = preg_replace('/\D/', '', (string) ($phone['numero'] ?? ''));

        if ($ddd === '' || $numero === '') {
            return $this->formatApiPhone($phone['numero'] ?? $phone['ddd'] ?? null);
        }

        return $this->formatApiPhone($ddd . $numero);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractInscricaoEstadual(array $data): ?string
    {
        foreach ([
            'inscricao_estadual',
            'inscricao_estadual_1',
            'inscricao_estadual_2',
            'ie',
        ] as $key) {
            $value = trim((string) ($data[$key] ?? ''));

            if (filled($value) && ! in_array(Str::upper($value), ['ISENTO', 'ISENTA', 'N/A', 'NA'], true)) {
                return Str::upper($value);
            }
        }

        return null;
    }

    protected function formatApiPhone(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($value ?? ''));

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }

        return $digits;
    }

    protected function formatCep(string $cep): ?string
    {
        $digits = preg_replace('/\D/', '', $cep);

        if (strlen($digits) !== 8) {
            return filled($digits) ? $digits : null;
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
    }

    protected function formatCnpj(string $cnpj): ?string
    {
        $digits = preg_replace('/\D/', '', $cnpj);

        if (strlen($digits) !== 14) {
            return filled($digits) ? $digits : null;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2),
        );
    }

    protected function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights1[$i];
        }

        $digit1 = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);

        if ((int) $cnpj[12] !== $digit1) {
            return false;
        }

        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights2[$i];
        }

        $digit2 = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);

        return (int) $cnpj[13] === $digit2;
    }
}
