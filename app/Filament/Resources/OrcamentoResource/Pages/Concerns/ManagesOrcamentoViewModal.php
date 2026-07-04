<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;

trait ManagesOrcamentoViewModal
{
    public bool $viewModalOpen = false;

    public string $viewModalActiveTab = 'itens';

    /** @var array<string, string> */
    public array $viewModalHeader = [];

    /** @var array<int, array<string, string>> */
    public array $viewModalItens = [];

    /** @var array<string, string> */
    public array $viewModalTotais = [];

    public string $viewModalObservacoes = '';

    public function openOrcamentoView(int $orcamentoId): void
    {
        $orcamento = Orcamento::query()
            ->with(['cliente', 'vendedor', 'itens.product', 'itens.grade'])
            ->find($orcamentoId);

        if (! $orcamento) {
            Notification::make()
                ->title('Orçamento não encontrado.')
                ->danger()
                ->send();

            return;
        }

        $cliente = $orcamento->cliente;

        $this->viewModalHeader = [
            'numero' => $this->formatOrcamentoNumero($orcamento->numero),
            'cliente' => mb_strtoupper($cliente?->nome_razao ?? '—', 'UTF-8'),
            'cpf_cnpj' => $this->formatViewModalCpfCnpj($cliente?->cpf_cnpj),
            'endereco' => mb_strtoupper((string) ($cliente?->endereco ?? '—'), 'UTF-8'),
            'numero_end' => (string) ($cliente?->numero ?: '—'),
            'bairro' => mb_strtoupper((string) ($cliente?->bairro ?? '—'), 'UTF-8'),
            'cep' => $cliente?->cep ?: '—',
            'cidade' => mb_strtoupper((string) ($cliente?->cidade_nome ?? '—'), 'UTF-8'),
            'uf' => mb_strtoupper((string) ($cliente?->uf ?? '—'), 'UTF-8'),
            'fone' => $cliente?->fone1 ?: '—',
            'whatsapp' => $cliente?->celular1 ?: ($cliente?->whatsapp ?: '—'),
            'vendedor' => mb_strtoupper($orcamento->vendedor?->nome ?? '—', 'UTF-8'),
            'forma_pagamento' => mb_strtoupper((string) ($orcamento->forma_pagamento ?? ''), 'UTF-8') ?: '—',
            'validade_dias' => (string) ($orcamento->validade_dias ?? 0),
        ];

        $this->viewModalItens = $orcamento->itens
            ->sortByDesc('item')
            ->values()
            ->map(fn (OrcamentoItem $item): array => [
                'numero' => (int) $item->item,
                'codigo' => $this->formatOrcamentoItemCodigo($item->product?->codigo),
                'descricao' => mb_strtoupper((string) ($item->descricao ?? $item->product?->descricao ?? ''), 'UTF-8'),
                'quantidade' => ErpMoney::formatBr((float) $item->quantidade, 3),
                'unidade' => mb_strtoupper((string) ($item->product?->unidade ?? 'UN'), 'UTF-8'),
                'preco' => ErpMoney::formatBr((float) $item->preco_unitario),
                'total' => ErpMoney::formatBr((float) $item->total),
                'grade' => mb_strtoupper((string) ($item->grade?->descricao ?? ''), 'UTF-8') ?: '—',
            ])
            ->all();

        $this->viewModalTotais = [
            'subtotal' => ErpMoney::formatBr((float) $orcamento->subtotal),
            'desconto_pct' => ErpMoney::formatBr((float) $orcamento->percentual_desconto, 2),
            'desconto_valor' => ErpMoney::formatBr((float) $orcamento->desconto_valor),
            'total' => ErpMoney::formatBr((float) $orcamento->total),
        ];

        $this->viewModalObservacoes = (string) ($orcamento->observacoes ?? '');
        $this->viewModalActiveTab = 'itens';
        $this->viewModalOpen = true;
    }

    public function closeOrcamentoView(): void
    {
        $this->viewModalOpen = false;
        $this->viewModalActiveTab = 'itens';
        $this->viewModalHeader = [];
        $this->viewModalItens = [];
        $this->viewModalTotais = [];
        $this->viewModalObservacoes = '';
    }

    public function setViewModalTab(string $tab): void
    {
        if (! in_array($tab, ['itens', 'observacoes'], true)) {
            return;
        }

        $this->viewModalActiveTab = $tab;
    }

    protected function formatOrcamentoNumero(?string $numero): string
    {
        if (blank($numero)) {
            return '—';
        }

        $trimmed = ltrim($numero, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    protected function formatOrcamentoItemCodigo(mixed $codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return '—';
        }

        $trimmed = ltrim((string) $codigo, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    protected function formatViewModalCpfCnpj(?string $value): string
    {
        if (! filled($value)) {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
        }

        return $value;
    }
}
