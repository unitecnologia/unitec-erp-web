<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\Pdv\PdvMesaCredencial;
use App\Support\Erp\Pdv\PdvMesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pulso do painel de mesas: status compartilhado entre terminais, renovação da reserva
 * com atividade e liberação ao fechar a aba (sendBeacon). Rota sem sessão.
 * Resposta sem "m" quando nada mudou desde a assinatura enviada pelo navegador.
 */
class PdvMesasPulsoController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $credencial = PdvMesaCredencial::validar((string) $request->input('c', ''));

        if ($credencial === null) {
            return response()->json(['erro' => 'credencial'], 401);
        }

        try {
            return $this->responder($request, $credencial);
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return response()->json(['erro' => 'indisponivel'], 503, ['Cache-Control' => 'no-store']);
        }
    }

    /**
     * @param  array{e: int, t: int|null, u: int|null}  $credencial
     */
    private function responder(Request $request, array $credencial): JsonResponse|Response
    {
        $service = PdvMesaService::make();
        $mesaId = (int) $request->input('m', 0);
        $token = (string) $request->input('k', '');
        $acao = (string) $request->input('a', '');
        $perdida = false;

        if ($mesaId > 0 && $token !== '') {
            if ($acao === 'liberar') {
                $service->liberar($mesaId, $token, $credencial['e']);

                return response()->noContent();
            }

            if ($acao === 'renovar') {
                $perdida = ! PdvMesaService::make(PdvMesaService::reservaSegundosDaEmpresa($credencial['e']))
                    ->renovar($mesaId, $token, $credencial['e']);
            }
        }

        $mesas = $service->painel($credencial['e'], $token !== '' ? $token : null);
        $assinatura = md5((string) json_encode($mesas));
        $payload = ['s' => $assinatura, 'p' => $perdida];

        if ($assinatura !== (string) $request->input('s', '')) {
            $payload['m'] = $mesas;
        }

        return response()->json($payload, 200, ['Cache-Control' => 'no-store']);
    }
}
