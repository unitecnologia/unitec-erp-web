<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve empresas emitentes autorizadas no Monitor (config Matriz ∩ acesso do usuário ∩ ativas).
 */
final class NfeMonitorEmitenteResolver
{
    public function flagAtivo(?Empresa $empresa = null): bool
    {
        $empresa ??= ErpContext::currentEmpresa();

        if (! $empresa) {
            return false;
        }

        if (! Schema::hasColumn('empresas', 'param_monitor_vendas_escolher_empresa_emitente_nfe')) {
            return false;
        }

        return (bool) ($empresa->param_monitor_vendas_escolher_empresa_emitente_nfe ?? false);
    }

    /**
     * @return list<array{id: int, nome: string, fantasia: string, cnpj: string, label: string}>
     */
    public function opcoesParaUsuario(?Empresa $matriz = null, ?User $user = null): array
    {
        $matriz ??= ErpContext::currentEmpresa();
        $user ??= auth()->user();

        if (! $matriz || ! $user instanceof User) {
            return [];
        }

        if (! Schema::hasTable('empresa_nfe_emitente')) {
            return [];
        }

        $acessiveis = array_map('intval', $user->accessibleEmpresaIds());
        if ($acessiveis === []) {
            return [];
        }

        return $matriz->nfeEmitentes()
            ->where('empresas.ativo', true)
            ->whereIn('empresas.id', $acessiveis)
            ->orderBy('empresas.nome')
            ->get(['empresas.id', 'empresas.nome', 'empresas.fantasia', 'empresas.razao_social', 'empresas.cnpj'])
            ->map(function (Empresa $e): array {
                $nome = trim((string) ($e->razao_social ?: $e->nome ?: ''));
                $fantasia = trim((string) ($e->fantasia ?: ''));
                $cnpj = $this->formatCnpj((string) ($e->cnpj ?? ''));
                $titulo = $nome !== '' ? $nome : ($fantasia !== '' ? $fantasia : 'Empresa #'.$e->id);
                if ($fantasia !== '' && $fantasia !== $titulo) {
                    $titulo .= ' / '.$fantasia;
                }
                if ($cnpj !== '') {
                    $titulo .= ' — CNPJ '.$cnpj;
                }

                return [
                    'id' => (int) $e->id,
                    'nome' => $nome,
                    'fantasia' => $fantasia,
                    'cnpj' => $cnpj,
                    'label' => $titulo,
                ];
            })
            ->values()
            ->all();
    }

    public function emitentePermitida(int $emitenteId, ?Empresa $matriz = null, ?User $user = null): bool
    {
        if ($emitenteId <= 0) {
            return false;
        }

        foreach ($this->opcoesParaUsuario($matriz, $user) as $opcao) {
            if ((int) $opcao['id'] === $emitenteId) {
                return true;
            }
        }

        return false;
    }

    private function formatCnpj(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) !== 14) {
            return $digits;
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12, 2);
    }
}
