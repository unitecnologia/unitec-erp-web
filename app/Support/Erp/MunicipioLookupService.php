<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MunicipioLookupService
{
    private const CACHE_TTL_DAYS = 30;

    /**
     * Busca municípios em todo o Brasil. Se $ufPreferida for informada,
     * resultados dessa UF aparecem primeiro (ex.: SC), mas outras UFs também entram
     * (ex.: CURITIBA/PR mesmo com UF=SC selecionada).
     *
     * @return list<array{codigo: string, nome: string, uf: string}>
     */
    public function search(string $termo, ?string $ufPreferida = null, int $limit = 25): array
    {
        $termo = trim($termo);
        $ufPreferida = Str::upper(trim((string) $ufPreferida));

        if (mb_strlen($termo) < 2) {
            return [];
        }

        $needle = $this->normalize($termo);
        $matches = [];

        foreach ($this->todosMunicipios() as $municipio) {
            $nomeNorm = $this->normalize($municipio['nome']);

            if (! str_contains($nomeNorm, $needle)) {
                continue;
            }

            $mesmaUf = strlen($ufPreferida) === 2 && $municipio['uf'] === $ufPreferida;
            $prefixo = str_starts_with($nomeNorm, $needle);

            // Menor score = melhor: prefixo + mesma UF primeiro.
            $score = ($prefixo ? 0 : 2) + ($mesmaUf ? 0 : 1);

            $matches[] = [
                'score' => $score,
                'nome' => $municipio['nome'],
                'row' => $municipio,
            ];
        }

        usort($matches, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $a['score'] <=> $b['score'];
            }

            return $a['nome'] <=> $b['nome'];
        });

        return array_values(array_map(
            static fn (array $item): array => $item['row'],
            array_slice($matches, 0, $limit),
        ));
    }

    /**
     * @return list<array{codigo: string, nome: string, uf: string}>
     */
    public function municipiosPorUf(string $uf): array
    {
        $uf = Str::upper(trim($uf));

        if (strlen($uf) !== 2) {
            return [];
        }

        return array_values(array_filter(
            $this->todosMunicipios(),
            static fn (array $row): bool => $row['uf'] === $uf,
        ));
    }

    /**
     * @return list<array{codigo: string, nome: string, uf: string}>
     */
    public function todosMunicipios(): array
    {
        return Cache::remember(
            'erp.ibge.municipios.br',
            now()->addDays(self::CACHE_TTL_DAYS),
            fn (): array => $this->fetchAllFromIbge(),
        );
    }

    /**
     * @return list<array{codigo: string, nome: string, uf: string}>
     */
    protected function fetchAllFromIbge(): array
    {
        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->get('https://servicodados.ibge.gov.br/api/v1/localidades/municipios', [
                    'orderBy' => 'nome',
                ]);
        } catch (\Throwable) {
            throw new RuntimeException('Não foi possível consultar os municípios do IBGE. Verifique a conexão.');
        }

        if (! $response->ok()) {
            throw new RuntimeException('Serviço de municípios do IBGE indisponível no momento.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        $rows = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $codigo = preg_replace('/\D/', '', (string) ($item['id'] ?? '')) ?? '';
            $nome = mb_strtoupper(trim((string) ($item['nome'] ?? '')), 'UTF-8');
            $uf = Str::upper(trim((string) data_get(
                $item,
                'microrregiao.mesorregiao.UF.sigla',
                data_get($item, 'regiao-imediata.regiao-intermediaria.UF.sigla', ''),
            )));

            if (strlen($codigo) !== 7 || $nome === '' || strlen($uf) !== 2) {
                continue;
            }

            $rows[] = [
                'codigo' => $codigo,
                'nome' => $nome,
                'uf' => $uf,
            ];
        }

        return $rows;
    }

    protected function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }
}
