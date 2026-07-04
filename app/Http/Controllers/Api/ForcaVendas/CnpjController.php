<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Support\Erp\CnpjLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CnpjController
{
    /**
     * Consulta CNPJ (mesma lógica do ERP) e devolve campos para preencher o cadastro no app.
     *
     * @return array<string, string|null>
     */
    private function mobileFields(array $fields): array
    {
        $keys = [
            'cpf_cnpj',
            'nome_razao',
            'apelido_fantasia',
            'cep',
            'endereco',
            'numero',
            'bairro',
            'cidade_nome',
            'uf',
            'email',
            'fone1',
            'fone2',
        ];

        $out = [];

        foreach ($keys as $key) {
            $value = $fields[$key] ?? null;

            if (filled($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public function show(Request $request, string $cnpj, CnpjLookupService $lookup): JsonResponse
    {
        try {
            $fields = $this->mobileFields($lookup->fetch($cnpj));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => $fields,
        ]);
    }
}
