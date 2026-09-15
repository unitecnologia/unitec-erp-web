<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Models\OrdemServico;
use App\Models\OrdemServicoImagem;
use App\Models\User;
use App\Support\UnitecOs\OrdemServicoImagemService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OrdemServicoMidiaController
{
    public function storeFoto(
        Request $request,
        int $id,
        OrdemServicoImagemService $service,
    ): JsonResponse {
        $user = $this->user($request);
        $os = $this->findScopedOs($user, $id);

        $data = $request->validate([
            'foto' => ['required', 'file', 'image', 'max:12288'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['foto'];
        $img = $service->storeFoto($os, $user, $file);

        return response()->json([
            'ok' => true,
            'message' => 'Foto enviada.',
            'data' => $this->payload($img),
        ], 201);
    }

    public function storeAssinatura(
        Request $request,
        int $id,
        OrdemServicoImagemService $service,
    ): JsonResponse {
        $user = $this->user($request);
        $os = $this->findScopedOs($user, $id);

        $data = $request->validate([
            'assinatura' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['assinatura'];
        $img = $service->storeAssinatura($os, $user, $file);

        return response()->json([
            'ok' => true,
            'message' => 'Assinatura enviada.',
            'data' => $this->payload($img),
        ], 201);
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        $os = $this->findScopedOs($user, $id);

        $itens = $os->imagens()
            ->orderBy('tipo')
            ->orderBy('item')
            ->orderBy('id')
            ->get()
            ->map(fn (OrdemServicoImagem $img): array => $this->payload($img))
            ->values();

        return response()->json(['data' => $itens]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(OrdemServicoImagem $img): array
    {
        $url = null;
        if (filled($img->caminho) && Storage::disk('public')->exists((string) $img->caminho)) {
            $url = Storage::disk('public')->url((string) $img->caminho);
        }

        return [
            'id' => (int) $img->id,
            'ordem_servico_id' => (int) $img->ordem_servico_id,
            'tipo' => (string) ($img->tipo ?? OrdemServicoImagem::TIPO_FOTO),
            'empresa_id' => $img->empresa_id ? (int) $img->empresa_id : null,
            'usuario_id' => $img->usuario_id ? (int) $img->usuario_id : null,
            'atendente_id' => $img->atendente_id ? (int) $img->atendente_id : null,
            'item' => $img->item ? (int) $img->item : null,
            'caminho' => (string) ($img->caminho ?? ''),
            'url' => $url,
            'mime' => (string) ($img->mime ?? ''),
            'tamanho' => $img->tamanho ? (int) $img->tamanho : null,
            'created_at' => optional($img->created_at)?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function findScopedOs(User $user, int $id): OrdemServico
    {
        $query = $this->scopedQuery($user);
        if ($query === null) {
            throw ValidationException::withMessages([
                'os' => 'OS não encontrada ou sem permissão (empresa/técnico).',
            ]);
        }

        /** @var OrdemServico|null $os */
        $os = $query->whereKey($id)->first();
        if (! $os instanceof OrdemServico) {
            throw ValidationException::withMessages([
                'os' => 'OS não encontrada ou sem permissão (empresa/técnico).',
            ]);
        }

        return $os;
    }

    private function scopedQuery(User $user): ?Builder
    {
        $empresaId = (int) ($user->empresa_id ?? 0);
        $vendedorId = $user->vendedor_id ? (int) $user->vendedor_id : null;

        if ($empresaId <= 0 || $vendedorId === null) {
            return null;
        }

        return OrdemServico::query()
            ->where('empresa_id', $empresaId)
            ->where(function (Builder $q) use ($vendedorId): void {
                $q->where('atendente_id', $vendedorId)
                    ->orWhereHas('itens', function (Builder $itens) use ($vendedorId): void {
                        $itens->where('funcionario_id', $vendedorId);
                    });
            });
    }
}
