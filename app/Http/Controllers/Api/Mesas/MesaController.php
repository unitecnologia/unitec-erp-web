<?php

namespace App\Http\Controllers\Api\Mesas;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\Pdv\PdvMesaService;
use App\Support\Mesas\MesasCatalogoService;
use App\Support\Mesas\MesasPainelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MesaController
{
    public function __construct(
        private readonly MesasPainelService $painel,
        private readonly MesasCatalogoService $catalogo,
    ) {}

    /** Lista das mesas; `s` igual ao do app = nada mudou (resposta mínima). */
    public function index(Request $request): JsonResponse
    {
        $lista = $this->painel->lista($this->empresa($request));

        if ((string) $request->query('s', '') === $lista['s']) {
            return response()->json(['inalterado' => true, 's' => $lista['s']]);
        }

        return response()->json($lista + ['server_time' => now()->format('Y-m-d H:i:s')]);
    }

    public function show(Request $request, int $numero): JsonResponse
    {
        $empresa = $this->empresa($request);

        if ($numero < 1 || $numero > PdvMesaService::quantidade($empresa)) {
            return response()->json(['message' => 'Mesa inexistente.', 'code' => 'mesa_invalida'], 404);
        }

        return response()->json($this->painel->comanda($empresa, $numero));
    }

    /** Catálogo completo só quando a versão do app for diferente da do servidor. */
    public function catalogo(Request $request): JsonResponse
    {
        $empresa = $this->empresa($request);
        $versao = $this->catalogo->versao($empresa);

        if ((string) $request->query('versao', '') === $versao) {
            return response()->json(['inalterado' => true, 'versao' => $versao]);
        }

        return response()->json([
            'versao' => $versao,
            'campos' => MesasCatalogoService::CAMPOS,
            'produtos' => $this->catalogo->produtos($empresa, $versao),
            'grupos' => $this->catalogo->grupos($empresa, $versao),
            'tab_preco' => $this->catalogo->tabPrecos($empresa, $versao),
            'composicoes' => $this->catalogo->composicoes($empresa, $versao),
        ]);
    }

    private function empresa(Request $request): Empresa
    {
        /** @var User $user */
        $user = $request->user();

        return Empresa::query()->findOrFail((int) $user->empresa_id);
    }
}
