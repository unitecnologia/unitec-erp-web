<?php

namespace App\Support\Erp\Financeiro;

use App\Models\ContaReceber;
use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ContaReceberDetalheService
{
    /**
     * @return array<string, mixed>|null
     */
    public function montar(int $contaId): ?array
    {
        $conta = ContaReceber::query()
            ->with(['cliente'])
            ->find($contaId);

        if (! $conta) {
            return null;
        }

        $cliente = $conta->cliente;
        $origem = $this->resolverOrigem($conta);
        $parcelas = $this->parcelasRelacionadas($conta);
        $itens = $this->montarItens($origem);

        $numeroConta = ltrim((string) $conta->numero, '0') ?: '0';
        $clienteNome = mb_strtoupper($cliente?->nome_razao ?? '—', 'UTF-8');

        return [
            'titulo' => 'Conta nº ' . $numeroConta . ' — ' . $clienteNome,
            'conta' => [
                'numero' => $numeroConta,
                'documento' => $conta->documento ?: '—',
                'historico' => mb_strtoupper((string) $conta->historico, 'UTF-8'),
                'emissao' => $conta->emissao?->format('d/m/Y') ?? '—',
                'vencimento' => $conta->vencimento?->format('d/m/Y') ?? '—',
                'forma' => ContaReceber::formaLabels()[$conta->forma] ?? $conta->forma,
                'valor' => ErpMoney::formatBr((float) $conta->valor),
                'desconto' => ErpMoney::formatBr((float) $conta->desconto),
                'juros' => ErpMoney::formatBr((float) $conta->juros),
                'valor_recebido' => ErpMoney::formatBr((float) $conta->valor_recebido),
                'saldo' => ErpMoney::formatBr((float) $conta->saldo),
                'recebido_em' => $conta->recebido_em?->format('d/m/Y') ?? '—',
            ],
            'cliente' => [
                'nome' => $clienteNome,
                'cpf_cnpj' => $this->formatCpfCnpj($cliente?->cpf_cnpj),
                'fone' => $cliente?->fone1 ?: ($cliente?->celular1 ?: '—'),
                'cidade' => mb_strtoupper((string) ($cliente?->cidade_nome ?? '—'), 'UTF-8'),
                'uf' => mb_strtoupper((string) ($cliente?->uf ?? '—'), 'UTF-8'),
            ],
            'origem' => $origem['label'],
            'origem_detalhe' => $origem['detalhe'],
            'vendedor' => $origem['vendedor'],
            'forma_pagamento' => $origem['forma_pagamento'],
            'itens' => $itens,
            'parcelas' => $parcelas,
            'totais' => [
                'subtotal' => $origem['subtotal'],
                'desconto' => $origem['desconto'],
                'total' => $origem['total'],
            ],
        ];
    }

    /**
     * @return array{label: string, detalhe: string, vendedor: string, forma_pagamento: string, subtotal: string, desconto: string, total: string, venda: ?Venda, orcamento: ?Orcamento, pdv: ?PdvVenda}
     */
    private function resolverOrigem(ContaReceber $conta): array
    {
        $vazio = [
            'label' => 'Título financeiro',
            'detalhe' => $conta->documento ?: '—',
            'vendedor' => '—',
            'forma_pagamento' => ContaReceber::formaLabels()[$conta->forma] ?? '—',
            'subtotal' => '—',
            'desconto' => '—',
            'total' => '—',
            'venda' => null,
            'orcamento' => null,
            'pdv' => null,
        ];

        $documento = trim((string) ($conta->documento ?? ''));

        if ($documento === '') {
            return $vazio;
        }

        if (preg_match('/^FV-(\d+)(?:\/\d+)?$/', $documento, $matches)) {
            $order = ForcaVendasOrder::query()
                ->with(['orcamento.vendedor', 'orcamento.itens.product', 'orcamento.itens.grade'])
                ->find((int) $matches[1]);

            if (! $order) {
                return [
                    ...$vazio,
                    'label' => 'Pedido Força de Vendas',
                    'detalhe' => $documento,
                ];
            }

            $orcamento = $order->orcamento;
            $venda = $order->venda_id
                ? Venda::query()->with(['vendedor', 'itens.product'])->find($order->venda_id)
                : null;

            $numeroPedido = $orcamento?->numero
                ? ltrim((string) $orcamento->numero, '0') ?: '0'
                : ('#' . $order->id);

            return [
                'label' => 'Pedido Força de Vendas',
                'detalhe' => 'Pedido APP ' . $numeroPedido . ' (' . $documento . ')',
                'vendedor' => mb_strtoupper($orcamento?->vendedor?->nome ?? '—', 'UTF-8'),
                'forma_pagamento' => mb_strtoupper((string) ($orcamento?->forma_pagamento ?? ($order->payload['forma_pagamento'] ?? '—')), 'UTF-8'),
                'subtotal' => $orcamento ? ErpMoney::formatBr((float) $orcamento->subtotal) : '—',
                'desconto' => $orcamento ? ErpMoney::formatBr((float) $orcamento->desconto_valor) : '—',
                'total' => ErpMoney::formatBr((float) ($venda?->total ?? $orcamento?->total ?? $order->total)),
                'venda' => $venda,
                'orcamento' => $orcamento?->loadMissing(['itens.product', 'itens.grade']),
                'pdv' => null,
            ];
        }

        if (preg_match('/^PDV-(\d+)$/', $documento, $matches)) {
            $numeroPdv = (int) $matches[1];
            $pdv = PdvVenda::query()
                ->with(['itens.product', 'person', 'venda.itens.product', 'pagamentos'])
                ->where('numero', $numeroPdv)
                ->first();

            $venda = $pdv?->venda_id
                ? Venda::query()->with(['vendedor', 'itens.product'])->find($pdv->venda_id)
                : null;

            return [
                'label' => 'Venda PDV',
                'detalhe' => 'Cupom PDV #' . str_pad((string) $numeroPdv, 6, '0', STR_PAD_LEFT),
                'vendedor' => mb_strtoupper($pdv?->vendedor_nome ?? $venda?->vendedorNome() ?? '—', 'UTF-8'),
                'forma_pagamento' => mb_strtoupper((string) ($pdv?->forma_pagamento ?? '—'), 'UTF-8'),
                'subtotal' => $pdv ? ErpMoney::formatBr((float) $pdv->subtotal) : '—',
                'desconto' => $pdv ? ErpMoney::formatBr((float) $pdv->desconto) : '—',
                'total' => ErpMoney::formatBr((float) ($pdv?->total ?? $venda?->total ?? 0)),
                'venda' => $venda,
                'orcamento' => null,
                'pdv' => $pdv,
            ];
        }

        if (preg_match('/^(?:VD|VENDA)\s*0*(\d+)/i', $documento, $matches)) {
            $venda = Venda::query()
                ->with(['cliente', 'vendedor', 'itens.product'])
                ->where('numero', str_pad($matches[1], 6, '0', STR_PAD_LEFT))
                ->first();

            if ($venda) {
                return [
                    'label' => 'Venda',
                    'detalhe' => 'Venda nº ' . (ltrim((string) $venda->numero, '0') ?: '0'),
                    'vendedor' => mb_strtoupper($venda->vendedorNome(), 'UTF-8'),
                    'forma_pagamento' => ContaReceber::formaLabels()[$conta->forma] ?? '—',
                    'subtotal' => ErpMoney::formatBr((float) $venda->total),
                    'desconto' => '0,00',
                    'total' => ErpMoney::formatBr((float) $venda->total),
                    'venda' => $venda,
                    'orcamento' => null,
                    'pdv' => null,
                ];
            }
        }

        if (preg_match('/^(?:ORC|ORÇAMENTO)\s*0*(\d+)/iu', $documento, $matches)) {
            $orcamento = Orcamento::query()
                ->with(['vendedor', 'itens.product', 'itens.grade'])
                ->where('numero', str_pad($matches[1], 6, '0', STR_PAD_LEFT))
                ->first();

            if ($orcamento) {
                return [
                    'label' => 'Orçamento',
                    'detalhe' => 'Orçamento nº ' . (ltrim((string) $orcamento->numero, '0') ?: '0'),
                    'vendedor' => mb_strtoupper($orcamento->vendedor?->nome ?? '—', 'UTF-8'),
                    'forma_pagamento' => mb_strtoupper((string) ($orcamento->forma_pagamento ?? '—'), 'UTF-8'),
                    'subtotal' => ErpMoney::formatBr((float) $orcamento->subtotal),
                    'desconto' => ErpMoney::formatBr((float) $orcamento->desconto_valor),
                    'total' => ErpMoney::formatBr((float) $orcamento->total),
                    'venda' => null,
                    'orcamento' => $orcamento,
                    'pdv' => null,
                ];
            }
        }

        return $vazio;
    }

    /**
     * @param  array{label: string, detalhe: string, vendedor: string, forma_pagamento: string, subtotal: string, desconto: string, total: string, venda: ?Venda, orcamento: ?Orcamento, pdv: ?PdvVenda}  $origem
     * @return array<int, array<string, string>>
     */
    private function montarItens(array $origem): array
    {
        if ($origem['venda'] instanceof Venda) {
            return $origem['venda']->itens
                ->values()
                ->map(fn (VendaItem $item, int $index): array => $this->itemDeVenda($item, $index + 1))
                ->all();
        }

        if ($origem['pdv'] instanceof PdvVenda && $origem['pdv']->itens->isNotEmpty()) {
            return $origem['pdv']->itens
                ->values()
                ->map(function ($item, int $index): array {
                    return [
                        'item' => (string) ($index + 1),
                        'codigo' => $this->formatCodigo($item->product?->codigo),
                        'descricao' => mb_strtoupper((string) ($item->descricao ?? $item->product?->descricao ?? '—'), 'UTF-8'),
                        'quantidade' => ErpMoney::formatBr((float) $item->quantidade, 3),
                        'unidade' => mb_strtoupper((string) ($item->unidade ?? $item->product?->unidade ?? 'UN'), 'UTF-8'),
                        'preco' => ErpMoney::formatBr((float) $item->preco_unitario),
                        'total' => ErpMoney::formatBr((float) $item->total),
                    ];
                })
                ->all();
        }

        if ($origem['orcamento'] instanceof Orcamento) {
            return $origem['orcamento']->itens
                ->sortBy('item')
                ->values()
                ->map(fn (OrcamentoItem $item): array => [
                    'item' => (string) $item->item,
                    'codigo' => $this->formatCodigo($item->product?->codigo),
                    'descricao' => mb_strtoupper((string) ($item->descricao ?? $item->product?->descricao ?? '—'), 'UTF-8'),
                    'quantidade' => ErpMoney::formatBr((float) $item->quantidade, 3),
                    'unidade' => mb_strtoupper((string) ($item->product?->unidade ?? 'UN'), 'UTF-8'),
                    'preco' => ErpMoney::formatBr((float) $item->preco_unitario),
                    'total' => ErpMoney::formatBr((float) $item->total),
                ])
                ->all();
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function itemDeVenda(VendaItem $item, int $numero): array
    {
        return [
            'item' => (string) $numero,
            'codigo' => $this->formatCodigo($item->product?->codigo),
            'descricao' => mb_strtoupper((string) ($item->product?->descricao ?? '—'), 'UTF-8'),
            'quantidade' => ErpMoney::formatBr((float) $item->quantidade, 3),
            'unidade' => mb_strtoupper((string) ($item->product?->unidade ?? 'UN'), 'UTF-8'),
            'preco' => ErpMoney::formatBr((float) $item->valor_item),
            'total' => ErpMoney::formatBr((float) $item->total),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parcelasRelacionadas(ContaReceber $conta): array
    {
        $documento = trim((string) ($conta->documento ?? ''));

        if ($documento === '') {
            return [$this->parcelaRow($conta, true)];
        }

        $base = preg_replace('#/\d+$#', '', $documento) ?: $documento;

        /** @var Collection<int, ContaReceber> $parcelas */
        $parcelas = ContaReceber::query()
            ->where(fn (Builder $query) => $query
                ->where('documento', $base)
                ->orWhere('documento', 'like', $base . '/%'))
            ->orderBy('vencimento')
            ->orderBy('numero')
            ->get();

        if ($parcelas->isEmpty()) {
            return [$this->parcelaRow($conta, true)];
        }

        return $parcelas
            ->map(fn (ContaReceber $parcela): array => $this->parcelaRow($parcela, $parcela->id === $conta->id))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function parcelaRow(ContaReceber $conta, bool $atual): array
    {
        $hoje = ErpTimezone::toLocal()->startOfDay();
        $vencimento = $conta->vencimento ? ErpTimezone::toLocal($conta->vencimento)->startOfDay() : null;
        $saldo = (float) $conta->saldo;

        $situacao = match (true) {
            $saldo <= 0 => 'Recebida',
            $vencimento !== null && $vencimento->lt($hoje) => 'Atrasada',
            default => 'A receber',
        };

        return [
            'numero' => ltrim((string) $conta->numero, '0') ?: '0',
            'documento' => $conta->documento ?: '—',
            'vencimento' => $conta->vencimento?->format('d/m/Y') ?? '—',
            'valor' => ErpMoney::formatBr((float) $conta->valor),
            'saldo' => ErpMoney::formatBr($saldo),
            'situacao' => $situacao,
            'atual' => $atual ? '1' : '0',
        ];
    }

    private function formatCodigo(mixed $codigo): string
    {
        if ($codigo === null || $codigo === '') {
            return '—';
        }

        $trimmed = ltrim((string) $codigo, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    private function formatCpfCnpj(?string $value): string
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
