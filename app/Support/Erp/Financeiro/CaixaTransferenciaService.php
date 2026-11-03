<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\User;
use App\Support\Erp\ErpContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

final class CaixaTransferenciaService
{
    /**
     * Contas elegíveis para transferência no Livro Caixa (não PDV operacional).
     *
     * @return list<int>
     */
    public function tiposPermitidos(): array
    {
        return [
            CaixaConta::TIPO_BANCO,
            CaixaConta::TIPO_SUBCAIXA,
            CaixaConta::TIPO_COFRE,
        ];
    }

    /**
     * @return array<int, string> id => nome
     */
    public function contasTransferiveis(?User $usuario = null, ?int $empresaId = null): array
    {
        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        $usuario ??= auth()->user();

        $query = CaixaConta::query()
            ->visiveisNaEmpresa($empresaId > 0 ? $empresaId : null)
            ->where('ativo', true)
            ->whereIn('tipo', $this->tiposPermitidos())
            ->orderBy('nome');

        $contas = $query->get(['id', 'nome', 'tipo', 'sistema', 'empresa_id']);

        $idsPermitidos = null;
        if ($usuario instanceof User && ! $usuario->is_admin && $empresaId > 0) {
            $assigned = DB::table('caixa_conta_user')
                ->where('user_id', $usuario->getKey())
                ->where('empresa_id', $empresaId)
                ->pluck('caixa_conta_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            // Pivot vazio = sem restrição (mesmo padrão do Livro: vê todas as ativas da empresa).
            if ($assigned !== []) {
                $idsPermitidos = $assigned;
            }
        }

        $out = [];
        foreach ($contas as $conta) {
            $id = (int) $conta->id;
            $isGeral = $conta->isSistema();

            // Conta exclusiva de outra empresa nunca entra (já filtrada no scope; reforço).
            if (! $conta->pertenceOuCompartilhadaCom($empresaId)) {
                continue;
            }

            if ($idsPermitidos !== null && ! $isGeral && ! in_array($id, $idsPermitidos, true)) {
                continue;
            }

            $out[$id] = (string) $conta->nome;
        }

        return $out;
    }

    /**
     * @return array{documento: string, origem_id: int, destino_id: int, valor: float}
     */
    public function transferir(
        int $origemId,
        int $destinoId,
        float $valor,
        Carbon|string $emissao,
        string $historico,
        ?int $empresaId = null,
        ?User $usuario = null,
        ?string $documentoInformado = null,
    ): array {
        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa logada não encontrada para a transferência.');
        }

        $usuario ??= auth()->user();
        $valor = round(max(0, $valor), 2);
        if ($valor <= 0) {
            throw new InvalidArgumentException('Informe um valor maior que zero.');
        }

        if ($origemId <= 0 || $destinoId <= 0 || $origemId === $destinoId) {
            throw new InvalidArgumentException('Selecione contas de origem e destino diferentes.');
        }

        $emissaoData = $emissao instanceof Carbon
            ? $emissao->toDateString()
            : Carbon::parse((string) $emissao)->toDateString();

        $obs = trim($historico);
        if ($obs === '') {
            throw new InvalidArgumentException('Informe o histórico da transferência.');
        }

        $documento = trim((string) ($documentoInformado ?? ''));
        if ($documento === '' || ! CaixaTransferencia::isDocumentoTransferencia($documento)) {
            $documento = CaixaTransferencia::novoDocumento();
        }
        $documento = mb_substr($documento, 0, 40);

        $contasOk = $this->contasTransferiveis($usuario instanceof User ? $usuario : null, $empresaId);
        if (! isset($contasOk[$origemId]) || ! isset($contasOk[$destinoId])) {
            throw new InvalidArgumentException('Sem permissão ou conta inválida para transferência.');
        }

        $guard = app(CaixaAbertoGuard::class);
        $guard->assertPodeLancarNaConta($origemId, $empresaId);
        $guard->assertPodeLancarNaConta($destinoId, $empresaId);

        try {
            return DB::transaction(function () use (
                $origemId,
                $destinoId,
                $valor,
                $emissaoData,
                $obs,
                $empresaId,
                $documento,
                $contasOk,
                $guard,
            ): array {
                // Trava as contas para evitar duas transferências consumirem o mesmo saldo.
                CaixaConta::query()->whereKey([$origemId, $destinoId])->orderBy('id')->lockForUpdate()->get();
                $guard->assertPodeLancarNaConta($origemId, $empresaId);
                $guard->assertPodeLancarNaConta($destinoId, $empresaId);

                // Idempotência: reenvio com o mesmo documento não duplica.
                $existentes = CaixaLancamento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('documento', $documento)
                    ->lockForUpdate()
                    ->get();

                if ($existentes->count() >= 2) {
                    return [
                        'documento' => $documento,
                        'origem_id' => $origemId,
                        'destino_id' => $destinoId,
                        'valor' => $valor,
                        'reutilizado' => true,
                    ];
                }

                if ($existentes->isNotEmpty()) {
                    throw new InvalidArgumentException(
                        'Transferência incompleta detectada para este documento. Contate o suporte antes de repetir.'
                    );
                }

                $this->assertContaTransferivel($origemId, $empresaId);
                $this->assertContaTransferivel($destinoId, $empresaId);

                $saldo = $this->saldoContaComLock($origemId, $empresaId, $emissaoData);
                if ($saldo + 0.00001 < $valor) {
                    throw new InvalidArgumentException(
                        'Saldo insuficiente na conta de origem (disponível: R$ '
                        .number_format($saldo, 2, ',', '.').').'
                    );
                }

                $nomeOrigem = $contasOk[$origemId] ?? ('#'.$origemId);
                $nomeDestino = $contasOk[$destinoId] ?? ('#'.$destinoId);
                $obsUpper = mb_strtoupper($obs, 'UTF-8');

                // Reserva dois códigos sob lock para o par (evita colisão no unique).
                CaixaLancamento::query()->orderByDesc('codigo')->lockForUpdate()->value('codigo');
                $codigoSaida = CaixaLancamento::nextCodigo();
                $codigoEntrada = $codigoSaida + 1;

                $base = [
                    'empresa_id' => $empresaId,
                    'emissao' => $emissaoData,
                    'documento' => $documento,
                    'plano_conta_id' => null,
                    'plano_contas' => null,
                ];

                CaixaLancamento::query()->create([
                    ...$base,
                    'codigo' => $codigoSaida,
                    'caixa_conta_id' => $origemId,
                    'entrada' => 0,
                    'saida' => $valor,
                    'historico' => mb_substr(
                        CaixaTransferencia::HISTORICO_PREFIXO.' PARA '.$nomeDestino.' — '.$obsUpper,
                        0,
                        500
                    ),
                ]);

                CaixaLancamento::query()->create([
                    ...$base,
                    'codigo' => $codigoEntrada,
                    'caixa_conta_id' => $destinoId,
                    'entrada' => $valor,
                    'saida' => 0,
                    'historico' => mb_substr(
                        CaixaTransferencia::HISTORICO_PREFIXO.' DE '.$nomeOrigem.' — '.$obsUpper,
                        0,
                        500
                    ),
                ]);

                return [
                    'documento' => $documento,
                    'origem_id' => $origemId,
                    'destino_id' => $destinoId,
                    'valor' => $valor,
                    'reutilizado' => false,
                ];
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                'Não foi possível concluir a transferência. Tente novamente.',
                0,
                $e
            );
        }
    }

    /**
     * Exclui o par completo pelo documento (estorno rastreável).
     */
    public function excluirPar(string $documento, ?int $empresaId = null): int
    {
        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0 || ! CaixaTransferencia::isDocumentoTransferencia($documento)) {
            return 0;
        }

        return (int) DB::transaction(function () use ($documento, $empresaId): int {
            return CaixaLancamento::query()
                ->where('empresa_id', $empresaId)
                ->where('documento', $documento)
                ->delete();
        });
    }

    private function assertContaTransferivel(int $contaId, int $empresaId): void
    {
        $conta = CaixaConta::query()
            ->visiveisNaEmpresa($empresaId)
            ->whereKey($contaId)
            ->first();

        if (! $conta || ! $conta->ativo) {
            throw new InvalidArgumentException('Conta inválida, inativa ou de outra empresa.');
        }

        if (! $conta->pertenceOuCompartilhadaCom($empresaId)) {
            throw new InvalidArgumentException('Não é permitido transferir entre contas de empresas diferentes.');
        }

        if (! in_array((string) $conta->tipo, $this->tiposPermitidos(), true)) {
            throw new InvalidArgumentException(
                'Contas do tipo PDV operacional não podem ser usadas em transferência do Livro Caixa.'
            );
        }
    }

    private function saldoContaComLock(int $contaId, int $empresaId, string $ate): float
    {
        $q = CaixaLancamento::query()
            ->where('caixa_conta_id', $contaId)
            ->whereDate('emissao', '<=', $ate);

        if (Schema::hasColumn('caixa_lancamentos', 'empresa_id')) {
            $q->where('empresa_id', $empresaId);
        }

        // lockForUpdate no aggregate não é portável; as contas já estão travadas.
        $row = $q
            ->selectRaw('COALESCE(SUM(entrada), 0) as entradas, COALESCE(SUM(saida), 0) as saidas')
            ->first();

        return round((float) ($row->entradas ?? 0) - (float) ($row->saidas ?? 0), 2);
    }
}
