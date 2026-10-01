<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\PixCobranca;
use App\Support\Pix\PixCobrancaService;
use App\Support\Pix\PixProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook público Ailos Pix.
 *
 * POST /api/webhooks/ailos — sem login.
 * Não confia no payload: extrai txid, consulta GET /cob/{txid} com OAuth2+mTLS
 * e só baixa a fatura se status = CONCLUIDA.
 */
final class AilosPixWebhookController
{
    public function __construct(
        private readonly PixCobrancaService $service,
        private readonly PixProviderManager $providers,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            $txid = $this->extractTxid($request);

            Log::info('Ailos Pix webhook recebido', [
                'has_txid' => $txid !== '',
                'txid_prefix' => $txid !== '' ? substr($txid, 0, 12) : null,
                'keys' => array_keys($request->all()),
            ]);

            if ($txid === '') {
                // 200 para a Ailos não retentar em loop por payload vazio/desconhecido.
                return response()->json(['ok' => true, 'pending' => 'txid_missing']);
            }

            $cobranca = PixCobranca::query()
                ->where('provedor', 'ailos')
                ->where(function ($q) use ($txid): void {
                    $q->where('provider_ref', $txid)->orWhere('txid', $txid);
                })
                ->orderByDesc('id')
                ->first();

            if ($cobranca === null) {
                return response()->json(['ok' => true, 'pending' => 'cobranca_nao_encontrada']);
            }

            if ($cobranca->isPago()) {
                return response()->json(['ok' => true, 'status' => 'pago']);
            }

            if (! $cobranca->isPendente()) {
                return response()->json(['ok' => true, 'status' => $cobranca->status]);
            }

            // Confirmação real na API Ailos (OAuth2 + mTLS) — nunca baixar só pelo POST.
            $providerRef = (string) ($cobranca->provider_ref ?: $cobranca->txid);
            $status = $this->providers->paraCobranca($cobranca)->consultarStatus($providerRef);

            if ($status === PixCobranca::STATUS_PAGO) {
                $this->service->registrarPagamento($cobranca);

                return response()->json(['ok' => true, 'status' => 'pago']);
            }

            $this->service->atualizarStatus($cobranca->fresh() ?? $cobranca);

            return response()->json(['ok' => true, 'status' => $status]);
        } catch (Throwable $e) {
            Log::warning('Ailos Pix webhook falhou (polling cobrirá)', [
                'message' => $e->getMessage(),
            ]);

            // 200 evita storm de retry; a conciliação por polling continua.
            return response()->json(['ok' => true, 'pending' => 'retry_later']);
        }
    }

    private function extractTxid(Request $request): string
    {
        $payload = $request->all();

        $candidates = [
            $request->input('txid'),
            $request->query('txid'),
            data_get($payload, 'pix.0.txid'),
            data_get($payload, 'pix.txid'),
            data_get($payload, 'data.txid'),
            data_get($payload, 'cobranca.txid'),
            data_get($payload, 'charge.txid'),
        ];

        // Lista de pix (padrão Bacen / PSP).
        $pixList = data_get($payload, 'pix');
        if (is_array($pixList)) {
            foreach ($pixList as $item) {
                if (is_array($item) && isset($item['txid'])) {
                    $candidates[] = $item['txid'];
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }
            $txid = trim((string) $candidate);
            if ($txid !== '') {
                return $txid;
            }
        }

        return '';
    }
}
