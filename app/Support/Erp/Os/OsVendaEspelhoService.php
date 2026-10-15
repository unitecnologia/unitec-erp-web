<?php

namespace App\Support\Erp\Os;

use App\Models\FormaPagamento;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Illuminate\Support\Facades\Schema;

/**
 * Espelho comercial da OS na Lista de Vendas.
 * Não movimenta estoque, financeiro, caixa nem comissão — só cabeçalho/itens.
 */
final class OsVendaEspelhoService
{
    /**
     * @param  list<array{forma: FormaPagamento, valor: string}>  $linhas
     */
    public function sincronizarAoFaturar(OrdemServico $os, array $linhas): ?Venda
    {
        if (! Schema::hasColumn($os->getTable(), 'venda_id')) {
            return null;
        }

        $os->loadMissing(['itens', 'atendente']);

        $formaPagamento = $this->rotuloFormas($linhas);
        $empresaId = $os->empresa_id ? (int) $os->empresa_id : ErpContext::currentEmpresaId();
        $agora = ErpTimezone::toLocal();
        $data = $os->data_termino
            ? (string) $os->data_termino
            : $agora->toDateString();
        $hora = $os->hora_termino
            ? substr((string) $os->hora_termino, 0, 8)
            : $agora->format('H:i:s');

        $payload = [
            'empresa_id' => $empresaId,
            'data' => $data,
            'hora' => $hora,
            'cliente_id' => $os->cliente_id,
            'vendedor_id' => $os->atendente_id,
            'vendedor_nome' => $os->atendente?->nome,
            'total' => round((float) ($os->total_geral ?? 0), 2),
            'forma_pagamento' => $formaPagamento,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_OS,
        ];

        $venda = $this->resolverVendaExistente($os);

        if ($venda instanceof Venda) {
            $venda->forceFill($payload)->save();
        } else {
            $venda = Venda::query()->create(array_merge($payload, [
                'numero' => Venda::nextNumero(),
                'hora_abertura' => $os->hora_inicio
                    ? substr((string) $os->hora_inicio, 0, 8)
                    : null,
            ]));
        }

        $this->sincronizarItens($venda, $os);

        if ((int) ($os->venda_id ?? 0) !== (int) $venda->id) {
            $os->forceFill(['venda_id' => $venda->id])->save();
        }

        return $venda;
    }

    public function marcarAberta(OrdemServico $os): void
    {
        $venda = $this->vendaDaOs($os);

        if (! $venda instanceof Venda) {
            return;
        }

        if ($venda->status === Venda::STATUS_CANCELADO) {
            return;
        }

        if ($venda->status !== Venda::STATUS_ABERTO) {
            $venda->forceFill(['status' => Venda::STATUS_ABERTO])->save();
        }
    }

    public function marcarCancelada(OrdemServico $os): void
    {
        $venda = $this->vendaDaOs($os);

        if (! $venda instanceof Venda) {
            return;
        }

        if ($venda->status !== Venda::STATUS_CANCELADO) {
            $venda->forceFill(['status' => Venda::STATUS_CANCELADO])->save();
        }
    }

    private function resolverVendaExistente(OrdemServico $os): ?Venda
    {
        $vendaId = (int) ($os->venda_id ?? 0);

        if ($vendaId <= 0) {
            return null;
        }

        /** @var Venda|null $venda */
        $venda = Venda::query()->whereKey($vendaId)->lockForUpdate()->first();

        if (! $venda instanceof Venda) {
            return null;
        }

        // Cancelada definitiva: próximo faturamento (se ocorrer) cria nova venda.
        if ($venda->status === Venda::STATUS_CANCELADO) {
            return null;
        }

        return $venda;
    }

    private function vendaDaOs(OrdemServico $os): ?Venda
    {
        if (! Schema::hasColumn($os->getTable(), 'venda_id')) {
            return null;
        }

        $vendaId = (int) ($os->venda_id ?? 0);

        if ($vendaId <= 0) {
            return null;
        }

        /** @var Venda|null $venda */
        $venda = Venda::query()->whereKey($vendaId)->lockForUpdate()->first();

        return $venda instanceof Venda ? $venda : null;
    }

    private function sincronizarItens(Venda $venda, OrdemServico $os): void
    {
        VendaItem::query()->where('venda_id', (int) $venda->id)->delete();

        foreach ($os->itens as $item) {
            if (! $item instanceof OrdemServicoItem || ! $item->product_id) {
                continue;
            }

            $qtd = (float) ($item->qtd ?? 0);

            if ($qtd <= 0) {
                continue;
            }

            $total = round((float) ($item->total ?? 0), 2);
            $valorItem = round($total / $qtd, 2);

            VendaItem::query()->create([
                'venda_id' => $venda->id,
                'product_id' => (int) $item->product_id,
                'quantidade' => $qtd,
                'valor_item' => $valorItem,
                'total' => $total,
                'comissao_pct' => 0,
            ]);
        }
    }

    /**
     * @param  list<array{forma: FormaPagamento, valor: string}>  $linhas
     */
    private function rotuloFormas(array $linhas): ?string
    {
        $labels = [];

        foreach ($linhas as $linha) {
            $forma = $linha['forma'] ?? null;

            if (! $forma instanceof FormaPagamento) {
                continue;
            }

            $label = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

            if ($label !== '' && ! in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }

        if ($labels === []) {
            return null;
        }

        return implode(' + ', $labels);
    }
}
