<?php

namespace App\Filament\Resources\PromocaoResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Product;
use App\Models\Promocao;
use App\Models\PromocaoItem;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ProductEmpresaPrecoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ManagesPromocaoFormModal
{
    public bool $showForm = false;

    public ?int $formId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /**
     * @var list<array{product_id:int,codigo:string,descricao:string,preco_normal:string,preco_promocao:string,mostrar_pdv:bool}>
     */
    public array $itens = [];

    public string $itemProdutoSearch = '';

    /** @var list<array{id:int,codigo:string,descricao:string,preco:string}> */
    public array $produtoResults = [];

    public bool $produtoLookupOpen = false;

    public int $produtoSelecionadoIndex = 0;

    public function createPromocao(): void
    {
        if (! \App\Support\Erp\ErpAccess::currentCan('promocoes.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $empresaId = ErpContext::currentEmpresaId();

        $this->formId = null;
        $this->form = [
            'descricao' => '',
            'data_inicio' => now('America/Sao_Paulo')->toDateString(),
            'data_fim' => now('America/Sao_Paulo')->addDays(7)->toDateString(),
            'empresa_id' => $empresaId ? (string) $empresaId : '',
            'ativa' => true,
        ];
        $this->itens = [];
        $this->resetProdutoLookup();
        $this->showForm = true;
    }

    public function editPromocao(): void
    {
        if (! \App\Support\Erp\ErpAccess::currentCan('promocoes.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $this->openPromocaoForm((int) $recordId);
    }

    public function openPromocaoForm(int $id): void
    {
        $promocao = Promocao::query()->with(['itens.product'])->find($id);

        if (! $promocao) {
            Notification::make()->title('Promoção não encontrada.')->warning()->send();

            return;
        }

        $empresaId = (int) $promocao->empresa_id;
        $this->formId = (int) $promocao->id;
        $this->form = [
            'descricao' => (string) $promocao->descricao,
            'data_inicio' => $promocao->data_inicio?->toDateString() ?? '',
            'data_fim' => $promocao->data_fim?->toDateString() ?? '',
            'empresa_id' => (string) $empresaId,
            'ativa' => (bool) $promocao->ativa,
        ];

        $priceService = app(ProductEmpresaPrecoService::class);
        $this->itens = [];

        foreach ($promocao->itens as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $precos = $priceService->resolve($product, $empresaId);

            $this->itens[] = [
                'product_id' => (int) $product->id,
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => mb_strtoupper(trim((string) $product->descricao), 'UTF-8'),
                'preco_normal' => ErpMoney::formatBr((float) ($precos['preco_venda'] ?? 0)),
                'preco_promocao' => ErpMoney::formatBr((float) $item->preco_promocao),
                'mostrar_pdv' => (bool) $item->mostrar_pdv,
            ];
        }

        $this->resetProdutoLookup();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->formId = null;
        $this->form = [];
        $this->itens = [];
        $this->resetProdutoLookup();
    }

    public function savePromocao(): void
    {
        $can = $this->formId
            ? \App\Support\Erp\ErpAccess::currentCan('promocoes.update')
            : \App\Support\Erp\ErpAccess::currentCan('promocoes.create');

        if (! $can) {
            Notification::make()->title('Sem permissão para salvar.')->warning()->send();

            return;
        }

        $descricao = mb_strtoupper(trim((string) ($this->form['descricao'] ?? '')), 'UTF-8');
        $inicio = trim((string) ($this->form['data_inicio'] ?? ''));
        $fim = trim((string) ($this->form['data_fim'] ?? ''));
        $empresaId = (int) ($this->form['empresa_id'] ?? 0);
        $ativa = (bool) ($this->form['ativa'] ?? false);

        if ($descricao === '') {
            throw ValidationException::withMessages(['form.descricao' => 'Informe a descrição.']);
        }

        if ($inicio === '' || $fim === '') {
            throw ValidationException::withMessages(['form.data_inicio' => 'Informe as datas de início e fim.']);
        }

        if ($fim < $inicio) {
            throw ValidationException::withMessages(['form.data_fim' => 'Data fim deve ser maior ou igual à data início.']);
        }

        if ($empresaId <= 0 || ! ErpContext::userCanAccessEmpresa($empresaId)) {
            throw ValidationException::withMessages(['form.empresa_id' => 'Empresa inválida.']);
        }

        if ($this->itens === []) {
            Notification::make()->title('Inclua ao menos um produto.')->warning()->send();

            return;
        }

        foreach ($this->itens as $i => $row) {
            $preco = ErpMoney::parseBr((string) ($row['preco_promocao'] ?? ''), 2);
            if ($preco <= 0) {
                throw ValidationException::withMessages([
                    "itens.{$i}.preco_promocao" => 'Preço promoção inválido na linha '.($i + 1).'.',
                ]);
            }
        }

        DB::transaction(function () use ($descricao, $inicio, $fim, $empresaId, $ativa): void {
            $promocao = $this->formId
                ? Promocao::query()->findOrFail($this->formId)
                : new Promocao;

            $promocao->fill([
                'empresa_id' => $empresaId,
                'descricao' => $descricao,
                'data_inicio' => $inicio,
                'data_fim' => $fim,
                'ativa' => $ativa,
            ]);
            $promocao->save();

            $promocao->itens()->delete();

            foreach ($this->itens as $row) {
                PromocaoItem::query()->create([
                    'promocao_id' => $promocao->id,
                    'product_id' => (int) $row['product_id'],
                    'preco_promocao' => ErpMoney::parseBr((string) $row['preco_promocao'], 2),
                    'mostrar_pdv' => (bool) ($row['mostrar_pdv'] ?? false),
                ]);
            }

            $this->formId = (int) $promocao->id;
        });

        Notification::make()->title('Promoção salva.')->success()->send();
        $this->closeForm();
        $this->resetTable();
    }

    public function deletePromocao(): void
    {
        if (! \App\Support\Erp\ErpAccess::currentCan('promocoes.delete')) {
            Notification::make()->title('Sem permissão para excluir.')->warning()->send();

            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $record = Promocao::query()->find($recordId);

        if (! $record) {
            Notification::make()->title('Promoção não encontrada.')->warning()->send();

            return;
        }

        $record->delete();
        Notification::make()->title('Promoção excluída.')->success()->send();
        $this->clearListSelection();
        $this->resetTable();
    }

    /**
     * @return array<int, string>
     */
    public function empresaOptions(): array
    {
        $ids = ErpContext::accessibleEmpresaIds();

        if ($ids === []) {
            return [];
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->mapWithKeys(fn ($nome, $id) => [(string) $id => (string) $nome])
            ->all();
    }

    public function updatedItemProdutoSearch(string $value): void
    {
        $this->itemProdutoSearch = mb_strtoupper($value, 'UTF-8');
        $this->atualizarProdutoResults();
    }

    public function abrirProdutoLookup(): void
    {
        $this->produtoLookupOpen = true;
        $this->atualizarProdutoResults();
    }

    public function fecharProdutoLookup(): void
    {
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
    }

    public function atualizarProdutoResults(): void
    {
        $term = trim($this->itemProdutoSearch);
        if ($term === '') {
            $this->produtoResults = [];
            $this->produtoSelecionadoIndex = 0;

            return;
        }

        $like = '%'.$term.'%';
        $empresaId = (int) ($this->form['empresa_id'] ?? 0) ?: ErpContext::currentEmpresaId();
        $priceService = app(ProductEmpresaPrecoService::class);

        $this->produtoResults = Product::query()
            ->where('ativo', true)
            ->where(function ($q) use ($like, $term): void {
                $q->where('codigo', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('descricao', 'like', $like)
                    ->orWhere('referencia', 'like', $like);
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            })
            ->orderBy('descricao')
            ->limit(30)
            ->get()
            ->map(function (Product $p) use ($priceService, $empresaId): array {
                $precos = $priceService->resolve($p, $empresaId ?: null);

                return [
                    'id' => (int) $p->id,
                    'codigo' => (string) ($p->codigo ?? ''),
                    'descricao' => mb_strtoupper(trim((string) $p->descricao), 'UTF-8'),
                    'preco' => ErpMoney::formatBr((float) ($precos['preco_venda'] ?? 0)),
                ];
            })
            ->values()
            ->all();

        $this->produtoSelecionadoIndex = 0;
        $this->produtoLookupOpen = $this->produtoResults !== [];
    }

    public function moverProdutoSelecionado(int $delta): void
    {
        if ($this->produtoResults === []) {
            return;
        }

        $count = count($this->produtoResults);
        $this->produtoSelecionadoIndex = max(0, min($count - 1, $this->produtoSelecionadoIndex + $delta));
    }

    public function selecionarProdutoInclusao(int $productId): void
    {
        $this->adicionarProdutoNaGrade($productId);
    }

    public function confirmarInclusaoProduto(?string $termo = null): void
    {
        if (filled($termo)) {
            $this->itemProdutoSearch = mb_strtoupper(trim($termo), 'UTF-8');
            $this->atualizarProdutoResults();
        }

        if ($this->produtoResults === []) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $row = $this->produtoResults[$this->produtoSelecionadoIndex] ?? $this->produtoResults[0];
        $this->adicionarProdutoNaGrade((int) $row['id']);
    }

    protected function adicionarProdutoNaGrade(int $productId): void
    {
        foreach ($this->itens as $row) {
            if ((int) ($row['product_id'] ?? 0) === $productId) {
                Notification::make()->title('Produto já está na promoção.')->warning()->send();

                return;
            }
        }

        $product = Product::query()->whereKey($productId)->where('ativo', true)->first();

        if (! $product) {
            Notification::make()->title('Produto indisponível.')->warning()->send();

            return;
        }

        $empresaId = (int) ($this->form['empresa_id'] ?? 0) ?: ErpContext::currentEmpresaId();
        $precos = app(ProductEmpresaPrecoService::class)->resolve($product, $empresaId ?: null);
        $normal = (float) ($precos['preco_venda'] ?? 0);

        $this->itens[] = [
            'product_id' => (int) $product->id,
            'codigo' => (string) ($product->codigo ?? ''),
            'descricao' => mb_strtoupper(trim((string) $product->descricao), 'UTF-8'),
            'preco_normal' => ErpMoney::formatBr($normal),
            'preco_promocao' => ErpMoney::formatBr($normal > 0 ? $normal : 0),
            'mostrar_pdv' => false,
        ];

        $this->resetProdutoLookup();
        $this->itemProdutoSearch = '';
    }

    public function removerItemPromocao(int $index): void
    {
        if (! isset($this->itens[$index])) {
            return;
        }

        unset($this->itens[$index]);
        $this->itens = array_values($this->itens);
    }

    public function toggleMostrarPdv(int $index): void
    {
        if (! isset($this->itens[$index])) {
            return;
        }

        $this->itens[$index]['mostrar_pdv'] = ! (bool) ($this->itens[$index]['mostrar_pdv'] ?? false);
    }

    public function atualizarPrecoPromocao(int $index, ?string $valor = null): void
    {
        if (! isset($this->itens[$index])) {
            return;
        }

        if ($valor !== null) {
            $this->itens[$index]['preco_promocao'] = $valor;
        }

        $preco = ErpMoney::parseBr((string) $this->itens[$index]['preco_promocao'], 2);
        $this->itens[$index]['preco_promocao'] = ErpMoney::formatBr(max(0, $preco));
    }

    protected function resetProdutoLookup(): void
    {
        $this->itemProdutoSearch = '';
        $this->produtoResults = [];
        $this->produtoLookupOpen = false;
        $this->produtoSelecionadoIndex = 0;
    }
}
