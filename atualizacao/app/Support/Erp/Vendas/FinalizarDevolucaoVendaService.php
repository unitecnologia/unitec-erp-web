<?php

namespace App\Support\Erp\Vendas;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\DevolucaoVenda;
use App\Models\DevolucaoVendaItem;
use App\Models\Empresa;
use App\Models\PdvVendaItem;
use App\Models\Product;
use App\Models\Venda;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
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
    public function finalizar(DevolucaoVenda $devolucao): DevolucaoVenda
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

        DB::transaction(function () use ($devolucao, $venda, $total): void {
            $this->devolverEstoque($devolucao, $venda);

            $restoFinanceiro = $this->estornarContasReceberAbertas($venda, $total, $devolucao);

            if ($restoFinanceiro > 0.009) {
                $this->lancarSaidaCaixa($devolucao, $restoFinanceiro);
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
            ],
            empresaId: $devolucao->empresa_id ? (int) $devolucao->empresa_id : ErpContext::currentEmpresaId(),
        );

        return $devolucao->fresh(['itens', 'venda']) ?? $devolucao;
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
        $pdvItens = $venda->pdvVenda?->itens ?? collect();

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
     * Reduz títulos em aberto da venda; devolve o valor que sobrou para saída de caixa.
     */
    private function estornarContasReceberAbertas(Venda $venda, float $totalDevolucao, DevolucaoVenda $devolucao): float
    {
        $resto = round($totalDevolucao, 2);

        if ($resto <= 0 || ! $venda->cliente_id) {
            return $resto;
        }

        if (! Schema::hasTable((new ContaReceber)->getTable())) {
            return $resto;
        }

        $docs = $this->documentosDaVenda($venda);

        if ($docs === []) {
            return $resto;
        }

        $contas = ContaReceber::query()
            ->where('cliente_id', $venda->cliente_id)
            ->where(function ($q) use ($docs): void {
                foreach ($docs as $doc) {
                    $q->orWhere('documento', $doc)
                        ->orWhere('documento', 'like', $doc.'/%');
                }
            })
            ->where('saldo', '>', 0)
            ->orderBy('vencimento')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($contas as $conta) {
            if ($resto <= 0.009) {
                break;
            }

            $saldo = round((float) $conta->saldo, 2);
            $aplicar = min($saldo, $resto);

            // Reduz o valor do título (mantém o já recebido).
            $novoValor = round(max((float) $conta->valor_recebido, (float) $conta->valor - $aplicar), 2);
            $conta->valor = $novoValor;
            $conta->historico = trim((string) $conta->historico.' | DEV#'.$devolucao->numero);
            $conta->save();

            $resto = round($resto - $aplicar, 2);
        }

        return max(0, $resto);
    }

    /**
     * @return list<string>
     */
    private function documentosDaVenda(Venda $venda): array
    {
        $docs = [];

        $pdv = $venda->pdvVenda;

        if ($pdv?->numero) {
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

        $empresaId = $devolucao->empresa_id
            ? (int) $devolucao->empresa_id
            : ErpContext::currentEmpresaId();

        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;
        $planoId = $empresa?->param_plano_devolucao
            ? (int) $empresa->param_plano_devolucao
            : null;

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
            'plano_contas' => null,
            'plano_conta_id' => $planoId > 0 ? $planoId : null,
            'caixa_conta_id' => $caixaContaId > 0 ? $caixaContaId : null,
            'entrada' => 0,
            'saida' => $valor,
        ]);
    }
}
