<?php

namespace App\Support\Erp;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\PriceTable;
use App\Models\RhCargo;
use App\Models\RhFuncionario;
use App\Models\Terminal;
use App\Models\User;
use App\Models\Vendedor;
use Database\Seeders\ProductAuxiliarySeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Após cadastrar a empresa, deixa o ERP pronto para vender em modo não fiscal
 * (Pedido/Concluir no PDV): estoque, caixa PDV, terminal sem NFC-e e operador.
 */
final class EmpresaVendaProntaBootstrap
{
    public static function forEmpresa(Empresa $empresa, ?User $user = null): void
    {
        self::ensureCadastrosAuxiliares();
        CaixaConta::ensureCaixaGeral();

        $estoque = self::ensureEstoqueLoja($empresa);
        $caixaPdv = self::ensureCaixaPdv($empresa);
        self::ensureTerminalNaoFiscal($empresa);

        if ($user instanceof User) {
            self::ensureOperadorVendaPronta($empresa, $user, $estoque, $caixaPdv);
        }
    }

    public static function ensureCadastrosAuxiliares(): void
    {
        (new ProductAuxiliarySeeder)->run();
    }

    public static function ensureEstoqueLoja(Empresa $empresa): Estoque
    {
        $existente = Estoque::query()
            ->where('empresa_id', $empresa->id)
            ->where(function ($q): void {
                $q->whereRaw('UPPER(nome) = ?', ['LOJA'])
                    ->orWhereRaw('UPPER(codigo) = ?', ['1']);
            })
            ->orderBy('id')
            ->first();

        if ($existente) {
            if (! $existente->ativo) {
                $existente->forceFill(['ativo' => true])->save();
            }

            return $existente;
        }

        return Estoque::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => Estoque::nextCodigo((int) $empresa->id),
            'nome' => 'LOJA',
            'ativo' => true,
        ]);
    }

    public static function ensureCaixaPdv(Empresa $empresa): CaixaConta
    {
        $codigoEmpresa = trim((string) ($empresa->codigo ?? ''));
        $nome = $codigoEmpresa !== ''
            ? 'CAIXA PDV '.$codigoEmpresa
            : 'CAIXA PDV';

        $existente = CaixaConta::query()
            ->whereRaw('UPPER(nome) = ?', [mb_strtoupper($nome, 'UTF-8')])
            ->first();

        if ($existente) {
            $existente->fill([
                'tipo' => CaixaConta::TIPO_PDV,
                'situacao' => CaixaConta::SITUACAO_ABERTO,
                'ativo' => true,
                'nome' => $nome,
            ]);

            if ($existente->isDirty()) {
                $existente->save();
            }

            return $existente;
        }

        $codigo = ((int) CaixaConta::query()->max('codigo')) + 1;

        if ($codigo < 1) {
            $codigo = 1;
        }

        return CaixaConta::query()->create([
            'codigo' => $codigo,
            'nome' => $nome,
            'tipo' => CaixaConta::TIPO_PDV,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);
    }

    public static function ensureTerminalNaoFiscal(Empresa $empresa): Terminal
    {
        $attrs = [
            ...Terminal::defaultAttributes((int) $empresa->id),
            'empresa_id' => $empresa->id,
            'nome' => 'CAIXA-1',
            'velocidade' => 9600,
            'porta' => 'RAW:IMPRESSORA',
            // Modo não fiscal: só Pedido/Concluir (F5 → F10).
            'exibe_f3' => false,
            'exibe_f4' => false,
            'exibe_f5' => true,
            'exibe_f6' => false,
            'numero_logico_terminal' => 1,
            'ativo' => true,
        ];

        $terminal = Terminal::query()
            ->where('empresa_id', $empresa->id)
            ->where('nome', 'CAIXA-1')
            ->first();

        if ($terminal) {
            $terminal->fill([
                'exibe_f3' => false,
                'exibe_f4' => false,
                'exibe_f5' => true,
                'exibe_f6' => false,
                'ativo' => true,
            ]);

            if ($terminal->isDirty()) {
                $terminal->save();
            }

            return $terminal;
        }

        if (! Terminal::query()->where('empresa_id', $empresa->id)->exists()) {
            return Terminal::query()->create($attrs);
        }

        return Terminal::query()->create([
            ...$attrs,
            'numero_logico_terminal' => ((int) Terminal::query()
                ->where('empresa_id', $empresa->id)
                ->max('numero_logico_terminal')) + 1,
        ]);
    }

    public static function ensureOperadorVendaPronta(
        Empresa $empresa,
        User $user,
        Estoque $estoque,
        CaixaConta $caixaPdv,
    ): Vendedor {
        $vendedor = $user->vendedor_id
            ? Vendedor::query()->find($user->vendedor_id)
            : null;

        if (! $vendedor) {
            $vendedor = Vendedor::query()
                ->where('nome', mb_strtoupper(trim((string) $user->name), 'UTF-8'))
                ->where('ativo', true)
                ->orderBy('id')
                ->first();
        }

        $tabelaId = PriceTable::query()
            ->whereRaw('UPPER(descricao) = ?', ['VAREJO'])
            ->value('id');

        if (! $vendedor) {
            $vendedor = Vendedor::query()->create([
                'codigo' => Vendedor::nextCodigo(),
                'nome' => mb_strtoupper(trim((string) $user->name) ?: 'OPERADOR', 'UTF-8'),
                'ativo' => true,
                'empresa_id' => $empresa->id,
                'cargo' => 'OPERADOR',
                'estoque_id' => $estoque->id,
                'estoque' => $estoque->nome,
                'tabela_venda_id' => $tabelaId ? (int) $tabelaId : null,
                'efetua_venda' => true,
                'setor_vendas' => true,
                'comissao_av' => 0,
                'comissao_ap' => 0,
            ]);
        } else {
            $vendedor->fill([
                'ativo' => true,
                'empresa_id' => $vendedor->empresa_id ?: $empresa->id,
                'estoque_id' => $vendedor->estoque_id ?: $estoque->id,
                'estoque' => $vendedor->estoque ?: $estoque->nome,
                'tabela_venda_id' => $vendedor->tabela_venda_id ?: ($tabelaId ? (int) $tabelaId : null),
                'efetua_venda' => true,
                'setor_vendas' => true,
                'cargo' => filled($vendedor->cargo) ? $vendedor->cargo : 'OPERADOR',
            ]);

            if ($vendedor->isDirty()) {
                $vendedor->save();
            }
        }

        $user->forceFill(['vendedor_id' => $vendedor->id])->save();

        $vendedor->empresas()->syncWithoutDetaching([
            $empresa->id => ['caixa_conta_id' => $caixaPdv->id],
        ]);

        self::ensureRhVinculo($vendedor, $user);

        return $vendedor->fresh();
    }

    private static function ensureRhVinculo(Vendedor $vendedor, User $user): void
    {
        if (! Schema::hasTable('rh_funcionarios')) {
            return;
        }

        if ($vendedor->rhFuncionario()->exists()) {
            return;
        }

        $cargoId = null;

        if (Schema::hasTable('rh_cargos')) {
            $cargo = RhCargo::query()->firstOrCreate(
                ['nome' => 'OPERADOR'],
                [
                    'codigo' => RhCargo::nextCodigo(),
                    'ativo' => true,
                ],
            );
            $cargoId = $cargo->id;
        }

        $nome = mb_strtoupper(trim((string) $user->name) ?: (string) $vendedor->nome, 'UTF-8');

        RhFuncionario::query()->create([
            'codigo' => RhFuncionario::nextCodigo(),
            'nome' => $nome,
            'cargo_id' => $cargoId,
            'user_id' => $user->id,
            'vendedor_id' => $vendedor->id,
            'ativo' => true,
            'data_admissao' => now()->toDateString(),
        ]);
    }
}
