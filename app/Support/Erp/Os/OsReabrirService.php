<?php

namespace App\Support\Erp\Os;

use App\Models\Boleto;
use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\Product;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Erp\Financeiro\ContaReceberEstornoService;
use App\Support\Erp\Pdv\PdvStockService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reabre ou cancela OS desfazendo o faturamento (OsFaturamentoService::faturar):
 * recebimentos, contas a receber, Livro Caixa e baixa das peças.
 */
final class OsReabrirService
{
    public const OPERACAO = 'REABRIR_ORDEM_SERVICO';

    public const OPERACAO_CANCELAR = 'CANCELAR_ORDEM_SERVICO';

    private const SITUACOES_FATURADAS = [OrdemServico::SITUACAO_FINALIZADA, OrdemServico::SITUACAO_ENTREGUE];

    /**
     * NFS-e nesses status ainda não existe na prefeitura (ou foi cancelada).
     *
     * @var list<string>
     */
    private const NFSE_LIBERADA = [Nfse::STATUS_ABERTA, Nfse::STATUS_CANCELADA, Nfse::STATUS_REJEITADA];

    /**
     * @var list<string>
     */
    private const NFE_EMITIDA = [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA, Nfe::STATUS_DUPLICIDADE];

    public function __construct(
        private readonly PdvStockService $stock = new PdvStockService(),
        private readonly ContaReceberEstornoService $estornoRecebimento = new ContaReceberEstornoService(),
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
    ) {}

    public function motivoBloqueio(OrdemServico $os): ?string
    {
        if (! in_array($os->situacao, self::SITUACOES_FATURADAS, true)) {
            return 'Só é possível reabrir OS finalizada.';
        }

        return $this->bloqueioDocumentos($os, 'reabrir');
    }

    public function motivoBloqueioCancelamento(OrdemServico $os): ?string
    {
        if ($os->situacao === OrdemServico::SITUACAO_CANCELADA) {
            return 'OS já está cancelada.';
        }

        return $this->bloqueioDocumentos($os, 'cancelar');
    }

    private function bloqueioDocumentos(OrdemServico $os, string $acao): ?string
    {
        $nfse = Nfse::query()
            ->daOrdemServico((int) $os->id)
            ->whereNotIn('status', self::NFSE_LIBERADA)
            ->orderByDesc('id')
            ->first();

        if ($nfse instanceof Nfse) {
            $numero = trim((string) ($nfse->numero_nfse ?: $nfse->numero_dps));

            return 'Esta OS tem NFS-e emitida'.($numero !== '' ? ' (nº '.$numero.')' : '').'. Cancele a NFS-e antes de '.$acao.' a OS.';
        }

        $nfe = $this->nfeEmitida($os);

        if ($nfe instanceof Nfe) {
            $numero = trim((string) ($nfe->numero ?? ''));

            return 'Esta OS tem NF-e emitida'.($numero !== '' ? ' (nº '.$numero.')' : '').'. Cancele a NF-e antes de '.$acao.' a OS.';
        }

        if ($this->boletosRegistrados($os)->isNotEmpty()) {
            return 'Esta OS tem boleto registrado no banco. Cancele o boleto no Contas a Receber antes de '.$acao.' a OS.';
        }

        return null;
    }

    /**
     * @throws DomainException
     */
    public function reabrir(OrdemServico $os): OrdemServico
    {
        return $this->executar(
            $os,
            fn (OrdemServico $o): ?string => $this->motivoBloqueio($o),
            OrdemServico::SITUACAO_ABERTA,
            self::OPERACAO,
            'reaberta',
        );
    }

    /**
     * OS aberta só muda a situação; finalizada/entregue tem o faturamento estornado antes.
     *
     * @throws DomainException
     */
    public function cancelar(OrdemServico $os): OrdemServico
    {
        return $this->executar(
            $os,
            fn (OrdemServico $o): ?string => $this->motivoBloqueioCancelamento($o),
            OrdemServico::SITUACAO_CANCELADA,
            self::OPERACAO_CANCELAR,
            'cancelada',
        );
    }

    /**
     * A trava é reavaliada sob lock: uma segunda chamada vê a situação nova e não estorna de novo.
     *
     * @param  callable(OrdemServico): ?string  $bloqueio
     *
     * @throws DomainException
     */
    private function executar(OrdemServico $os, callable $bloqueio, string $situacaoFinal, string $operacao, string $verbo): OrdemServico
    {
        $motivo = $bloqueio($os);

        if ($motivo !== null) {
            throw new DomainException($motivo);
        }

        $resumo = DB::transaction(function () use ($os, $bloqueio, $situacaoFinal): array {
            $travada = OrdemServico::query()->whereKey($os->id)->lockForUpdate()->first();

            if (! $travada instanceof OrdemServico) {
                throw new DomainException('Ordem de serviço não encontrada.');
            }

            $motivo = $bloqueio($travada);

            if ($motivo !== null) {
                throw new DomainException($motivo);
            }

            $situacaoAnterior = (string) $travada->situacao;
            $resumo = ['situacao_anterior' => $situacaoAnterior, 'faturamento_estornado' => false];
            $payload = ['situacao' => $situacaoFinal];

            if (in_array($situacaoAnterior, self::SITUACOES_FATURADAS, true)) {
                $resumo = array_merge($resumo, $this->estornarFaturamento($travada), ['faturamento_estornado' => true]);
                $payload = array_merge($payload, $this->limpezaFaturamento($travada));
            }

            $travada->forceFill($payload)->save();

            return $resumo;
        });

        $os->refresh();

        $this->operacaoLog->registrar(
            operacao: $operacao,
            resumo: 'OS #'.$os->numero.' '.$verbo.($resumo['faturamento_estornado'] ? ': faturamento estornado.' : '.'),
            origem: 'lista_ordens_servico',
            documentoTipo: 'ordem_servico',
            documentoId: (int) $os->id,
            documentoNumero: (string) $os->numero,
            detalhes: $resumo,
            empresaId: $os->empresa_id ? (int) $os->empresa_id : null,
        );

        return $os;
    }

    /**
     * @return array{recebimentos_estornados: int, contas_receber_apagadas: int, caixa_estornado: float, pecas_devolvidas: int}
     */
    private function estornarFaturamento(OrdemServico $os): array
    {
        $empresaId = $os->empresa_id ? (int) $os->empresa_id : ErpContext::currentEmpresaId();
        $contas = $this->contasDaOs($os)->lockForUpdate()->get();
        $recebimentosEstornados = $this->estornarRecebimentos($contas);

        foreach ($contas as $conta) {
            $conta->delete();
        }

        return [
            'recebimentos_estornados' => $recebimentosEstornados,
            'contas_receber_apagadas' => $contas->count(),
            'caixa_estornado' => $this->estornarCaixa($os, $empresaId),
            'pecas_devolvidas' => $this->devolverPecas($os, $empresaId),
        ];
    }

    /**
     * @return array<string, null>
     */
    private function limpezaFaturamento(OrdemServico $os): array
    {
        $payload = [
            'data_termino' => null,
            'hora_termino' => null,
        ];

        if (Schema::hasColumn($os->getTable(), 'faturamento_pagamentos')) {
            $payload['faturamento_pagamentos'] = null;
        }

        if ($os->situacao === OrdemServico::SITUACAO_ENTREGUE) {
            $payload['data_entrega'] = null;
            $payload['hora_entrega'] = null;
        }

        return $payload;
    }

    private function documentoBase(OrdemServico $os): string
    {
        return 'OS-'.preg_replace('/\D/', '', (string) $os->numero);
    }

    /**
     * Mesmo documento gravado em OsFaturamentoService (OS-123 ou OS-123-1, OS-123-2 nas parcelas).
     *
     * @return Builder<ContaReceber>
     */
    private function contasDaOs(OrdemServico $os): Builder
    {
        $base = $this->documentoBase($os);

        return ContaReceber::query()
            ->where('cliente_id', (int) $os->cliente_id)
            ->when($os->empresa_id, fn (Builder $q) => $q->where('empresa_id', (int) $os->empresa_id))
            ->where(fn (Builder $q) => $q->where('documento', $base)->orWhere('documento', 'like', $base.'-%'));
    }

    /**
     * @param  Collection<int, ContaReceber>  $contas
     */
    private function estornarRecebimentos(Collection $contas): int
    {
        $total = 0;

        foreach ($contas as $conta) {
            $conta->loadMissing('pagamentos');

            foreach ($conta->pagamentos as $pagamento) {
                if ((float) $pagamento->valor_recebido > 0) {
                    $this->estornoRecebimento->estornarPagamento((int) $pagamento->id);
                    $total++;
                } else {
                    $pagamento->delete();
                }
            }
        }

        return $total;
    }

    /**
     * Contra-lançamento do saldo líquido de cada documento/conta de caixa da OS.
     */
    private function estornarCaixa(OrdemServico $os, ?int $empresaId): float
    {
        if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return 0.0;
        }

        $base = $this->documentoBase($os);
        $lancamentos = CaixaLancamento::query()
            ->when($os->empresa_id, fn (Builder $q) => $q->where('empresa_id', (int) $os->empresa_id))
            ->where(fn (Builder $q) => $q->where('documento', $base)->orWhere('documento', 'like', $base.'-%'))
            ->orderBy('id')
            ->get();

        $baixa = app(ContaReceberBaixaService::class);
        $hoje = ErpTimezone::toLocal()->toDateString();
        $estornado = 0.0;

        $grupos = $lancamentos->groupBy(
            fn (CaixaLancamento $l): string => ((int) ($l->caixa_conta_id ?? 0)).'|'.(string) $l->documento
        );

        foreach ($grupos as $grupo) {
            $liquido = round((float) $grupo->sum('entrada') - (float) $grupo->sum('saida'), 2);

            if ($liquido <= 0) {
                continue;
            }

            /** @var CaixaLancamento $ref */
            $ref = $grupo->first(fn (CaixaLancamento $l): bool => (float) $l->entrada > 0) ?? $grupo->first();
            $historico = trim((string) $ref->historico);

            $baixa->registrarSaidaCaixa(
                valor: $liquido,
                data: $hoje,
                documento: (string) $ref->documento,
                historico: 'ESTORNO '.($historico !== '' ? $historico : 'OS '.$os->numero),
                caixaContaId: $ref->caixa_conta_id ? (int) $ref->caixa_conta_id : null,
                empresaId: $ref->empresa_id ? (int) $ref->empresa_id : $empresaId,
                planoContaId: $ref->plano_conta_id ? (int) $ref->plano_conta_id : null,
                planoNome: filled($ref->plano_contas) ? (string) $ref->plano_contas : null,
            );

            $estornado += $liquido;
        }

        return round($estornado, 2);
    }

    private function devolverPecas(OrdemServico $os, ?int $empresaId): int
    {
        $os->loadMissing('itens.product');
        $devolvidas = 0;

        foreach ($os->itens as $item) {
            if (mb_strtoupper(trim((string) $item->tipo), 'UTF-8') !== 'P' || ! $item->product_id) {
                continue;
            }

            $product = $item->product ?? Product::query()->find($item->product_id);
            $qtd = round((float) $item->qtd, 3);

            if (! $product instanceof Product || $qtd <= 0) {
                continue;
            }

            $this->stock->estornoItemVenda(
                $product,
                $qtd,
                null,
                null,
                null,
                EstoqueMovimentacaoContext::make(
                    EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
                    empresaId: $empresaId,
                    origemTipo: 'ordem_servico',
                    origemId: (int) $os->id,
                    origemNumero: (string) $os->numero,
                ),
            );

            $devolvidas++;
        }

        return $devolvidas;
    }

    /**
     * NF-e de peças gerada pela OS (NfeOrdemServicoService grava o número da OS em npedido).
     */
    private function nfeEmitida(OrdemServico $os): ?Nfe
    {
        $numero = ltrim(preg_replace('/\D/', '', (string) $os->numero) ?? '', '0');

        if ($numero === '') {
            return null;
        }

        return Nfe::query()
            ->whereIn('status', self::NFE_EMITIDA)
            ->whereIn('npedido', [$numero, 'OS-'.$numero, 'OS '.$numero])
            ->where('obs_contribuinte', 'like', '%ORIGINADA DA OS%')
            ->when($os->empresa_id, fn (Builder $q) => $q->where('empresa_id', (int) $os->empresa_id))
            ->when($os->cliente_id, fn (Builder $q) => $q->where('cliente_id', (int) $os->cliente_id))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Boleto>
     */
    private function boletosRegistrados(OrdemServico $os): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable((new Boleto)->getTable())) {
            return collect();
        }

        $contaIds = $this->contasDaOs($os)->pluck('id')->all();

        if ($contaIds === []) {
            return collect();
        }

        return Boleto::query()
            ->whereIn('conta_receber_id', $contaIds)
            ->where('status', Boleto::STATUS_ABERTO)
            ->where(function (Builder $q): void {
                $q->where(fn (Builder $l) => $l->whereNotNull('linha_digitavel')->where('linha_digitavel', '!=', ''))
                    ->orWhere(fn (Builder $n) => $n->whereNotNull('nosso_numero')->where('nosso_numero', '!=', '')->where('nosso_numero', '!=', '0'))
                    ->orWhere(fn (Builder $e) => $e->whereNotNull('id_externo')->where('id_externo', '!=', '')->where('id_externo', '!=', '0'));
            })
            ->get();
    }
}
