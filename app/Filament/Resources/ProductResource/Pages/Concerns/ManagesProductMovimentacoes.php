<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ManagesProductMovimentacoes
{
    public string $movFiltroPeriodoDe = '';

    public string $movFiltroPeriodoAte = '';

    public string $movFiltroTipo = '';

    public int $movPage = 1;

    public int $movPerPage = 50;

    public int $movTotal = 0;

    /** @var list<array<string, mixed>> */
    public array $productMovimentacoes = [];

    public bool $productMovimentacoesLoaded = false;

    public function loadProductMovimentacoes(?Product $product = null): void
    {
        $product ??= $this->record instanceof Product ? $this->record : null;
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($product === null || $empresaId <= 0 || ! Schema::hasTable('estoque_movimentacoes')) {
            $this->productMovimentacoes = [];
            $this->movTotal = 0;
            $this->productMovimentacoesLoaded = true;

            return;
        }

        $produtoId = (int) $product->id;
        $perPage = max(1, min(100, $this->movPerPage));
        $page = max(1, $this->movPage);
        $offset = ($page - 1) * $perPage;

        // Sempre restringe à empresa ativa do ERP (não há filtro de empresa na UI).
        $base = DB::table('estoque_movimentacoes as m')
            ->where('m.produto_id', $produtoId)
            ->where('m.empresa_id', $empresaId);

        if ($this->movFiltroPeriodoDe !== '') {
            $base->whereDate('m.data_movimentacao', '>=', $this->movFiltroPeriodoDe);
        }

        if ($this->movFiltroPeriodoAte !== '') {
            $base->whereDate('m.data_movimentacao', '<=', $this->movFiltroPeriodoAte);
        }

        if ($this->movFiltroTipo !== '') {
            $base->where('m.tipo', $this->movFiltroTipo);
        }

        $this->movTotal = (int) (clone $base)->count();

        $select = [
            'm.id',
            'm.data_movimentacao',
            'm.tipo',
            'm.quantidade',
            'm.saldo_anterior',
            'm.saldo_atual',
            'm.origem_tipo',
            'm.origem_id',
            'm.origem_numero',
            'u.name as usuario_nome',
        ];
        $hasDocFiscal = Schema::hasColumn('estoque_movimentacoes', 'doc_fiscal_tipo')
            && Schema::hasColumn('estoque_movimentacoes', 'doc_fiscal_numero');
        if ($hasDocFiscal) {
            $select[] = 'm.doc_fiscal_tipo';
            $select[] = 'm.doc_fiscal_numero';
        }

        $rows = (clone $base)
            ->leftJoin('users as u', 'u.id', '=', 'm.usuario_id')
            ->orderByDesc('m.data_movimentacao')
            ->orderByDesc('m.id')
            ->offset($offset)
            ->limit($perPage)
            ->get($select);

        $this->productMovimentacoes = [];

        $nfcePorOrigem = $hasDocFiscal
            ? \App\Support\Erp\EstoqueMovimentacaoDocumento::nfcePorOrigem(
                $rows->filter(fn ($row): bool => trim((string) ($row->doc_fiscal_numero ?? '')) === '')
            )
            : [];

        foreach ($rows as $row) {
            if ($hasDocFiscal && trim((string) ($row->doc_fiscal_numero ?? '')) === '') {
                $fallback = $nfcePorOrigem[(string) $row->origem_tipo.':'.(int) $row->origem_id] ?? null;
                if ($fallback !== null) {
                    $row->doc_fiscal_tipo = $fallback['docFiscalTipo'];
                    $row->doc_fiscal_numero = $fallback['docFiscalNumero'];
                }
            }

            $qtd = (string) ($row->quantidade ?? '0');
            $usuarioNome = trim((string) ($row->usuario_nome ?? ''));
            $documento = EstoqueMovimentacao::documentoLabel(
                (string) ($row->origem_tipo ?? ''),
                $row->origem_numero !== null ? (string) $row->origem_numero : null,
            );
            $docFiscal = $hasDocFiscal
                ? EstoqueMovimentacao::docFiscalLabel(
                    $row->doc_fiscal_tipo !== null ? (string) $row->doc_fiscal_tipo : null,
                    $row->doc_fiscal_numero !== null ? (string) $row->doc_fiscal_numero : null,
                )
                : '—';
            $this->productMovimentacoes[] = [
                'id' => (int) $row->id,
                'data' => $this->formatMovData((string) $row->data_movimentacao),
                'tipo' => (string) $row->tipo,
                'tipo_label' => EstoqueMovimentacao::tipoLabel((string) $row->tipo),
                'quantidade' => $this->formatMovQty($qtd),
                'quantidade_sinal' => $this->movQtySign($qtd),
                'saldo' => $this->formatMovSaldo(
                    (string) ($row->saldo_anterior ?? '0'),
                    (string) ($row->saldo_atual ?? '0'),
                ),
                'documento' => $documento,
                'doc_fiscal' => $docFiscal,
                'usuario' => $usuarioNome !== '' ? $usuarioNome : 'Sistema',
            ];
        }

        $this->productMovimentacoesLoaded = true;
    }

    public function aplicarFiltrosMovimentacoes(): void
    {
        $this->movPage = 1;
        $this->loadProductMovimentacoes();
    }

    public function limparFiltrosMovimentacoes(): void
    {
        $this->movFiltroPeriodoDe = '';
        $this->movFiltroPeriodoAte = '';
        $this->movFiltroTipo = '';
        $this->movPage = 1;
        $this->loadProductMovimentacoes();
    }

    public function irPaginaMovimentacoes(int $page): void
    {
        $max = max(1, (int) ceil($this->movTotal / max(1, $this->movPerPage)));
        $this->movPage = max(1, min($max, $page));
        $this->loadProductMovimentacoes();
    }

    /**
     * @return array<string, string>
     */
    public function getMovimentacaoTiposFiltroProperty(): array
    {
        return EstoqueMovimentacao::tiposLabels();
    }

    public function getMovimentacoesLastPageProperty(): int
    {
        return max(1, (int) ceil($this->movTotal / max(1, $this->movPerPage)));
    }

    private function formatMovData(string $raw): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($raw)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $raw;
        }
    }

    private function formatMovQty(string $qtd): string
    {
        $normalized = function_exists('bcadd') ? bcadd($qtd, '0', 3) : sprintf('%.3f', (float) $qtd);
        $sign = $this->movQtySign($normalized);
        $abs = function_exists('bccomp') && bccomp($normalized, '0', 3) < 0
            ? (function_exists('bcmul') ? bcmul($normalized, '-1', 3) : sprintf('%.3f', abs((float) $normalized)))
            : $normalized;

        $fmt = number_format((float) $abs, 3, ',', '.');

        return ($sign === 'neg' ? '-' : ($sign === 'pos' ? '+' : '')).$fmt;
    }

    private function movQtySign(string $qtd): string
    {
        if (function_exists('bccomp')) {
            $cmp = bccomp($qtd, '0', 3);
            if ($cmp > 0) {
                return 'pos';
            }
            if ($cmp < 0) {
                return 'neg';
            }

            return 'zero';
        }

        $n = (float) $qtd;
        if ($n > 0) {
            return 'pos';
        }
        if ($n < 0) {
            return 'neg';
        }

        return 'zero';
    }

    private function formatMovSaldo(string $antes, string $depois): string
    {
        $a = number_format((float) (function_exists('bcadd') ? bcadd($antes, '0', 3) : $antes), 3, ',', '.');
        $d = number_format((float) (function_exists('bcadd') ? bcadd($depois, '0', 3) : $depois), 3, ',', '.');

        return $a.' → '.$d;
    }
}
