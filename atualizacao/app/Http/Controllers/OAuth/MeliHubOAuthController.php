<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Support\MercadoLivre\MeliHubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeliHubOAuthController extends Controller
{
    public function connect(Request $request, MeliHubService $hub): RedirectResponse|View
    {
        $pair = trim((string) $request->query('pair', ''));

        if ($pair === '') {
            return response()->view('meli.hub-result', [
                'ok' => false,
                'title' => 'Mercado Livre',
                'message' => 'Pareamento inválido.',
            ], 422);
        }

        $result = $hub->beginHubAuthorization($pair);

        if (! $result['ok'] || ! is_array($result['data'])) {
            return response()->view('meli.hub-result', [
                'ok' => false,
                'title' => 'Mercado Livre',
                'message' => $result['message'],
            ], 422);
        }

        return redirect()->away((string) $result['data']['authorize_url']);
    }

    public function callback(Request $request, MeliHubService $hub): View
    {
        if (filled($request->query('error'))) {
            return view('meli.hub-result', [
                'ok' => false,
                'title' => 'Mercado Livre',
                'message' => (string) $request->query('error_description', 'Autorização recusada.'),
            ]);
        }

        $code = trim((string) $request->query('code', ''));
        $state = trim((string) $request->query('state', ''));

        if ($code === '' || $state === '') {
            return view('meli.hub-result', [
                'ok' => false,
                'title' => 'Mercado Livre',
                'message' => 'Retorno inválido do Mercado Livre.',
            ]);
        }

        $result = $hub->completeHubAuthorization($code, $state);

        return view('meli.hub-result', [
            'ok' => $result['ok'],
            'title' => 'Mercado Livre',
            'message' => $result['message'],
            'nickname' => $result['data']['nickname'] ?? null,
        ]);
    }
}
