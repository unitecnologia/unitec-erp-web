<?php

namespace App\Filament\Resources\CaixaResource\Pages\Concerns;

use App\Models\CaixaLancamento;
use App\Models\OrdemServico;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\CaixaHistoricoExibicao;
use App\Support\Erp\CaixaListRowFormatter;
use App\Support\Erp\ContaReceberPedidoExibicao;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Financeiro\CaixaLancamentoConsultaService;
use App\Support\Erp\Financeiro\CaixaLivroAberturaDisplay;
use App\Support\Erp\Queries\CaixaListQueryBuilder;
use App\Support\Erp\Queries\CaixaPdvMovimentacaoQuery;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

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
        $display = CaixaLivroAberturaDisplay::class;
        $contaPdv = is_numeric($this->contaFilter)
            && CaixaPdvMovimentacaoQuery::usaQueryVirtual((int) $this->contaFilter);

        // Em conta PDV, IDs no espaço -1e9/-2e9/-3e9 são espelhos de fechamento — não o livro.
        if ($display::isLinhaFechamento($lancamentoId) && ! $contaPdv) {
            $this->selecionarHistoricoFechamento($display::movimentoIdFromVirtual($lancamentoId));

            return;
        }

        if ($display::isLinhaAbertura($lancamentoId) && ! $contaPdv) {
            $payload = app(CaixaLancamentoConsultaService::class)
                ->montarAberturaLivro($lancamentoId, is_numeric($this->contaFilter) ? (int) $this->contaFilter : null);

            if (! $payload) {
                Notification::make()
                    ->title('Abertura de caixa não encontrada.')
                    ->danger()
                    ->send();

                return;
            }

            $this->viewModalData = $payload;
            $this->viewModalOpen = true;

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

        try {
            $this->viewModalData = app(CaixaLancamentoConsultaService::class)->montar($lancamento);
        } catch (\RuntimeException) {
            Notification::make()
                ->title('Lançamento não encontrado.')
                ->danger()
                ->send();

            return;
        }

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
        $ordenados = [];

        foreach ($grupoIds as $id) {
            $lancamento = $porId[$id] ?? null;
            if (! $lancamento) {
                continue;
            }

            $primeiro ??= $lancamento;
            $ordenados[] = $lancamento;
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

        try {
            $this->viewModalData = app(CaixaLancamentoConsultaService::class)
                ->montarGrupo($primeiro, $ordenados, $membros, $sumEntrada, $sumSaida);
        } catch (\RuntimeException) {
            Notification::make()
                ->title('Lançamentos do grupo não encontrados.')
                ->danger()
                ->send();

            return;
        }

        $this->viewModalOpen = true;
    }

    public function abrirCaixaItensVenda(): void
    {
        $pdvVendaId = (int) ($this->viewModalData['pdv_venda_id'] ?? 0);
        $vendaId = (int) ($this->viewModalData['venda_id'] ?? 0);
        $osId = (int) ($this->viewModalData['os_id'] ?? 0);
        $empresaId = ErpContext::currentEmpresaId();

        if ($pdvVendaId > 0) {
            $query = PdvVenda::query()
                ->with(['itens', 'person:id,nome_razao,apelido_fantasia']);

            if ($empresaId && Schema::hasColumn((new PdvVenda)->getTable(), 'empresa_id')) {
                $query->where('empresa_id', $empresaId);
            }

            $venda = $query->find($pdvVendaId);
            if ($venda) {
                $this->preencherCaixaItensDePdvVenda($venda);

                return;
            }
        }

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
        $empresaId = ErpContext::currentEmpresaId();

        if ($lancamentoId < 0) {
            $lancamento = (new CaixaListQueryBuilder(contaFilter: $this->contaFilter, applyDefaultOrder: false))
                ->buildForList()
                ->where('id', $lancamentoId)
                ->first();

            if ($lancamento && $empresaId && (int) ($lancamento->empresa_id ?? 0) > 0
                && (int) $lancamento->empresa_id !== (int) $empresaId) {
                return null;
            }

            return $lancamento;
        }

        $query = CaixaLancamento::query()->with(['conta', 'formaPagamento:id,descricao'])->whereKey($lancamentoId);

        if ($empresaId && Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id')) {
            $query->where('empresa_id', $empresaId);
        }

        if (is_numeric($this->contaFilter)) {
            $query->where('caixa_conta_id', (int) $this->contaFilter);
        }

        return $query->first();
    }

    private function preencherCaixaItensDePdvVenda(PdvVenda $venda): void
    {
        $numero = filled($venda->numero)
            ? (ltrim((string) $venda->numero, '0') ?: (string) $venda->numero)
            : '0';
        $cliente = $venda->person?->apelido_fantasia
            ?: $venda->person?->nome_razao
            ?: '—';
        $rows = [];
        $index = 1;
        $total = 0.0;

        foreach ($venda->itens as $item) {
            $total += (float) $item->total;
            $codigo = $item->codigo;
            $codigoFmt = '—';
            if ($codigo !== null && $codigo !== '') {
                $trimmed = ltrim((string) $codigo, '0');
                $codigoFmt = $trimmed !== '' ? $trimmed : '0';
            }

            $rows[] = [
                'item' => (string) $index++,
                'codigo' => $codigoFmt,
                'produto' => (string) ($item->descricao ?: '—'),
                'qtd' => number_format((float) $item->quantidade, 3, ',', '.'),
                'preco' => number_format((float) $item->preco_unitario, 2, ',', '.'),
                'total' => number_format((float) $item->total, 2, ',', '.'),
                'desconto' => number_format((float) $item->desconto, 2, ',', '.'),
            ];
        }

        $this->caixaItensModalTitulo = 'Itens do pedido nº '.$numero;
        $this->caixaItensModalCliente = (string) $cliente;
        $this->caixaItensModalRows = $rows;
        $this->caixaItensModalTotal = 'R$ '.number_format(
            $total > 0 ? $total : (float) $venda->total,
            2,
            ',',
            '.',
        );
        $this->caixaItensModalOpen = true;
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
}
