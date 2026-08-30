<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ErpProductFormPage;
use App\Models\Empresa;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\NotaFornecedor\NotaFornecedorProductPrefill;
use App\Support\Erp\NotaFornecedor\NotaFornecedorXmlProdutoMatcher;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use ErpProductFormPage;

    protected static string $resource = ProductResource::class;

    public function mount(): void
    {
        if (request()->boolean('pdv')) {
            $this->embedsInPdv = true;
        }

        if (request()->boolean('orcamento')) {
            $this->embedsInOrcamento = true;
        }

        if (request()->boolean('nota_fornecedor')) {
            $this->embedsInNotaFornecedor = true;
        }

        parent::mount();

        ErpScreen::set('Cadastro de Produtos');
        $this->ensureProductFormEmpresaId();

        if ($this->embedsInPdv) {
            $this->activeFormTab = 'dados';
        }

        // Sempre busca a empresa ativa na hora do "Novo" para já trazer o Imposto Padrão.
        $empresaId = $this->currentProductEmpresaId();
        $empresa = $empresaId > 0 ? Empresa::query()->find($empresaId) : null;

        $defaults = [
            ...($this->data ?? []),
            ...static::defaultProductFormData($empresa),
            ...$this->notaFornecedorPrefillOverrides(),
        ];

        // Garante imposto padrão fresco (ICMS, PIS/COFINS, IPI, IVA…) da empresa.
        $defaults = [
            ...$defaults,
            ...\App\Support\Erp\ProductFormValidator::fiscalDefaultsFromEmpresa($empresa),
        ];

        $defaults = $this->formatProductFormDataForDisplay($defaults);

        $this->form->fill($defaults);
        // form->fill pode normalizar números; reafirma o estado BR usado pelos inputs da tela.
        $this->data = array_merge($this->data ?? [], $defaults);
        $this->hydrateNcmDescricaoFromCatalog(fillForm: true);
        $this->dispatch('erp-masks-refresh');
        $this->mountProductPhoto();
        $this->loadProductGrades();
        $this->loadProductCompositions();
        $this->loadProductPriceTableItems();
        $this->loadProductPriceHistories();
        $this->loadProductImeis();

        if ($this->embedsInNotaFornecedor && filled($this->data['ncm'] ?? null)) {
            $this->hydrateNcmDescricaoFromCatalog(fillForm: true);
        }

        $this->captureProductFormBaseline();
    }

    protected function getRedirectUrl(): string
    {
        if ($this->embedsInPdv) {
            return static::getResource()::getUrl('create') . '?pdv=1';
        }

        if ($this->embedsInOrcamento) {
            return static::getResource()::getUrl('create') . '?orcamento=1';
        }

        if ($this->embedsInNotaFornecedor) {
            return static::getResource()::getUrl('create') . '?nota_fornecedor=1';
        }

        return $this->erpFormReturnRedirectUrl($this->getProductListRedirectUrl());
    }

    protected function afterCreate(): void
    {
        $this->syncProductChildRecords($this->record);

        if ($this->record instanceof Product) {
            app(\App\Support\Erp\ProductLoteService::class)->garantirLoteInicial($this->record->fresh());
        }

        $prefill = NotaFornecedorProductPrefill::peek();
        $itemIndex = is_array($prefill) ? (int) ($prefill['item_index'] ?? -1) : -1;

        if ($this->embedsInNotaFornecedor && $this->record instanceof Product) {
            $this->vincularProdutoNotaFornecedor($this->record, $prefill);
            NotaFornecedorProductPrefill::forget();

            Notification::make()
                ->title('Produto gravado com sucesso.')
                ->success()
                ->send();

            $this->closeEmbedOverlay([
                'produtoId' => (int) $this->record->id,
                'produtoCodigo' => (string) ($this->record->codigo ?? ''),
                'produtoDescricao' => (string) ($this->record->descricao ?? ''),
                'produtoGrupo' => (string) ($this->record->grupo ?? ''),
                'produtoPrecoVenda' => number_format((float) ($this->record->preco_venda ?? 0), 3, ',', '.'),
                'itemIndex' => $itemIndex,
            ]);

            return;
        }

        Notification::make()
            ->title('Produto gravado com sucesso.')
            ->success()
            ->send();

        if ($this->embedsInOrcamento) {
            $this->closeEmbedOverlay([
                'produtoCodigo' => (string) ($this->record?->codigo ?? ''),
            ]);

            return;
        }

        $this->flashOrcamentoReturnContextAfterProductSave();

        if ($this->embedsInPdv) {
            $this->closePdvEmbedOverlay();
        }
    }

    public function cancelForm(): void
    {
        if ($this->productExitConfirmOpen) {
            return;
        }

        if ($this->productFormHasUnsavedChanges()) {
            $this->productExitConfirmOpen = true;

            return;
        }

        $this->leaveProductForm();
    }

    protected function leaveProductForm(): void
    {
        if ($this->embedsInNotaFornecedor) {
            NotaFornecedorProductPrefill::forget();
        }

        if ($this->embedsInParentOverlay()) {
            $this->closeEmbedOverlay();

            return;
        }

        $this->redirectToErpFormReturnOr(
            $this->getProductListRedirectUrl(),
            'Produtos',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function notaFornecedorPrefillOverrides(): array
    {
        if (! $this->embedsInNotaFornecedor) {
            return [];
        }

        $prefill = NotaFornecedorProductPrefill::peek();

        if (! is_array($prefill)) {
            return [];
        }

        $preco = $this->parseXmlMoney($prefill['preco'] ?? 0);
        $ean = preg_replace('/\D/', '', (string) ($prefill['ean'] ?? '')) ?? '';
        $ncm = preg_replace('/\D/', '', (string) ($prefill['ncm'] ?? '')) ?? '';
        $unidade = trim((string) ($prefill['unidade'] ?? ''));
        $referencia = trim((string) ($prefill['codigo_fornecedor'] ?? ''));
        $descricao = trim((string) ($prefill['descricao'] ?? ''));
        $personId = (int) ($prefill['person_id'] ?? 0);

        return array_filter([
            'descricao' => $descricao !== '' && $descricao !== '—' ? mb_strtoupper($descricao, 'UTF-8') : null,
            'codigo_barras' => strlen($ean) >= 8 ? $ean : null,
            'referencia' => $referencia !== '' && $referencia !== '—' ? $referencia : null,
            'unidade' => $unidade !== '' ? mb_strtoupper($unidade, 'UTF-8') : null,
            'ncm' => strlen($ncm) >= 8 ? substr($ncm, 0, 8) : null,
            'preco_compra' => $preco,
            'preco_custo' => $preco,
            'ult_compra' => $preco,
            'ult_fornecedor_id' => $personId > 0 ? $personId : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>|null  $prefill
     */
    private function vincularProdutoNotaFornecedor(Product $product, ?array $prefill): void
    {
        if (! is_array($prefill)) {
            return;
        }

        $matcher = new NotaFornecedorXmlProdutoMatcher();
        $personId = (int) ($prefill['person_id'] ?? 0);
        $fornecedor = $personId > 0
            ? Person::query()->find($personId)
            : $matcher->resolveFornecedorByCnpj((string) ($prefill['cnpj'] ?? ''));

        if (! $fornecedor instanceof Person) {
            return;
        }

        $matcher->vincularProduto(
            $product,
            $fornecedor,
            (string) ($prefill['codigo_fornecedor'] ?? $product->codigo),
        );
    }

    private function parseXmlMoney(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 4);
        }

        return BrDecimal::parse((string) $value, 4);
    }
}
