<?php

namespace App\Http\Controllers\Erp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PdvCarneBobinaReportController
{
    /** Payload compartilhado entre carnê A4 e bobina 80. */
    public const SESSION_KEY = 'erp.pdv.carne';

    public function __invoke(Request $request): View|Response
    {
        abort_unless(Auth::user(), 403);

        $payload = session(self::SESSION_KEY);

        if (! is_array($payload) || empty($payload['parcelas']) || ! is_array($payload['parcelas'])) {
            abort(404, 'Carnê não encontrado. Gere as parcelas novamente no PDV.');
        }

        $data = [
            'empresaNome' => (string) ($payload['empresa_nome'] ?? 'EMPRESA'),
            'clienteNome' => (string) ($payload['cliente_nome'] ?? 'CONSUMIDOR FINAL'),
            'vendedorNome' => (string) ($payload['vendedor_nome'] ?? 'LOJA'),
            'observacao' => (string) ($payload['observacao'] ?? 'OBRIGADO PELA PREFERÊNCIA!'),
            'emissao' => (string) ($payload['emissao'] ?? now()->format('d/m/Y')),
            'numeroBase' => (string) ($payload['numero_base'] ?? '0'),
            'parcelas' => array_values($payload['parcelas']),
            'autoPrint' => $request->boolean('auto'),
            'embedded' => $request->boolean('embed'),
        ];

        return view('reports.pdv-carne-bobina', $data);
    }
}
