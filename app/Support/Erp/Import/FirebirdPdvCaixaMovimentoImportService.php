<?php

namespace App\Support\Erp\Import;

use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\Terminal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdPdvCaixaMovimentoImportService
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $sessaoIdByLote
     * @param  array<int, int>  $pdvVendaIdByNumero
     * @return array<string, mixed>|null
     */
    public function mapFirebirdRow(array $row, array $sessaoIdByLote, array $pdvVendaIdByNumero): ?array
    {
        $lote = trim((string) ($row['LOTE'] ?? $row['lote'] ?? ''));
        $sessaoId = $lote !== ''
            ? ($sessaoIdByLote[$lote] ?? $sessaoIdByLote[(string) (int) $lote] ?? null)
            : null;

        if (! $sessaoId) {
            return null;
        }

        $historico = trim((string) ($row['HISTORICO'] ?? $row['historico'] ?? ''));
        if ($historico === '') {
            $historico = '-';
        }

        $tipo = $this->mapTipo($historico);
        $fkVenda = (int) ($row['FKVENDA'] ?? $row['fkvenda'] ?? 0);
        $pdvVendaId = $fkVenda > 0 ? ($pdvVendaIdByNumero[$fkVenda] ?? null) : null;

        $quando = $this->mapDateTime(
            $row['DATA'] ?? $row['data'] ?? null,
            $row['HORA'] ?? $row['hora'] ?? null,
        );

        return [
            'pdv_caixa_sessao_id' => (int) $sessaoId,
            'tipo' => $tipo,
            'historico' => Str::upper($historico),
            'forma_pagamento' => null,
            'plano_conta_codigo' => null,
            'sangria_destino' => null,
            'entrada' => BrDecimalImport::parse($row['ENTRADA'] ?? 0),
            'saida' => BrDecimalImport::parse($row['SAIDA'] ?? 0),
            'pdv_venda_id' => $pdvVendaId,
            '_quando' => $quando,
            '_fb_codigo' => (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $sessaoIdByLote
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(
        array $rows,
        array $sessaoIdByLote,
        bool $dryRun = false,
        bool $replaceExisting = true,
    ): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $pdvVendaIdByNumero = PdvVenda::query()
            ->pluck('id', 'numero')
            ->mapWithKeys(fn ($id, $numero) => [(int) $numero => (int) $id])
            ->all();

        if (! $dryRun && $replaceExisting) {
            $sessaoIds = array_values(array_unique(array_filter(array_map('intval', $sessaoIdByLote))));
            if ($sessaoIds !== []) {
                PdvCaixaMovimento::query()->whereIn('pdv_caixa_sessao_id', $sessaoIds)->delete();
            }
        }

        DB::transaction(function () use (
            $rows,
            $sessaoIdByLote,
            $pdvVendaIdByNumero,
            $dryRun,
            &$stats,
        ): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapFirebirdRow($row, $sessaoIdByLote, $pdvVendaIdByNumero);

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $quando = $payload['_quando'] ?? null;
                unset($payload['_fb_codigo'], $payload['_quando']);

                if ($dryRun) {
                    $stats['created']++;

                    continue;
                }

                $movimento = new PdvCaixaMovimento($payload);
                if (is_string($quando) && $quando !== '') {
                    $movimento->created_at = $quando;
                    $movimento->updated_at = $quando;
                }
                $movimento->save();
                $stats['created']++;
            }
        });

        return $stats;
    }

    /**
     * Associa LOTE Firebird → sessão web.
     * Prefere a sessão onde já estão as vendas daquele lote; senão usa ordem de abertura.
     *
     * @param  array<int, array<string, mixed>>  $loteRows  LOTE, DE, ATE, USU
     * @param  array<string, int>  $userIdByFbCodigo
     * @param  array<string, list<int>>  $vendaNumerosByLote
     * @return array<string, int>
     */
    public function resolveSessaoIdByLote(
        array $loteRows,
        array $userIdByFbCodigo,
        int $fallbackUserId,
        bool $dryRun = false,
        array $vendaNumerosByLote = [],
    ): array {
        $lotes = [];
        foreach ($loteRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lote = trim((string) ($row['LOTE'] ?? ''));
            if ($lote === '') {
                continue;
            }
            $lotes[] = [
                'lote' => $lote,
                'de' => $row['DE'] ?? null,
                'usu' => trim((string) ($row['USU'] ?? '')),
            ];
        }

        usort($lotes, fn (array $a, array $b): int => ((int) $a['lote']) <=> ((int) $b['lote']));

        $sessoes = PdvCaixaSessao::query()->orderBy('aberto_em')->orderBy('id')->get();
        $map = [];
        $usedSessaoIds = [];

        foreach ($lotes as $i => $info) {
            $lote = $info['lote'];
            $sessaoId = null;

            $numeros = $vendaNumerosByLote[$lote] ?? $vendaNumerosByLote[(string) (int) $lote] ?? [];
            if ($numeros !== []) {
                $sessaoId = PdvVenda::query()
                    ->whereIn('numero', $numeros)
                    ->selectRaw('pdv_caixa_sessao_id, COUNT(*) AS qtd')
                    ->groupBy('pdv_caixa_sessao_id')
                    ->orderByDesc('qtd')
                    ->value('pdv_caixa_sessao_id');
                $sessaoId = $sessaoId ? (int) $sessaoId : null;
                if ($sessaoId && isset($usedSessaoIds[$sessaoId])) {
                    $sessaoId = null;
                }
            }

            if (! $sessaoId) {
                foreach ($sessoes as $sessao) {
                    $id = (int) $sessao->id;
                    if (isset($usedSessaoIds[$id])) {
                        continue;
                    }
                    $sessaoId = $id;
                    break;
                }
            }

            if ($sessaoId) {
                $usedSessaoIds[$sessaoId] = true;
                $map[$lote] = $sessaoId;
                $map[(string) (int) $lote] = $sessaoId;

                continue;
            }

            if ($dryRun) {
                $map[$lote] = -1 * ((int) $lote ?: ($i + 1));
                $map[(string) (int) $lote] = $map[$lote];

                continue;
            }

            $fkUser = $info['usu'];
            $userId = $fkUser !== ''
                ? ($userIdByFbCodigo[$fkUser] ?? $userIdByFbCodigo[(string) (int) $fkUser] ?? $fallbackUserId)
                : $fallbackUserId;

            $abertoEm = null;
            try {
                if ($info['de']) {
                    $abertoEm = Carbon::parse($info['de'])->startOfDay()->toDateTimeString();
                }
            } catch (\Throwable) {
                $abertoEm = null;
            }

            $nova = PdvCaixaSessao::query()->create([
                'user_id' => $userId,
                'empresa_id' => Empresa::query()->orderBy('id')->value('id'),
                'terminal_id' => Terminal::query()->orderBy('id')->value('id'),
                'valor_abertura' => 0,
                'valor_fechamento' => null,
                'aberto_em' => $abertoEm ?? now(),
                'fechado_em' => null,
            ]);

            $usedSessaoIds[(int) $nova->id] = true;
            $map[$lote] = (int) $nova->id;
            $map[(string) (int) $lote] = (int) $nova->id;
            $sessoes->push($nova);
        }

        return $map;
    }

    protected function mapTipo(string $historico): string
    {
        $h = Str::upper(trim($historico));

        if (str_starts_with($h, 'ABERTURA')) {
            return 'abertura';
        }
        if (str_contains($h, 'SANGRIA')) {
            return 'sangria';
        }
        if (str_contains($h, 'SUPRIMENTO')) {
            return 'suprimento';
        }
        if (str_contains($h, 'ESTORNO')) {
            return 'estorno';
        }
        if (str_contains($h, 'FECHAMENTO')) {
            return 'fechamento';
        }
        if (str_starts_with($h, 'REF.VENDA') || str_starts_with($h, 'REF. VENDA') || str_contains($h, 'VENDA')) {
            return 'venda';
        }

        return 'movimento';
    }

    protected function mapDateTime(mixed $date, mixed $time): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $d = Carbon::parse($date)->format('Y-m-d');
            $t = '00:00:00';
            if ($time !== null && $time !== '') {
                $raw = trim((string) $time);
                if (preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $raw, $m)) {
                    $t = $m[1];
                }
            }

            return $d.' '.$t;
        } catch (\Throwable) {
            return null;
        }
    }
}
