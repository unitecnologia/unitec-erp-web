<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Orcamento;
use App\Models\Product;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvImportarPedidoQuery;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait ManagesPdvImportar
{
    public const IMPORTAR_PEDIDO = 'pedido';

    public const IMPORTAR_ORCAMENTO = 'orcamento';

    public const IMPORTAR_ORDEM_SERVICO = 'ordem_servico';

    public const IMPORTAR_PRE_VENDA = 'pre_venda';

    public string $importarSearch = '';

    public ?string $importarTipo = null;

    /** @var array<int, array<string, mixed>> */
    public array $importarResults = [];

    public ?int $selectedImportarIndex = null;

    public ?int $selectedImportarMenuIndex = 0;

    public string $importarPedidoNumero = '';

    public string $importarPedidoDe = '';

    public string $importarPedidoAte = '';

    /** @var array<int, array<string, mixed>> */
    public array $importarPedidoResults = [];

    public ?int $selectedImportarPedidoIndex = null;

    /**
     * @return list<array{key: string, fn: string, label: string}>
     */
    public function getImportarMenuOptionsProperty(): array
    {
        return [
            ['key' => self::IMPORTAR_PEDIDO, 'fn' => 'F2', 'label' => 'Pedido'],
            ['key' => self::IMPORTAR_ORCAMENTO, 'fn' => 'F3', 'label' => 'Orçamento'],
            ['key' => self::IMPORTAR_ORDEM_SERVICO, 'fn' => 'F4', 'label' => 'Ordem de Serviço'],
            ['key' => self::IMPORTAR_PRE_VENDA, 'fn' => 'F5', 'label' => 'Pré-Venda'],
        ];
    }

    public function getImportarTituloProperty(): string
    {
        return match ($this->importarTipo) {
            self::IMPORTAR_PEDIDO => 'F2 — Importar Pedido',
            self::IMPORTAR_ORCAMENTO => 'F3 — Importar Orçamento',
            self::IMPORTAR_ORDEM_SERVICO => 'F4 — Importar Ordem de Serviço',
            self::IMPORTAR_PRE_VENDA => 'F5 — Importar Pré-Venda',
            default => 'Importar',
        };
    }

    public function openImportarModal(): void
    {
        if (! $this->assertPodeImportar()) {
            return;
        }

        $this->importarTipo = null;
        $this->importarSearch = '';
        $this->importarResults = [];
        $this->selectedImportarIndex = null;
        $this->selectedImportarMenuIndex = 0;
        $this->openPdvModal('importar_menu');
        $this->dispatch('erp-pdv-focus-importar-menu');
    }

    public function selectImportarMenuRow(int $index): void
    {
        if (isset($this->importarMenuOptions[$index])) {
            $this->selectedImportarMenuIndex = $index;
        }
    }

    public function moveImportarMenuSelection(int $delta): void
    {
        $count = count($this->importarMenuOptions);

        if ($count === 0) {
            return;
        }

        $index = ($this->selectedImportarMenuIndex ?? 0) + $delta;
        $this->selectedImportarMenuIndex = max(0, min($count - 1, $index));
    }

    public function confirmImportarMenuSelection(): void
    {
        $index = $this->selectedImportarMenuIndex ?? 0;
        $option = $this->importarMenuOptions[$index] ?? null;

        if ($option === null) {
            $this->notifyPdvError('Selecione o que deseja importar.');

            return;
        }

        $this->selectImportarTipo($option['key']);
    }

    public function selectImportarTipo(string $tipo): void
    {
        if (! in_array($tipo, [
            self::IMPORTAR_PEDIDO,
            self::IMPORTAR_ORCAMENTO,
            self::IMPORTAR_ORDEM_SERVICO,
            self::IMPORTAR_PRE_VENDA,
        ], true)) {
            return;
        }

        if ($tipo === self::IMPORTAR_PEDIDO) {
            $this->openImportarPedidoModal();

            return;
        }

        if ($tipo === self::IMPORTAR_ORDEM_SERVICO) {
            $this->modulePending('Importar Ordem de Serviço');

            return;
        }

        $this->importarTipo = $tipo;
        $this->importarSearch = '';
        $this->refreshImportarResults();
        $this->activeModal = 'importar';
        $this->dispatch('erp-pdv-focus-importar');
    }

    public function openImportarPedidoModal(): void
    {
        $hoje = now()->format('d/m/Y');
        $this->importarTipo = self::IMPORTAR_PEDIDO;
        $this->importarPedidoNumero = '';
        $this->importarPedidoDe = $hoje;
        $this->importarPedidoAte = $hoje;
        $this->importarPedidoResults = [];
        $this->selectedImportarPedidoIndex = null;
        $this->refreshImportarPedidoResults();
        $this->activeModal = 'importar_pedido';
        $this->dispatch('erp-pdv-focus-importar-pedido');
    }

    public function updatedImportarPedidoNumero(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->importarPedidoNumero !== $upper) {
            $this->importarPedidoNumero = $upper;
        }
    }

    public function refreshImportarPedidoResults(): void
    {
        $query = new PdvImportarPedidoQuery(
            numero: $this->importarPedidoNumero,
            dataDe: $this->parseImportarPedidoDate($this->importarPedidoDe),
            dataAte: $this->parseImportarPedidoDate($this->importarPedidoAte),
        );

        $this->importarPedidoResults = $query->build()
            ->limit(100)
            ->get()
            ->map(fn (Venda $venda): array => [
                'venda_id' => $venda->id,
                'numero' => $venda->numero,
                'cliente' => mb_strtoupper($venda->cliente?->nome_razao ?? '—', 'UTF-8'),
                'data' => $venda->data?->format('d/m/Y') ?? '',
                'total' => ErpMoney::formatBr($venda->total),
                'cancelado' => $venda->status === Venda::STATUS_CANCELADO,
            ])
            ->values()
            ->all();

        $this->selectedImportarPedidoIndex = $this->importarPedidoResults === [] ? null : 0;
    }

    public function selectImportarPedidoRow(int $index): void
    {
        if (isset($this->importarPedidoResults[$index])) {
            $this->selectedImportarPedidoIndex = $index;
        }
    }

    public function moveImportarPedidoSelection(int $delta): void
    {
        if ($this->importarPedidoResults === []) {
            return;
        }

        $count = count($this->importarPedidoResults);
        $index = ($this->selectedImportarPedidoIndex ?? 0) + $delta;
        $this->selectedImportarPedidoIndex = max(0, min($count - 1, $index));
    }

    public function confirmImportarPedido(): void
    {
        $index = $this->selectedImportarPedidoIndex;

        if ($index === null || ! isset($this->importarPedidoResults[$index])) {
            $this->notifyPdvError('Selecione um pedido.');

            return;
        }

        if ($this->importarPedidoResults[$index]['cancelado'] ?? false) {
            $this->notifyPdvError('Pedido cancelado não pode ser importado.');

            return;
        }

        $vendaId = (int) ($this->importarPedidoResults[$index]['venda_id'] ?? 0);
        $venda = Venda::query()
            ->with(['itens.product', 'cliente', 'vendedor'])
            ->find($vendaId);

        if (! $venda || $venda->tipo !== Venda::TIPO_PEDIDO) {
            $this->notifyPdvError('Pedido indisponível para importação.');

            return;
        }

        if ($venda->status === Venda::STATUS_CANCELADO) {
            $this->notifyPdvError('Pedido cancelado não pode ser importado.');

            return;
        }

        if (! Venda::query()->whereKey($venda->id)->semDocumentoFiscalEmitido()->exists()) {
            $this->notifyPdvError('Pedido já possui documento fiscal emitido.');

            return;
        }

        if ($venda->itens->isEmpty()) {
            $this->notifyPdvError('Pedido sem itens cadastrados.');

            return;
        }

        $validator = $this->pdvItemValidator();
        $importados = 0;
        $ignorados = [];

        foreach ($venda->itens as $item) {
            $product = $item->product;

            if (! $product || ! $product->ativo) {
                $ignorados[] = $product?->descricao ?? 'Item inválido';

                continue;
            }

            if ($product->usa_imei) {
                $ignorados[] = $product->descricao . ' (IMEI)';

                continue;
            }

            if ($product->is_grade) {
                $ignorados[] = $product->descricao . ' (grade)';

                continue;
            }

            $quantidade = (float) $item->quantidade;
            $preco = (float) $item->valor_item;
            $descricao = mb_strtoupper($product->descricao, 'UTF-8');

            if ($msg = $validator->validaQuantidade($quantidade)) {
                $ignorados[] = $descricao;

                continue;
            }

            if ($msg = $validator->validaEstoque($product, $quantidade, null)) {
                $ignorados[] = $descricao;

                continue;
            }

            $this->addProductToCupom($product, $quantidade, $preco, null, null, $descricao);
            $importados++;
        }

        if ($importados === 0) {
            $this->notifyPdvError('Nenhum item pôde ser importado.');

            return;
        }

        $cliente = $venda->cliente;
        $clienteNome = mb_strtoupper($cliente?->nome_razao ?? 'CONSUMIDOR FINAL', 'UTF-8');

        session([
            'erp.pdv.venda_id' => $venda->id,
            'erp.pdv.import_cliente_id' => $cliente?->id,
            'erp.pdv.import_cliente_nome' => $clienteNome,
        ]);

        if ($venda->vendedor) {
            $this->applyImportVendedor($venda->vendedor);
        } elseif (filled($venda->vendedor_nome)) {
            $this->vendedor = mb_strtoupper((string) $venda->vendedor_nome, 'UTF-8');
            $this->vendedorId = $venda->vendedor_id;
            $this->persistVendedorToSession();
        }

        $this->persistCupomToSession();
        $this->importarTipo = null;
        $this->closePdvModal();

        $notification = Notification::make()
            ->title('Pedido importado.')
            ->body("{$importados} item(ns) carregado(s).");

        if ($ignorados !== []) {
            $notification->body(
                "{$importados} item(ns) carregado(s). Ignorados: " . implode(', ', array_slice($ignorados, 0, 3))
                . (count($ignorados) > 3 ? '...' : ''),
            );
        }

        $notification->success()->send();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function parseImportarPedidoDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function backImportarMenu(): void
    {
        $this->importarTipo = null;
        $this->importarSearch = '';
        $this->importarResults = [];
        $this->selectedImportarIndex = null;
        $this->importarPedidoNumero = '';
        $this->importarPedidoDe = '';
        $this->importarPedidoAte = '';
        $this->importarPedidoResults = [];
        $this->selectedImportarPedidoIndex = null;
        $this->selectedImportarMenuIndex = 0;
        $this->activeModal = 'importar_menu';
        $this->dispatch('erp-pdv-focus-importar-menu');
    }

    protected function assertPodeImportar(): bool
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return false;
        }

        if ($this->cupomTemItens()) {
            $this->notifyPdvError('Cupom possui itens. Cancele (F6) antes de importar.');

            return false;
        }

        return true;
    }

    public function updatedImportarSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->importarSearch !== $upper) {
            $this->importarSearch = $upper;
        }

        $this->refreshImportarResults();
    }

    public function refreshImportarResults(): void
    {
        $term = trim($this->importarSearch);
        $like = $term !== '' ? '%' . $term . '%' : null;

        $query = Orcamento::query()
            ->visivelNaListaOrcamentos()
            ->with(['cliente:id,nome_razao,codigo'])
            ->where('status', Orcamento::STATUS_ABERTO)
            ->orderByDesc('data')
            ->orderByDesc('id');

        if ($like) {
            $query->where(function ($q) use ($like): void {
                $q->where('numero', 'like', $like)
                    ->orWhereHas('cliente', fn ($sub) => $sub->where('nome_razao', 'like', $like));
            });
        }

        $this->importarResults = $query
            ->limit(50)
            ->get()
            ->map(fn (Orcamento $orcamento): array => [
                'orcamento_id' => $orcamento->id,
                'numero' => $orcamento->numero,
                'data' => $orcamento->data?->format('d/m/Y') ?? '',
                'cliente' => mb_strtoupper($orcamento->cliente?->nome_razao ?? '—', 'UTF-8'),
                'total' => ErpMoney::formatBr($orcamento->total),
            ])
            ->values()
            ->all();

        $this->selectedImportarIndex = $this->importarResults === [] ? null : 0;
    }

    public function selectImportarRow(int $index): void
    {
        if (isset($this->importarResults[$index])) {
            $this->selectedImportarIndex = $index;
        }
    }

    public function moveImportarSelection(int $delta): void
    {
        if ($this->importarResults === []) {
            return;
        }

        $count = count($this->importarResults);
        $index = ($this->selectedImportarIndex ?? 0) + $delta;
        $this->selectedImportarIndex = max(0, min($count - 1, $index));
    }

    public function confirmImportarOrcamento(): void
    {
        $index = $this->selectedImportarIndex;

        if ($index === null || ! isset($this->importarResults[$index])) {
            $this->notifyPdvError('Selecione um orçamento.');

            return;
        }

        $orcamentoId = (int) ($this->importarResults[$index]['orcamento_id'] ?? 0);
        $orcamento = Orcamento::query()
            ->with(['itens.product', 'itens.grade', 'cliente', 'vendedor'])
            ->find($orcamentoId);

        if (! $orcamento || $orcamento->status !== Orcamento::STATUS_ABERTO) {
            $this->notifyPdvError('Orçamento indisponível para importação.');

            return;
        }

        if ($orcamento->itens->isEmpty()) {
            $this->notifyPdvError('Orçamento sem itens cadastrados.');

            return;
        }

        $validator = $this->pdvItemValidator();
        $importados = 0;
        $ignorados = [];

        foreach ($orcamento->itens as $item) {
            $product = $item->product;

            if (! $product || ! $product->ativo) {
                $ignorados[] = $item->descricao ?? 'Item inválido';

                continue;
            }

            if ($product->usa_imei) {
                $ignorados[] = $product->descricao . ' (IMEI)';

                continue;
            }

            if ($product->is_grade && ! $item->product_grade_id) {
                $ignorados[] = $product->descricao . ' (grade)';

                continue;
            }

            $quantidade = (float) $item->quantidade;
            $preco = (float) $item->preco_unitario;
            $gradeId = $item->product_grade_id ? (int) $item->product_grade_id : null;
            $descricao = $item->descricao
                ?? ($item->grade
                    ? $product->descricao . ' - ' . $item->grade->descricao
                    : $product->descricao);

            if ($msg = $validator->validaQuantidade($quantidade)) {
                $ignorados[] = $descricao;

                continue;
            }

            if ($msg = $validator->validaEstoque($product, $quantidade, $gradeId)) {
                $ignorados[] = $descricao;

                continue;
            }

            $this->addProductToCupom(
                $product,
                $quantidade,
                $preco,
                $gradeId,
                null,
                mb_strtoupper($descricao, 'UTF-8'),
            );
            $importados++;
        }

        if ($importados === 0) {
            $this->notifyPdvError('Nenhum item pôde ser importado.');

            return;
        }

        DB::transaction(function () use ($orcamento): void {
            $orcamento->update(['status' => Orcamento::STATUS_IMPORTADO]);
        });

        (new \App\Support\VendasInternas\VendasInternasPdvHookService())->onOrcamentoImportado((int) $orcamento->id);

        $cliente = $orcamento->cliente;
        $clienteNome = mb_strtoupper($cliente?->nome_razao ?? 'CONSUMIDOR FINAL', 'UTF-8');

        session([
            'erp.pdv.orcamento_id' => $orcamento->id,
            'erp.pdv.import_cliente_id' => $cliente?->id,
            'erp.pdv.import_cliente_nome' => $clienteNome,
        ]);

        if ($orcamento->vendedor) {
            $this->applyImportVendedor($orcamento->vendedor);
        }

        $tipoImportado = $this->importarTipo;

        $this->persistCupomToSession();
        $this->importarTipo = null;
        $this->closePdvModal();

        $notification = Notification::make()
            ->title(match ($tipoImportado) {
                self::IMPORTAR_PRE_VENDA => 'Pré-venda importada.',
                default => 'Orçamento importado.',
            })
            ->body("{$importados} item(ns) carregado(s).");

        if ($ignorados !== []) {
            $notification->body(
                "{$importados} item(ns) carregado(s). Ignorados: " . implode(', ', array_slice($ignorados, 0, 3))
                . (count($ignorados) > 3 ? '...' : ''),
            );
        }

        $notification->success()->send();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function applyImportVendedor(Vendedor $vendedor): void
    {
        // orcamentos.vendedor_id já referencia "vendedores": usa direto.
        $this->vendedor = mb_strtoupper((string) ($vendedor->nome ?? 'SEM OPERADOR'), 'UTF-8');
        $this->vendedorId = $vendedor->id;
        $this->persistVendedorToSession();
    }

    public function cancelImportar(): void
    {
        if (in_array($this->activeModal, ['importar', 'importar_pedido'], true)) {
            $this->backImportarMenu();

            return;
        }

        $this->importarTipo = null;
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelImportarMenu(): void
    {
        $this->importarTipo = null;
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function clearImportSession(): void
    {
        session()->forget([
            'erp.pdv.orcamento_id',
            'erp.pdv.venda_id',
            'erp.pdv.import_cliente_id',
            'erp.pdv.import_cliente_nome',
        ]);
    }
}
