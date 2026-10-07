<?php

namespace App\Support\Erp\Vendas;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\ClienteCreditoMovimentacao;
use App\Models\ContaReceber;
use App\Models\DevolucaoVenda;
use App\Models\DevolucaoVendaItem;
use App\Models\PdvVendaItem;
use App\Models\PlanoConta;
use App\Models\EstoqueMovimentacao;
use App\Models\Product;
use App\Models\Venda;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ClienteCreditoService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\Pdv\PdvStockService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finaliza devolução de venda: estoque + financeiro (estorno CR / saída caixa).
 *
 * Cancelamento de devolução finalizada não é suportado aqui — só aberta
 * (ver ListDevolucoesVenda::cancelDevolucao).
 */
final class FinalizarDevolucaoVendaService
{
    public const OPERACAO = 'FINALIZAR_DEVOLUCAO_VENDA';

    public const DESTINO_DINHEIRO = 'dinheiro';

    public const DESTINO_CREDITO = 'credito';

    public function __construct(
        private readonly PdvStockService $stockService = new PdvStockService(),
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
    ) {}

    /**
     * Aplica efeitos de finalização na devolução já persistida (situacao ainda
     * pode estar aberta; este método marca finalizada ao concluir).
     *
     * @throws DomainException
     */
    public function finalizar(DevolucaoVenda $devolucao, string $destinoResto = self::DESTINO_DINHEIRO): DevolucaoVenda
    {
        $devolucao->loadMissing(['itens.product', 'venda.pdvVenda.itens', 'venda.forcaVendasOrder', 'cliente']);

        if ($devolucao->situacao === DevolucaoVenda::SITUACAO_FINALIZADA) {
            return $devolucao;
        }

        if ($devolucao->situacao === DevolucaoVenda::SITUACAO_CANCELADA) {
            throw new DomainException('Devolução cancelada não pode ser finalizada.');
        }

        $venda = $devolucao->venda;

        if (! $venda) {
            throw new DomainException('Devolução sem venda de origem.');
        }

        if (! in_array($venda->status, [Venda::STATUS_FECHADO, Venda::STATUS_GRAVADO], true)) {
            throw new DomainException('Só é possível devolver venda fechada ou gravada.');
        }

        if ($devolucao->itens->isEmpty()) {
            throw new DomainException('Devolução sem itens.');
        }

        $this->validarQuantidades($devolucao, $venda);

        $total = round((float) $devolucao->total, 2);
        $destinoResto = $destinoResto === self::DESTINO_CREDITO
            ? self::DESTINO_CREDITO
            : self::DESTINO_DINHEIRO;

        DB::transaction(function () use ($devolucao, $venda, $total, $destinoResto): void {
            $this->devolverEstoque($devolucao, $venda);

            $restoFinanceiro = $this->estornarContasReceberAbertas($venda, $total, $devolucao);

            if ($restoFinanceiro > 0.009) {
                if ($destinoResto === self::DESTINO_CREDITO) {
                    $this->gerarCreditoCliente($devolucao, $restoFinanceiro);
                } else {
                    $this->lancarSaidaCaixa($devolucao, $restoFinanceiro);
                }
            }

            $devolucao->update([
                'situacao' => DevolucaoVenda::SITUACAO_FINALIZADA,
            ]);
        });

        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: 'Devolução #'.$devolucao->numero.' finalizada (estoque + financeiro).',
            origem: 'devolucao_venda',
            documentoTipo: 'devolucao_venda',
            documentoId: (int) $devolucao->id,
            documentoNumero: (string) $devolucao->numero,
            detalhes: [
                'venda_id' => $venda->id,
                'venda_numero' => $venda->numero,
                'total' => $total,
                'destino_resto' => $destinoResto,
            ],
            empresaId: $devolucao->empresa_id ? (int) $devolucao->empresa_id : ErpContext::currentEmpresaId(),
        );

        return $devolucao->fresh(['itens', 'venda']) ?? $devolucao;
    }

    /**
     * Estorna o crédito gerado por esta devolução com movimento inverso.
     * Não apaga o extrato e não desfaz estoque/caixa.
     */
    public function estornarCreditoGerado(DevolucaoVenda $devolucao, ?int $usuarioId = null): int
    {
        $estornados = (new ClienteCreditoService())->estornarCreditosDaOrigem(
            ClienteCreditoMovimentacao::ORIGEM_DEVOLUCAO,
            (int) $devolucao->id,
            $usuarioId,
            'Estorno da devolução #'.($devolucao->numero ?: $devolucao->id),
        );

        if ($estornados > 0) {
            $this->operacaoLog->registrar(
                operacao: 'ESTORNAR_CREDITO_DEVOLUCAO',
                resumo: 'Crédito da devolução #'.$devolucao->numero.' estornado no extrato do cliente.',
                origem: 'devolucao_venda',
                documentoTipo: 'devolucao_venda',
                documentoId: (int) $devolucao->id,
                documentoNumero: (string) $devolucao->numero,
                empresaId: $devolucao->empresa_id ? (int) $devolucao->empresa_id : ErpContext::currentEmpresaId(),
            );
        }

        return $estornados;
    }

    private function validarQuantidades(DevolucaoVenda $devolucao, Venda $venda): void
    {
        $jaDevolvido = $this->quantidadesJaDevolvidas((int) $venda->id, (int) $devolucao->id);

        foreach ($devolucao->itens as $item) {
            $qtd = round((float) $item->qtd, 3);

            if ($qtd <= 0) {
                throw new DomainException('Item com quantidade inválida na devolução.');
            }

            $vendaItemId = $item->venda_item_id ? (int) $item->venda_item_id : 0;
            $vendida = round((float) $item->qtd_vendida, 3);

            if ($vendaItemId > 0) {
                $prev = $jaDevolvido[$vendaItemId] ?? 0.0;
                $disponivel = round(max(0, $vendida - $prev), 3);

                if ($qtd > $disponivel + 0.0005) {
                    $desc = $item->produto_descricao ?: ('item #'.$item->item);
                    throw new DomainException(
                        "Quantidade devolvida de \"{$desc}\" excede o disponível ({$disponivel})."
                    );
                }
            }
        }
    }

    /**
     * @return array<int, float> venda_item_id => qtd já devolvida em devoluções finalizadas
     */
    private function quantidadesJaDevolvidas(int $vendaId, int $excetoDevolucaoId): array
    {
        $rows = DevolucaoVendaItem::query()
            ->whereNotNull('venda_item_id')
            ->whereHas('devolucao', function ($q) use ($vendaId, $excetoDevolucaoId): void {
                $q->where('venda_id', $vendaId)
                    ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
                    ->where('id', '!=', $excetoDevolucaoId);
            })
            ->get(['venda_item_id', 'qtd']);

        $map = [];

        foreach ($rows as $row) {
            $id = (int) $row->venda_item_id;
            $map[$id] = round(($map[$id] ?? 0) + (float) $row->qtd, 3);
        }

        return $map;
    }

    private function devolverEstoque(DevolucaoVenda $devolucao, Venda $venda): void
    {
        $pdvVenda = $venda->pdvVenda;
        $pdvItens = $pdvVenda !== null && ! $pdvVenda->isRegularizacaoFiscal() ? $pdvVenda->itens : collect();

        foreach ($devolucao->itens as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = $item->product ?? Product::query()->find($item->product_id);

            if (! $product) {
                continue;
            }

            [$gradeId, $serialId] = $this->resolveGradeSerial($item, $pdvItens);

            $this->stockService->estornoItemVenda(
                $product,
                (float) $item->qtd,
                $gradeId,
                $serialId,
                null,
                EstoqueMovimentacaoContext::make(
                    EstoqueMovimentacao::TIPO_DEVOLUCAO_VENDA,
                    empresaId: $devolucao->empresa_id
                        ? (int) $devolucao->empresa_id
                        : ErpContext::currentEmpresaId(),
                    origemTipo: 'devolucao_venda',
                    origemId: (int) $devolucao->id,
                    origemNumero: $devolucao->numero !== null ? (string) $devolucao->numero : null,
                ),
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PdvVendaItem>  $pdvItens
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveGradeSerial(DevolucaoVendaItem $item, $pdvItens): array
    {
        if ($pdvItens->isEmpty() || ! $item->product_id) {
            return [null, null];
        }

        $candidatos = $pdvItens->filter(
            fn (PdvVendaItem $pdv): bool => (int) $pdv->product_id === (int) $item->product_id
        );

        if ($candidatos->isEmpty()) {
            return [null, null];
        }

        $match = $candidatos->first(function (PdvVendaItem $pdv) use ($item): bool {
            return abs((float) $pdv->quantidade - (float) $item->qtd_vendida) < 0.001
                || abs((float) $pdv->quantidade - (float) $item->qtd) < 0.001;
        }) ?? $candidatos->first();

        return [
            $match?->product_grade_id ? (int) $match->product_grade_id : null,
            $match?->product_serial_id ? (int) $match->product_serial_id : null,
        ];
    }

    /**
     * @return array{total: float, abatimentos: float, a_devolver: float}
     */
    public function preverRestituicao(?int $vendaId, float $totalDevolucao): array
    {
        $total = round($totalDevolucao, 2);
        $aDevolver = $this->restoDinheiroPrevisto($vendaId, $total);

        return [
            'total' => $total,
            'abatimentos' => round(max(0, $total - $aDevolver), 2),
            'a_devolver' => $aDevolver,
        ];
    }

    /**
     * Valor que iria sair do caixa (já pago), sem alterar títulos.
     * Crédito só nasce desse resto — título em aberto continua abatendo a conta.
     */
    public function restoDinheiroPrevisto(?int $vendaId, float $totalDevolucao): float
    {
        $totalDevolucao = round($totalDevolucao, 2);

        if (! $vendaId || $totalDevolucao <= 0) {
            return 0.0;
        }

        $venda = Venda::query()->with(['pdvVenda', 'forcaVendasOrder'])->find($vendaId);

        if (! $venda) {
            return 0.0;
        }

        return $this->restoAposTitulosAbertos($venda, $totalDevolucao, aplicar: false, devolucao: null);
    }

    /**
     * Reduz títulos em aberto da venda; devolve o valor que sobrou para saída de caixa.
     */
    private function estornarContasReceberAbertas(Venda $venda, float $totalDevolucao, DevolucaoVenda $devolucao): float
    {
        return $this->restoAposTitulosAbertos($venda, $totalDevolucao, aplicar: true, devolucao: $devolucao);
    }

    private function restoAposTitulosAbertos(Venda $venda, float $totalDevolucao, bool $aplicar, ?DevolucaoVenda $devolucao): float
    {
        $resto = round($totalDevolucao, 2);

        if ($resto <= 0 || ! $venda->cliente_id) {
            return max(0, $resto);
        }

        if (! Schema::hasTable((new ContaReceber)->getTable())) {
            return $resto;
        }

        $docs = $this->documentosDaVenda($venda);

        if ($docs === []) {
            return $resto;
        }

        $query = ContaReceber::query()
            ->where('cliente_id', $venda->cliente_id)
            ->where(function ($q) use ($docs): void {
                foreach ($docs as $doc) {
                    $q->orWhere('documento', $doc)
                        ->orWhere('documento', 'like', $doc.'/%');
                }
            })
            ->where('saldo', '>', 0)
            ->orderBy('vencimento')
            ->orderBy('id');

        if ($aplicar) {
            $query->lockForUpdate();
        }

        foreach ($query->get() as $conta) {
            if ($resto <= 0.009) {
                break;
            }

            $saldo = round((float) $conta->saldo, 2);
            $aplicarValor = min($saldo, $resto);

            if ($aplicar && $devolucao) {
                $novoValor = round(max((float) $conta->valor_recebido, (float) $conta->valor - $aplicarValor), 2);
                $conta->valor = $novoValor;
                $conta->historico = trim((string) $conta->historico.' | DEV#'.$devolucao->numero);
                $conta->save();
            }

            $resto = round($resto - $aplicarValor, 2);
        }

        return max(0, $resto);
    }

    private function gerarCreditoCliente(DevolucaoVenda $devolucao, float $valor): void
    {
        $clienteId = $devolucao->cliente_id ? (int) $devolucao->cliente_id : null;
        $credito = new ClienteCreditoService();

        if (! $credito->podeReceberCredito($clienteId)) {
            throw new DomainException('Selecione o cliente da venda para gerar crédito. Consumidor final não recebe saldo.');
        }

        $empresaId = $devolucao->empresa_id
            ? (int) $devolucao->empresa_id
            : (int) (ErpContext::currentEmpresaId() ?? 0);

        $numeroVenda = trim((string) ($devolucao->venda_numero ?: $devolucao->venda_id));

        $credito->gerar(
            clienteId: (int) $clienteId,
            valor: $valor,
            empresaId: $empresaId,
            origemTipo: ClienteCreditoMovimentacao::ORIGEM_DEVOLUCAO,
            origemId: (int) $devolucao->id,
            origemNumero: $numeroVenda !== '' ? $numeroVenda : null,
            observacao: 'Devolução #'.($devolucao->numero ?: $devolucao->id),
            usuarioId: $devolucao->usuario_id ? (int) $devolucao->usuario_id : null,
        );
    }

    /**
     * @return list<string>
     */
    private function documentosDaVenda(Venda $venda): array
    {
        $docs = [];

        $pdv = $venda->pdvVenda;

        if ($pdv?->numero && ! $pdv->isRegularizacaoFiscal()) {
            $docs[] = 'PDV-'.str_pad((string) $pdv->numero, 6, '0', STR_PAD_LEFT);
        }

        $order = $venda->forcaVendasOrder;

        if ($order?->id) {
            $docs[] = 'FV-'.$order->id;
        }

        return $docs;
    }

    private function lancarSaidaCaixa(DevolucaoVenda $devolucao, float $valor): void
    {
        if (! Schema::hasTable((new CaixaLancamento)->getTable()) || $valor <= 0) {
            return;
        }

        $plano = $this->planoDevolucao(9);

        $caixaContaId = (int) CaixaConta::ensureCaixaGeral()->id;

        CaixaLancamento::query()->create([
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => ErpTimezone::toLocal()->toDateString(),
            'documento' => mb_substr('DEV-'.($devolucao->numero ?: $devolucao->id), 0, 40),
            'historico' => mb_substr(
                'Devolução venda #'.($devolucao->venda_numero ?: $devolucao->venda_id)
                .' — DEV#'.($devolucao->numero ?: $devolucao->id),
                0,
                180
            ),
            'plano_contas' => $plano
                ? mb_substr(mb_strtoupper((string) $plano->descricao, 'UTF-8'), 0, 120)
                : null,
            'plano_conta_id' => $plano?->id,
            'caixa_conta_id' => $caixaContaId > 0 ? $caixaContaId : null,
            'entrada' => 0,
            'saida' => $valor,
        ]);
    }

    /**
     * Resolve plano de contas por id ou código legado. Se não existir, o caixa segue sem plano.
     */
    private function planoDevolucao(mixed $parametro): ?PlanoConta
    {
        $valor = (int) $parametro;

        if ($valor <= 0) {
            return null;
        }

        return PlanoConta::query()->whereKey($valor)->first()
            ?? PlanoConta::query()->where('codigo', $valor)->first();
    }
}
