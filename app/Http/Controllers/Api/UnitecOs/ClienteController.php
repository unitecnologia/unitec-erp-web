<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Models\Person;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->empresa_id) {
            return response()->json(['data' => []]);
        }

        $term = trim((string) $request->query('q', ''));

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $digits = preg_replace('/\D/', '', $term) ?? '';

            $query->where(function ($sub) use ($like, $digits, $term): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like)
                    ->orWhere('fone1', 'like', $like)
                    ->orWhere('celular1', 'like', $like);

                if (strlen($digits) >= 2) {
                    $digitsLike = '%'.$digits.'%';
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        [$digitsLike]
                    );
                }

                if (ctype_digit($term)) {
                    $sub->orWhere('codigo', 'like', $like);
                }
            });
        }

        $itens = $query
            ->orderBy('nome_razao')
            ->limit(30)
            ->get()
            ->map(static function (Person $person): array {
                $endereco = trim(implode(' — ', array_filter([
                    trim((string) ($person->endereco ?? '')),
                    trim((string) ($person->numero ?? '')) !== '' ? 'nº '.trim((string) $person->numero) : '',
                    trim((string) ($person->bairro ?? '')),
                    trim((string) ($person->cidade_nome ?? '')),
                    trim((string) ($person->uf ?? '')),
                ], static fn (string $p): bool => $p !== '')));

                return [
                    'id' => (int) $person->id,
                    'nome' => mb_strtoupper((string) $person->nome_razao, 'UTF-8'),
                    'fantasia' => mb_strtoupper((string) ($person->apelido_fantasia ?? ''), 'UTF-8'),
                    'telefone' => (string) ($person->fone1 ?: $person->celular1 ?: $person->fone2 ?: ''),
                    'email' => (string) ($person->email ?? ''),
                    'cpf_cnpj' => (string) ($person->cpf_cnpj ?? ''),
                    'cep' => (string) ($person->cep ?? ''),
                    'endereco' => mb_strtoupper(trim((string) ($person->endereco ?? '')), 'UTF-8'),
                    'endereco_completo' => mb_strtoupper($endereco, 'UTF-8'),
                    'numero' => (string) ($person->numero ?? ''),
                    'bairro' => mb_strtoupper((string) ($person->bairro ?? ''), 'UTF-8'),
                    'cidade' => mb_strtoupper((string) ($person->cidade_nome ?? ''), 'UTF-8'),
                    'uf' => mb_strtoupper((string) ($person->uf ?? ''), 'UTF-8'),
                ];
            })
            ->values();

        return response()->json(['data' => $itens]);
    }
}
