<?php

namespace App\Http\Controllers\Api\Entregas;

use App\Models\Carga;
use App\Models\CargaEntrega;
use App\Models\CargaEntregaItem;
use App\Models\CargaPedido;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Sync Unitec Entregas — pull de cargas + push de conclusões (idempotente via app_local_uuid).
 */
class SyncController
{
    public function pull(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = (int) ($user->empresa_id ?? 0);

        if ($empresaId <= 0) {
            return response()->json([
                'message' => 'Usuário sem empresa. Sessão inválida.',
                'code' => 'empresa_required',
            ], 403);
        }

        $cargas = Carga::query()
            ->with([
                'motorista:id,proprietario,apelido',
                'veiculo:id,placa,descricao',
                'pedidos' => function ($q): void {
                    $q->with([
                        'cliente:id,nome_razao,cpf_cnpj,fone1,fone2,celular1,celular2,cep,endereco,numero,bairro,cidade_nome,uf',
                        'itens.product:id,codigo,descricao,unidade',
                    ])->orderBy('numero');
                },
            ])
            ->where('empresa_id', $empresaId)
            ->where('entregador_user_id', $user->id)
            ->where('status', Carga::STATUS_FECHADA)
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        $payload = $cargas->map(fn (Carga $carga): array => $this->mapCarga($carga))->values()->all();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'cargas' => $payload,
        ]);
    }

    /**
     * Recebe conclusão de entrega + foto (multipart).
     * Idempotente por app_local_uuid e por (carga_id, pedido_id).
     */
    public function push(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $empresaId = (int) ($user->empresa_id ?? 0);

        if ($empresaId <= 0) {
            return response()->json([
                'message' => 'Usuário sem empresa. Sessão inválida.',
                'code' => 'empresa_required',
            ], 403);
        }

        $data = $request->validate([
            'app_local_uuid' => ['required', 'uuid'],
            'carga_id' => ['required', 'integer'],
            'pedido_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'in:entregue,parcial,nao_entregue'],
            'motivo_nao_entrega' => ['nullable', 'string', 'max:60'],
            'observacao' => ['nullable', 'string', 'max:2000'],
            'concluida_em' => ['nullable', 'date'],
            'foto' => ['nullable', 'file', 'image', 'max:10240'],
            'assinatura' => ['nullable', 'file', 'image', 'max:5120'],
            'itens' => ['nullable', 'string'], // JSON array
        ]);

        $status = (string) $data['status'];
        $motivo = isset($data['motivo_nao_entrega']) ? trim((string) $data['motivo_nao_entrega']) : '';
        $itensPayload = $this->parseItensPayload($data['itens'] ?? null);

        if ($status === CargaEntrega::STATUS_NAO_ENTREGUE) {
            $motivosValidos = array_keys(CargaEntrega::motivosNaoEntrega());
            if ($motivo === '' || ! in_array($motivo, $motivosValidos, true)) {
                throw ValidationException::withMessages([
                    'motivo_nao_entrega' => 'Informe o motivo da não entrega.',
                ]);
            }
            if ($motivo === CargaEntrega::MOTIVO_OUTRO && blank($data['observacao'] ?? null)) {
                throw ValidationException::withMessages([
                    'observacao' => 'Informe a observação quando o motivo for Outro.',
                ]);
            }
        } else {
            $motivo = null;
            if ($status === CargaEntrega::STATUS_PARCIAL && $itensPayload === []) {
                throw ValidationException::withMessages([
                    'itens' => 'Informe ao menos um item entregue na parcial.',
                ]);
            }
        }

        $uuid = (string) $data['app_local_uuid'];

        $existente = CargaEntrega::query()->where('app_local_uuid', $uuid)->first();
        if ($existente !== null) {
            return response()->json([
                'message' => 'Entrega já registrada.',
                'duplicated' => true,
                'entrega' => $this->mapEntrega($existente),
            ]);
        }

        $cargaId = (int) $data['carga_id'];
        $pedidoId = (int) $data['pedido_id'];

        $carga = Carga::query()
            ->whereKey($cargaId)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($carga === null) {
            throw ValidationException::withMessages([
                'carga_id' => 'Carga não encontrada para esta empresa.',
            ]);
        }

        if ((int) $carga->entregador_user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Carga não atribuída a este entregador.',
                'code' => 'entregador_mismatch',
            ], 403);
        }

        $pedidoNaCarga = CargaPedido::query()
            ->where('carga_id', $cargaId)
            ->where('pedido_id', $pedidoId)
            ->exists();

        if (! $pedidoNaCarga) {
            throw ValidationException::withMessages([
                'pedido_id' => 'Pedido não pertence a esta carga.',
            ]);
        }

        $jaConcluido = CargaEntrega::query()
            ->where('carga_id', $cargaId)
            ->where('pedido_id', $pedidoId)
            ->first();

        if ($jaConcluido !== null) {
            return response()->json([
                'message' => 'Pedido já concluído nesta carga.',
                'duplicated' => true,
                'entrega' => $this->mapEntrega($jaConcluido),
            ]);
        }

        $concluidaEm = ! empty($data['concluida_em'])
            ? Carbon::parse($data['concluida_em'])
            : now();

        $entrega = DB::transaction(function () use ($request, $data, $uuid, $empresaId, $cargaId, $pedidoId, $user, $concluidaEm, $status, $motivo, $itensPayload): CargaEntrega {
            $dir = 'carga-entregas/'.$cargaId.'/'.$pedidoId;
            $fotoPath = null;
            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store($dir, 'public');
            }

            $assinaturaPath = null;
            if ($request->hasFile('assinatura')) {
                $assinaturaPath = $request->file('assinatura')->store($dir, 'public');
            }

            $entrega = CargaEntrega::query()->create([
                'empresa_id' => $empresaId,
                'carga_id' => $cargaId,
                'pedido_id' => $pedidoId,
                'entregador_user_id' => $user->id,
                'app_local_uuid' => $uuid,
                'status' => $status,
                'motivo_nao_entrega' => $motivo,
                'observacao' => $data['observacao'] ?? null,
                'foto_path' => $fotoPath,
                'assinatura_path' => $assinaturaPath,
                'concluida_em' => $concluidaEm,
            ]);

            foreach ($itensPayload as $item) {
                CargaEntregaItem::query()->create([
                    'carga_entrega_id' => $entrega->id,
                    'produto_id' => $item['produto_id'],
                    'codigo' => $item['codigo'],
                    'descricao' => $item['descricao'],
                    'quantidade_original' => $item['quantidade_original'],
                    'quantidade' => $item['quantidade'],
                    'unidade' => $item['unidade'],
                ]);
            }

            return $entrega->load('itens');
        });

        return response()->json([
            'message' => 'Entrega registrada.',
            'duplicated' => false,
            'entrega' => $this->mapEntrega($entrega),
        ], 201);
    }

    /**
     * @return list<array{produto_id: ?int, codigo: ?string, descricao: string, quantidade_original: float, quantidade: float, unidade: ?string}>
     */
    private function parseItensPayload(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'itens' => 'Itens inválidos.',
                ]);
            }
            $raw = $decoded;
        }

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $qtd = round((float) ($row['quantidade'] ?? 0), 3);
            if ($qtd <= 0) {
                continue;
            }
            $descricao = trim((string) ($row['descricao'] ?? ''));
            if ($descricao === '') {
                $descricao = 'PRODUTO';
            }
            $out[] = [
                'produto_id' => isset($row['produto_id']) && is_numeric($row['produto_id']) ? (int) $row['produto_id'] : null,
                'codigo' => filled($row['codigo'] ?? null) ? (string) $row['codigo'] : null,
                'descricao' => $descricao,
                'quantidade_original' => round((float) ($row['quantidade_original'] ?? $qtd), 3),
                'quantidade' => $qtd,
                'unidade' => filled($row['unidade'] ?? null) ? (string) $row['unidade'] : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEntrega(CargaEntrega $e): array
    {
        if (! $e->relationLoaded('itens')) {
            $e->load('itens');
        }

        return [
            'id' => (int) $e->id,
            'app_local_uuid' => (string) $e->app_local_uuid,
            'carga_id' => (int) $e->carga_id,
            'pedido_id' => (int) $e->pedido_id,
            'status' => (string) $e->status,
            'motivo_nao_entrega' => $e->motivo_nao_entrega,
            'observacao' => $e->observacao,
            'foto_path' => $e->foto_path,
            'foto_url' => filled($e->foto_path) ? Storage::disk('public')->url((string) $e->foto_path) : null,
            'assinatura_path' => $e->assinatura_path,
            'assinatura_url' => filled($e->assinatura_path) ? Storage::disk('public')->url((string) $e->assinatura_path) : null,
            'concluida_em' => optional($e->concluida_em)->toIso8601String(),
            'itens' => $e->itens->map(fn (CargaEntregaItem $it): array => [
                'produto_id' => $it->produto_id ? (int) $it->produto_id : null,
                'codigo' => $it->codigo,
                'descricao' => (string) $it->descricao,
                'quantidade_original' => round((float) $it->quantidade_original, 3),
                'quantidade' => round((float) $it->quantidade, 3),
                'unidade' => $it->unidade,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCarga(Carga $carga): array
    {
        $motorista = $carga->motorista;
        $veiculo = $carga->veiculo;

        return [
            'id' => (int) $carga->id,
            'numero' => (string) $carga->numero,
            'data' => optional($carga->data)->format('Y-m-d'),
            'motorista' => $motorista
                ? (trim((string) ($motorista->apelido ?: $motorista->proprietario)) ?: null)
                : null,
            'veiculo' => $veiculo
                ? trim((string) ($veiculo->placa.($veiculo->descricao ? ' — '.$veiculo->descricao : '')))
                : null,
            'observacao' => $carga->observacao,
            'status' => (string) $carga->status,
            'pedidos' => $carga->pedidos->map(fn (Venda $pedido): array => $this->mapPedido($pedido))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPedido(Venda $pedido): array
    {
        $cliente = $pedido->cliente;
        $telefone = null;
        $documento = null;
        $endereco = null;

        if ($cliente !== null) {
            $telefone = collect([
                $cliente->celular1,
                $cliente->fone1,
                $cliente->celular2,
                $cliente->fone2,
            ])->map(fn ($v) => trim((string) $v))->first(fn (string $v): bool => $v !== '');

            $documento = filled($cliente->cpf_cnpj) ? (string) $cliente->cpf_cnpj : null;
            $endereco = $this->enderecoCompleto($cliente);
        }

        return [
            'pedido_id' => (int) $pedido->id,
            'numero' => (string) $pedido->numero,
            'cliente' => (string) ($cliente?->nome_razao ?: 'CONSUMIDOR'),
            'documento' => $documento,
            'telefone' => $telefone,
            'endereco' => $endereco,
            'valor_total' => round((float) ($pedido->total ?? 0), 2),
            'observacao' => null,
            'itens' => $pedido->itens->map(fn (VendaItem $item): array => $this->mapItem($item))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItem(VendaItem $item): array
    {
        $product = $item->product;

        return [
            'produto_id' => $item->product_id ? (int) $item->product_id : null,
            'codigo' => (string) ($product?->codigo ?: ''),
            'descricao' => (string) ($product?->descricao ?: 'PRODUTO'),
            'quantidade' => round((float) $item->quantidade, 3),
            'unidade' => (string) ($product?->unidade ?: 'UN'),
        ];
    }

    private function enderecoCompleto($cliente): ?string
    {
        $partes = array_filter([
            trim((string) ($cliente->endereco ?? '')),
            filled($cliente->numero) ? 'nº '.$cliente->numero : null,
            trim((string) ($cliente->bairro ?? '')),
            trim((string) ($cliente->cidade_nome ?? '')),
            trim((string) ($cliente->uf ?? '')),
            filled($cliente->cep) ? 'CEP '.$cliente->cep : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($partes === []) {
            return null;
        }

        return implode(', ', $partes);
    }
}
