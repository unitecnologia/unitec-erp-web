<?php

namespace App\Support\Erp;

use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vendedores que podem ser escolhidos no fechamento (PDV, Tela de Venda e OS).
 * Ativo, efetua venda, com RH e vinculado à empresa.
 */
final class VendedorAtivoEmpresa
{
    /**
     * @return list<array{id: int, codigo: string, nome: string, busca: string}>
     */
    public static function listar(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        return self::query($empresaId)
            ->limit(300)
            ->get()
            ->sortBy(fn (Vendedor $vendedor): string => self::rotulo($vendedor))
            ->values()
            ->map(function (Vendedor $vendedor): array {
                $codigo = trim((string) ($vendedor->rhFuncionario?->codigo ?? $vendedor->codigo ?? ''));
                $nome = trim((string) ($vendedor->rhFuncionario?->nome ?? $vendedor->nome ?? ''));
                $rotulo = self::rotulo($vendedor);

                return [
                    'id' => (int) $vendedor->id,
                    'codigo' => $codigo,
                    'nome' => $nome !== '' ? mb_strtoupper($nome, 'UTF-8') : $rotulo,
                    'busca' => mb_strtoupper(trim($codigo.' '.$nome.' '.$rotulo), 'UTF-8'),
                ];
            })
            ->all();
    }

    public static function buscar(int $empresaId, int $vendedorId): ?Vendedor
    {
        if ($empresaId <= 0 || $vendedorId <= 0) {
            return null;
        }

        return self::query($empresaId)->whereKey($vendedorId)->first();
    }

    public static function rotulo(Vendedor $vendedor): string
    {
        $vendedor->loadMissing('rhFuncionario');
        $codigo = trim((string) ($vendedor->rhFuncionario?->codigo ?? $vendedor->codigo ?? ''));
        $nome = trim((string) ($vendedor->rhFuncionario?->nome ?? $vendedor->nome ?? ''));
        $rotulo = trim(($codigo !== '' ? $codigo.' - ' : '').$nome);

        return $rotulo !== '' ? mb_strtoupper($rotulo, 'UTF-8') : 'VENDEDOR';
    }

    private static function query(int $empresaId): Builder
    {
        return Vendedor::query()
            ->where('ativo', true)
            ->where('efetua_venda', true)
            ->whereHas('rhFuncionario')
            ->whereHas(
                'empresas',
                fn ($query) => $query->where('empresas.id', $empresaId),
            )
            ->with(['rhFuncionario' => fn ($q) => $q->where('empresa_id', $empresaId)]);
    }
}
