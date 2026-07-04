<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\PixCobranca;
use App\Support\Pix\PixCobrancaService;
use App\Support\Pix\Providers\MercadoPagoPixProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Webhook público do Mercado Pago (atalho opcional). A confirmação principal é
 * por polling; aqui só antecipamos a baixa quando o servidor é acessível.
 *
 * A autenticidade é garantida porque consultamos o pagamento na API do MP com
 * o nosso token — um POST forjado não consegue marcar nada como pago.
 */
class MercadoPagoWebhookController
{
    public function __construct(private readonly PixCobrancaService $service)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            $provider = new MercadoPagoPixProvider(
                (string) config('services.mercadopago.access_token'),
                (string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'),
            );

            $providerRef = $provider->parseWebhook($request);

            if ($providerRef !== null) {
                $cobranca = PixCobranca::query()
                    ->where('provider_ref', $providerRef)
                    ->first();

                if ($cobranca !== null) {
                    $this->service->atualizarStatus($cobranca);
                }
            }
        } catch (Throwable) {
            // Nunca devolve erro ao MP para evitar reentregas em loop; o polling
            // garante a baixa de qualquer forma.
        }

        return response()->json(['ok' => true]);
    }
}
