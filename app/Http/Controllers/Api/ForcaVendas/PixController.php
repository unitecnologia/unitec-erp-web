<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Models\ContaReceber;
use App\Models\PixCobranca;
use App\Support\Pix\PixCobrancaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PixController
{
    public function __construct(private readonly PixCobrancaService $service)
    {
    }

    /**
     * Cria uma cobrança Pix para um pedido (origem=pedido, ref=uuid) ou para um
     * título já existente (origem=titulo, ref=id da conta a receber).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origem' => ['required', 'in:pedido,titulo'],
            'ref' => ['required', 'string', 'max:100'],
            'valor' => ['nullable', 'numeric', 'min:0.01'],
            'payer_email' => ['nullable', 'email', 'max:150'],
        ]);

        try {
            if ($data['origem'] === PixCobranca::ORIGEM_TITULO) {
                $conta = ContaReceber::query()->find((int) $data['ref']);

                if ($conta === null) {
                    return response()->json(['message' => 'Título não encontrado.'], 404);
                }

                if ((float) $conta->saldo <= 0) {
                    return response()->json(['message' => 'Este título já está quitado.'], 422);
                }

                $cobranca = $this->service->criarParaTitulo(
                    $conta,
                    $data['payer_email'] ?? null,
                    $request->user()?->empresa_id,
                );
            } else {
                $valor = (float) ($data['valor'] ?? 0);

                if ($valor <= 0) {
                    return response()->json(['message' => 'Informe o valor do pedido.'], 422);
                }

                $cobranca = $this->service->criarParaPedido(
                    orderUuid: $data['ref'],
                    valor: $valor,
                    payerEmail: $data['payer_email'] ?? null,
                    empresaId: $request->user()?->empresa_id,
                );
            }
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->present($cobranca), 201);
    }

    public function status(PixCobranca $cobranca): JsonResponse
    {
        $cobranca = $this->service->atualizarStatus($cobranca);

        return response()->json($this->present($cobranca));
    }

    public function cancelar(PixCobranca $cobranca): JsonResponse
    {
        $cobranca = $this->service->cancelar($cobranca);

        return response()->json($this->present($cobranca));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PixCobranca $cobranca): array
    {
        return [
            'id' => $cobranca->id,
            'status' => $cobranca->status,
            'valor' => (float) $cobranca->valor,
            'qr_copia_cola' => $cobranca->qr_copia_cola,
            'qr_imagem_base64' => $cobranca->qr_imagem_base64,
            'expira_em' => optional($cobranca->expira_em)->toIso8601String(),
            'pago_em' => optional($cobranca->pago_em)->toIso8601String(),
        ];
    }
}
