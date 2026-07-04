<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVenda;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;

trait ManagesPdvConsultaVenda
{
    public string $consultaVendaSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $consultaVendaResults = [];

    public ?int $selectedConsultaVendaIndex = null;

    /** @var array<string, mixed>|null */
    public ?array $consultaVendaDetalhe = null;

    public ?int $consultaVendaEstornoId = null;

    public function openConsultaVendaModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $this->consultaVendaSearch = '';
        $this->consultaVendaDetalhe = null;
        $this->consultaVendaEstornoId = null;
        $this->refreshConsultaVendaResults();
        $this->openPdvModal('consulta_venda');
        $this->dispatch('erp-pdv-focus-consulta-venda');
    }

    public function updatedConsultaVendaSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->consultaVendaSearch !== $upper) {
            $this->consultaVendaSearch = $upper;
        }

        $this->refreshConsultaVendaResults();
    }

    public function refreshConsultaVendaResults(): void
    {
        if (! $this->caixaSessaoId) {
            $this->consultaVendaResults = [];
            $this->selectedConsultaVendaIndex = null;
            $this->consultaVendaDetalhe = null;

            return;
        }

        $term = trim($this->consultaVendaSearch);
        $like = $term !== '' ? '%' . $term . '%' : null;

        $query = PdvVenda::query()
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('situacao', '!=', 'C')
            ->orderByDesc('numero');

        if ($like) {
            $query->where(function ($q) use ($like, $term): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('vendedor_nome', 'like', $like);

                if (is_numeric($term)) {
                    $q->orWhere('numero', (int) $term);
                }
            });
        }

        $this->consultaVendaResults = $query
            ->limit(50)
            ->get()
            ->map(fn (PdvVenda $venda): array => [
                'venda_id' => $venda->id,
                'numero' => str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT),
                'total' => ErpMoney::formatBr($venda->total),
                'forma' => $venda->forma_pagamento ?? '—',
                'situacao' => $venda->situacao ?? 'F',
            ])
            ->values()
            ->all();

        $this->selectedConsultaVendaIndex = $this->consultaVendaResults === [] ? null : 0;
        $this->loadConsultaVendaDetalhe();
    }

    public function selectConsultaVendaRow(int $index): void
    {
        if (isset($this->consultaVendaResults[$index])) {
            $this->selectedConsultaVendaIndex = $index;
            $this->loadConsultaVendaDetalhe();
        }
    }

    public function moveConsultaVendaSelection(int $delta): void
    {
        if ($this->consultaVendaResults === []) {
            return;
        }

        $count = count($this->consultaVendaResults);
        $index = ($this->selectedConsultaVendaIndex ?? 0) + $delta;
        $this->selectedConsultaVendaIndex = max(0, min($count - 1, $index));
        $this->loadConsultaVendaDetalhe();
    }

    protected function loadConsultaVendaDetalhe(): void
    {
        $index = $this->selectedConsultaVendaIndex;

        if ($index === null || ! isset($this->consultaVendaResults[$index])) {
            $this->consultaVendaDetalhe = null;

            return;
        }

        $vendaId = (int) ($this->consultaVendaResults[$index]['venda_id'] ?? 0);
        $venda = PdvVenda::query()->with(['itens', 'pagamentos', 'person'])->find($vendaId);

        if (! $venda) {
            $this->consultaVendaDetalhe = null;

            return;
        }

        $this->consultaVendaDetalhe = [
            'venda_id' => $venda->id,
            'numero' => str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT),
            'total' => ErpMoney::formatBr($venda->total),
            'subtotal' => ErpMoney::formatBr($venda->subtotal),
            'desconto' => ErpMoney::formatBr($venda->desconto),
            'acrescimo' => ErpMoney::formatBr($venda->acrescimo),
            'cliente' => $venda->person?->nome_razao ?? 'CONSUMIDOR FINAL',
            'forma' => $venda->forma_pagamento ?? '—',
            'itens' => $venda->itens->map(fn ($item): array => [
                'descricao' => $item->descricao,
                'quantidade' => (float) $item->quantidade,
                'total' => ErpMoney::formatBr($item->total),
            ])->all(),
            'pagamentos' => $venda->pagamentos->map(fn ($pag): array => [
                'forma' => $pag->forma,
                'valor' => ErpMoney::formatBr($pag->valor),
            ])->all(),
        ];
    }

    public function imprimirConsultaVenda(): void
    {
        $vendaId = (int) ($this->consultaVendaDetalhe['venda_id'] ?? 0);

        if ($vendaId <= 0) {
            $this->notifyPdvError('Selecione uma venda.');

            return;
        }

        $copias = $this->pdvConfig()->pedidoDuasVias() ? 2 : 1;
        $this->imprimirCupomPosVenda($vendaId, $copias);
    }

    public function requestEstornarConsultaVenda(): void
    {
        $vendaId = (int) ($this->consultaVendaDetalhe['venda_id'] ?? 0);

        if ($vendaId <= 0) {
            $this->notifyPdvError('Selecione uma venda.');

            return;
        }

        if ($this->pdvConfig()->pedirAutorizacaoExcluir() && ! $this->pdvAutorizado()) {
            $this->consultaVendaEstornoId = $vendaId;
            $this->pdvAuthPendingAction = 'estornar_venda';
            $this->pdvAuthPassword = '';
            $this->openPdvModal('autorizacao');
            $this->dispatch('erp-pdv-focus-autorizacao');

            return;
        }

        $this->estornarVenda($vendaId);
    }

    public function estornarVenda(int $vendaId): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            return;
        }

        $venda = PdvVenda::query()
            ->with(['itens', 'pagamentos'])
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('situacao', '!=', 'C')
            ->find($vendaId);

        if (! $venda) {
            $this->notifyPdvError('Venda não encontrada ou já estornada.');

            return;
        }

        if ($venda->fiscal && $this->pdvConfig()->bloquearCancelamentoDocFiscal()) {
            $this->notifyPdvError('Venda com documento fiscal não pode ser estornada pelo PDV web.');

            return;
        }

        $stockService = new \App\Support\Erp\Pdv\PdvStockService();
        $financeiroService = new \App\Support\Erp\Pdv\PdvVendaFinanceiroService();
        $caixaMovimentoService = new \App\Support\Erp\Pdv\PdvCaixaMovimentoService($this->pdvConfig());
        $retaguardaMirrorService = new \App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService();

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($venda, $stockService, $financeiroService, $caixaMovimentoService, $retaguardaMirrorService): void {
                $erroFinanceiro = $financeiroService->estornarContasReceber($venda);

                if ($erroFinanceiro !== null) {
                    throw new \DomainException($erroFinanceiro);
                }

                foreach ($venda->itens as $item) {
                    if (! $item->product_id) {
                        continue;
                    }

                    $product = \App\Models\Product::query()->find($item->product_id);

                    if ($product) {
                        $stockService->estornoItemVenda(
                            $product,
                            (float) $item->quantidade,
                            $item->product_grade_id ? (int) $item->product_grade_id : null,
                            $item->product_serial_id ? (int) $item->product_serial_id : null,
                        );
                    }
                }

                $caixaMovimentoService->registrarSaidasEstornoFromModel(
                    $this->caixaSessaoId,
                    $venda,
                    $venda->pagamentos,
                );

                $retaguardaMirrorService->estornar($venda);

                $venda->update(['situacao' => 'C']);
            });
        } catch (\DomainException $exception) {
            $this->notifyPdvError($exception->getMessage());

            return;
        }

        $this->consultaVendaEstornoId = null;
        $this->clearPdvAutorizacao();
        $this->refreshConsultaVendaResults();

        Notification::make()
            ->title('Venda estornada.')
            ->body('Venda #' . str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT))
            ->success()
            ->send();
    }

    public function cancelConsultaVenda(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
