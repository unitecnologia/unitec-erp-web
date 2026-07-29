<?php

namespace App\Support\Logistica;

use App\Models\Entrega;
use App\Models\EntregaEvento;
use App\Models\EntregaItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\Expedicao\ExpedicaoConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ExpedicaoService
{
    public function criarAPartirDaVenda(Venda $venda, string $origem): ?Entrega
    {
        if ($venda->status !== Venda::STATUS_FECHADO) {
            return null;
        }

        if (Entrega::query()->where('venda_id', $venda->id)->exists()) {
            return Entrega::query()->where('venda_id', $venda->id)->first();
        }

        if (! ExpedicaoConfig::make()->origemHabilitada($origem)) {
            return null;
        }

        $venda->loadMissing(['itens.product', 'cliente']);

        return DB::transaction(function () use ($venda, $origem): Entrega {
            $cliente = $venda->cliente;
            $endereco = $this->snapshotEndereco($cliente);

            $entrega = Entrega::query()->create([
                'numero' => Entrega::nextNumero(),
                'venda_id' => $venda->id,
                'cliente_id' => $venda->cliente_id,
                'cliente_nome' => $cliente?->nome_razao ?? $cliente?->apelido_fantasia ?? 'CONSUMIDOR',
                'cliente_telefone' => $cliente?->celular1 ?: $cliente?->fone1,
                ...$endereco,
                'status' => Entrega::STATUS_PENDENTE,
                'origem' => $origem,
                'usuario_expedicao_id' => Auth::id(),
            ]);

            foreach ($venda->itens as $item) {
                if (! $item->product_id) {
                    continue;
                }

                /** @var Product|null $product */
                $product = $item->relationLoaded('product') ? $item->product : Product::query()->find($item->product_id);

                if ($product?->is_servico) {
                    continue;
                }

                EntregaItem::query()->create([
                    'entrega_id' => $entrega->id,
                    'venda_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'codigo' => $product?->codigo,
                    'codigo_barras' => $product?->codigo_barras,
                    'descricao' => $product?->descricao ?? 'Item',
                    'localizacao' => $product?->localizacao,
                    'quantidade_pedida' => $item->quantidade,
                    'quantidade_expedida' => 0,
                    'quantidade_separada' => 0,
                    'quantidade_conferida' => 0,
                ]);
            }

            $this->registrarEvento($entrega, null, Entrega::STATUS_PENDENTE, 'Expedição gerada automaticamente.');

            return $entrega->fresh(['itens']);
        });
    }

    /**
     * @param  list<int>  $entregaIds
     */
    public function iniciarSessao(array $entregaIds, ?User $user = null): void
    {
        Entrega::query()
            ->whereIn('id', $entregaIds)
            ->where('status', Entrega::STATUS_PENDENTE)
            ->update([
                'status' => Entrega::STATUS_EM_EXPEDICAO,
                'usuario_expedicao_id' => $user?->id ?? Auth::id(),
            ]);
    }

    public function biparPorCodigo(string $codigo, float $quantidade, array $entregaIds): ?EntregaItem
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        $item = EntregaItem::query()
            ->whereHas('entrega', fn ($q) => $q->whereIn('id', $entregaIds)->whereIn('status', [
                Entrega::STATUS_PENDENTE,
                Entrega::STATUS_EM_EXPEDICAO,
            ]))
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo_barras', $codigo)
                    ->orWhere('codigo', $codigo);
            })
            ->whereRaw('quantidade_expedida < quantidade_pedida')
            ->orderBy('id')
            ->first();

        if ($item === null) {
            return null;
        }

        return $this->incrementarExpedicao($item, $quantidade);
    }

    public function incrementarExpedicao(EntregaItem $item, float $quantidade): EntregaItem
    {
        if ($quantidade <= 0) {
            throw new InvalidArgumentException('Quantidade deve ser maior que zero.');
        }

        $pedida = (float) $item->quantidade_pedida;
        $expedida = (float) $item->quantidade_expedida;
        $nova = min($pedida, $expedida + $quantidade);

        $item->forceFill(['quantidade_expedida' => $nova])->save();

        return $item->fresh();
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function estornarItens(array $itemIds): void
    {
        EntregaItem::query()
            ->whereIn('id', $itemIds)
            ->update(['quantidade_expedida' => 0]);
    }

    /**
     * @param  list<int>  $entregaIds
     * @return array{confirmados: int, parciais: int}
     */
    public function confirmarExpedicao(array $entregaIds, ?User $user = null): array
    {
        $confirmados = 0;
        $parciais = 0;

        $entregas = Entrega::query()
            ->with('itens')
            ->whereIn('id', $entregaIds)
            ->whereIn('status', [Entrega::STATUS_PENDENTE, Entrega::STATUS_EM_EXPEDICAO])
            ->get();

        foreach ($entregas as $entrega) {
            if ($entrega->estaCompleta()) {
                $de = $entrega->status;
                $entrega->forceFill([
                    'status' => Entrega::STATUS_EXPEDIDO,
                    'expedido_em' => now(),
                    'finalizado_em' => now(),
                ])->save();

                $this->registrarEvento($entrega, $de, Entrega::STATUS_EXPEDIDO, 'Expedição confirmada.', $user);
                $confirmados++;
            } else {
                $parciais++;
            }
        }

        return ['confirmados' => $confirmados, 'parciais' => $parciais];
    }

    /**
     * @return array{peso_kg: float, itens_sem_peso: int}
     */
    public function calcularPesoExpedicao(Entrega $entrega): array
    {
        if (! $entrega->relationLoaded('itens')) {
            $entrega->loadMissing(['itens.product']);
        }

        $peso = 0.0;
        $itensSemPeso = 0;

        foreach ($entrega->itens as $item) {
            $quantidade = (float) $item->quantidade_expedida;

            if ($quantidade <= 0) {
                continue;
            }

            $pesoKg = $item->product?->peso_kg;

            if ($pesoKg === null || (float) $pesoKg <= 0) {
                $itensSemPeso++;

                continue;
            }

            $peso += $quantidade * (float) $pesoKg;
        }

        return [
            'peso_kg' => round($peso, 3),
            'itens_sem_peso' => $itensSemPeso,
        ];
    }

    /**
     * @param  array{
     *     tipo_saida: string,
     *     qtd_volumes?: int|null,
     *     peso_calculado_kg?: float|null,
     *     transportadora_id?: int|null,
     * }  $dados
     */
    public function confirmarExpedicaoPedido(Entrega $entrega, array $dados, ?User $user = null): bool
    {
        if (! $entrega->estaCompleta()) {
            return false;
        }

        if (! in_array($entrega->status, [Entrega::STATUS_PENDENTE, Entrega::STATUS_EM_EXPEDICAO], true)) {
            return false;
        }

        $tipoSaida = $dados['tipo_saida'] ?? null;

        if (! in_array($tipoSaida, [Entrega::TIPO_SAIDA_ENTREGA, Entrega::TIPO_SAIDA_RETIRADA], true)) {
            throw new InvalidArgumentException('Tipo de saída inválido.');
        }

        $de = $entrega->status;

        $payload = [
            'status' => Entrega::STATUS_EXPEDIDO,
            'expedido_em' => now(),
            'finalizado_em' => now(),
            'tipo_saida' => $tipoSaida,
        ];

        if ($tipoSaida === Entrega::TIPO_SAIDA_ENTREGA) {
            $payload['qtd_volumes'] = $dados['qtd_volumes'] ?? null;
            $payload['peso_calculado_kg'] = $dados['peso_calculado_kg'] ?? null;
            $payload['transportadora_id'] = $dados['transportadora_id'] ?? null;
        } else {
            $payload['qtd_volumes'] = null;
            $payload['peso_calculado_kg'] = null;
            $payload['transportadora_id'] = null;
        }

        $entrega->forceFill($payload)->save();

        $observacao = $tipoSaida === Entrega::TIPO_SAIDA_RETIRADA
            ? 'Expedição confirmada — retirada pelo cliente.'
            : 'Expedição confirmada — envio para entrega.';

        $this->registrarEvento($entrega, $de, Entrega::STATUS_EXPEDIDO, $observacao, $user);

        return true;
    }

    public function registrarRomaneioRetiradaEmitido(Entrega $entrega): void
    {
        $entrega->forceFill(['romaneio_retirada_emitido_em' => now()])->save();
    }

    public function cancelarPorVenda(Venda $venda, ?string $motivo = null): void
    {
        $entrega = Entrega::query()->where('venda_id', $venda->id)->first();

        if ($entrega === null) {
            return;
        }

        if ($entrega->status === Entrega::STATUS_EXPEDIDO) {
            return;
        }

        if ($entrega->status === Entrega::STATUS_CANCELADO) {
            return;
        }

        $this->transicionar($entrega, Entrega::STATUS_CANCELADO, Auth::user(), $motivo ?? 'Venda cancelada/estornada.');
    }

    public function transicionar(
        Entrega $entrega,
        string $novoStatus,
        ?User $user = null,
        ?string $observacao = null,
    ): Entrega {
        $statusAnterior = $entrega->status;

        if ($statusAnterior === $novoStatus) {
            return $entrega;
        }

        $entrega->forceFill(['status' => $novoStatus])->save();
        $this->registrarEvento($entrega, $statusAnterior, $novoStatus, $observacao, $user);

        return $entrega->fresh();
    }

    /**
     * @return Collection<int, Entrega>
     */
    public function listarControle(
        string $periodoDe,
        string $periodoAte,
        string $statusFiltro,
        ?string $numeroPedido = null,
    ): Collection {
        $query = Entrega::query()
            ->with(['venda', 'usuarioExpedicao'])
            ->whereIn('status', Entrega::statusControleFiltro($statusFiltro))
            ->whereHas('venda', function ($venda) use ($periodoDe, $periodoAte, $numeroPedido): void {
                if ($periodoDe !== '') {
                    $venda->whereDate('data', '>=', $periodoDe);
                }

                if ($periodoAte !== '') {
                    $venda->whereDate('data', '<=', $periodoAte);
                }

                if ($numeroPedido !== null && $numeroPedido !== '') {
                    $term = ltrim(trim($numeroPedido), '0') ?: '0';
                    $venda->where('numero', 'like', '%' . $term . '%');
                }
            })
            ->orderByDesc('created_at');

        return $query->get();
    }

    /**
     * @return array<string, string|null>
     */
    private function snapshotEndereco(?Person $cliente): array
    {
        if ($cliente === null) {
            return [
                'endereco_cep' => null,
                'endereco_logradouro' => null,
                'endereco_numero' => null,
                'endereco_complemento' => null,
                'endereco_bairro' => null,
                'endereco_cidade' => null,
                'endereco_uf' => null,
                'endereco_completo' => null,
            ];
        }

        return [
            'endereco_cep' => $cliente->cep,
            'endereco_logradouro' => $cliente->endereco,
            'endereco_numero' => $cliente->numero,
            'endereco_complemento' => $cliente->complemento,
            'endereco_bairro' => $cliente->bairro,
            'endereco_cidade' => $cliente->cidade_nome,
            'endereco_uf' => $cliente->uf,
            'endereco_completo' => $cliente->endereco_lista,
        ];
    }

    private function registrarEvento(
        Entrega $entrega,
        ?string $deStatus,
        string $paraStatus,
        ?string $observacao = null,
        ?User $user = null,
    ): void {
        EntregaEvento::query()->create([
            'entrega_id' => $entrega->id,
            'user_id' => $user?->id ?? Auth::id(),
            'de_status' => $deStatus,
            'para_status' => $paraStatus,
            'observacao' => $observacao,
            'created_at' => now(),
        ]);
    }
}
