<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Orcamento;
use App\Models\Product;
use App\Models\Vendedor;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

trait ManagesPdvImportar
{
    public string $importarSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $importarResults = [];

    public ?int $selectedImportarIndex = null;

    public function openImportarModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if ($this->cupomTemItens()) {
            $this->notifyPdvError('Cupom possui itens. Cancele (F6) antes de importar.');

            return;
        }

        $this->importarSearch = '';
        $this->refreshImportarResults();
        $this->openPdvModal('importar');
        $this->dispatch('erp-pdv-focus-importar');
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
            ->with(['cliente:id,nome_razao,codigo', 'vendedor:id,nome_razao'])
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

        $this->persistCupomToSession();
        $this->closePdvModal();

        $notification = Notification::make()
            ->title('Orçamento importado.')
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
        $this->vendedor = mb_strtoupper((string) ($vendedor->nome ?? 'LOJA'), 'UTF-8');
        $this->vendedorId = $vendedor->id;
        $this->persistVendedorToSession();
    }

    public function cancelImportar(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function clearImportSession(): void
    {
        session()->forget([
            'erp.pdv.orcamento_id',
            'erp.pdv.import_cliente_id',
            'erp.pdv.import_cliente_nome',
        ]);
    }
}
