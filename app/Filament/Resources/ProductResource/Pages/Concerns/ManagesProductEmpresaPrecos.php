<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Product;
use App\Support\Erp\ProductEmpresaPrecoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

trait ManagesProductEmpresaPrecos
{
    /** @var array<int, array<string, float>> rascunho de preços por empresa (antes de gravar) */
    public array $productEmpresaPrecosDraft = [];

    /** @var list<int> */
    public array $productReplicaPrecosEmpresaIds = [];

    public bool $productReplicaPrecosOpen = false;

    /** @var list<int|string> */
    public array $productReplicaPrecosSelecionadas = [];

    /** @var array<int, array{id: int, nome: string, codigo: string|null}> */
    public array $productReplicaPrecosOpcoes = [];

    /**
     * Empresa do contexto de preços deste formulário (Precificação / overlay).
     * Independente de session('erp_empresa_id') — não troca a empresa ativa do ERP.
     */
    public int $productFormEmpresaId = 0;

    protected function productEmpresaPrecoService(): ProductEmpresaPrecoService
    {
        return app(ProductEmpresaPrecoService::class);
    }

    protected function ensureProductFormEmpresaId(): void
    {
        if ($this->productFormEmpresaId > 0) {
            return;
        }

        $this->productFormEmpresaId = (int) (session('erp_empresa_id') ?? auth()->user()?->empresa_id ?? 0);
    }

    protected function currentProductEmpresaId(): int
    {
        $this->ensureProductFormEmpresaId();

        return $this->productFormEmpresaId;
    }

    /**
     * Carrega preços da empresa atual no formulário (edit).
     */
    protected function applyEmpresaPrecosToFormData(?Product $product = null): void
    {
        $product ??= $this->record ?? null;
        $empresaId = $this->currentProductEmpresaId();

        if (! $product instanceof Product || $empresaId <= 0 || ! is_array($this->data)) {
            return;
        }

        $service = $this->productEmpresaPrecoService();
        $service->ensureForEmpresa($product, $empresaId);

        $prices = $this->productEmpresaPrecosDraft[$empresaId]
            ?? $service->resolve($product, $empresaId);

        $this->productEmpresaPrecosDraft[$empresaId] = $prices;

        foreach (ProductEmpresaPrecoService::FIELDS as $field) {
            $this->data[$field] = $this->formatBrDecimal($prices[$field] ?? 0, 2);
        }
    }

    protected function stashCurrentEmpresaPrecosDraft(): void
    {
        $empresaId = $this->currentProductEmpresaId();

        if ($empresaId <= 0 || ! is_array($this->data)) {
            return;
        }

        $this->productEmpresaPrecosDraft[$empresaId] = $this->productEmpresaPrecoService()
            ->extractFromFormData($this->data);
    }

    /**
     * Após gravar o produto: persiste preços da empresa atual + réplicas pendentes.
     */
    protected function syncProductEmpresaPrecos(Product $product): void
    {
        $service = $this->productEmpresaPrecoService();
        $empresaId = $this->currentProductEmpresaId();
        $prices = $service->extractFromFormData(is_array($this->data) ? $this->data : []);

        if ($empresaId > 0) {
            $this->productEmpresaPrecosDraft[$empresaId] = $prices;
            $service->upsert($product, $empresaId, $prices);
        }

        // Persiste rascunhos de outras empresas (troca de seletor sem gravar antes).
        foreach ($this->productEmpresaPrecosDraft as $draftEmpresaId => $draftPrices) {
            $draftEmpresaId = (int) $draftEmpresaId;

            if ($draftEmpresaId <= 0 || $draftEmpresaId === $empresaId) {
                continue;
            }

            $service->upsert($product, $draftEmpresaId, $draftPrices);
        }

        $replicarIds = array_values(array_filter(
            array_map('intval', $this->productReplicaPrecosEmpresaIds),
            fn (int $id): bool => $id > 0 && $id !== $empresaId
        ));

        if ($replicarIds !== []) {
            $count = $service->replicate($product, $prices, $replicarIds);

            foreach ($replicarIds as $id) {
                $this->productEmpresaPrecosDraft[$id] = $prices;
            }

            $this->productReplicaPrecosEmpresaIds = [];

            if ($count > 0) {
                Notification::make()
                    ->title('Preços replicados.')
                    ->body($count === 1
                        ? 'Valores aplicados em 1 empresa.'
                        : "Valores aplicados em {$count} empresas.")
                    ->success()
                    ->send();
            }
        }
    }

    /**
     * Chamado após aplicar precificação no formulário.
     */
    protected function handlePrecificacaoReplicaAposAplicar(): void
    {
        $empresaId = $this->currentProductEmpresaId();
        $outras = $this->productEmpresaPrecoService()
            ->empresasParaReplicacao($empresaId, auth()->user());

        if ($outras->isEmpty()) {
            return;
        }

        $this->stashCurrentEmpresaPrecosDraft();

        $empresa = $empresaId > 0 ? Empresa::query()->find($empresaId) : null;
        $perguntar = (bool) ($empresa?->param_geral_perguntar_replicar_preco_filiais ?? false);

        if ($perguntar) {
            $this->productReplicaPrecosOpcoes = $outras
                ->map(fn (Empresa $e): array => [
                    'id' => (int) $e->id,
                    'nome' => (string) $e->nome,
                    'codigo' => $e->codigo !== null ? (string) $e->codigo : null,
                ])
                ->values()
                ->all();
            $this->productReplicaPrecosSelecionadas = [];
            $this->productReplicaPrecosOpen = true;

            return;
        }

        // Sem parâmetro: replica em todas as outras empresas (na gravação / imediato se já existir).
        $this->productReplicaPrecosEmpresaIds = $outras->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($this instanceof EditRecord && $this->record instanceof Product) {
            $this->syncProductEmpresaPrecos($this->record);
        } else {
            Notification::make()
                ->title('Replicação agendada.')
                ->body('Ao gravar o produto, os preços serão aplicados nas demais empresas.')
                ->info()
                ->send();
        }
    }

    public function confirmarReplicaPrecosEmpresas(): void
    {
        $ids = array_values(array_unique(array_map(
            'intval',
            $this->productReplicaPrecosSelecionadas
        )));
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id > 0));

        $this->productReplicaPrecosEmpresaIds = $ids;
        $this->productReplicaPrecosOpen = false;
        $this->productReplicaPrecosSelecionadas = [];
        $this->productReplicaPrecosOpcoes = [];

        if ($ids === []) {
            Notification::make()
                ->title('Nenhuma empresa selecionada.')
                ->body('Os preços ficam só na empresa atual.')
                ->warning()
                ->send();

            return;
        }

        if ($this instanceof EditRecord && $this->record instanceof Product) {
            $this->syncProductEmpresaPrecos($this->record);
        } else {
            Notification::make()
                ->title('Replicação agendada.')
                ->body('Ao gravar o produto, os preços serão aplicados nas empresas marcadas.')
                ->info()
                ->send();
        }
    }

    public function cancelarReplicaPrecosEmpresas(): void
    {
        $this->productReplicaPrecosOpen = false;
        $this->productReplicaPrecosSelecionadas = [];
        $this->productReplicaPrecosOpcoes = [];
        $this->productReplicaPrecosEmpresaIds = [];
    }

    public function toggleTodasReplicaPrecosEmpresas(bool $marcar): void
    {
        if ($marcar) {
            $this->productReplicaPrecosSelecionadas = collect($this->productReplicaPrecosOpcoes)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return;
        }

        $this->productReplicaPrecosSelecionadas = [];
    }

    /**
     * Troca empresa no formulário: guarda preços da atual e carrega da nova.
     */
    protected function switchProductFormEmpresaPrecos(int $fromEmpresaId, int $toEmpresaId): void
    {
        if ($fromEmpresaId > 0 && is_array($this->data)) {
            $this->productEmpresaPrecosDraft[$fromEmpresaId] = $this->productEmpresaPrecoService()
                ->extractFromFormData($this->data);
        }

        if ($toEmpresaId <= 0 || ! is_array($this->data)) {
            return;
        }

        $product = $this->record ?? null;
        $service = $this->productEmpresaPrecoService();

        if (isset($this->productEmpresaPrecosDraft[$toEmpresaId])) {
            $prices = $this->productEmpresaPrecosDraft[$toEmpresaId];
        } elseif ($product instanceof Product) {
            $service->ensureForEmpresa($product, $toEmpresaId);
            $prices = $service->resolve($product, $toEmpresaId);
            $this->productEmpresaPrecosDraft[$toEmpresaId] = $prices;
        } else {
            return;
        }

        foreach (ProductEmpresaPrecoService::FIELDS as $field) {
            $this->data[$field] = $this->formatBrDecimal($prices[$field] ?? 0, 2);
        }

        $this->form->fill($this->data);
        $this->dispatch('erp-masks-refresh');
    }
}
