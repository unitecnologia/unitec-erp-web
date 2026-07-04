<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvVenda;
use App\Models\Person;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Carbon;

final class PdvVendaRetaguardaMirrorService
{
    public const CONSUMIDOR_FINAL_CODIGO = 'CF';

    public function espelhar(PdvVenda $pdvVenda): Venda
    {
        if ($pdvVenda->venda_id) {
            $existing = Venda::query()->find($pdvVenda->venda_id);

            if ($existing) {
                return $existing;
            }
        }

        $pdvVenda->loadMissing('itens');

        $fechamento = $this->resolveFechamento($pdvVenda);

        $venda = Venda::query()->create([
            'numero' => Venda::nextNumero(),
            'data' => $fechamento->toDateString(),
            'hora' => $fechamento->format('H:i:s'),
            'cliente_id' => $this->resolveClienteId($pdvVenda),
            'vendedor_id' => $pdvVenda->vendedor_id,
            'vendedor_nome' => $pdvVenda->vendedor_nome,
            'total' => $pdvVenda->total,
            'forma_pagamento' => $pdvVenda->forma_pagamento,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_CUPOM,
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

    private function resolveClienteId(PdvVenda $pdvVenda): int
    {
        if ($pdvVenda->person_id) {
            return (int) $pdvVenda->person_id;
        }

        return $this->resolveConsumidorFinalClienteId();
    }

    private function resolveConsumidorFinalClienteId(): int
    {
        $person = Person::query()
            ->where('codigo', self::CONSUMIDOR_FINAL_CODIGO)
            ->first();

        if ($person) {
            return (int) $person->id;
        }

        $person = Person::query()->create([
            'codigo' => self::CONSUMIDOR_FINAL_CODIGO,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CONSUMIDOR FINAL',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        return (int) $person->id;
    }

    private function resolveFechamento(PdvVenda $pdvVenda): Carbon
    {
        $moment = $pdvVenda->fechado_em ?? $pdvVenda->created_at ?? now();

        return ErpTimezone::toLocal($moment);
    }
}
