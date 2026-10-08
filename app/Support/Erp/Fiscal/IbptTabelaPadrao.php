<?php

namespace App\Support\Erp\Fiscal;

use App\Models\FiscalIbptItem;
use Illuminate\Support\Facades\DB;

/**
 * Tabela IBPTax padrão embutida no sistema (database/data/fiscal/ibpt_itens.jsonl.gz).
 * Só carrega quando a tabela está vazia: importação feita pelo cliente nunca é sobrescrita.
 */
final class IbptTabelaPadrao
{
    /**
     * @return int Registros gravados (0 quando já havia dados ou o arquivo não existe)
     */
    public static function carregarSeVazia(): int
    {
        if (FiscalIbptItem::query()->exists()) {
            return 0;
        }

        $payload = self::linhasDoArquivo();

        if ($payload === []) {
            return 0;
        }

        DB::transaction(function () use ($payload): void {
            foreach (array_chunk($payload, 500) as $chunk) {
                FiscalIbptItem::query()->insert($chunk);
            }
        });

        try {
            (new NcmCatalogService)->syncFromIbpt();
        } catch (\Throwable $e) {
            report($e);
        }

        return count($payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function linhasDoArquivo(): array
    {
        $contents = self::conteudoArquivo();

        if ($contents === null) {
            return [];
        }

        $now = now();
        $payload = [];

        foreach (preg_split("/\r\n|\n|\r/", $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = json_decode($line, true);

            if (! is_array($row) || blank($row['ncm'] ?? null)) {
                continue;
            }

            $payload[] = [
                'ncm' => (string) $row['ncm'],
                'ex_tipi' => $row['ex_tipi'] ?? null,
                'tipo' => $row['tipo'] ?? null,
                'descricao' => isset($row['descricao']) ? mb_substr((string) $row['descricao'], 0, 500) : null,
                'aliq_nacional' => (float) ($row['aliq_nacional'] ?? 0),
                'aliq_importado' => (float) ($row['aliq_importado'] ?? 0),
                'aliq_estadual' => (float) ($row['aliq_estadual'] ?? 0),
                'aliq_municipal' => (float) ($row['aliq_municipal'] ?? 0),
                'vigencia_inicio' => $row['vigencia_inicio'] ?? null,
                'vigencia_fim' => $row['vigencia_fim'] ?? null,
                'chave' => $row['chave'] ?? null,
                'versao' => $row['versao'] ?? null,
                'fonte' => $row['fonte'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $payload;
    }

    private static function conteudoArquivo(): ?string
    {
        $gzPath = database_path('data/fiscal/ibpt_itens.jsonl.gz');
        $plainPath = database_path('data/fiscal/ibpt_itens.jsonl');

        if (is_file($gzPath)) {
            $raw = file_get_contents($gzPath);
            $contents = $raw === false ? false : @gzdecode($raw);
        } elseif (is_file($plainPath)) {
            $contents = file_get_contents($plainPath);
        } else {
            return null;
        }

        return is_string($contents) && trim($contents) !== '' ? $contents : null;
    }
}
