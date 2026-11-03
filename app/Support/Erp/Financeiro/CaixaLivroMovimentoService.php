<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\CaixaLivroMovimento;
use App\Models\CaixaLivroMovimentoEvento;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

final class CaixaLivroMovimentoService
{
    /**
     * Contas elegíveis ao F8 Abrir/Fechar (não PDV operacional).
     *
     * @return list<string>
     */
    public function tiposElegiveis(): array
    {
        return [
            CaixaConta::TIPO_SUBCAIXA,
            CaixaConta::TIPO_COFRE,
            CaixaConta::TIPO_BANCO,
        ];
    }

    public function exigeTroco(CaixaConta $conta): bool
    {
        return (string) $conta->tipo !== CaixaConta::TIPO_BANCO;
    }

    public function tabelaPronta(): bool
    {
        return Schema::hasTable('caixa_livro_movimentos');
    }

    public function movimentoAberto(int $empresaId, int $contaId): ?CaixaLivroMovimento
    {
        if (! $this->tabelaPronta() || $empresaId <= 0 || $contaId <= 0) {
            return null;
        }

        return CaixaLivroMovimento::query()
            ->with(['abertoPor:id,name'])
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->where('status', CaixaLivroMovimento::STATUS_ABERTO)
            ->orderByDesc('id')
            ->first();
    }

    public function assertContaElegivel(int $contaId, int $empresaId): CaixaConta
    {
        $conta = CaixaConta::query()
            ->visiveisNaEmpresa($empresaId)
            ->whereKey($contaId)
            ->first();

        if (! $conta || ! $conta->ativo) {
            throw new InvalidArgumentException('Conta inválida, inativa ou de outra empresa.');
        }

        if (! in_array((string) $conta->tipo, $this->tiposElegiveis(), true)) {
            throw new InvalidArgumentException(
                'Contas PDV operacionais usam o fechamento do PDV. Use Abrir/Fechar apenas em SUBCAIXA, COFRE ou BANCO.'
            );
        }

        return $conta;
    }

    /**
     * @return array{
     *     empresa_id: int,
     *     conta: CaixaConta,
     *     saldo: float,
     *     exige_troco: bool,
     *     aberto: ?CaixaLivroMovimento
     * }
     */
    public function contexto(int $contaId, ?int $empresaId = null): array
    {
        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa logada não encontrada.');
        }

        $conta = $this->assertContaElegivel($contaId, $empresaId);
        $aberto = $this->movimentoAberto($empresaId, $contaId);
        $saldo = ErpFinanceiroMetricas::saldoCaixa($contaId, $empresaId);

        return [
            'empresa_id' => $empresaId,
            'conta' => $conta,
            'saldo' => $saldo,
            'exige_troco' => $this->exigeTroco($conta),
            'aberto' => $aberto,
        ];
    }

    /**
     * Janela operacional do movimento (abertura → fechamento ou agora).
     * Vários movimentos no mesmo dia são independentes — não usar só a data de emissão.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function limitesDoMovimento(CaixaLivroMovimento $mov, ?Carbon $ateLimite = null): array
    {
        $de = Carbon::parse($mov->aberto_em);
        if ($ateLimite !== null) {
            $ate = $ateLimite->copy();
        } elseif ((string) $mov->status === CaixaLivroMovimento::STATUS_FECHADO && filled($mov->fechado_em)) {
            $ate = Carbon::parse($mov->fechado_em);
        } else {
            $ate = ErpTimezone::toLocal();
        }

        if ($ate->lt($de)) {
            $ate = $de->copy();
        }

        return [$de, $ate];
    }

    /**
     * Lançamentos pertencentes ao movimento (created_at dentro da janela da abertura).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\CaixaLancamento>
     */
    public function queryLancamentosDoMovimento(CaixaLivroMovimento $mov, ?Carbon $ateLimite = null)
    {
        [$de, $ate] = $this->limitesDoMovimento($mov, $ateLimite);

        return CaixaLancamento::query()
            ->where('empresa_id', (int) $mov->empresa_id)
            ->where('caixa_conta_id', (int) $mov->caixa_conta_id)
            ->where('created_at', '>=', $de->format('Y-m-d H:i:s'))
            ->where('created_at', '<=', $ate->format('Y-m-d H:i:s'));
    }

    /**
     * Totais operacionais de um movimento específico (abertura atual ou fechado).
     *
     * @return array{
     *     entradas: float,
     *     saidas: float,
     *     transferencias_entrada: float,
     *     transferencias_saida: float,
     *     saldo_financeiro: float,
     *     saldo_movimento: float,
     *     saldo_calculado: float
     * }
     */
    public function totaisDoMovimento(CaixaLivroMovimento $mov, ?Carbon $ateLimite = null): array
    {
        return $this->totaisDaJanela(
            $this->queryLancamentosDoMovimento($mov, $ateLimite),
            (float) $mov->saldo_abertura,
            (float) $mov->troco_inicial,
        );
    }

    /**
     * Totais entre instantes (created_at). Preferir {@see totaisDoMovimento()} para F8/rodapé.
     *
     * @return array{
     *     entradas: float,
     *     saidas: float,
     *     transferencias_entrada: float,
     *     transferencias_saida: float,
     *     saldo_financeiro: float,
     *     saldo_movimento: float,
     *     saldo_calculado: float
     * }
     */
    public function totaisPeriodo(
        int $empresaId,
        int $contaId,
        Carbon $de,
        Carbon $ate,
        float $saldoAbertura,
        float $trocoInicial = 0.0,
    ): array {
        $q = CaixaLancamento::query()
            ->where('caixa_conta_id', $contaId)
            ->where('empresa_id', $empresaId)
            ->where('created_at', '>=', $de->format('Y-m-d H:i:s'))
            ->where('created_at', '<=', $ate->format('Y-m-d H:i:s'));

        return $this->totaisDaJanela($q, $saldoAbertura, $trocoInicial);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\CaixaLancamento>  $q
     * @return array{
     *     entradas: float,
     *     saidas: float,
     *     transferencias_entrada: float,
     *     transferencias_saida: float,
     *     saldo_financeiro: float,
     *     saldo_movimento: float,
     *     saldo_calculado: float
     * }
     */
    private function totaisDaJanela($q, float $saldoAbertura, float $trocoInicial): array
    {
        $row = (clone $q)
            ->selectRaw(
                'COALESCE(SUM(entrada),0) as entradas, COALESCE(SUM(saida),0) as saidas'
            )
            ->first();

        $entradas = round((float) ($row->entradas ?? 0), 2);
        $saidas = round((float) ($row->saidas ?? 0), 2);

        $trfQ = (clone $q);
        CaixaTransferencia::excluirDoQuery($trfQ);
        // transferências = total - operacional
        $op = $trfQ
            ->selectRaw(
                'COALESCE(SUM(entrada),0) as entradas, COALESCE(SUM(saida),0) as saidas'
            )
            ->first();
        $opEnt = round((float) ($op->entradas ?? 0), 2);
        $opSai = round((float) ($op->saidas ?? 0), 2);

        $trfEnt = round($entradas - $opEnt, 2);
        $trfSai = round($saidas - $opSai, 2);

        $saldoFinanceiro = round($saldoAbertura + $entradas - $saidas, 2);
        $saldoMovimento = round($trocoInicial + $entradas - $saidas, 2);

        return [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'transferencias_entrada' => $trfEnt,
            'transferencias_saida' => $trfSai,
            'saldo_financeiro' => $saldoFinanceiro,
            'saldo_movimento' => $saldoMovimento,
            // Conferência do operador = saldo do movimento (troco + E − S).
            'saldo_calculado' => $saldoMovimento,
        ];
    }

    public function abrir(
        int $contaId,
        float $trocoInicial,
        string $observacao = '',
        ?int $empresaId = null,
        ?User $usuario = null,
    ): CaixaLivroMovimento {
        if (! $this->tabelaPronta()) {
            throw new InvalidArgumentException('Estrutura de abertura/fechamento ainda não está disponível.');
        }

        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        $usuario ??= auth()->user();
        if (! $usuario instanceof User) {
            throw new InvalidArgumentException('Usuário responsável não identificado.');
        }
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa logada não encontrada.');
        }

        $conta = $this->assertContaElegivel($contaId, $empresaId);
        $trocoInicial = round(max(0, $trocoInicial), 2);
        if (! $this->exigeTroco($conta)) {
            $trocoInicial = 0.0;
        }

        try {
            return DB::transaction(function () use (
                $conta,
                $empresaId,
                $usuario,
                $trocoInicial,
                $observacao,
            ): CaixaLivroMovimento {
                CaixaConta::query()->whereKey($conta->id)->lockForUpdate()->first();

                $existente = CaixaLivroMovimento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('caixa_conta_id', $conta->id)
                    ->where('status', CaixaLivroMovimento::STATUS_ABERTO)
                    ->lockForUpdate()
                    ->first();

                if ($existente) {
                    throw new InvalidArgumentException(
                        'Já existe um movimento aberto para esta conta nesta empresa.'
                    );
                }

                $agora = ErpTimezone::toLocal();
                $saldo = ErpFinanceiroMetricas::saldoCaixa((int) $conta->id, $empresaId);

                $mov = CaixaLivroMovimento::query()->create([
                    'empresa_id' => $empresaId,
                    'caixa_conta_id' => (int) $conta->id,
                    'status' => CaixaLivroMovimento::STATUS_ABERTO,
                    'aberto_em' => $agora,
                    'aberto_por' => $usuario->getKey(),
                    'saldo_abertura' => $saldo,
                    'troco_inicial' => $trocoInicial,
                    'observacao_abertura' => mb_substr(trim($observacao), 0, 500) ?: null,
                ]);

                $this->registrarEvento($mov, CaixaLivroMovimentoEvento::TIPO_ABRIR, $usuario, [
                    'saldo_abertura' => $saldo,
                    'troco_inicial' => $trocoInicial,
                ]);

                return $mov->fresh() ?? $mov;
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Não foi possível abrir o caixa. Tente novamente.', 0, $e);
        }
    }

    /**
     * Fecha o movimento recalculando saldos/totais no servidor.
     * Do cliente só entram: observação + valores informados por forma.
     * Empresa/conta/troco/saldos do frontend NÃO são fonte de verdade.
     *
     * @param  array<string, mixed>|null  $informadosPorForma  valores BR/float por chave (dinheiro, pix, …)
     */
    public function fechar(
        int $contaId,
        string $observacao = '',
        ?int $empresaId = null,
        ?User $usuario = null,
        ?array $informadosPorForma = null,
        ?int $movimentoIdEsperado = null,
    ): CaixaLivroMovimento {
        if (! $this->tabelaPronta()) {
            throw new InvalidArgumentException('Estrutura de abertura/fechamento ainda não está disponível.');
        }

        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        $usuario ??= auth()->user();
        if (! $usuario instanceof User) {
            throw new InvalidArgumentException('Usuário responsável não identificado.');
        }
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa logada não encontrada.');
        }
        if ($contaId <= 0) {
            throw new InvalidArgumentException('Conta inválida para fechamento.');
        }

        $conta = $this->assertContaElegivel($contaId, $empresaId);
        $exigeTroco = $this->exigeTroco($conta);
        app(CaixaAbertoGuard::class)->assertPodeFecharConta($conta, $empresaId);

        try {
            return DB::transaction(function () use (
                $conta,
                $contaId,
                $empresaId,
                $usuario,
                $observacao,
                $informadosPorForma,
                $exigeTroco,
                $movimentoIdEsperado,
            ): CaixaLivroMovimento {
                CaixaConta::query()->whereKey($contaId)->lockForUpdate()->first();

                // Revalida PDV sob lock da conta (corrida com abertura F2).
                app(CaixaAbertoGuard::class)->assertPodeFecharConta($conta, $empresaId);

                $q = CaixaLivroMovimento::query()
                    ->where('empresa_id', $empresaId)
                    ->where('caixa_conta_id', $contaId)
                    ->where('status', CaixaLivroMovimento::STATUS_ABERTO)
                    ->lockForUpdate();

                if ($movimentoIdEsperado !== null && $movimentoIdEsperado > 0) {
                    $q->whereKey($movimentoIdEsperado);
                }

                $mov = $q->first();

                if (! $mov) {
                    throw new InvalidArgumentException(
                        'Não há movimento aberto válido para fechar nesta conta/empresa (já fechado ou inconsistente).'
                    );
                }

                // Impede fechamento duplicado sob concorrência.
                if ((string) $mov->status !== CaixaLivroMovimento::STATUS_ABERTO) {
                    throw new InvalidArgumentException('Este movimento já foi fechado.');
                }

                // Sempre recalcula só os lançamentos desta abertura (não o dia/mês inteiro).
                $agora = ErpTimezone::toLocal();
                $totais = $this->totaisDoMovimento($mov, $agora);

                $modalidades = CaixaLivroFechamentoConferencia::modalidades(
                    $conta,
                    $totais['saldo_movimento'],
                    $exigeTroco,
                );

                $chavesOk = [];
                foreach ($modalidades as $mod) {
                    $chave = (string) ($mod['chave'] ?? '');
                    if ($chave !== '') {
                        $chavesOk[$chave] = true;
                    }
                }

                $informados = [];
                foreach (is_array($informadosPorForma) ? $informadosPorForma : [] as $chave => $valor) {
                    $chave = (string) $chave;
                    if (! isset($chavesOk[$chave])) {
                        continue;
                    }
                    $informados[$chave] = $valor;
                }

                $payload = CaixaLivroFechamentoConferencia::montarPayload(
                    $modalidades,
                    $informados,
                    $exigeTroco,
                );

                $valorContadoFinal = $payload['valor_contado'];
                // Diferença só quando o esperado físico for apurável (troco/movimento).
                // Sem esperado confirmado, não inventa diferença zerada/falsa.
                $diferenca = $payload['diferenca_disponivel']
                    ? $payload['diferenca']
                    : null;

                $fill = [
                    'status' => CaixaLivroMovimento::STATUS_FECHADO,
                    'fechado_em' => $agora,
                    'fechado_por' => $usuario->getKey(),
                    'total_entradas' => $totais['entradas'],
                    'total_saidas' => $totais['saidas'],
                    'total_transferencias_entrada' => $totais['transferencias_entrada'],
                    'total_transferencias_saida' => $totais['transferencias_saida'],
                    'saldo_calculado' => $totais['saldo_calculado'],
                    'valor_contado' => $valorContadoFinal,
                    'diferenca' => $diferenca,
                    'observacao_fechamento' => mb_substr(trim($observacao), 0, 500) ?: null,
                ];

                if (Schema::hasColumn('caixa_livro_movimentos', 'conferencia_formas')) {
                    $fill['conferencia_formas'] = [
                        'linhas' => $payload['linhas'],
                        'totais' => [
                            'informado' => $payload['total_informado'],
                            'fisico' => $payload['total_informado_fisico'],
                            'eletronico' => $payload['total_informado_eletronico'],
                            'outros' => $payload['total_informado_outros'],
                        ],
                        'diferenca_fisico' => $payload['diferenca'],
                        'diferenca_disponivel' => $payload['diferenca_disponivel'],
                    ];
                }
                if (Schema::hasColumn('caixa_livro_movimentos', 'total_informado')) {
                    $fill['total_informado'] = $payload['total_informado'];
                }

                $mov->fill($fill);
                $mov->save();

                $this->registrarEvento($mov, CaixaLivroMovimentoEvento::TIPO_FECHAR, $usuario, [
                    'valor_contado' => $valorContadoFinal,
                    'diferenca' => $diferenca,
                    'saldo_calculado' => $totais['saldo_calculado'],
                    'total_informado' => $payload['total_informado'],
                    'conferencia' => $payload['linhas'],
                ]);

                return $mov->fresh() ?? $mov;
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Não foi possível fechar o caixa. Tente novamente.', 0, $e);
        }
    }

    /**
     * Reabertura auditável (permissão específica). Não gera lançamentos.
     */
    public function reabrir(
        int $movimentoId,
        string $motivo,
        ?int $empresaId = null,
        ?User $usuario = null,
    ): CaixaLivroMovimento {
        if (! $this->tabelaPronta()) {
            throw new InvalidArgumentException('Estrutura de abertura/fechamento ainda não está disponível.');
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('Informe a justificativa da reabertura.');
        }

        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        $usuario ??= auth()->user();
        if (! $usuario instanceof User || $empresaId <= 0) {
            throw new InvalidArgumentException('Empresa/usuário inválidos para reabertura.');
        }

        return DB::transaction(function () use ($movimentoId, $motivo, $empresaId, $usuario): CaixaLivroMovimento {
            $mov = CaixaLivroMovimento::query()
                ->whereKey($movimentoId)
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->first();

            if (! $mov || ! $mov->isFechado()) {
                throw new InvalidArgumentException('Movimento fechado não encontrado para reabertura.');
            }

            $abertoOutro = CaixaLivroMovimento::query()
                ->where('empresa_id', $empresaId)
                ->where('caixa_conta_id', $mov->caixa_conta_id)
                ->where('status', CaixaLivroMovimento::STATUS_ABERTO)
                ->lockForUpdate()
                ->exists();

            if ($abertoOutro) {
                throw new InvalidArgumentException(
                    'Já existe um movimento aberto nesta conta. Feche-o antes de reabrir um anterior.'
                );
            }

            $agora = ErpTimezone::toLocal();
            $fill = [
                'status' => CaixaLivroMovimento::STATUS_ABERTO,
                'fechado_em' => null,
                'fechado_por' => null,
                'total_entradas' => null,
                'total_saidas' => null,
                'total_transferencias_entrada' => null,
                'total_transferencias_saida' => null,
                'saldo_calculado' => null,
                'valor_contado' => null,
                'diferenca' => null,
                'observacao_fechamento' => null,
                'reaberto_em' => $agora,
                'reaberto_por' => $usuario->getKey(),
                'motivo_reabertura' => mb_substr($motivo, 0, 500),
            ];
            if (Schema::hasColumn('caixa_livro_movimentos', 'conferencia_formas')) {
                $fill['conferencia_formas'] = null;
            }
            if (Schema::hasColumn('caixa_livro_movimentos', 'total_informado')) {
                $fill['total_informado'] = null;
            }
            $mov->fill($fill);
            $mov->save();

            $this->registrarEvento($mov, CaixaLivroMovimentoEvento::TIPO_REABRIR, $usuario, [
                'motivo' => $motivo,
            ]);

            return $mov->fresh() ?? $mov;
        });
    }

    /**
     * Bloqueia mutação se a data cai em movimento já fechado da conta/empresa.
     */
    public function assertPodeMutarLancamento(int $contaId, string $emissao, ?int $empresaId = null): void
    {
        if (! $this->tabelaPronta()) {
            return;
        }

        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0 || $contaId <= 0) {
            return;
        }

        $data = Carbon::parse($emissao)->toDateString();

        $bloqueado = CaixaLivroMovimento::query()
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->where('status', CaixaLivroMovimento::STATUS_FECHADO)
            ->whereDate('aberto_em', '<=', $data)
            ->whereDate('fechado_em', '>=', $data)
            ->exists();

        if ($bloqueado) {
            throw new InvalidArgumentException(
                'Período já fechado para esta conta/empresa. Reabertura exige permissão específica.'
            );
        }
    }

    /**
     * Exige CAIXA GERAL aberto na empresa e, em SUBCAIXA/COFRE, o próprio F8.
     * Fonte de verdade: {@see CaixaAbertoGuard}.
     */
    public function assertPodeOperarConta(int $contaId, ?int $empresaId = null): void
    {
        app(CaixaAbertoGuard::class)->assertPodeLancarNaConta($contaId, $empresaId);
    }

    public function assertEmpresaOperacional(?int $empresaId = null): void
    {
        app(CaixaAbertoGuard::class)->assertEmpresaOperacional($empresaId);
    }

    private function registrarEvento(
        CaixaLivroMovimento $mov,
        string $tipo,
        User $usuario,
        array $payload = [],
    ): void {
        if (! Schema::hasTable('caixa_livro_movimento_eventos')) {
            return;
        }

        CaixaLivroMovimentoEvento::query()->create([
            'caixa_livro_movimento_id' => $mov->id,
            'tipo' => $tipo,
            'user_id' => $usuario->getKey(),
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
