<?php

namespace App\Filament\Pages\Concerns;

use App\Models\FormaPagamento;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Orcamento\OrcamentoDescontoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Importar orçamentos finalizados (status fechado) para a Tela de Venda.
 */
trait ManagesForcaVendasTelaVendaImportarOrcamento
{
    public bool $fvImportarOrcamentoOpen = false;

    public string $fvImportarOrcamentoSearch = '';

    /** @var list<array{orcamento_id: int, numero: string, data: string, cliente: string, total: string}> */
    public array $fvImportarOrcamentoResults = [];

    public ?int $fvImportarOrcamentoSelectedIndex = null;

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

        $this->fvImportarOrcamentoSearch = '';
        $this->refreshFvImportarOrcamentoResults();
        $this->fvImportarOrcamentoOpen = true;
        $this->dispatch('erp-fv-focus-importar-orcamento');
    }

    public function fecharImportarOrcamento(): void
    {
        $this->fvImportarOrcamentoOpen = false;
        $this->fvImportarOrcamentoSearch = '';
        $this->fvImportarOrcamentoResults = [];
        $this->fvImportarOrcamentoSelectedIndex = null;
        $this->dispatch('fv-tela-venda-focus-barcode');
    }

    public function updatedFvImportarOrcamentoSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->fvImportarOrcamentoSearch !== $upper) {
            $this->fvImportarOrcamentoSearch = $upper;
        }

        $this->refreshFvImportarOrcamentoResults();
    }

    public function refreshFvImportarOrcamentoResults(): void
    {
        $term = trim($this->fvImportarOrcamentoSearch);
        $like = $term !== '' ? '%'.$term.'%' : null;

        $query = Orcamento::query()
            ->visivelNaListaOrcamentos()
            ->with(['cliente:id,nome_razao,codigo'])
            ->where('status', Orcamento::STATUS_FECHADO)
            ->orderByDesc('data')
            ->orderByDesc('id');

        if ($like) {
            $query->where(function ($q) use ($like): void {
                $q->where('numero', 'like', $like)
                    ->orWhereHas('cliente', fn ($sub) => $sub->where('nome_razao', 'like', $like));
            });
        }

        $this->fvImportarOrcamentoResults = $query
            ->limit(50)
            ->get()
            ->map(fn (Orcamento $orcamento): array => [
                'orcamento_id' => $orcamento->id,
                'numero' => (string) $orcamento->numero,
                'data' => $orcamento->data?->format('d/m/Y') ?? '',
                'cliente' => mb_strtoupper($orcamento->cliente?->nome_razao ?? '—', 'UTF-8'),
                'total' => ErpMoney::formatBr($orcamento->total),
            ])
            ->values()
            ->all();

        $this->fvImportarOrcamentoSelectedIndex = $this->fvImportarOrcamentoResults === [] ? null : 0;
    }

    public function selectFvImportarOrcamentoRow(int $index): void
    {
        if (isset($this->fvImportarOrcamentoResults[$index])) {
            $this->fvImportarOrcamentoSelectedIndex = $index;
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
        $this->dispatch('erp-fv-scroll-importar-orcamento', index: $this->fvImportarOrcamentoSelectedIndex);
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
            ->with(['itens.product', 'itens.grade', 'cliente', 'vendedor'])
            ->find($orcamentoId);

        if (! $orcamento || $orcamento->status !== Orcamento::STATUS_FECHADO) {
            Notification::make()->title('Orçamento indisponível para importação.')->warning()->send();
            $this->refreshFvImportarOrcamentoResults();

            return;
        }

        if ($orcamento->itens->isEmpty()) {
            Notification::make()->title('Orçamento sem itens cadastrados.')->warning()->send();

            return;
        }

        $itens = [];
        $ignorados = [];

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
            $partes = app(OrcamentoDescontoService::class)->partesDaLinha($item);

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
                'foto' => $product->fotoUrl(),
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

        DB::transaction(function () use ($orcamento): void {
            $orcamento->update(['status' => Orcamento::STATUS_IMPORTADO]);
        });

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
}
