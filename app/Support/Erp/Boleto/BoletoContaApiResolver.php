<?php

namespace App\Support\Erp\Boleto;

use App\Models\BoletoContaApi;
use App\Models\Empresa;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Resolve contas de cobrança API ativas da empresa.
 */
final class BoletoContaApiResolver
{
    /**
     * @return Collection<int, BoletoContaApi>
     */
    public function ativas(Empresa $empresa): Collection
    {
        return BoletoContaApi::query()
            ->where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->orderByDesc('padrao')
            ->orderBy('id')
            ->get();
    }

    public function padraoOuUnica(Empresa $empresa): ?BoletoContaApi
    {
        $ativas = $this->ativas($empresa);
        if ($ativas->isEmpty()) {
            return null;
        }

        if ($ativas->count() === 1) {
            return $ativas->first();
        }

        return $ativas->firstWhere('padrao', true) ?? $ativas->first();
    }

    /**
     * @throws RuntimeException
     */
    public function findAtiva(Empresa $empresa, int $contaApiId): BoletoContaApi
    {
        $conta = BoletoContaApi::query()
            ->where('empresa_id', $empresa->id)
            ->whereKey($contaApiId)
            ->where('ativo', true)
            ->first();

        if (! $conta instanceof BoletoContaApi) {
            throw new RuntimeException('Conta de cobrança API não encontrada ou inativa.');
        }

        return $conta;
    }
}
