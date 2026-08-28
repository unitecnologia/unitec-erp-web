<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvVenda;
use App\Models\Person;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Carbon;

final class PdvVendaRetaguardaMirrorService
{
    /** @deprecated Use Person::CODIGO_CONSUMIDOR_FINAL */
    public const CONSUMIDOR_FINAL_CODIGO = Person::CODIGO_CONSUMIDOR_FINAL;

    public function espelhar(PdvVenda $pdvVenda): Venda
    {
        if ($pdvVenda->venda_id) {
            $existing = Venda::query()->find($pdvVenda->venda_id);

            if ($existing) {
                return $existing;
            }
        }

        $pdvVenda->loadMissing(['itens', 'pagamentos', 'sessao']);

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

        return $venda;
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
