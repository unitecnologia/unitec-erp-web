<?php

namespace App\Filament\Pages\Concerns;

use App\Models\FormaPagamento;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Orcamento\OrcamentoDescontoService;
use App\Support\Erp\Orcamento\OrcamentoFaturamentoGuard;
use App\Support\Erp\ProductPhotoUrl;
use Filament\Notifications\Notification;

/**
 * Importar orçamentos finalizados (status fechado) para a Tela de Venda.
 */
trait ManagesForcaVendasTelaVendaImportarOrcamento
{
    public bool $fvImportarOrcamentoOpen = false;

    public string $fvImportarOrcNumero = '';

    public string $fvImportarOrcCliente = '';

    public string $fvImportarOrcDataDe = '';

    public string $fvImportarOrcDataAte = '';

    /** @var list<array{orcamento_id: int, numero: string, data: string, cliente: string, total: string}> */
    public array $fvImportarOrcamentoResults = [];

    public ?int $fvImportarOrcamentoSelectedIndex = null;

    /** Orçamento importado nesta sessão (vínculo → forca_vendas_orders.orcamento_id no F3). */
    public ?int $orcamentoOrigemId = null;

    public function abrirImportarOrcamento(): void
    {
        if ($this->etapa !== 'venda') {
            Notification::make()->title('Volte para a venda para importar um orçamento.')->warning()->send();

            return;
        }

        if ($this->descontoModalOpen || $this->excluirItemModalOpen || $this->fvImportarOrcamentoOpen) {
            return;
        }

        if ($this->pedidoId) {
            Notification::make()
                ->title('Não é possível importar em um pedido em edição.')
                ->warning()
                ->send();

            return;
        }

        if ($this->itens !== []) {
            Notification::make()
                ->title('Já há itens na venda.')
                ->body('Cancele a venda atual antes de importar um orçamento.')
                ->warning()
                ->send();

            return;
        }

        $this->fvImportarOrcNumero = '';
        $this->fvImportarOrcCliente = '';
        $mes = ErpTimezone::toLocal();
        $this->fvImportarOrcDataDe = $mes->copy()->startOfMonth()->toDateString();
        $this->fvImportarOrcDataAte = $mes->copy()->endOfMonth()->toDateString();
        $this->refreshFvImportarOrcamentoResults();
        $this->fvImportarOrcamentoOpen = true;
        $this->dispatch('erp-fv-focus-importar-orcamento');
    }

    public function fecharImportarOrcamento(): void
    {
        $this->fvImportarOrcamentoOpen = false;
        $this->fvImportarOrcNumero = '';
        $this->fvImportarOrcCliente = '';
        $this->fvImportarOrcDataDe = '';
        $this->fvImportarOrcDataAte = '';
        $this->fvImportarOrcamentoResults = [];
        $this->fvImportarOrcamentoSelectedIndex = null;
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    public function updatedFvImportarOrcNumero(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->fvImportarOrcNumero !== $upper) {
            $this->fvImportarOrcNumero = $upper;
        }

        $this->refreshFvImportarOrcamentoResults();
    }

    public function updatedFvImportarOrcCliente(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->fvImportarOrcCliente !== $upper) {
            $this->fvImportarOrcCliente = $upper;
        }

        $this->refreshFvImportarOrcamentoResults();
    }

    public function updatedFvImportarOrcDataDe(): void
    {
        $this->refreshFvImportarOrcamentoResults();
    }

    public function updatedFvImportarOrcDataAte(): void
    {
        $this->refreshFvImportarOrcamentoResults();
    }

    public function refreshFvImportarOrcamentoResults(): void
    {
        $numero = trim($this->fvImportarOrcNumero);
        $cliente = trim($this->fvImportarOrcCliente);
        $dataDe = $this->dataFiltroFvImportarOrcamento($this->fvImportarOrcDataDe);
        $dataAte = $this->dataFiltroFvImportarOrcamento($this->fvImportarOrcDataAte);

        $query = OrcamentoFaturamentoGuard::aplicarNaoFaturados(Orcamento::query()->visivelNaListaOrcamentos())
            ->with(['cliente:id,nome_razao,apelido_fantasia,codigo'])
            ->where('status', Orcamento::STATUS_FECHADO)
            ->orderByDesc('data')
            ->orderByDesc('id');

        if ($numero !== '') {
            $query->where('numero', 'like', '%'.$numero.'%');
        }

        if ($cliente !== '') {
            $like = '%'.$cliente.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('cliente_nome', 'like', $like)
                    ->orWhereHas('cliente', function ($sub) use ($like): void {
                        $sub->where('nome_razao', 'like', $like)
                            ->orWhere('apelido_fantasia', 'like', $like);
                    });
            });
        }

        if ($dataDe !== null) {
            $query->whereDate('data', '>=', $dataDe);
        }

        if ($dataAte !== null) {
            $query->whereDate('data', '<=', $dataAte);
        }

        $this->fvImportarOrcamentoResults = $query
            ->limit(50)
            ->get()
            ->map(function (Orcamento $orcamento): array {
                $clienteNome = $orcamento->clienteDisplayNome();
                if ($clienteNome === '') {
                    $clienteNome = (string) ($orcamento->cliente?->nome_razao ?? '—');
                }

                return [
                    'orcamento_id' => $orcamento->id,
                    'numero' => (string) $orcamento->numero,
                    'data' => $orcamento->data?->format('d/m/Y') ?? '',
                    'cliente' => mb_strtoupper($clienteNome, 'UTF-8'),
                    'total' => ErpMoney::formatBr($orcamento->total),
                ];
            })
            ->values()
            ->all();

        $this->fvImportarOrcamentoSelectedIndex = $this->fvImportarOrcamentoResults === [] ? null : 0;
    }

    public function selectFvImportarOrcamentoRow(int $index): void
    {
        if (isset($this->fvImportarOrcamentoResults[$index])) {
            $this->fvImportarOrcamentoSelectedIndex = $index;
            $this->dispatch('erp-fv-highlight-importar-orcamento', index: $index);
            $this->skipRender();
        }
    }

    public function moveFvImportarOrcamentoSelection(int $delta): void
    {
        if ($this->fvImportarOrcamentoResults === []) {
            return;
        }

        $count = count($this->fvImportarOrcamentoResults);
        $index = ($this->fvImportarOrcamentoSelectedIndex ?? 0) + $delta;
        $this->fvImportarOrcamentoSelectedIndex = max(0, min($count - 1, $index));
        $this->dispatch('erp-fv-highlight-importar-orcamento', index: $this->fvImportarOrcamentoSelectedIndex);
        $this->skipRender();
    }

    public function confirmarImportarOrcamento(): void
    {
        $index = $this->fvImportarOrcamentoSelectedIndex;

        if ($index === null || ! isset($this->fvImportarOrcamentoResults[$index])) {
            Notification::make()->title('Selecione um orçamento.')->warning()->send();

            return;
        }

        $orcamentoId = (int) ($this->fvImportarOrcamentoResults[$index]['orcamento_id'] ?? 0);
        $orcamento = Orcamento::query()
            ->with([
                'itens' => fn ($q) => $q->orderBy('item')->orderBy('id'),
                'itens.product:id,codigo,descricao,ativo,is_grade,foto_path',
                'itens.grade:id,descricao',
                'cliente',
                'vendedor',
            ])
            ->find($orcamentoId);

        if (! $orcamento || $orcamento->status !== Orcamento::STATUS_FECHADO) {
            Notification::make()->title('Orçamento indisponível para importação.')->warning()->send();
            $this->refreshFvImportarOrcamentoResults();

            return;
        }

        $motivoFaturado = OrcamentoFaturamentoGuard::motivoFaturado($orcamentoId);

        if ($motivoFaturado !== null) {
            Notification::make()->title('Orçamento indisponível para importação.')->body($motivoFaturado)->warning()->send();
            $this->refreshFvImportarOrcamentoResults();

            return;
        }

        if ($orcamento->itens->isEmpty()) {
            Notification::make()->title('Orçamento sem itens cadastrados.')->warning()->send();

            return;
        }

        $itens = [];
        $ignorados = [];
        $descontoSvc = app(OrcamentoDescontoService::class);

        foreach ($orcamento->itens as $item) {
            /** @var OrcamentoItem $item */
            $product = $item->product;

            if (! $product || ! $product->ativo) {
                $ignorados[] = $item->descricao ?? 'Item inválido';

                continue;
            }

            if ($product->is_grade && ! $item->product_grade_id) {
                $ignorados[] = ($product->descricao ?? 'Item').' (grade)';

                continue;
            }

            $qtd = (float) $item->quantidade;
            $preco = (float) $item->preco_unitario;
            $partes = $descontoSvc->partesDaLinha($item);

            if ($qtd <= 0) {
                $ignorados[] = $item->descricao ?? $product->descricao;

                continue;
            }

            $descricao = (string) ($item->descricao
                ?: ($item->grade
                    ? $product->descricao.' - '.$item->grade->descricao
                    : $product->descricao));

            $itens[] = [
                'key' => uniqid('i', true),
                'product_id' => (int) $product->id,
                'product_grade_id' => $item->product_grade_id ? (int) $item->product_grade_id : null,
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => $descricao,
                'quantidade' => $qtd,
                'preco_unitario' => $preco,
                'acrescimo' => $partes['acrescimo'],
                'desconto' => $partes['desconto'],
                'total' => $partes['total'],
                // Sem cache-bust em disco (exists/lastModified) — import em lote.
                'foto' => ProductPhotoUrl::forPath($product->foto_path ?? null, cacheBust: false),
            ];
        }

        if ($itens === []) {
            Notification::make()
                ->title('Nenhum item pôde ser importado.')
                ->body($ignorados !== [] ? 'Ignorados: '.implode(', ', array_slice($ignorados, 0, 3)) : null)
                ->warning()
                ->send();

            return;
        }

        // F2 só carrega na venda: status permanece FECHADO no banco.
        // IMPORTADO + forca_vendas_orders.orcamento_id só na gravação do DAV (F3/Sair/F10).
        $this->orcamentoOrigemId = (int) $orcamento->id;

        if ($orcamento->cliente) {
            $this->aplicarCliente($orcamento->cliente);
            $this->clienteBusca = $this->formatarClienteBusca($orcamento->cliente);
        }

        $this->aplicarSnapshotClienteOrcamento($orcamento);

        if ($orcamento->vendedor) {
            $this->aplicarVendedor($orcamento->vendedor, permitirHistorico: true);
        }

        $this->itens = $itens;
        $this->itemSelecionado = $itens !== [] ? count($itens) - 1 : null;
        $this->descontoPedidoValor = $this->formatMoney((float) ($orcamento->desconto_valor ?? 0));
        $this->descontoPedidoPct = $this->formatMoney((float) ($orcamento->percentual_desconto ?? 0));
        $this->acrescimoPedidoValor = '0,00';
        $this->acrescimoPedidoPct = '0,00';
        $this->observacoes = trim((string) ($orcamento->observacoes ?? ''));
        $this->aplicarFormaPagamentoOrcamento($orcamento);
        $this->limparEntradaItem();

        $this->fecharImportarOrcamento();

        $notification = Notification::make()
            ->title('Orçamento importado.')
            ->body(count($itens).' item(ns) carregado(s). DAV origem Nº '.$orcamento->numero.'.')
            ->success();

        if ($ignorados !== []) {
            $notification->body(
                count($itens).' item(ns) carregado(s). Ignorados: '
                .implode(', ', array_slice($ignorados, 0, 3))
                .(count($ignorados) > 3 ? '…' : '')
            );
        }

        $notification->send();
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    /**
     * Cliente avulso guarda nome, documento e endereço no orçamento.
     * Não substitui esse snapshot pelos dados genéricos do Consumidor Final.
     */
    protected function aplicarSnapshotClienteOrcamento(Orcamento $orcamento): void
    {
        $temSnapshot = filled($orcamento->cliente_nome)
            || filled($orcamento->cliente_cpf_cnpj)
            || filled($orcamento->cliente_endereco)
            || filled($orcamento->cliente_numero)
            || filled($orcamento->cliente_bairro)
            || filled($orcamento->cliente_cep)
            || filled($orcamento->cliente_cidade)
            || filled($orcamento->cliente_fone)
            || filled($orcamento->cliente_whatsapp);

        if (! $temSnapshot) {
            return;
        }

        $nome = $orcamento->clienteDisplayNome();

        if ($nome !== '') {
            $this->clienteNome = $nome;
            $this->clienteBusca = $nome;
        }

        $this->clienteCpfCnpj = (string) ($orcamento->cliente_cpf_cnpj ?? '');
        $this->clienteEndereco = (string) ($orcamento->cliente_endereco ?? '');
        $this->clienteNumero = (string) ($orcamento->cliente_numero ?? '');
        $this->clienteBairro = (string) ($orcamento->cliente_bairro ?? '');
        $this->clienteCep = (string) ($orcamento->cliente_cep ?? '');
        $this->clienteCidade = (string) ($orcamento->cliente_cidade ?? '');
        $this->clienteUf = (string) ($orcamento->cliente_uf ?? '');
        $this->clienteFone = (string) ($orcamento->cliente_fone ?? '');
        $this->clienteWhatsapp = (string) ($orcamento->cliente_whatsapp ?? '');
    }

    protected function aplicarFormaPagamentoOrcamento(Orcamento $orcamento): void
    {
        $formaNome = trim((string) ($orcamento->forma_pagamento ?? ''));

        if ($formaNome === '') {
            return;
        }

        $this->carregarMeiosPagamento();

        $formaId = (int) (FormaPagamento::query()
            ->where(function ($query) use ($formaNome): void {
                $query->where('descricao', $formaNome)
                    ->orWhereRaw('LOWER(descricao) = ?', [mb_strtolower($formaNome, 'UTF-8')]);
            })
            ->value('id') ?? 0);

        if ($formaId <= 0) {
            return;
        }

        $this->formaSelecionadaId = $formaId;
        $totalLiquido = $this->totalLiquido();

        foreach ($this->meiosPagamento as $index => $meio) {
            if ((int) ($meio['id'] ?? 0) !== $formaId) {
                continue;
            }

            $this->meiosPagamento[$index]['valor'] = ErpMoney::formatBr($totalLiquido);
            $this->selectedPagamentoIndex = $index;

            break;
        }

        $this->valorPagamento = $this->formatMoney($this->valorRestante());
    }

    protected function dataFiltroFvImportarOrcamento(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
