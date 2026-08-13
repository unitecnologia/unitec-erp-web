<?php

namespace App\Support\Erp\Rh;

use App\Models\RhFuncionario;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\BrDecimal;
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
     *   usar_agendamento: bool,
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

        if (! $ehOperador) {
            $this->desativarOperador($funcionario, $vendedor);

            return;
        }

        $usuarioId = $operador['usuario_id'] ?? null;
        $usuarioId = $usuarioId !== null && $usuarioId > 0 ? (int) $usuarioId : null;

        if (! $usuarioId) {
            throw new InvalidArgumentException('Selecione o usuário do operador.');
        }

        $outroVendedorId = (int) (User::query()->whereKey($usuarioId)->value('vendedor_id') ?? 0);

        // Reaproveita o operador já amarrado ao usuário quando for o mesmo vínculo
        // (ex.: bootstrap / cadastro antigo), em vez de bloquear o save sem feedback útil.
        if ($outroVendedorId > 0 && (! $vendedor || $outroVendedorId !== (int) $vendedor->getKey())) {
            $usadoPorOutroFuncionario = RhFuncionario::query()
                ->where('vendedor_id', $outroVendedorId)
                ->when(
                    $funcionario->getKey(),
                    fn ($q) => $q->whereKeyNot($funcionario->getKey())
                )
                ->exists();

            if ($usadoPorOutroFuncionario) {
                throw new InvalidArgumentException(
                    'Este usuário já está vinculado a outro operador. Escolha outro usuário ou desative o vínculo anterior.'
                );
            }

            if (! $vendedor) {
                $vendedor = Vendedor::query()->find($outroVendedorId);
            } else {
                throw new InvalidArgumentException(
                    'Este usuário já está vinculado a outro operador. Escolha outro usuário ou desative o vínculo anterior.'
                );
            }
        }

        $estoqueId = $operador['estoque_id'] ?? null;
        $estoqueId = $estoqueId !== null && $estoqueId > 0 ? (int) $estoqueId : null;

        if (! $estoqueId) {
            throw new InvalidArgumentException('Selecione o estoque do operador.');
        }

        $empresasSync = $this->empresaVendedorSyncFromUsuario($usuarioId);

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
            'empresa_id' => array_key_first($empresasSync),
            'estoque_id' => $estoqueId,
            'usar_agendamento' => (bool) ($operador['usar_agendamento'] ?? false),
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

        $vendedor->empresas()->sync($empresasSync);

        $terminalIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($operador['terminais'] ?? []))
        )));
        $vendedor->terminais()->sync($terminalIds);

        $this->syncVendedorUsuario((int) $vendedor->getKey(), $usuarioId);

        $funcionario->vendedor_id = (int) $vendedor->getKey();

        if (Schema::hasColumn('rh_funcionarios', 'user_id')) {
            $funcionario->user_id = $usuarioId;
        }

        $funcionario->save();
    }

    private function desativarOperador(RhFuncionario $funcionario, ?Vendedor $vendedor): void
    {
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

        // Mantém vendedor_id no funcionário para histórico/FKs; só desativa o operador.
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

    private function syncVendedorUsuario(int $vendedorId, ?int $usuarioId): void
    {
        User::query()
            ->where('vendedor_id', $vendedorId)
            ->when($usuarioId !== null, fn ($query) => $query->where('id', '!=', $usuarioId))
            ->update(['vendedor_id' => null]);

        if ($usuarioId !== null) {
            User::query()->whereKey($usuarioId)->update(['vendedor_id' => $vendedorId]);
        }
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
