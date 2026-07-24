<?php

namespace App\Support\Erp\Pdv;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\PdvCaixaSessao;
use App\Models\User;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Lança o saldo em dinheiro do fechamento do PDV no Livro Caixa (CAIXA GERAL).
 */
final class PdvCaixaFechamentoService
{
    public function lancarNoLivroCaixa(PdvCaixaSessao $sessao, ?User $usuario = null): ?CaixaLancamento
    {
        if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return null;
        }

        $documento = $this->documento($sessao);

        $existente = CaixaLancamento::query()
            ->where('documento', $documento)
            ->first();

        if ($existente) {
            return $existente;
        }

        $valor = round((float) $sessao->saldoDinheiro(), 2);

        if ($valor <= 0) {
            return null;
        }

        $conta = CaixaConta::ensureCaixaGeral();
        $usuario ??= Auth::user() ?? $sessao->user;
        $nome = mb_strtoupper(trim((string) ($usuario?->name ?? 'USUARIO')), 'UTF-8');
        $agora = $sessao->fechado_em
            ? ErpTimezone::toLocal($sessao->fechado_em)
            : ErpTimezone::toLocal();

        return CaixaLancamento::query()->create([
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $agora->toDateString(),
            'documento' => mb_substr($documento, 0, 40),
            'historico' => mb_substr(
                sprintf('FECHAMENTO DO CX:CAIXA-%s-%s', $nome, $agora->format('d/m/Y H:i:s')),
                0,
                180,
            ),
            'plano_contas' => null,
            'plano_conta_id' => null,
            'caixa_conta_id' => $conta->id,
            'entrada' => $valor,
            'saida' => 0,
        ]);
    }

    /**
     * Gera lançamentos faltantes de sessões já fechadas (ex.: fechamento antes desta correção).
     */
    public function backfillSessoesRecentes(int $dias = 14): int
    {
        if (! Schema::hasTable((new PdvCaixaSessao)->getTable())) {
            return 0;
        }

        $desde = Carbon::now()->subDays($dias);
        $criados = 0;

        $sessoes = PdvCaixaSessao::query()
            ->with('user')
            ->whereNotNull('fechado_em')
            ->where('fechado_em', '>=', $desde)
            ->orderByDesc('id')
            ->get();

        foreach ($sessoes as $sessao) {
            if (CaixaLancamento::query()->where('documento', $this->documento($sessao))->exists()) {
                continue;
            }

            if ($this->lancarNoLivroCaixa($sessao, $sessao->user) !== null) {
                $criados++;
            }
        }

        return $criados;
    }

    private function documento(PdvCaixaSessao $sessao): string
    {
        return 'PDV-CX-'.$sessao->id;
    }
}
