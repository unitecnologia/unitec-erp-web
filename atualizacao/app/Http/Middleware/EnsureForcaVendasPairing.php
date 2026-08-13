<?php

namespace App\Http\Middleware;

use App\Models\ForcaVendasSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureForcaVendasPairing
{
    public function handle(Request $request, Closure $next): Response
    {
        $setting = ForcaVendasSetting::current();

        if ($setting->pairing_required) {
            $provided = (string) $request->header('X-FV-Pairing', '');

            if ($provided === '' || ! hash_equals($setting->pairing_secret, $provided)) {
                return response()->json([
                    'message' => 'Aparelho não pareado. Leia o QR Code de configuração no ERP.',
                    'code' => 'pairing_required',
                ], 403);
            }
        }

        return $next($request);
    }
}
