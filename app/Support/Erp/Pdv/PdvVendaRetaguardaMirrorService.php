<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvVenda;
use App\Models\Person;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PdvVendaRetaguardaMirrorService
{
    /** @deprecated Use Person::CODIGO_CONSUMIDOR_FINAL */
    public const CONSUMIDOR_FINAL_CODIGO = Person::CODIGO_CONSUMIDOR_FINAL;

    /**
     * @param  Venda|null  $pedidoOrigem  Pedido pendente importado no PDV: vira o registro
     *                                    da venda (fechado, itens do cupom) em vez de criar outro.
     */
    public function espelhar(PdvVenda $pdvVenda, ?Venda $pedidoOrigem = null): Venda
    {
        if ($pdvVenda->venda_id) {
            $existing = Venda::query()->find($pdvVenda->venda_id);

            if ($existing) {
                $this->sincronizarOrigemMovimentacoesEstoque($pdvVenda, $existing);

                return $existing;
            }
        }

        $pdvVenda->loadMissing(['itens', 'pagamentos', 'sessao']);

        if ($pedidoOrigem !== null) {
            return $this->fecharPedidoOrigem($pdvVenda, $pedidoOrigem);
        }

        $fechamento = $this->resolveFechamento($pdvVenda);
        $horaAbertura = $pdvVenda->aberto_em
            ? ErpTimezone::toLocal($pdvVenda->aberto_em)->format('H:i:s')
            : null;

        $venda = Venda::query()->create([
            'empresa_id' => $pdvVenda->sessao?->empresa_id
                ?? ErpContext::currentEmpresaId(),
            'numero' => Venda::nextNumero(),
            'data' => $fechamento->toDateString(),
            'hora' => $fechamento->format('H:i:s'),
            'hora_abertura' => $horaAbertura,
            'cliente_id' => $this->resolveClienteId($pdvVenda),
            'vendedor_id' => $pdvVenda->vendedor_id,
            'vendedor_nome' => $pdvVenda->vendedor_nome,
            'total' => $pdvVenda->total,
            'forma_pagamento' => $this->resolveFormaPagamento($pdvVenda),
            'status' => Venda::STATUS_FECHADO,
            'tipo' => $pdvVenda->fiscal ? Venda::TIPO_CUPOM : Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_PDV,
        ]);

        foreach ($pdvVenda->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            VendaItem::query()->create([
                'venda_id' => $venda->id,
                'product_id' => $item->product_id,
                'quantidade' => $item->quantidade,
                'valor_item' => $item->preco_unitario,
                'total' => $item->total,
            ]);
        }

        $pdvVenda->update(['venda_id' => $venda->id]);
        $this->sincronizarOrigemMovimentacoesEstoque($pdvVenda, $venda);

        return $venda;
    }

    /**
     * Fecha o pedido importado com os dados da venda PDV. Pedido em aberto não baixou
     * estoque nem gerou financeiro; isso foi feito pelo PDV nesta venda.
     */
    private function fecharPedidoOrigem(PdvVenda $pdvVenda, Venda $pedido): Venda
    {
        $fechamento = $this->resolveFechamento($pdvVenda);

        $pedido->forceFill([
            'data' => $fechamento->toDateString(),
            'hora' => $fechamento->format('H:i:s'),
            'cliente_id' => $this->resolveClienteId($pdvVenda),
            'vendedor_id' => $pdvVenda->vendedor_id ?? $pedido->vendedor_id,
            'vendedor_nome' => $pdvVenda->vendedor_nome ?: $pedido->vendedor_nome,
            'total' => $pdvVenda->total,
            'forma_pagamento' => $this->resolveFormaPagamento($pdvVenda),
            'status' => Venda::STATUS_FECHADO,
            'tipo' => $pdvVenda->fiscal ? Venda::TIPO_CUPOM : Venda::TIPO_PEDIDO,
        ])->save();

        VendaItem::query()->where('venda_id', $pedido->id)->delete();

        foreach ($pdvVenda->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            VendaItem::query()->create([
                'venda_id' => $pedido->id,
                'product_id' => $item->product_id,
                'quantidade' => $item->quantidade,
                'valor_item' => $item->preco_unitario,
                'total' => $item->total,
            ]);
        }

        $pdvVenda->update(['venda_id' => $pedido->id]);
        $this->sincronizarOrigemMovimentacoesEstoque($pdvVenda, $pedido);

        return $pedido;
    }

    /**
     * A baixa de estoque no PDV ocorre antes do espelho retaguarda.
     * Atualiza o extrato para o número da venda que o usuário vê na lista (ex.: 479),
     * não o número sequencial do caixa PDV (ex.: 1).
     */
    private function sincronizarOrigemMovimentacoesEstoque(PdvVenda $pdvVenda, Venda $venda): void
    {
        if (! Schema::hasTable('estoque_movimentacoes')) {
            return;
        }

        $numero = ltrim((string) ($venda->numero ?? ''), '0');
        if ($numero === '') {
            $numero = (string) ($venda->numero ?? $venda->id);
        }

        DB::table('estoque_movimentacoes')
            ->where('origem_tipo', 'pdv_venda')
            ->where('origem_id', (int) $pdvVenda->id)
            ->update([
                'origem_tipo' => 'venda',
                'origem_id' => (int) $venda->id,
                'origem_numero' => $numero,
            ]);

        $nfce = $pdvVenda->nfce()->first();
        if ($nfce !== null) {
            \App\Support\Erp\EstoqueMovimentacaoDocumento::sincronizarNfcePdvVenda($nfce);
        }
    }

    public function estornar(PdvVenda $pdvVenda): void
    {
        if (! $pdvVenda->venda_id) {
            return;
        }

        Venda::query()
            ->whereKey($pdvVenda->venda_id)
            ->update(['status' => Venda::STATUS_CANCELADO]);
    }

    private function resolveFormaPagamento(PdvVenda $pdvVenda): string
    {
        if ($pdvVenda->pagamentos->isNotEmpty()) {
            // Só o nome da forma (CREDIARIO, PIX…), sem (10x)/canhoto — evita “formas” novas no relatório.
            return $pdvVenda->pagamentos
                ->map(fn ($pagamento): string => trim((string) ($pagamento->forma ?? '')))
                ->filter(fn (string $forma): bool => $forma !== '')
                ->unique()
                ->values()
                ->implode(' / ');
        }

        return (string) ($pdvVenda->forma_pagamento ?? '');
    }

    private function resolveClienteId(PdvVenda $pdvVenda): int
    {
        if ($pdvVenda->person_id) {
            return (int) $pdvVenda->person_id;
        }

        return $this->resolveConsumidorFinalClienteId();
    }

    private function resolveConsumidorFinalClienteId(): int
    {
        return (int) Person::resolveConsumidorFinal()->id;
    }

    private function resolveFechamento(PdvVenda $pdvVenda): Carbon
    {
        $moment = $pdvVenda->fechado_em ?? $pdvVenda->created_at ?? now();

        return ErpTimezone::toLocal($moment);
    }
}
