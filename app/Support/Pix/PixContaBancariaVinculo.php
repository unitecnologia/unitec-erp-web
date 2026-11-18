<?php

namespace App\Support\Pix;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Vínculo da API PIX com uma conta BANCO da própria empresa.
 * Não altera provedor, credenciais nem cobranças já emitidas.
 */
final class PixContaBancariaVinculo
{
    public const COLUMN = EmpresaParametros::COLUMN_PIX_CAIXA_CONTA_ID;

    public const MSG_OBRIGATORIA = 'Selecione a conta bancária vinculada para habilitar a API PIX. Se não houver, cadastre-a em Financeiro → Contas (tipo BANCO).';

    public const MSG_INVALIDA = 'A conta bancária vinculada precisa ser ativa, do tipo BANCO e pertencer a esta empresa. Cadastre-a em Financeiro → Contas se ainda não existir.';

    public const MSG_SEM_EMPRESA = 'Grave a empresa e cadastre uma conta bancária em Financeiro → Contas antes de habilitar a API PIX.';

    public static function colunaExiste(): bool
    {
        return Schema::hasTable('empresas')
            && Schema::hasColumn('empresas', self::COLUMN);
    }

    public static function normalizarId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * Contas BANCO ativas exclusivas da empresa (sem compartilhadas).
     *
     * @return list<array{id: int, label: string}>
     */
    public static function opcoesParaEmpresa(?int $empresaId, ?int $selecionadaId = null): array
    {
        $empresaId = (int) $empresaId;
        if ($empresaId <= 0 || ! Schema::hasTable('caixa_contas')) {
            return [];
        }

        $rows = CaixaConta::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', CaixaConta::TIPO_BANCO)
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('nome')
            ->limit(200)
            ->get(['id', 'codigo', 'nome']);

        $selecionadaId = (int) $selecionadaId;
        if ($selecionadaId > 0 && ! $rows->contains(fn (CaixaConta $c): bool => (int) $c->id === $selecionadaId)) {
            $extra = CaixaConta::query()
                ->whereKey($selecionadaId)
                ->where('empresa_id', $empresaId)
                ->where('tipo', CaixaConta::TIPO_BANCO)
                ->first(['id', 'codigo', 'nome']);
            if ($extra) {
                $rows->push($extra);
            }
        }

        return $rows
            ->map(fn (CaixaConta $c): array => [
                'id' => (int) $c->id,
                'label' => self::rotulo($c),
            ])
            ->values()
            ->all();
    }

    public static function contaIdDaEmpresa(?int $empresaId): ?int
    {
        $empresaId = (int) $empresaId;
        if ($empresaId <= 0 || ! self::colunaExiste()) {
            return null;
        }

        $contaId = self::normalizarId(
            Empresa::query()->whereKey($empresaId)->value(self::COLUMN)
        );

        return self::contaValidaParaEmpresa($empresaId, $contaId) ? $contaId : null;
    }

    public static function contaValidaParaEmpresa(?int $empresaId, mixed $contaId): bool
    {
        $empresaId = (int) $empresaId;
        $contaId = (int) $contaId;

        if ($empresaId <= 0 || $contaId <= 0 || ! Schema::hasTable('caixa_contas')) {
            return false;
        }

        return CaixaConta::query()
            ->whereKey($contaId)
            ->where('empresa_id', $empresaId)
            ->where('tipo', CaixaConta::TIPO_BANCO)
            ->where('ativo', true)
            ->exists();
    }

    public static function mensagemSeInvalido(?int $empresaId, mixed $contaId, bool $pixHabilitado): ?string
    {
        if (! $pixHabilitado) {
            return null;
        }

        $empresaId = (int) $empresaId;
        if ($empresaId <= 0) {
            return self::MSG_SEM_EMPRESA;
        }

        $contaId = self::normalizarId($contaId);
        if ($contaId === null) {
            return self::MSG_OBRIGATORIA;
        }

        return self::contaValidaParaEmpresa($empresaId, $contaId) ? null : self::MSG_INVALIDA;
    }

    public static function validarEmpresaOuFalhar(Empresa $empresa): void
    {
        if (! self::colunaExiste()) {
            return;
        }

        $habilitado = filter_var($empresa->param_pix_habilitar ?? false, FILTER_VALIDATE_BOOLEAN);
        $contaId = self::normalizarId($empresa->{self::COLUMN} ?? null);
        $empresa->{self::COLUMN} = $contaId;

        if (! $habilitado) {
            return;
        }

        $erro = self::mensagemSeInvalido((int) ($empresa->getKey() ?: 0), $contaId, true);
        if ($erro === null) {
            return;
        }

        throw ValidationException::withMessages([
            self::COLUMN => $erro,
            'data.'.self::COLUMN => $erro,
        ]);
    }

    private static function rotulo(CaixaConta $conta): string
    {
        $codigo = trim((string) ($conta->codigo ?? ''));
        $nome = mb_strtoupper(trim((string) ($conta->nome ?? '')), 'UTF-8');

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' — '.$nome;
        }

        return $nome !== '' ? $nome : ($codigo !== '' ? $codigo : '#'.(int) $conta->id);
    }
}
