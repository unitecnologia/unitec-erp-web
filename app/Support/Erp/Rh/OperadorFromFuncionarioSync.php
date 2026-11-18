<?php

namespace App\Support\Erp\Rh;

use App\Models\RhFuncionario;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpUppercase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Mantém o registro em `vendedores` a partir do cadastro de Funcionário (RH).
 * A tela Operadores foi descontinuada; PDV/FV continuam lendo Vendedor.
 */
final class OperadorFromFuncionarioSync
{
    /**
     * @param  array{
     *   eh_operador: bool,
     *   usuario_id: int|null,
     *   terminais: list<int>,
     *   estoque_id: int|null,
     *   entregador: bool,
     *   setor_vendas: bool,
     *   tabela_venda_id: int|null,
     *   comissao_av: float,
     *   comissao_ap: float,
     *   mobile_meta_venda: float,
     *   ganha_comissao_todas_vendas: bool,
     *   setor_servicos: bool,
     *   comissao_servico: float,
     *   ganha_comissao_todos_servicos: bool,
     *   observacoes: string|null,
     * }  $operador
     *
     * @throws InvalidArgumentException
     */
    public function sync(RhFuncionario $funcionario, array $operador): void
    {
        $ehOperador = (bool) ($operador['eh_operador'] ?? false);
        $vendedorId = (int) ($funcionario->vendedor_id ?? 0);
        $vendedor = $vendedorId > 0 ? Vendedor::query()->find($vendedorId) : null;

        // Funcionário inativo (ou sem flag de operador) não pode continuar vendendo.
        if (! $ehOperador || ! (bool) $funcionario->ativo) {
            $this->desativarOperador($funcionario, $vendedor);

            return;
        }

        $usuarioId = $operador['usuario_id'] ?? null;
        $usuarioId = $usuarioId !== null && $usuarioId > 0 ? (int) $usuarioId : null;

        if (! $usuarioId) {
            throw new InvalidArgumentException('Selecione o usuário do operador.');
        }

        $empresaId = (int) ($funcionario->empresa_id ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        }

        // Um usuário pode ser operador em várias empresas; na empresa atual só um vínculo.
        if (Schema::hasColumn('rh_funcionarios', 'user_id')) {
            $outroFuncionarioNaEmpresa = RhFuncionario::query()
                ->where('user_id', $usuarioId)
                ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
                ->when(
                    $funcionario->getKey(),
                    fn ($q) => $q->whereKeyNot($funcionario->getKey())
                )
                ->exists();

            if ($outroFuncionarioNaEmpresa) {
                throw new InvalidArgumentException(
                    'Este usuário já está vinculado a outro funcionário nesta empresa. Escolha outro usuário ou desative o vínculo anterior.'
                );
            }
        }

        $outroVendedorId = (int) (User::query()->whereKey($usuarioId)->value('vendedor_id') ?? 0);

        // Reaproveita o vendedor do usuário só se não estiver preso a funcionário de outra empresa.
        if ($outroVendedorId > 0 && (! $vendedor || $outroVendedorId !== (int) $vendedor->getKey())) {
            $usadoNaMesmaEmpresa = RhFuncionario::query()
                ->where('vendedor_id', $outroVendedorId)
                ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
                ->when(
                    $funcionario->getKey(),
                    fn ($q) => $q->whereKeyNot($funcionario->getKey())
                )
                ->exists();

            if ($usadoNaMesmaEmpresa) {
                throw new InvalidArgumentException(
                    'Este usuário já está vinculado a outro operador nesta empresa. Escolha outro usuário ou desative o vínculo anterior.'
                );
            }

            $usadoEmOutraEmpresa = $empresaId > 0 && RhFuncionario::query()
                ->where('vendedor_id', $outroVendedorId)
                ->where('empresa_id', '!=', $empresaId)
                ->exists();

            if (! $usadoEmOutraEmpresa && ! $vendedor) {
                $vendedor = Vendedor::query()->find($outroVendedorId);
            }
        }

        $estoqueId = $operador['estoque_id'] ?? null;
        $estoqueId = $estoqueId !== null && $estoqueId > 0 ? (int) $estoqueId : null;

        if (! $estoqueId) {
            throw new InvalidArgumentException('Selecione o estoque do operador.');
        }

        $empresasSync = $this->empresasSyncParaFuncionario($usuarioId, $empresaId);

        if ($empresasSync === []) {
            throw new InvalidArgumentException(
                'Este usuário não tem empresas liberadas. Configure em Permissões / Usuários → Empresas.'
            );
        }

        $funcionario->loadMissing('cargo');
        $cargoNome = trim((string) ($funcionario->cargo?->nome ?? ''));
        $cargoNome = $cargoNome !== '' ? ErpUppercase::uppercase($cargoNome) : null;

        $payload = [
            'nome' => (string) $funcionario->nome,
            'cargo' => $cargoNome,
            'ativo' => (bool) $funcionario->ativo,
            'empresa_id' => $empresaId > 0 ? $empresaId : array_key_first($empresasSync),
            'estoque_id' => $estoqueId,
            'entregador' => (bool) ($operador['entregador'] ?? true),
            'setor_vendas' => (bool) ($operador['setor_vendas'] ?? true),
            'tabela_venda_id' => $operador['tabela_venda_id'] ?? null,
            'comissao_av' => (float) ($operador['comissao_av'] ?? 0),
            'comissao_ap' => (float) ($operador['comissao_ap'] ?? 0),
            'mobile_meta_venda' => (float) ($operador['mobile_meta_venda'] ?? 0),
            'ganha_comissao_todas_vendas' => (bool) ($operador['ganha_comissao_todas_vendas'] ?? false),
            'setor_servicos' => (bool) ($operador['setor_servicos'] ?? false),
            'comissao_servico' => (float) ($operador['comissao_servico'] ?? 0),
            'ganha_comissao_todos_servicos' => (bool) ($operador['ganha_comissao_todos_servicos'] ?? false),
            'efetua_venda' => true,
            'observacoes' => $operador['observacoes'] ?? null,
            'cpf' => $funcionario->cpf,
            'rg' => $funcionario->rg,
            'pis_pasep' => $funcionario->pis_pasep,
            'data_nascimento' => $funcionario->data_nascimento,
            'cep' => $funcionario->cep,
            'logradouro' => $funcionario->logradouro,
            'endereco' => $funcionario->endereco,
            'numero' => $funcionario->numero,
            'bairro' => $funcionario->bairro,
            'complemento' => $funcionario->complemento,
            'cidade_codigo' => $funcionario->cidade_codigo,
            'cidade_nome' => $funcionario->cidade_nome,
            'uf' => $funcionario->uf,
            'telefone' => $funcionario->telefone,
            'whatsapp' => $funcionario->whatsapp,
            'email' => $funcionario->email,
            'ctps' => $funcionario->ctps,
            'admissao' => $funcionario->data_admissao,
            'demissao' => $funcionario->data_demissao,
            'tipo_salario' => $funcionario->tipo_salario,
            'salario' => $funcionario->salario,
            'inss' => $funcionario->inss,
        ];

        if ($vendedor) {
            $vendedor->update($payload);
        } else {
            $vendedor = Vendedor::query()->create([
                ...$payload,
                'codigo' => Vendedor::nextCodigo(),
            ]);
        }

        // Só garante a empresa do funcionário; não remove vínculos de outras empresas no mesmo vendedor.
        $vendedor->empresas()->syncWithoutDetaching($empresasSync);

        $terminalIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($operador['terminais'] ?? []))
        )));
        $vendedor->terminais()->sync($terminalIds);

        $this->syncVendedorUsuario((int) $vendedor->getKey(), $usuarioId, $empresaId);

        $funcionario->vendedor_id = (int) $vendedor->getKey();

        if (Schema::hasColumn('rh_funcionarios', 'user_id')) {
            $funcionario->user_id = $usuarioId;
        }

        $funcionario->save();
    }

    /**
     * Desativa o operador sem apagar o registro em `vendedores` (preserva histórico de vendas).
     * Mantém vendedor_id no funcionário para histórico/FKs.
     */
    public function desativarOperador(RhFuncionario $funcionario, ?Vendedor $vendedor): void
    {
        $this->desativarVendedor($vendedor ?? ((int) ($funcionario->vendedor_id ?? 0) ?: null));
    }

    /**
     * Desativa vendedor por id/model — usado na exclusão/desativação de usuário ou funcionário.
     * Nunca dá DELETE em `vendedores`.
     */
    public function desativarVendedor(Vendedor|int|null $vendedor): void
    {
        if ($vendedor === null || $vendedor === 0) {
            return;
        }

        if (! $vendedor instanceof Vendedor) {
            $vendedor = Vendedor::query()->find((int) $vendedor);
        }

        if (! $vendedor) {
            return;
        }

        $vendedor->update([
            'ativo' => false,
            'efetua_venda' => false,
        ]);

        User::query()
            ->where('vendedor_id', $vendedor->getKey())
            ->update(['vendedor_id' => null]);
    }

    /**
     * @return array<int, array{caixa_conta_id: int|null}>
     */
    public function empresaVendedorSyncFromUsuario(int $usuarioId): array
    {
        $user = User::query()->find($usuarioId);

        if (! $user) {
            return [];
        }

        $empresaIds = $user->accessibleEmpresaIds();

        if ($empresaIds === []) {
            return [];
        }

        if (filled($user->empresa_id)) {
            $padrao = (int) $user->empresa_id;
            $empresaIds = array_values(array_unique([
                $padrao,
                ...array_filter($empresaIds, static fn (int $id): bool => $id !== $padrao),
            ]));
        }

        $payload = [];
        foreach ($empresaIds as $empresaId) {
            $caixaId = $user->defaultCaixaContaId($empresaId);
            $payload[(int) $empresaId] = [
                'caixa_conta_id' => $caixaId && $caixaId > 0 ? $caixaId : null,
            ];
        }

        return $payload;
    }

    /**
     * Vínculo do vendedor na empresa do funcionário (não força todas as empresas do usuário).
     *
     * @return array<int, array{caixa_conta_id: int|null}>
     */
    public function empresasSyncParaFuncionario(int $usuarioId, int $empresaId): array
    {
        if ($empresaId <= 0) {
            return $this->empresaVendedorSyncFromUsuario($usuarioId);
        }

        $user = User::query()->find($usuarioId);

        if (! $user) {
            return [];
        }

        $acessiveis = $user->accessibleEmpresaIds();

        if ($acessiveis !== [] && ! in_array($empresaId, $acessiveis, true)) {
            throw new InvalidArgumentException(
                'Este usuário não tem acesso a esta empresa. Configure em Permissões / Usuários → Empresas.'
            );
        }

        $caixaId = $user->defaultCaixaContaId($empresaId);

        return [
            $empresaId => [
                'caixa_conta_id' => $caixaId && $caixaId > 0 ? $caixaId : null,
            ],
        ];
    }

    private function syncVendedorUsuario(int $vendedorId, ?int $usuarioId, int $empresaId = 0): void
    {
        User::query()
            ->where('vendedor_id', $vendedorId)
            ->when($usuarioId !== null, fn ($query) => $query->where('id', '!=', $usuarioId))
            ->update(['vendedor_id' => null]);

        if ($usuarioId === null) {
            return;
        }

        $atual = (int) (User::query()->whereKey($usuarioId)->value('vendedor_id') ?? 0);

        if ($atual === $vendedorId) {
            return;
        }

        // Não sobrescreve ponteiro global se ele aponta para operador de outra empresa.
        if ($atual > 0 && $empresaId > 0) {
            $apontaOutraEmpresa = RhFuncionario::query()
                ->where('vendedor_id', $atual)
                ->where('empresa_id', '!=', $empresaId)
                ->exists();

            if ($apontaOutraEmpresa) {
                return;
            }
        }

        User::query()->whereKey($usuarioId)->update(['vendedor_id' => $vendedorId]);
    }

    public static function parseMoney(mixed $value): float
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return 0.0;
        }

        return (float) BrDecimal::parse($raw, 2);
    }
}
