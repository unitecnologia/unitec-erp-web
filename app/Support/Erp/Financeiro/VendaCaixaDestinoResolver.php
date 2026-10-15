<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\FormaPagamento;
use App\Models\PdvCaixaSessao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Destino de caixa para vendas comerciais (Tela / FV / Monitor).
 *
 * Prioridade:
 * 1) destino ativo da forma de pagamento
 * 2) conta explicitamente selecionada e autorizada
 * 3) caixa PDV vinculado ao usuário (Permissões → Caixas)
 * 4) CAIXA GERAL
 *
 * “Vinculado” = linha em `caixa_conta_user` (não usa o fallback de
 * User::accessibleCaixaContaIds que libera todos os PDVs quando o pivot está vazio).
 *
 * Quando a conta resolvida é PDV e há sessão aberta, devolve `sessao_id`
 * para o caller lançar só na sessão (sem Livro Caixa na hora).
 */
final class VendaCaixaDestinoResolver
{
    /**
     * @return array{caixa_conta_id: int, sessao_id: int|null}
     */
    public function resolver(
        ?FormaPagamento $forma,
        ?int $selecionadaId,
        ?User $usuario,
        ?int $empresaId = null,
    ): array {
        $caixaContaId = $this->resolverContaId($forma, $selecionadaId, $usuario, $empresaId);
        $sessaoId = $this->sessaoAbertaId($caixaContaId, $empresaId);

        return [
            'caixa_conta_id' => $caixaContaId,
            'sessao_id' => $sessaoId,
        ];
    }

    public function resolverContaId(
        ?FormaPagamento $forma,
        ?int $selecionadaId,
        ?User $usuario,
        ?int $empresaId = null,
    ): int {
        $destinoForma = (int) ($forma?->conta_destino_id ?? 0);
        if ($destinoForma > 0 && $this->contaAtiva($destinoForma)) {
            return $destinoForma;
        }

        $selecionada = (int) ($selecionadaId ?? 0);
        if ($selecionada > 0 && $this->contaAtiva($selecionada) && $this->contaAutorizada($selecionada, $usuario, $empresaId)) {
            return $selecionada;
        }

        $vinculada = $this->caixaVinculadoUsuario($usuario, $empresaId);
        if ($vinculada !== null) {
            return $vinculada;
        }

        return (int) CaixaConta::ensureCaixaGeral()->id;
    }

    public function isContaTipoPdv(int $caixaContaId): bool
    {
        $tipo = (string) (CaixaConta::query()->whereKey($caixaContaId)->value('tipo') ?? '');

        return in_array($tipo, [CaixaConta::TIPO_PDV, 'CAIXA', 'X'], true);
    }

    public function sessaoAbertaId(int $caixaContaId, ?int $empresaId = null): ?int
    {
        if ($caixaContaId <= 0 || ! $this->isContaTipoPdv($caixaContaId)) {
            return null;
        }

        if (! Schema::hasTable((new PdvCaixaSessao)->getTable())) {
            return null;
        }

        if (! Schema::hasColumn('pdv_caixa_sessoes', 'caixa_conta_id')) {
            return null;
        }

        $query = PdvCaixaSessao::query()
            ->where('caixa_conta_id', $caixaContaId)
            ->whereNull('fechado_em')
            ->orderByDesc('id');

        if ($empresaId && $empresaId > 0 && Schema::hasColumn('pdv_caixa_sessoes', 'empresa_id')) {
            $query->where('empresa_id', $empresaId);
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Contas sugeridas na Tela de Vendas: CAIXA GERAL + PDVs com vínculo explícito.
     *
     * @return list<array{id: int, label: string, situacao: string}>
     */
    public function opcoesParaUsuario(?User $usuario, ?int $empresaId = null): array
    {
        $geral = CaixaConta::ensureCaixaGeral();
        $opcoes = [[
            'id' => (int) $geral->id,
            'label' => trim(($geral->codigo ? $geral->codigo.' - ' : '').($geral->nome ?? CaixaConta::NOME_CAIXA_GERAL)),
            'situacao' => (string) ($geral->situacao ?? ''),
        ]];

        $ids = $this->idsCaixaExplicitos($usuario, $empresaId);
        if ($ids === []) {
            return $opcoes;
        }

        $contas = CaixaConta::query()
            ->whereIn('id', $ids)
            ->where('ativo', true)
            ->whereIn('tipo', [CaixaConta::TIPO_PDV, 'CAIXA', 'X'])
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'situacao']);

        foreach ($contas as $conta) {
            if ((int) $conta->id === (int) $geral->id) {
                continue;
            }

            $opcoes[] = [
                'id' => (int) $conta->id,
                'label' => trim(($conta->codigo ? $conta->codigo.' - ' : '').($conta->nome ?? '')),
                'situacao' => (string) ($conta->situacao ?? ''),
            ];
        }

        return $opcoes;
    }

    /**
     * Primeiro caixa PDV com vínculo explícito (is_padrao, senão menor id).
     */
    private function caixaVinculadoUsuario(?User $usuario, ?int $empresaId): ?int
    {
        foreach ($this->idsCaixaExplicitos($usuario, $empresaId) as $id) {
            if ($this->contaAtiva($id) && $this->isContaTipoPdv($id)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * IDs em `caixa_conta_user` para a empresa (ordem: padrão primeiro).
     * Vazio = sem vínculo (não libera todos os PDVs).
     *
     * @return list<int>
     */
    private function idsCaixaExplicitos(?User $usuario, ?int $empresaId): array
    {
        if (! $usuario || ! $empresaId || $empresaId <= 0) {
            return [];
        }

        if (! Schema::hasTable('caixa_conta_user')) {
            return [];
        }

        return DB::table('caixa_conta_user')
            ->where('user_id', $usuario->getKey())
            ->where('empresa_id', $empresaId)
            ->orderByDesc('is_padrao')
            ->orderBy('caixa_conta_id')
            ->pluck('caixa_conta_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function contaAutorizada(int $contaId, ?User $usuario, ?int $empresaId): bool
    {
        $geralId = (int) CaixaConta::ensureCaixaGeral()->id;
        if ($contaId === $geralId) {
            return true;
        }

        $conta = CaixaConta::query()->whereKey($contaId)->where('ativo', true)->first(['id', 'tipo']);
        if (! $conta) {
            return false;
        }

        $tipo = (string) ($conta->tipo ?? '');
        $ehPdv = in_array($tipo, [CaixaConta::TIPO_PDV, 'CAIXA', 'X'], true);

        // PDV: só vínculo explícito em Permissões → Caixas.
        if ($ehPdv) {
            return in_array($contaId, $this->idsCaixaExplicitos($usuario, $empresaId), true);
        }

        // BANCO / SUBCAIXA / COFRE etc.: conta explícita ativa (outras contas configuradas).
        return true;
    }

    private function contaAtiva(int $contaId): bool
    {
        return $contaId > 0
            && CaixaConta::query()->whereKey($contaId)->where('ativo', true)->exists();
    }
}
