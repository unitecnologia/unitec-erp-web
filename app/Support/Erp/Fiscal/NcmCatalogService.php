<?php

namespace App\Support\Erp\Fiscal;

use App\Models\FiscalIbptItem;
use App\Models\Ncm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mantém a tabela `ncms` como catálogo único para lupa/cadastro de produto.
 * Alimenta a partir da IPBTAX (descrições oficiais) e do cadastro manual.
 * Nunca esvazia a tabela: só cria/atualiza códigos (upsert).
 */
final class NcmCatalogService
{
    /**
     * Sincroniza códigos NCM distintos de fiscal_ibpt_itens → ncms.
     *
     * @return array{synced: int, created: int, updated: int}
     */
    public function syncFromIbpt(): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $created = 0;
        $updated = 0;

        /** @var array<string, string> $byNcm codigo => descricao (primeira ocorrência = id mais recente) */
        $byNcm = [];

        foreach (
            FiscalIbptItem::query()
                ->select(['ncm', 'descricao'])
                ->orderByDesc('id')
                ->cursor() as $row
        ) {
            $codigo = $this->normalizeCodigo((string) $row->ncm);

            if ($codigo === null || isset($byNcm[$codigo])) {
                continue;
            }

            $descricao = trim((string) ($row->descricao ?? ''));
            $byNcm[$codigo] = $descricao !== ''
                ? Str::upper(Str::limit($descricao, 500, ''))
                : 'NCM '.$codigo;
        }

        if ($byNcm === []) {
            return [
                'synced' => 0,
                'created' => 0,
                'updated' => 0,
            ];
        }

        /** @var array<string, Ncm> $existingByCodigo */
        $existingByCodigo = [];

        foreach (array_chunk(array_keys($byNcm), 1000) as $codigos) {
            foreach (Ncm::query()->whereIn('codigo', $codigos)->get(['id', 'codigo', 'descricao', 'ativo']) as $ncm) {
                $existingByCodigo[(string) $ncm->codigo] = $ncm;
            }
        }

        $now = now();
        $toInsert = [];
        $toUpdate = [];

        foreach ($byNcm as $codigo => $descricao) {
            $existing = $existingByCodigo[$codigo] ?? null;

            if ($existing) {
                $needsUpdate = ! $existing->ativo
                    || blank($existing->descricao)
                    || $existing->descricao === 'NCM '.$codigo
                    || str_starts_with((string) $existing->descricao, 'PRODUTO NAO ESPECIFICADO');

                if ($needsUpdate) {
                    $toUpdate[] = [
                        'codigo' => $codigo,
                        'descricao' => $descricao,
                        'ativo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                continue;
            }

            $toInsert[] = [
                'codigo' => $codigo,
                'descricao' => $descricao,
                'ativo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($toInsert, $toUpdate, &$created, &$updated): void {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                Ncm::query()->insert($chunk);
                $created += count($chunk);
            }

            foreach (array_chunk($toUpdate, 500) as $chunk) {
                Ncm::query()->upsert(
                    $chunk,
                    ['codigo'],
                    ['descricao', 'ativo', 'updated_at'],
                );
                $updated += count($chunk);
            }
        });

        return [
            'synced' => count($byNcm),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function findByCodigo(string $codigo): ?Ncm
    {
        $codigo = $this->normalizeCodigo($codigo);

        if ($codigo === null) {
            return null;
        }

        return Ncm::query()->where('codigo', $codigo)->where('ativo', true)->first();
    }

    /**
     * Cadastra ou reativa NCM na tabela única.
     */
    public function cadastrar(string $codigo, string $descricao): Ncm
    {
        $codigo = $this->normalizeCodigo($codigo);

        if ($codigo === null) {
            throw new \InvalidArgumentException('Informe um NCM com 8 dígitos.');
        }

        $descricao = Str::upper(trim($descricao));

        if ($descricao === '') {
            throw new \InvalidArgumentException('Informe a descrição do NCM.');
        }

        $ncm = Ncm::query()->where('codigo', $codigo)->first();

        if ($ncm) {
            $ncm->fill([
                'descricao' => $descricao,
                'ativo' => true,
            ])->save();

            return $ncm->fresh() ?? $ncm;
        }

        return Ncm::query()->create([
            'codigo' => $codigo,
            'descricao' => $descricao,
            'ativo' => true,
        ]);
    }

    public function normalizeCodigo(string $codigo): ?string
    {
        $digits = preg_replace('/\D/', '', $codigo) ?? '';

        if ($digits === '' || strlen($digits) > 8) {
            return null;
        }

        $padded = str_pad($digits, 8, '0', STR_PAD_LEFT);

        return strlen($padded) === 8 ? $padded : null;
    }
}
