<?php

namespace App\Http\Controllers\Erp;

use App\Models\Empresa;
use App\Support\Erp\Pdv\PdvPedidoReportData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PdvCarneA4ReportController
{
    public function __invoke(Request $request): View|Response
    {
        abort_unless(Auth::user(), 403);

        $payload = session(PdvCarneBobinaReportController::SESSION_KEY);

        if (! is_array($payload) || empty($payload['parcelas']) || ! is_array($payload['parcelas'])) {
            abort(404, 'Carnê não encontrado. Gere as parcelas novamente no PDV.');
        }

        $parcelas = array_values($payload['parcelas']);
        $paginas = [];

        foreach (array_chunk($parcelas, 3) as $chunk) {
            while (count($chunk) < 3) {
                $chunk[] = [
                    'documento' => '',
                    'vencimento' => '',
                    'valor' => '',
                    '_empty' => true,
                ];
            }
            $paginas[] = $chunk;
        }

        $logoPath = (string) ($payload['empresa_logo_path'] ?? '');
        $empresa = null;

        if ($logoPath !== '') {
            $empresa = new Empresa(['logo_path' => $logoPath]);
        }

        return view('reports.pdv-carne-a4', [
            'empresaNome' => (string) ($payload['empresa_nome'] ?? 'EMPRESA'),
            'empresaRazao' => (string) ($payload['empresa_razao'] ?? ($payload['empresa_nome'] ?? 'EMPRESA')),
            'empresaCnpj' => (string) ($payload['empresa_cnpj'] ?? ''),
            'empresaIe' => (string) ($payload['empresa_ie'] ?? ''),
            'empresaTelefone' => (string) ($payload['empresa_telefone'] ?? ''),
            'empresaEmail' => (string) ($payload['empresa_email'] ?? ''),
            'empresaEnderecoLinhas' => is_array($payload['empresa_endereco_linhas'] ?? null)
                ? array_values($payload['empresa_endereco_linhas'])
                : [],
            'logoDataUri' => PdvPedidoReportData::logoDataUri($empresa),
            'clienteNome' => (string) ($payload['cliente_nome'] ?? 'CONSUMIDOR FINAL'),
            'vendedorNome' => (string) ($payload['vendedor_nome'] ?? 'LOJA'),
            'observacao' => (string) ($payload['observacao'] ?? 'OBRIGADO PELA PREFERÊNCIA!'),
            'emissao' => (string) ($payload['emissao'] ?? now()->format('d/m/Y')),
            'numeroBase' => (string) ($payload['numero_base'] ?? '0'),
            'totalParcelas' => (int) ($payload['total_parcelas'] ?? count($parcelas)),
            'totalValor' => (string) ($payload['total_valor'] ?? ''),
            'parcelas' => $parcelas,
            'paginas' => $paginas,
            'comCapa' => $request->boolean('capa'),
            'autoPrint' => $request->boolean('auto'),
        ]);
    }
}
