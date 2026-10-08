<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lançamentos de venda no Livro Caixa gravados sem Plano de Contas (OS, Força de Vendas,
 * fechamento PDV). Preenche só plano_conta_id/plano_contas com o "Plano de Contas de Venda"
 * da empresa, e só quando a origem é comprovada. Valores, datas e saldos não mudam.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('caixa_lancamentos')
            || ! Schema::hasColumn('caixa_lancamentos', 'plano_conta_id')
            || ! Schema::hasColumn('caixa_lancamentos', 'empresa_id')
            || ! Schema::hasTable('empresas')
            || ! Schema::hasColumn('empresas', 'param_plano_conta_venda_id')
            || ! Schema::hasTable('planos_contas')
        ) {
            return;
        }

        $planosVenda = $this->planosVendaPorEmpresa();

        if ($planosVenda === []) {
            return;
        }

        $candidatos = DB::table('caixa_lancamentos')
            ->whereNull('plano_conta_id')
            ->where(fn ($q) => $q->whereNull('plano_contas')->orWhere('plano_contas', ''))
            ->whereIn('empresa_id', array_keys($planosVenda))
            ->where(fn ($q) => $q
                ->where('documento', 'like', 'OS-%')
                ->orWhere('documento', 'like', 'FV-%')
                ->orWhere('documento', 'like', 'PDV-CX-%'))
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'documento', 'historico', 'entrada', 'saida']);

        $osPorEmpresa = [];

        foreach ($candidatos as $lancamento) {
            $empresaId = (int) $lancamento->empresa_id;
            $documento = (string) $lancamento->documento;
            $historico = mb_strtoupper(trim((string) $lancamento->historico), 'UTF-8');

            $ehVenda = match (true) {
                str_starts_with($documento, 'OS-') => $this->origemOs($documento, $historico, $empresaId, $osPorEmpresa),
                str_starts_with($documento, 'FV-') => $this->origemFv($documento, $historico),
                str_starts_with($documento, 'PDV-CX-') => $this->origemFechamentoPdvVendas($documento, $lancamento),
                default => false,
            };

            if (! $ehVenda) {
                continue;
            }

            DB::table('caixa_lancamentos')
                ->where('id', $lancamento->id)
                ->whereNull('plano_conta_id')
                ->update([
                    'plano_conta_id' => $planosVenda[$empresaId]['id'],
                    'plano_contas' => $planosVenda[$empresaId]['nome'],
                ]);
        }
    }

    public function down(): void
    {
        //
    }

    /**
     * @return array<int, array{id: int, nome: string}>
     */
    private function planosVendaPorEmpresa(): array
    {
        return DB::table('empresas as e')
            ->join('planos_contas as p', 'p.id', '=', 'e.param_plano_conta_venda_id')
            ->where('p.dc', 'C')
            ->where('p.ativo', true)
            ->get(['e.id as empresa_id', 'p.id as plano_id', 'p.descricao'])
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->empresa_id => [
                    'id' => (int) $row->plano_id,
                    'nome' => mb_substr(mb_strtoupper((string) $row->descricao, 'UTF-8'), 0, 120),
                ],
            ])
            ->all();
    }

    /**
     * Faturamento de OS (OsFaturamentoService) e seu estorno (OsReabrirService).
     *
     * @param  array<int, array<string, true>>  $cache
     */
    private function origemOs(string $documento, string $historico, int $empresaId, array &$cache): bool
    {
        if (! preg_match('/^OS-(\d+)$/', $documento, $m) || ! Schema::hasTable('ordens_servico')) {
            return false;
        }

        if (! preg_match('/^(ESTORNO )?OS \S+( \(|$)/u', $historico)) {
            return false;
        }

        if (! isset($cache[$empresaId])) {
            $cache[$empresaId] = DB::table('ordens_servico')
                ->where('empresa_id', $empresaId)
                ->pluck('numero')
                ->mapWithKeys(fn ($numero): array => [preg_replace('/\D/', '', (string) $numero) => true])
                ->all();
        }

        return isset($cache[$empresaId][$m[1]]);
    }

    /**
     * Faturamento FV/Tela de Venda à vista (ForcaVendasFaturamentoService) e seu estorno.
     */
    private function origemFv(string $documento, string $historico): bool
    {
        if (! preg_match('/^FV-(\d+)$/', $documento, $m) || ! Schema::hasTable('forca_vendas_orders')) {
            return false;
        }

        if (! preg_match('/^(ESTORNO )?VENDA (APP|ERP) /u', $historico)) {
            return false;
        }

        return DB::table('forca_vendas_orders')->where('id', (int) $m[1])->exists();
    }

    /**
     * Fechamento PDV: só quando o lançamento é exatamente o líquido de vendas da forma
     * (sem abertura, suprimento, recebimento ou sangria misturados).
     */
    private function origemFechamentoPdvVendas(string $documento, object $lancamento): bool
    {
        if (
            ! preg_match('/^PDV-CX-(\d+)(?:-([A-Z0-9]+))?$/', $documento, $m)
            || ! Schema::hasTable('pdv_caixa_movimentos')
        ) {
            return false;
        }

        $sufixo = $m[2] ?? '';

        if (preg_match('/^SG\d+$/', $sufixo)) {
            return false;
        }

        $vendas = 0.0;
        $outros = 0.0;

        $movimentos = DB::table('pdv_caixa_movimentos')
            ->where('pdv_caixa_sessao_id', (int) $m[1])
            ->get(['tipo', 'forma_pagamento', 'entrada', 'saida']);

        foreach ($movimentos as $movimento) {
            $forma = mb_strtoupper(trim((string) ($movimento->forma_pagamento ?? '')), 'UTF-8') ?: 'DINHEIRO';
            $formaSufixo = $forma === 'DINHEIRO'
                ? ''
                : (preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper(Str::ascii($forma), 'UTF-8')) ?: 'OUTROS');

            if ($formaSufixo !== $sufixo) {
                continue;
            }

            $valor = (float) $movimento->entrada - (float) $movimento->saida;

            if (in_array((string) $movimento->tipo, ['venda', 'estorno'], true)) {
                $vendas += $valor;
            } else {
                $outros += abs($valor);
            }
        }

        $liquidoLancamento = round((float) $lancamento->entrada - (float) $lancamento->saida, 2);

        return round($outros, 2) === 0.0
            && round($vendas, 2) !== 0.0
            && round($vendas, 2) === $liquidoLancamento;
    }
};
