<?php

namespace App\Filament\Resources\CaixaResource\Pages\Concerns;

use App\Models\CaixaLancamento;
use App\Models\ForcaVendasOrder;
use App\Models\OrdemServico;
use App\Models\Venda;
use App\Support\Erp\CaixaHistoricoExibicao;
use App\Support\Erp\CaixaListRowFormatter;
use App\Support\Erp\ContaReceberPedidoExibicao;
use App\Support\Erp\Financeiro\CaixaLancamentoOrigemResolver;
use App\Support\Erp\Queries\CaixaListQueryBuilder;
use Filament\Notifications\Notification;

trait ManagesCaixaViewModal
{
    public bool $viewModalOpen = false;

    /** @var array<string, mixed> */
    public array $viewModalData = [];

    public bool $caixaItensModalOpen = false;

    public string $caixaItensModalTitulo = '';

    public string $caixaItensModalCliente = '';

    /** @var list<array<string, string>> */
    public array $caixaItensModalRows = [];

    public string $caixaItensModalTotal = 'R$ 0,00';

    public function openCaixaView(int $lancamentoId, array $grupoIds = []): void
    {
        $display = \App\Support\Erp\Financeiro\CaixaLivroAberturaDisplay::class;

        if ($display::isLinhaFechamento($lancamentoId)) {
            $this->selecionarHistoricoFechamento($display::movimentoIdFromVirtual($lancamentoId));

            return;
        }

        if ($display::isLinhaVirtual($lancamentoId)) {
            Notification::make()
                ->title('Abertura de caixa')
                ->body('Linha de conferência do troco inicial — sem lançamento financeiro vinculado.')
                ->info()
                ->send();

            return;
        }

        $grupoIds = array_values(array_unique(array_filter(
            array_map('intval', $grupoIds),
            static fn (int $id): bool => $id !== 0,
        )));

        if (count($grupoIds) > 1) {
            $this->abrirVisualizacaoGrupo($grupoIds);

            return;
        }

        $lancamento = $this->carregarLancamentoCaixaView($lancamentoId);

        if (! $lancamento) {
            Notification::make()
                ->title('Lançamento não encontrado.')
                ->danger()
                ->send();

            return;
        }

        $origem = app(CaixaLancamentoOrigemResolver::class)->resolver($lancamento);
        $vinculo = $this->resolverVinculoItensVenda($lancamento);

        $this->viewModalData = [
            'eh_grupo' => false,
            'codigo' => (string) $lancamento->codigo,
            'emissao' => $lancamento->emissao?->format('d/m/Y') ?? '—',
            'documento' => ContaReceberPedidoExibicao::texto(
                $lancamento->documento,
                ContaReceberPedidoExibicao::mapa([$lancamento]),
            ),
            'historico' => CaixaListRowFormatter::comSessaoPdv(
                CaixaHistoricoExibicao::paraExibicao(
                    (string) $lancamento->historico,
                    ContaReceberPedidoExibicao::mapa([$lancamento]),
                ),
                $lancamento,
            ),
            'plano_contas' => mb_strtoupper((string) ($lancamento->plano_contas ?? ''), 'UTF-8') ?: '—',
            'conta' => mb_strtoupper($lancamento->conta?->nome ?? '—', 'UTF-8'),
            'entrada' => number_format((float) $lancamento->entrada, 2, ',', '.'),
            'saida' => number_format((float) $lancamento->saida, 2, ',', '.'),
            'tem_detalhe' => (bool) ($origem['tem_detalhe'] ?? false),
            'detalhes' => $origem['linhas'] ?? [],
            'membros' => [],
            'pode_ver_itens' => $vinculo['pode'],
            'venda_id' => $vinculo['venda_id'],
            'os_id' => $vinculo['os_id'],
        ];

        $this->viewModalOpen = true;
    }

    /**
     * @param  list<int>  $grupoIds
     */
    private function abrirVisualizacaoGrupo(array $grupoIds): void
    {
        $porId = [];
        foreach ($grupoIds as $id) {
            $lancamento = $this->carregarLancamentoCaixaView($id);
            if ($lancamento) {
                $porId[$id] = $lancamento;
            }
        }

        if ($porId === []) {
            Notification::make()
                ->title('Lançamentos do grupo não encontrados.')
                ->danger()
                ->send();

            return;
        }

        $membros = [];
        $sumEntrada = 0.0;
        $sumSaida = 0.0;
        $primeiro = null;

        foreach ($grupoIds as $id) {
            $lancamento = $porId[$id] ?? null;
            if (! $lancamento) {
                continue;
            }

            $primeiro ??= $lancamento;
            $entrada = round((float) ($lancamento->entrada ?? 0), 2);
            $saida = round((float) ($lancamento->saida ?? 0), 2);
            $sumEntrada += $entrada;
            $sumSaida += $saida;

            $hist = CaixaListRowFormatter::comSessaoPdv(
                CaixaHistoricoExibicao::paraExibicao(
                    (string) $lancamento->historico,
                    ContaReceberPedidoExibicao::mapa([$lancamento]),
                ),
                $lancamento,
            );

            $membros[] = [
                'codigo' => (string) ($lancamento->codigo ?: '—'),
                'historico' => $hist,
                'plano_contas' => mb_strtoupper((string) ($lancamento->plano_contas ?? ''), 'UTF-8') ?: '—',
                'entrada' => number_format($entrada, 2, ',', '.'),
                'saida' => number_format($saida, 2, ',', '.'),
            ];
        }

        if ($primeiro === null || $membros === []) {
            Notification::make()
                ->title('Lançamentos do grupo não encontrados.')
                ->danger()
                ->send();

            return;
        }

        $ordenados = [];
        foreach ($grupoIds as $id) {
            if (isset($porId[$id])) {
                $ordenados[] = $porId[$id];
            }
        }

        $pedidosMonitor = ContaReceberPedidoExibicao::mapa($ordenados);
        $grupo = \App\Support\Erp\CaixaListAgrupamentoVisual::montarLinhaGrupo($ordenados, $pedidosMonitor);
        $vinculo = $this->resolverVinculoItensVenda($primeiro);

        $this->viewModalData = [
            'eh_grupo' => true,
            'codigo' => count($membros).' lançamentos',
            'emissao' => $primeiro->emissao?->format('d/m/Y') ?? '—',
            'documento' => ContaReceberPedidoExibicao::texto(
                $primeiro->documento,
                ContaReceberPedidoExibicao::mapa([$primeiro]),
            ),
            'historico' => (string) ($grupo->getAttribute('caixa_grupo_resumo') ?? ''),
            'plano_contas' => $this->resumoPlanoContasGrupo($membros),
            'conta' => mb_strtoupper($primeiro->conta?->nome ?? '—', 'UTF-8'),
            'entrada' => number_format($sumEntrada, 2, ',', '.'),
            'saida' => number_format($sumSaida, 2, ',', '.'),
            'tem_detalhe' => false,
            'detalhes' => [],
            'membros' => $membros,
            'pode_ver_itens' => $vinculo['pode'],
            'venda_id' => $vinculo['venda_id'],
            'os_id' => $vinculo['os_id'],
        ];

        $this->viewModalOpen = true;
    }

    public function abrirCaixaItensVenda(): void
    {
        $vendaId = (int) ($this->viewModalData['venda_id'] ?? 0);
        $osId = (int) ($this->viewModalData['os_id'] ?? 0);

        // OS primeiro: título/itens pelo nº da OS (pedido operacional),
        // não pelo id/número da venda espelho gerada no faturamento.
        if ($osId > 0) {
            $ordem = OrdemServico::query()
                ->with(['itens.product', 'cliente'])
                ->find($osId);

            if ($ordem) {
                $this->preencherCaixaItensDeOs($ordem);

                return;
            }
        }

        if ($vendaId > 0) {
            $venda = Venda::query()
                ->with(['itens.product', 'cliente'])
                ->find($vendaId);

            if ($venda) {
                $this->preencherCaixaItensDeVenda($venda);

                return;
            }
        }

        Notification::make()
            ->title('Itens da venda não encontrados.')
            ->warning()
            ->send();
    }

    public function closeCaixaItensVenda(): void
    {
        $this->caixaItensModalOpen = false;
        $this->caixaItensModalTitulo = '';
        $this->caixaItensModalCliente = '';
        $this->caixaItensModalRows = [];
        $this->caixaItensModalTotal = 'R$ 0,00';
    }

    private function carregarLancamentoCaixaView(int $lancamentoId): ?CaixaLancamento
    {
        if ($lancamentoId < 0) {
            return (new CaixaListQueryBuilder(contaFilter: $this->contaFilter, applyDefaultOrder: false))
                ->buildForList()
                ->where('id', $lancamentoId)
                ->first();
        }

        return CaixaLancamento::query()
            ->with(['conta'])
            ->find($lancamentoId);
    }

    /**
     * @return array{pode: bool, venda_id: int|null, os_id: int|null}
     */
    private function resolverVinculoItensVenda(CaixaLancamento $lancamento): array
    {
        $documento = trim((string) ($lancamento->documento ?? ''));
        $historico = (string) ($lancamento->historico ?? '');

        if (preg_match('/^OS-(\d+)$/', $documento, $m) === 1
            || preg_match('/^OS\s+(\d+)/iu', $historico, $m) === 1) {
            $numero = (int) $m[1];
            $padded = str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
            $ordem = OrdemServico::query()
                ->where(function ($q) use ($numero, $padded): void {
                    $q->where('numero', (string) $numero)
                        ->orWhere('numero', $padded);
                })
                ->first(['id', 'venda_id']);

            if ($ordem) {
                return [
                    'pode' => true,
                    'venda_id' => $ordem->venda_id ? (int) $ordem->venda_id : null,
                    'os_id' => (int) $ordem->id,
                ];
            }
        }

        if (preg_match('/^FV-(\d+)/', $documento, $m) === 1
            || preg_match('/\[FV-(\d+)\]/i', $historico, $m) === 1) {
            $order = ForcaVendasOrder::query()->find((int) $m[1], ['id', 'venda_id']);
            if ($order?->venda_id) {
                return [
                    'pode' => true,
                    'venda_id' => (int) $order->venda_id,
                    'os_id' => null,
                ];
            }
        }

        if (preg_match('/^VENDA PDV #(\S+)/i', mb_strtoupper($historico, 'UTF-8'), $m) === 1) {
            $num = $m[1];
            $padded = str_pad(preg_replace('/\D/', '', $num) ?: '0', 6, '0', STR_PAD_LEFT);
            $venda = Venda::query()
                ->where(function ($q) use ($num, $padded): void {
                    $q->where('numero', $num)->orWhere('numero', $padded);
                })
                ->first(['id']);

            if ($venda) {
                return [
                    'pode' => true,
                    'venda_id' => (int) $venda->id,
                    'os_id' => null,
                ];
            }
        }

        return ['pode' => false, 'venda_id' => null, 'os_id' => null];
    }

    private function preencherCaixaItensDeVenda(Venda $venda): void
    {
        // Nº Pedido do Monitor (vendas.numero) — nunca o id FV/DAV.
        $numero = ContaReceberPedidoExibicao::formatarNumeroVenda($venda->numero) ?? '0';
        $cliente = $venda->cliente?->nome_razao ?? '—';
        $rows = [];
        $index = 1;
        $total = 0.0;

        foreach ($venda->itens as $item) {
            $bruto = (float) $item->quantidade * (float) $item->valor_item;
            $desconto = max(0, round($bruto - (float) $item->total, 2));
            $total += (float) $item->total;
            $codigo = $item->product?->codigo;
            $codigoFmt = '—';
            if ($codigo !== null && $codigo !== '') {
                $trimmed = ltrim((string) $codigo, '0');
                $codigoFmt = $trimmed !== '' ? $trimmed : '0';
            }

            $rows[] = [
                'item' => (string) $index++,
                'codigo' => $codigoFmt,
                'produto' => (string) ($item->product?->descricao ?? '—'),
                'qtd' => number_format((float) $item->quantidade, 3, ',', '.'),
                'preco' => number_format((float) $item->valor_item, 2, ',', '.'),
                'total' => number_format((float) $item->total, 2, ',', '.'),
                'desconto' => number_format($desconto, 2, ',', '.'),
            ];
        }

        $this->caixaItensModalTitulo = 'Itens do pedido nº '.$numero;
        $this->caixaItensModalCliente = $cliente;
        $this->caixaItensModalRows = $rows;
        $this->caixaItensModalTotal = 'R$ '.number_format($total, 2, ',', '.');
        $this->caixaItensModalOpen = true;
    }

    private function preencherCaixaItensDeOs(OrdemServico $ordem): void
    {
        $numero = ltrim((string) $ordem->numero, '0') ?: '0';
        $cliente = $ordem->cliente?->nome_razao ?? ($ordem->nome ?: '—');
        $rows = [];
        $index = 1;
        $total = 0.0;

        foreach ($ordem->itens as $item) {
            $total += (float) $item->total;
            $codigo = $item->product?->codigo ?? $item->codigo_legado;
            $codigoFmt = '—';
            if ($codigo !== null && $codigo !== '') {
                $trimmed = ltrim((string) $codigo, '0');
                $codigoFmt = $trimmed !== '' ? $trimmed : '0';
            }

            $descricao = trim((string) ($item->discriminacao ?: $item->nome ?: $item->product?->descricao ?: '—'));

            $rows[] = [
                'item' => (string) $index++,
                'codigo' => $codigoFmt,
                'produto' => $descricao,
                'qtd' => number_format((float) $item->qtd, 3, ',', '.'),
                'preco' => number_format((float) $item->preco, 2, ',', '.'),
                'total' => number_format((float) $item->total, 2, ',', '.'),
                'desconto' => number_format((float) $item->desconto, 2, ',', '.'),
            ];
        }

        $this->caixaItensModalTitulo = 'Itens da OS nº '.$numero;
        $this->caixaItensModalCliente = $cliente;
        $this->caixaItensModalRows = $rows;
        $this->caixaItensModalTotal = 'R$ '.number_format(
            $total > 0 ? $total : (float) ($ordem->total_geral ?? 0),
            2,
            ',',
            '.',
        );
        $this->caixaItensModalOpen = true;
    }

    public function closeCaixaView(): void
    {
        $this->viewModalOpen = false;
        $this->viewModalData = [];
        $this->closeCaixaItensVenda();
    }

    /**
     * @param  list<array{plano_contas?: string}>  $membros
     */
    private function resumoPlanoContasGrupo(array $membros): string
    {
        $planos = [];

        foreach ($membros as $membro) {
            $plano = trim((string) ($membro['plano_contas'] ?? ''));
            if ($plano === '' || $plano === '—') {
                continue;
            }

            $planos[$plano] = true;
        }

        if ($planos === []) {
            return '—';
        }

        return implode(' · ', array_keys($planos));
    }
}
