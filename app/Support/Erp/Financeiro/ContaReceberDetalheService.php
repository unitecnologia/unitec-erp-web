<?php

namespace App\Support\Erp\Financeiro;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\ContaReceber;
use App\Models\ForcaVendasOrder;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\EmpresaParametros;
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
        $boletoInfo = $this->resolverBoletoInfo($conta);
        $totais = $this->totaisDosItens($itens, $origem);

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
            'boleto_banco' => $boletoInfo['banco'],
            'boleto_conta_bancaria' => $boletoInfo['conta_bancaria'],
            'mostrar_boleto_info' => $boletoInfo['mostrar'],
            'itens' => $itens,
            'parcelas' => $parcelas,
            'totais' => $totais,
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
                ->with(['pedido.vendedor', 'pedido.itens.product', 'pedido.itens.grade'])
                ->find((int) $matches[1]);

            if (! $order) {
                return [
                    ...$vazio,
                    'label' => 'Pedido Força de Vendas',
                    'detalhe' => $documento,
                ];
            }

            $pedido = $order->pedido;
            $venda = $order->venda_id
                ? Venda::query()->with(['vendedor', 'itens.product'])->find($order->venda_id)
                : null;

            $numeroPedido = trim((string) ($pedido?->numero ?? ''));
            if ($numeroPedido === '') {
                $numeroPedido = '#'.$order->id;
            }

            return [
                'label' => 'Pedido Força de Vendas',
                'detalhe' => 'Pedido APP ' . $numeroPedido . ' (' . $documento . ')',
                'vendedor' => mb_strtoupper($pedido?->vendedor?->nome ?? '—', 'UTF-8'),
                'forma_pagamento' => mb_strtoupper((string) ($pedido?->forma_pagamento ?? ($order->payload['forma_pagamento'] ?? '—')), 'UTF-8'),
                'subtotal' => $pedido ? ErpMoney::formatBr((float) $pedido->subtotal) : '—',
                'desconto' => $pedido ? ErpMoney::formatBr((float) $pedido->desconto_valor) : '—',
                'total' => ErpMoney::formatBr((float) ($venda?->total ?? $pedido?->total ?? $order->total)),
                'venda' => $venda,
                'orcamento' => $pedido?->loadMissing(['itens.product', 'itens.grade']),
                'pdv' => null,
            ];
        }

        if (preg_match('/^PDV-(\d+)$/', $documento, $matches)) {
            $numeroPdv = (int) $matches[1];
            $pdv = PdvVenda::query()
                ->comercial()
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
     * @return array{mostrar: bool, banco: string, conta_bancaria: string}
     */
    private function resolverBoletoInfo(ContaReceber $conta): array
    {
        $ehBoleto = $conta->isFormaBoleto();

        if (! $ehBoleto) {
            return [
                'mostrar' => false,
                'banco' => '—',
                'conta_bancaria' => '—',
            ];
        }

        $boleto = Boleto::query()
            ->where('conta_receber_id', $conta->id)
            ->with('boletoContaApi')
            ->orderByRaw("case status when 'A' then 0 when 'P' then 1 when 'B' then 2 else 3 end")
            ->orderByDesc('id')
            ->first();

        if (! $boleto instanceof Boleto) {
            return [
                'mostrar' => true,
                'banco' => '—',
                'conta_bancaria' => 'Boleto ainda não gerado',
            ];
        }

        $contaApi = $boleto->boletoContaApi;
        if (! $contaApi instanceof BoletoContaApi && $boleto->boleto_conta_api_id) {
            $contaApi = BoletoContaApi::query()->find($boleto->boleto_conta_api_id);
        }

        if (! $contaApi instanceof BoletoContaApi) {
            return [
                'mostrar' => true,
                'banco' => '—',
                'conta_bancaria' => '—',
            ];
        }

        $compe = $contaApi->bancoCompe();
        $bancoNome = match ($compe) {
            EmpresaParametros::BOLETO_BANCO_AILOS => 'Ailos',
            EmpresaParametros::BOLETO_BANCO_SICREDI => 'Sicredi',
            default => $compe !== '' ? $compe : 'Banco',
        };

        $nomeConta = trim((string) ($contaApi->nome ?? ''));
        $agencia = trim((string) ($contaApi->agencia ?? ''));
        $agenciaDv = trim((string) ($contaApi->agencia_dv ?? ''));
        $numeroConta = trim((string) ($contaApi->conta ?? ''));
        $contaDv = trim((string) ($contaApi->conta_dv ?? ''));

        $agenciaFmt = $agencia !== ''
            ? ($agenciaDv !== '' ? $agencia.'-'.$agenciaDv : $agencia)
            : '';
        $contaFmt = $numeroConta !== ''
            ? ($contaDv !== '' ? $numeroConta.'-'.$contaDv : $numeroConta)
            : '';

        $partes = array_values(array_filter([
            $nomeConta !== '' ? $nomeConta : null,
            $agenciaFmt !== '' ? 'Ag '.$agenciaFmt : null,
            $contaFmt !== '' ? 'Cc '.$contaFmt : null,
        ]));

        return [
            'mostrar' => true,
            'banco' => mb_strtoupper($bancoNome, 'UTF-8'),
            'conta_bancaria' => $partes !== []
                ? mb_strtoupper(implode(' · ', $partes), 'UTF-8')
                : mb_strtoupper($contaApi->rotulo(), 'UTF-8'),
        ];
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
                    return $this->linhaItem(
                        numero: (string) ($index + 1),
                        codigo: $this->formatCodigo($item->product?->codigo),
                        descricao: mb_strtoupper((string) ($item->descricao ?? $item->product?->descricao ?? '—'), 'UTF-8'),
                        quantidade: (float) $item->quantidade,
                        unidade: mb_strtoupper((string) ($item->unidade ?? $item->product?->unidade ?? 'UN'), 'UTF-8'),
                        preco: (float) $item->preco_unitario,
                        total: (float) $item->total,
                        descontoInformado: isset($item->desconto) ? (float) $item->desconto : null,
                    );
                })
                ->all();
        }

        if ($origem['orcamento'] instanceof Orcamento || $origem['orcamento'] instanceof Pedido) {
            return $origem['orcamento']->itens
                ->sortBy('item')
                ->values()
                ->map(function ($item): array {
                    /** @var OrcamentoItem|PedidoItem $item */
                    return $this->linhaItem(
                        numero: (string) $item->item,
                        codigo: $this->formatCodigo($item->product?->codigo),
                        descricao: mb_strtoupper((string) ($item->descricao ?? $item->product?->descricao ?? '—'), 'UTF-8'),
                        quantidade: (float) $item->quantidade,
                        unidade: mb_strtoupper((string) ($item->product?->unidade ?? 'UN'), 'UTF-8'),
                        preco: (float) $item->preco_unitario,
                        total: (float) $item->total,
                        descontoInformado: (float) ($item->desconto ?? 0),
                    );
                })
                ->all();
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function itemDeVenda(VendaItem $item, int $numero): array
    {
        return $this->linhaItem(
            numero: (string) $numero,
            codigo: $this->formatCodigo($item->product?->codigo),
            descricao: mb_strtoupper((string) ($item->product?->descricao ?? '—'), 'UTF-8'),
            quantidade: (float) $item->quantidade,
            unidade: mb_strtoupper((string) ($item->product?->unidade ?? 'UN'), 'UTF-8'),
            preco: (float) $item->valor_item,
            total: (float) $item->total,
        );
    }

    /**
     * @return array<string, string|float>
     */
    private function linhaItem(
        string $numero,
        string $codigo,
        string $descricao,
        float $quantidade,
        string $unidade,
        float $preco,
        float $total,
        ?float $descontoInformado = null,
    ): array {
        $bruto = round($quantidade * $preco, 2);
        $total = round($total, 2);
        $desconto = ($descontoInformado !== null && $descontoInformado > 0)
            ? round($descontoInformado, 2)
            : round(max(0, $bruto - $total), 2);

        return [
            'item' => $numero,
            'codigo' => $codigo,
            'descricao' => $descricao,
            'quantidade' => ErpMoney::formatBr($quantidade, 3),
            'unidade' => $unidade,
            'preco' => ErpMoney::formatBr($preco),
            'desconto' => ErpMoney::formatBr($desconto),
            'total' => ErpMoney::formatBr($total),
            'bruto_valor' => $bruto,
            'desconto_valor' => $desconto,
            'total_valor' => $total,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @param  array{subtotal: string, desconto: string, total: string}  $origem
     * @return array{subtotal: string, desconto: string, total: string}
     */
    private function totaisDosItens(array $itens, array $origem): array
    {
        if ($itens === []) {
            return [
                'subtotal' => $origem['subtotal'],
                'desconto' => $origem['desconto'],
                'total' => $origem['total'],
            ];
        }

        $bruto = 0.0;
        $desconto = 0.0;
        $total = 0.0;

        foreach ($itens as $item) {
            $bruto += (float) ($item['bruto_valor'] ?? 0);
            $desconto += (float) ($item['desconto_valor'] ?? 0);
            $total += (float) ($item['total_valor'] ?? 0);
        }

        return [
            'subtotal' => ErpMoney::formatBr(round($bruto, 2)),
            'desconto' => ErpMoney::formatBr(round($desconto, 2)),
            'total' => ErpMoney::formatBr(round($total, 2)),
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
            'valor_recebido' => ErpMoney::formatBr((float) $conta->valor_recebido),
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
