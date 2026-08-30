<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Filament\Concerns\InteractsWithErpFormReturnUrl;
use App\Filament\Concerns\EmbedsInPdvOverlay;
use App\Filament\Concerns\ManagesCclassTribLookup;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpFormReturnUrl;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\ProductFormValidator;
use App\Support\Erp\ProductLocalizacao;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\UniqueConstraintViolationException;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

trait ErpProductFormPage
{
    use EmbedsInPdvOverlay;
    use InteractsWithErpFormReturnUrl;
    use ManagesCclassTribLookup;
    use ManagesProductBarcodeLookup;
    use ManagesProductCardex;
    use ManagesProductPhoto;
    use NormalizesErpUppercaseFormData;
    use ManagesProductCadastroLookup;
    use ManagesProductDuplicateCheck;
    use ManagesProductFormUi;
    use ManagesProductGrade;
    use ManagesProductComposicao;
    use ManagesProductImei;
    use ManagesProductTabPreco;
    use ManagesProductUltimosPrecos;
    use ManagesProductPriceCalculation;
    use ManagesProductPrecificacao;
    use ManagesProductEmpresaPrecos;
    use ManagesProductImpostoPadrao;
    use ManagesProductLocalizacao;
    use ManagesProductReservas;
    use ManagesProductLotes;
    use ManagesProductExitConfirm;

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        $classes = [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-produtos-form-page',
        ];

        if (! $this->isEditingProduct()) {
            $classes[] = 'erp-produtos-form-page--create';
        }

        if ($this->embedsInPdv) {
            $classes[] = 'erp-pdv-embed';
        }

        if ($this->embedsInOrcamento) {
            $classes[] = 'erp-orcamento-embed';
        }

        if ($this->embedsInNotaFornecedor) {
            $classes[] = 'erp-orcamento-embed';
        }

        return $classes;
    }

    public function content(Schema $schema): Schema
    {
        if ($this->embedsInPdv) {
            return $schema
                ->gap(false)
                ->components([
                    View::make('filament.components.erp.produtos.form.shell'),
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('form')
                        ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                        ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
                    View::make('filament.components.erp.produtos.form.action-bar'),
                ]);
        }

        // Standalone: rodapé vai dentro da janela (window.blade) para altura fullscreen.
        // Embed (orçamento/nota): mantém action-bar externo como antes.
        $components = [
            View::make('filament.components.erp.produtos.form.window'),
        ];

        if ($this->embedsInOrcamento || $this->embedsInNotaFornecedor) {
            $components[] = View::make('filament.components.erp.produtos.form.action-bar');
        }

        $components[] = Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
            ->extraAttributes(['class' => 'erp-pcad__filament-hidden']);

        return $schema
            ->gap(false)
            ->components($components);
    }

    public function saveForm(?string $localizacaoFormatted = null): void
    {
        $this->productPhotoDownloadFailureNotified = false;

        if ($localizacaoFormatted !== null) {
            $this->applyLocalizacaoFormatted($localizacaoFormatted);
        }

        $this->syncProductFormDataBeforeSave();

        try {
            $formData = $this->mergeLivewireFormData($this->form->getState());
            $formData = $this->validateAndNormalizeProductBeforeSave($formData);
            $this->form->fill($formData);

            if ($this instanceof EditRecord) {
                $this->save();
            } else {
                if ($this->promptDuplicateConfirmIfNeeded($formData)) {
                    return;
                }

                /** @var CreateRecord $this */
                $this->create();
            }

            $this->data = $this->formatProductFormDataForDisplay($formData);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $message = $this->formatProductValidationMessage($exception);

            Notification::make()
                ->title('Não foi possível gravar o produto.')
                ->body($message)
                ->danger()
                ->send();
        } catch (UniqueConstraintViolationException $exception) {
            if ($this->handleProductUniqueConstraintViolation($exception)) {
                return;
            }

            report($exception);

            Notification::make()
                ->title('Não foi possível gravar o produto.')
                ->body('Já existe um produto com estes dados.')
                ->danger()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível gravar o produto.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function handleProductUniqueConstraintViolation(UniqueConstraintViolationException $exception): bool
    {
        if (! $this instanceof CreateRecord) {
            return false;
        }

        if (! str_contains($exception->getMessage(), 'products.codigo')) {
            return false;
        }

        $this->data['codigo'] = Product::nextCodigo();
        $this->form->fill($this->data);

        Notification::make()
            ->title('Código do produto já utilizado.')
            ->body('O código foi atualizado automaticamente. Pressione F5 para salvar novamente.')
            ->warning()
            ->send();

        return true;
    }

    protected function syncProductFormDataBeforeSave(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $this->syncLocalizacaoFromParts();
        $this->recalculateProductPricesBeforeSave();
        $this->form->fill($this->data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->mergeLivewireFormData($data);
        $data = $this->ensureUniqueProductCodigo($data);
        $data = $this->resolveNcmDescricaoInFormData($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->mergeLivewireFormData($data);
        $data = $this->resolveNcmDescricaoInFormData($data);

        return $this->preserveProductEstoqueOnUpdate($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveNcmDescricaoInFormData(array $data): array
    {
        $catalog = app(\App\Support\Erp\Fiscal\NcmCatalogService::class);
        $ncm = $catalog->normalizeCodigo((string) ($data['ncm'] ?? ''));

        if ($ncm === null) {
            return $data;
        }

        $data['ncm'] = $ncm;
        $record = $catalog->findByCodigo($ncm);

        if ($record) {
            $data['ncm_descricao'] = (string) $record->descricao;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preserveProductEstoqueOnUpdate(array $data): array
    {
        if ($this->isEditingProduct()) {
            unset($data['estoque']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeLivewireFormData(array $data): array
    {
        $merged = array_merge($data, $this->data ?? []);

        if ($this->isEditingProduct() && $this->record) {
            $merged['estoque'] = (float) $this->record->estoque;
        }

        $merged = ErpUppercase::normalizeFormData($merged);
        $merged = $this->normalizeProductFormDataForSave($merged);

        if (blank($merged['localizacao'] ?? null) && filled($this->data['localizacao'] ?? null)) {
            $merged['localizacao'] = $this->data['localizacao'];
        }

        if (! empty($merged['validade']) && is_string($merged['validade'])) {
            $validade = trim($merged['validade']);

            if (str_contains($validade, '/')) {
                if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $validade)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'validade' => 'Data de validade inválida!',
                    ]);
                }

                try {
                    $merged['validade'] = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $validade)->format('Y-m-d');
                } catch (\Throwable) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'validade' => 'Data de validade inválida!',
                    ]);
                }
            }
        }

        if (($merged['validade'] ?? null) === '') {
            $merged['validade'] = null;
        }

        if (($merged['lote'] ?? null) === '') {
            $merged['lote'] = null;
        } elseif (isset($merged['lote'])) {
            $merged['lote'] = mb_substr(trim((string) $merged['lote']), 0, 60);
            if ($merged['lote'] === '') {
                $merged['lote'] = null;
            }
        }

        $merged['controla_lote_validade'] = (bool) ($merged['controla_lote_validade'] ?? false);

        foreach (['promo_data_inicio', 'promo_data_fim'] as $promoDateField) {
            if (! empty($merged[$promoDateField]) && is_string($merged[$promoDateField]) && str_contains($merged[$promoDateField], '/')) {
                try {
                    $merged[$promoDateField] = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', trim($merged[$promoDateField]))->format('Y-m-d');
                } catch (\Throwable) {
                    $merged[$promoDateField] = null;
                }
            }

            if (($merged[$promoDateField] ?? null) === '') {
                $merged[$promoDateField] = null;
            }
        }

        $merged = $this->persistProductPhotoForSave($merged);

        return $merged;
    }

    protected function syncProductFormData(): void
    {
        $state = $this->form->getState();

        $state = $this->formatProductFormDataForDisplay($state);

        $this->data = $state;
        $this->form->fill($state);
        $this->refreshProductFotoPreviewUrl();
        $this->loadProductReservas($this->record ?? null);
        $this->captureProductFormBaseline();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function formatProductFormDataForDisplay(array $data): array
    {
        foreach (['preco_compra', 'preco_custo', 'preco_venda', 'preco_atacado', 'preco_especial', 'preco_custo_anterior', 'preco_venda_anterior', 'ult_compra', 'ult_compra_anterior', 'promo_preco_venda', 'promo_preco_atacado'] as $field) {
            $data[$field] = $this->formatBrDecimal($data[$field] ?? 0, 2);
        }

        foreach (['pct_custos', 'pct_lucro'] as $field) {
            $data[$field] = $this->formatBrDecimal($data[$field] ?? 0, 2);
        }

        foreach (['aliq_icms', 'aliq_icms_externo', 'aliq_pis', 'aliq_cofins', 'aliq_ipi', 'fcp_pct', 'mva_pct', 'reducao_base_pct', 'glp_pct', 'gnn_pct', 'gni_pct', 'issqn'] as $field) {
            $data[$field] = $this->formatBrDecimal($data[$field] ?? 0, 2);
        }

        foreach (['nutri_valor_energetico', 'nutri_carboidratos', 'nutri_proteinas', 'nutri_gorduras_totais', 'nutri_gorduras_saturadas', 'nutri_gorduras_trans', 'nutri_fibra', 'nutri_sodio'] as $field) {
            $data[$field] = $this->formatBrDecimal($data[$field] ?? 0, 1);
        }

        foreach (['mva_normal', 'icms_diferido', 'aliq_deson', 'aliq_ibs_uf', 'aliq_cbs', 'aliq_ibs_mun', 'aliq_adrem_ibs', 'aliq_adrem_cbs', 'reducao_cbs', 'reducao_ibs'] as $field) {
            $data[$field] = $this->formatBrDecimal($data[$field] ?? 0, 4);
        }

        foreach (['valor_pequena', 'valor_media', 'valor_grande'] as $field) {
            $data[$field] = $this->formatBrDecimal($data[$field] ?? 0, 4);
        }

        $data['peso_liq'] = $this->formatBrDecimal($data['peso_liq'] ?? 0, 3);
        $data['e_medio'] = $this->formatBrDecimal($data['e_medio'] ?? 0, 3);

        $data['qtd_atacado'] = $this->formatBrDecimal($data['qtd_atacado'] ?? 0, 0);
        $data['estoque'] = $this->formatBrDecimal($data['estoque'] ?? 0, 3);
        $data['estoque_minimo'] = $this->formatBrDecimal($data['estoque_minimo'] ?? 0, 0);
        $data['peso_kg'] = $this->formatBrDecimal($data['peso_kg'] ?? 0, 3);

        if (! empty($data['validade'])) {
            $data['validade'] = $this->formatIsoDateForInput($data['validade']);
        }

        foreach (['promo_data_inicio', 'promo_data_fim'] as $promoDateField) {
            if (! empty($data[$promoDateField])) {
                $data[$promoDateField] = $this->formatIsoDateForInput($data[$promoDateField]);
            }
        }

        return $this->ensureLocalizacaoFormKeys($data);
    }

    protected function formatIsoDateForInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($value)->format('Y-m-d');
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed)) {
            return substr($trimmed, 0, 10);
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed)) {
            try {
                return \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $trimmed)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return \Illuminate\Support\Carbon::parse($trimmed)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function formatBrDateForDisplay(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($value)->format('d/m/Y');
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed)) {
            return $trimmed;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed)) {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', substr($trimmed, 0, 10))->format('d/m/Y');
        }

        try {
            return \Illuminate\Support\Carbon::parse($trimmed)->format('d/m/Y');
        } catch (\Throwable) {
            return $trimmed;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeProductFormDataForSave(array $data): array
    {
        foreach (['preco_compra', 'preco_custo', 'preco_venda', 'preco_atacado', 'preco_especial', 'preco_custo_anterior', 'preco_venda_anterior', 'ult_compra', 'ult_compra_anterior', 'promo_preco_venda', 'promo_preco_atacado'] as $field) {
            $data[$field] = $this->parseBrDecimal($data[$field] ?? 0, 2);
        }

        foreach (['pct_custos', 'pct_lucro'] as $field) {
            $data[$field] = $this->parseBrDecimal($data[$field] ?? 0, 2);
        }

        foreach (['aliq_icms', 'aliq_icms_externo', 'aliq_pis', 'aliq_cofins', 'aliq_ipi', 'fcp_pct', 'mva_pct', 'reducao_base_pct', 'glp_pct', 'gnn_pct', 'gni_pct', 'issqn'] as $field) {
            $data[$field] = $this->parseBrDecimal($data[$field] ?? 0, 2);
        }

        foreach (['nutri_valor_energetico', 'nutri_carboidratos', 'nutri_proteinas', 'nutri_gorduras_totais', 'nutri_gorduras_saturadas', 'nutri_gorduras_trans', 'nutri_fibra', 'nutri_sodio'] as $field) {
            $data[$field] = $this->parseBrDecimal($data[$field] ?? 0, 1);
        }

        $data['nutri_porcao_qtd'] = max(0, min(999, (int) ($data['nutri_porcao_qtd'] ?? 0)));
        $data['nutri_medida_inteiro'] = max(0, min(99, (int) ($data['nutri_medida_inteiro'] ?? 0)));
        $data['nutri_porcao_unidade'] = in_array(($data['nutri_porcao_unidade'] ?? '0'), ['0', '1', '2'], true)
            ? (string) $data['nutri_porcao_unidade']
            : '0';
        $data['nutri_medida_fracao'] = in_array(($data['nutri_medida_fracao'] ?? '0'), ['0', '1', '2', '3', '4', '5'], true)
            ? (string) $data['nutri_medida_fracao']
            : '0';
        $medidaTipo = preg_replace('/\D/', '', (string) ($data['nutri_medida_tipo'] ?? '00')) ?? '00';
        $data['nutri_medida_tipo'] = str_pad(substr($medidaTipo, 0, 2), 2, '0', STR_PAD_LEFT);

        foreach (['mva_normal', 'icms_diferido', 'aliq_deson', 'aliq_ibs_uf', 'aliq_cbs', 'aliq_ibs_mun', 'aliq_adrem_ibs', 'aliq_adrem_cbs', 'reducao_cbs', 'reducao_ibs'] as $field) {
            $data[$field] = $this->parseBrDecimal($data[$field] ?? 0, 4);
        }

        foreach (['valor_pequena', 'valor_media', 'valor_grande'] as $field) {
            $data[$field] = $this->parseBrDecimal($data[$field] ?? 0, 4);
        }

        $data['menu_id'] = filled($data['menu_id'] ?? null) ? (int) $data['menu_id'] : null;
        $data['qtd_sabores'] = (int) ($data['qtd_sabores'] ?? 0);
        $data['tempo_espera'] = (int) ($data['tempo_espera'] ?? 0);

        $data['peso_liq'] = $this->parseBrDecimal($data['peso_liq'] ?? 0, 3);
        $data['e_medio'] = $this->parseBrDecimal($data['e_medio'] ?? 0, 3);
        $data['origem'] = (int) ($data['origem'] ?? 0);
        $data['motivo_desoneracao'] = filled($data['motivo_desoneracao'] ?? null)
            ? (int) $data['motivo_desoneracao']
            : null;
        $data['principio_ativo_id'] = filled($data['principio_ativo_id'] ?? null)
            ? (int) $data['principio_ativo_id']
            : null;

        $data['qtd_atacado'] = $this->parseBrDecimal($data['qtd_atacado'] ?? 0, 0);
        $data['estoque'] = $this->parseBrDecimal($data['estoque'] ?? 0, 3);
        $data['estoque_minimo'] = $this->parseBrDecimal($data['estoque_minimo'] ?? 0, 0);
        $data['peso_kg'] = $this->parseBrDecimal($data['peso_kg'] ?? 0, 3);

        return ProductLocalizacao::collapseFromForm($data);
    }

    protected function parseBrDecimal(mixed $value, int $decimals = 2): float
    {
        return BrDecimal::parse($value, $decimals);
    }

    protected function formatBrDecimal(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return $decimals > 0 ? '0,' . str_repeat('0', $decimals) : '0';
        }

        $number = is_numeric($value) && ! is_string($value)
            ? (float) $value
            : $this->parseBrDecimal($value, $decimals);

        return number_format($number, $decimals, ',', '.');
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
        if ($this->embedsInParentOverlay()) {
            $this->closeEmbedOverlay();

            return;
        }

        $this->redirectToErpFormReturnOr(
            $this->getProductListRedirectUrl(),
            'Produtos',
        );
    }

    protected function closePdvEmbedOverlay(): void
    {
        $this->closeEmbedOverlay();
    }

    protected function getProductListRedirectUrl(): string
    {
        return ProductResource::getUrl('index');
    }

    protected function getRedirectUrl(): string
    {
        if ($this->embedsInPdv) {
            return ProductResource::getUrl('create') . '?pdv=1';
        }

        if ($this->embedsInOrcamento) {
            return ProductResource::getUrl('create') . '?orcamento=1';
        }

        if ($this->embedsInNotaFornecedor) {
            return ProductResource::getUrl('create') . '?nota_fornecedor=1';
        }

        return $this->erpFormReturnRedirectUrl($this->getProductListRedirectUrl());
    }

    protected function flashOrcamentoReturnContextAfterProductSave(): void
    {
        $returnUrl = $this->resolveErpFormReturnUrl();

        if (! ErpFormReturnUrl::isOrcamentoUrl($returnUrl)) {
            return;
        }

        $codigo = $this->record?->codigo ?? null;

        if (filled($codigo)) {
            session([ErpFormReturnUrl::SESSION_NEW_PRODUTO_CODIGO => (string) $codigo]);
        }
    }

    public function isEditingProduct(): bool
    {
        return $this instanceof EditRecord && $this->record?->exists;
    }

    protected function formatProductValidationMessage(\Illuminate\Validation\ValidationException $exception): string
    {
        $labels = [
            'descricao' => 'Descrição',
            'preco_venda' => 'Preço Varejo',
            'preco_atacado' => 'Preço Atacado',
            'preco_especial' => 'Preço Especial',
            'unidade' => 'Unidade',
            'cst_icms' => 'CST ICMS',
            'cest' => 'CEST',
            'codigo_barras' => 'Código de Barras',
            'referencia' => 'Referência',
            'validade' => 'Validade',
            'lote' => 'Lote',
            'motivo_desoneracao' => 'Motivo Desoneração',
            'menu_id' => 'Menu',
            'principio_ativo_id' => 'Princípio Ativo',
        ];

        $messages = [];

        foreach ($exception->errors() as $field => $fieldMessages) {
            $label = $labels[$field] ?? str_replace('_', ' ', $field);
            $text = $fieldMessages[0] ?? '';

            if ($text === 'validation.numeric' || str_contains($text, 'validation.numeric')) {
                $messages[] = "O campo {$label} deve ser numérico.";
            } elseif (str_starts_with($text, 'validation.')) {
                $messages[] = "O campo {$label} é inválido.";
            } else {
                $messages[] = $text;
            }
        }

        return $messages[0] ?? 'Verifique os campos obrigatórios.';
    }

    protected function currentEmpresa(): ?\App\Models\Empresa
    {
        $empresaId = session('erp_empresa_id', auth()->user()?->empresa_id);

        return $empresaId ? \App\Models\Empresa::query()->find($empresaId) : null;
    }

    /**
     * Empresas que o usuário logado pode usar no seletor do cadastro de produto.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Empresa>
     */
    public function productFormEmpresas()
    {
        $user = auth()->user();
        $query = \App\Models\Empresa::query()
            ->where('ativo', true)
            ->orderBy('nome');

        if ($user instanceof \App\Models\User && ! $user->is_admin) {
            $ids = $user->accessibleEmpresaIds();
            $query->whereIn('id', $ids !== [] ? $ids : [0]);
        }

        return $query->get(['id', 'nome', 'codigo']);
    }

    public function switchProductFormEmpresa(int|string|null $empresaId): void
    {
        $empresaId = (int) $empresaId;
        $user = auth()->user();
        $fromEmpresaId = $this->currentProductEmpresaId();

        if ($empresaId <= 0) {
            return;
        }

        if ($user instanceof \App\Models\User && ! $user->canAccessEmpresa($empresaId)) {
            \Filament\Notifications\Notification::make()
                ->title('Você não tem acesso a esta empresa.')
                ->danger()
                ->send();

            return;
        }

        if (! \App\Models\Empresa::query()->whereKey($empresaId)->where('ativo', true)->exists()) {
            return;
        }

        if ($fromEmpresaId === $empresaId) {
            return;
        }

        $this->switchProductFormEmpresaPrecos($fromEmpresaId, $empresaId);
        $this->productFormEmpresaId = $empresaId;

        if (! $this->isEditingProduct()) {
            $this->applyEmpresaImpostoPadraoToProductForm(notify: false);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultProductFormData(?\App\Models\Empresa $empresa = null): array
    {
        return [
            'codigo' => Product::nextCodigo(),
            'tipo_produto' => '00',
            'grupo' => 'DIVERSOS',
            'unidade' => 'UN',
            'ncm' => '00000000',
            'ncm_descricao' => 'PRODUTO NAO ESPECIFICADO NA LISTA DE NCM',
            'preco_compra' => 0,
            'pct_custos' => 0,
            'preco_custo' => 0,
            'pct_lucro' => 0,
            'preco_venda' => 0,
            'preco_venda_anterior' => 0,
            'preco_custo_anterior' => 0,
            'e_medio' => 0,
            'ult_compra' => 0,
            'ult_compra_anterior' => 0,
            'qtd_atacado' => 0,
            'preco_atacado' => 0,
            'preco_especial' => 0,
            'localizacao' => null,
            'loc_corredor' => '',
            'loc_modulo' => '',
            'loc_prateleira' => '',
            'loc_gaveta' => '',
            'loc_legado' => null,
            'validade' => null,
            'lote' => null,
            'controla_lote_validade' => false,
            'estoque' => 0,
            'estoque_minimo' => 1,
            'peso_kg' => 0,
            'ativo' => true,
            'is_fiscal' => true,
            'mostrar_no_app' => true,
            'is_restaurante' => false,
            'tipo_restaurante' => null,
            'menu_id' => null,
            'tipo_alimento' => null,
            'qtd_sabores' => 0,
            'valor_pequena' => 0,
            'valor_media' => 0,
            'valor_grande' => 0,
            'complemento' => null,
            'tempo_espera' => 0,
            'is_remedio' => false,
            'principio_ativo_id' => null,
            'aplicacao' => null,
            'produto_pesado' => false,
            'tem_info_nutricional' => false,
            'nutri_porcao_qtd' => 0,
            'nutri_porcao_unidade' => '0',
            'nutri_medida_inteiro' => 0,
            'nutri_medida_fracao' => '0',
            'nutri_medida_tipo' => '00',
            'nutri_valor_energetico' => 0,
            'nutri_carboidratos' => 0,
            'nutri_proteinas' => 0,
            'nutri_gorduras_totais' => 0,
            'nutri_gorduras_saturadas' => 0,
            'nutri_gorduras_trans' => 0,
            'nutri_fibra' => 0,
            'nutri_sodio' => 0,
            'paga_comissao' => false,
            'preco_variavel' => false,
            'is_composicao' => false,
            'is_servico' => false,
            'is_grade' => false,
            'usa_tab_preco' => false,
            'is_combustivel' => false,
            'usa_imei' => false,
            'contr_est_grade' => false,
            'foto_path' => null,
            'promo_data_inicio' => null,
            'promo_data_fim' => null,
            'promo_preco_venda' => 0,
            'promo_preco_atacado' => 0,
            ...ProductFormValidator::fiscalDefaultsFromEmpresa($empresa),
        ];
    }
}
