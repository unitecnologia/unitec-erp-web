<?php

namespace App\Filament\Resources\AjustaPrecoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\AjustaPrecoResource;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\Marca;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductEmpresaPreco;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ProductEmpresaPrecoService;
use App\Support\Erp\ProductPriceCalculator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListAjustaPrecos extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = AjustaPrecoResource::class;

    protected static ?string $title = '';

    public const SELECTION_LIMIT = 500;

    /** @var list<string> */
    public array $selecionados = [];

    /** Gate da grade: só carrega produtos após F5 Consulta (tela abre limpa). */
    public bool $pesquisado = false;

    #[Url(as: 'desc')]
    public string $descricaoFilter = '';

    #[Url(as: 'desc_op')]
    public string $descricaoOp = 'contem';

    #[Url(as: 'barra')]
    public string $codigoBarrasFilter = '';

    #[Url(as: 'marca')]
    public string $marcaFilter = 'todos';

    #[Url(as: 'grupo')]
    public string $grupoFilter = 'todos';

    #[Url(as: 'forn')]
    public string $fornecedorFilter = 'todos';

    #[Url(as: 'ncm')]
    public string $ncmFilter = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'ativo';

    public string $applyPrecoCompra = '0,00';

    public string $applyPrecoCusto = '0,00';

    public string $applyPctLucro = '0,00';

    public string $applyPrecoVenda = '0,00';

    public string $applyPrecoAtacado = '0,00';

    public string $applyPrecoEspecial = '0,00';

    public string $applyOrigem = '0';

    public string $applyCsosn = '000';

    public string $applyAliqIcms = '0,00';

    public string $applyCst = '000';

    /** @var list<array<string, mixed>> */
    public array $precosPainel = [];

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Ajuste de Preço em Lote');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-ajusta-precos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um produto';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-ajusta-precos__input--descricao',
            'create' => null,
            'edit' => null,
            'delete' => null,
            'refresh' => null,
            'extraKeys' => [
                'F5' => ['method' => 'pesquisar'],
                'F2' => ['method' => 'marcarOuInverterTodos'],
            ],
        ];
    }

    #[Computed]
    public function gruposOptions(): array
    {
        return Grupo::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'nome')->all();
    }

    #[Computed]
    public function marcasOptions(): array
    {
        return Marca::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'nome')->all();
    }

    #[Computed]
    public function fornecedoresOptions(): array
    {
        return Person::query()
            ->where('is_fornecedor', true)
            ->where('ativo', true)
            ->orderBy('nome_razao')
            ->limit(800)
            ->pluck('nome_razao', 'id')
            ->all();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(
            AjustaPrecoResource::table($table)
                ->recordUrl(null)
                ->recordAction(null)
                ->emptyStateHeading('Não há dados para mostrar')
                ->emptyStateDescription(
                    $this->pesquisado
                        ? 'Nenhum produto corresponde aos filtros.'
                        : 'Pressione F5 Consulta para carregar os produtos.'
                )
        );
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with(['ultFornecedor:id,nome_razao']);

        if (! $this->pesquisado) {
            return $query->whereRaw('0 = 1');
        }

        if (filled($this->descricaoFilter)) {
            $term = mb_strtoupper(trim($this->descricaoFilter), 'UTF-8');

            match ($this->descricaoOp) {
                'igual' => $query->whereRaw('UPPER(descricao) = ?', [$term]),
                'inicia' => $query->whereRaw('UPPER(descricao) LIKE ?', [$term.'%']),
                default => $query->whereRaw('UPPER(descricao) LIKE ?', ['%'.$term.'%']),
            };
        }

        if (filled($this->codigoBarrasFilter)) {
            $barra = trim($this->codigoBarrasFilter);
            $query->where(function (Builder $q) use ($barra): void {
                $q->where('codigo_barras', 'like', '%'.$barra.'%')
                    ->orWhere('codigo_barras_caixa', 'like', '%'.$barra.'%')
                    ->orWhere('codigo', 'like', '%'.$barra.'%')
                    ->orWhere('referencia', 'like', '%'.$barra.'%');
            });
        }

        if ($this->marcaFilter !== 'todos') {
            $query->where('marca', $this->marcaFilter);
        }

        if ($this->grupoFilter !== 'todos') {
            $query->where('grupo', $this->grupoFilter);
        }

        if ($this->fornecedorFilter !== 'todos' && ctype_digit((string) $this->fornecedorFilter)) {
            $query->where('ult_fornecedor_id', (int) $this->fornecedorFilter);
        }

        if (filled($this->ncmFilter)) {
            $ncm = preg_replace('/\D+/', '', $this->ncmFilter) ?: trim($this->ncmFilter);
            $query->where('ncm', 'like', $ncm.'%');
        }

        match ($this->statusFilter) {
            'inativo' => $query->where('ativo', false),
            'todos' => null,
            default => $query->where('ativo', true),
        };

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.ajusta-precos.titlebar'),
                View::make('filament.components.erp.ajusta-precos.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.ajusta-precos.prices-panel'),
                View::make('filament.components.erp.ajusta-precos.action-bar'),
            ]);
    }

    public function handleAjustaPrecosEscape(): void
    {
        $this->closeScreen();
    }

    public function highlightRecord(int|string $recordId): void
    {
        parent::highlightRecord($recordId);
        $this->carregarPrecosPainel((int) $recordId);
    }

    public function pesquisar(): void
    {
        $this->pesquisado = true;
        $this->selecionados = [];
        $this->clearListSelection();
        $this->precosPainel = [];
        $this->resetTable();
    }

    public function limparFiltros(): void
    {
        $this->descricaoFilter = '';
        $this->descricaoOp = 'contem';
        $this->codigoBarrasFilter = '';
        $this->marcaFilter = 'todos';
        $this->grupoFilter = 'todos';
        $this->fornecedorFilter = 'todos';
        $this->ncmFilter = '';
        $this->statusFilter = 'ativo';
        $this->pesquisado = false;
        $this->selecionados = [];
        $this->clearListSelection();
        $this->precosPainel = [];
        $this->resetApplyDefaults();
        $this->resetTable();
    }

    protected function resetApplyDefaults(): void
    {
        $this->applyPrecoCompra = '0,00';
        $this->applyPrecoCusto = '0,00';
        $this->applyPctLucro = '0,00';
        $this->applyPrecoVenda = '0,00';
        $this->applyPrecoAtacado = '0,00';
        $this->applyPrecoEspecial = '0,00';
        $this->applyOrigem = '0';
        $this->applyCsosn = '000';
        $this->applyCst = '000';
        $this->applyAliqIcms = '0,00';
    }

    public function marcarOuInverterTodos(): void
    {
        $ids = $this->getTableQuery()
            ->orderBy('codigo')
            ->limit(self::SELECTION_LIMIT)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if ($ids === []) {
            Notification::make()->title('Nenhum produto na consulta.')->warning()->send();

            return;
        }

        $allSelected = count(array_diff($ids, $this->selecionados)) === 0
            && count($this->selecionados) >= count($ids);

        if ($allSelected) {
            $this->selecionados = array_values(array_diff($this->selecionados, $ids));
            Notification::make()->title('Seleção desmarcada.')->success()->send();

            return;
        }

        $this->selecionados = array_values(array_unique([...$this->selecionados, ...$ids]));

        Notification::make()
            ->title(count($ids).' produto(s) marcado(s).')
            ->success()
            ->send();
    }

    public function toggleSelecionado(int $id): void
    {
        $key = (string) $id;

        if (in_array($key, $this->selecionados, true)) {
            $this->selecionados = array_values(array_filter(
                $this->selecionados,
                fn (string $value): bool => $value !== $key,
            ));

            return;
        }

        if (count($this->selecionados) >= self::SELECTION_LIMIT) {
            Notification::make()
                ->title('Limite de '.self::SELECTION_LIMIT.' produtos selecionados.')
                ->warning()
                ->send();

            return;
        }

        $this->selecionados[] = $key;
    }

    public function aplicarCampoSelecionados(string $campo): void
    {
        if (! ErpAccess::currentCan('ajusta_preco.update')) {
            Notification::make()->title('Sem permissão para alterar preços.')->danger()->send();

            return;
        }

        $map = [
            'preco_compra' => $this->applyPrecoCompra,
            'preco_custo' => $this->applyPrecoCusto,
            'pct_lucro' => $this->applyPctLucro,
            'preco_venda' => $this->applyPrecoVenda,
            'preco_atacado' => $this->applyPrecoAtacado,
            'preco_especial' => $this->applyPrecoEspecial,
            'origem' => $this->applyOrigem,
            'csosn' => $this->applyCsosn,
            'aliq_icms' => $this->applyAliqIcms,
            'cst_icms' => $this->applyCst,
        ];

        if (! array_key_exists($campo, $map)) {
            Notification::make()->title('Campo inválido.')->warning()->send();

            return;
        }

        $raw = trim((string) $map[$campo]);

        if ($raw === '') {
            Notification::make()->title('Informe um valor antes de aplicar.')->warning()->send();

            return;
        }

        if ($this->selecionados === []) {
            Notification::make()->title('Marque ao menos um produto (F2).')->warning()->send();

            return;
        }

        $ids = array_map('intval', $this->selecionados);
        $empresaId = (int) (session('erp_empresa_id') ?? 0);
        $service = app(ProductEmpresaPrecoService::class);
        $updated = 0;

        DB::transaction(function () use ($ids, $campo, $raw, $empresaId, $service, &$updated): void {
            Product::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->chunkById(100, function ($products) use ($campo, $raw, $empresaId, $service, &$updated): void {
                    foreach ($products as $product) {
                        $this->aplicarValorNoProduto($product, $campo, $raw, $empresaId, $service);
                        $updated++;
                    }
                });
        });

        if ($this->highlightedRecordId) {
            $this->carregarPrecosPainel((int) $this->highlightedRecordId);
        }

        $this->resetTable();

        Notification::make()
            ->title("Aplicado em {$updated} produto(s).")
            ->success()
            ->send();
    }

    public function salvarPrecoPainel(int $index): void
    {
        if (! ErpAccess::currentCan('ajusta_preco.update')) {
            Notification::make()->title('Sem permissão para alterar preços.')->danger()->send();

            return;
        }

        $row = $this->precosPainel[$index] ?? null;

        if (! is_array($row) || empty($row['product_id'])) {
            return;
        }

        $product = Product::query()->find((int) $row['product_id']);

        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->danger()->send();

            return;
        }

        $prices = [
            'preco_compra' => BrDecimal::parse($row['preco_compra'] ?? $product->preco_compra ?? 0, 2),
            'pct_custos' => BrDecimal::parse($row['pct_custos'] ?? $product->pct_custos ?? 0, 2),
            'preco_custo' => BrDecimal::parse($row['preco_custo'] ?? $product->preco_custo ?? 0, 2),
            'pct_lucro' => BrDecimal::parse($row['pct_lucro'] ?? 0, 2),
            'preco_venda' => BrDecimal::parse($row['preco_venda'] ?? 0, 2),
            'preco_atacado' => BrDecimal::parse($row['preco_atacado'] ?? 0, 2),
            'preco_especial' => BrDecimal::parse($row['preco_especial'] ?? 0, 2),
        ];

        // Preço varejo é a referência; a margem é recalculada a partir dele.
        $prices = ProductPriceCalculator::recalculateFromVenda($prices);

        $empresaId = (int) ($row['empresa_id'] ?? 0);
        $service = app(ProductEmpresaPrecoService::class);

        if ($empresaId > 0) {
            $service->upsert($product, $empresaId, $prices);
        }

        $currentEmpresa = (int) (session('erp_empresa_id') ?? 0);
        $syncCadastro = $empresaId === 0 || $empresaId === $currentEmpresa;

        $product->fill([
            'origem' => filled($row['origem'] ?? null) ? (int) $row['origem'] : $product->origem,
            'csosn' => filled($row['csosn'] ?? null) ? str_pad((string) $row['csosn'], 3, '0', STR_PAD_LEFT) : $product->csosn,
            'cst_icms' => filled($row['cst_icms'] ?? null) ? str_pad((string) $row['cst_icms'], 3, '0', STR_PAD_LEFT) : $product->cst_icms,
            'aliq_icms' => filled($row['aliq_icms'] ?? null) ? BrDecimal::parse($row['aliq_icms'], 2) : $product->aliq_icms,
        ]);

        if ($syncCadastro) {
            $product->fill([
                'preco_compra' => $prices['preco_compra'],
                'pct_custos' => $prices['pct_custos'],
                'preco_custo' => $prices['preco_custo'],
                'pct_lucro' => $prices['pct_lucro'],
                'preco_venda' => $prices['preco_venda'],
                'preco_atacado' => $prices['preco_atacado'],
                'preco_especial' => $prices['preco_especial'],
            ]);

            if ($currentEmpresa > 0) {
                $service->upsert($product, $currentEmpresa, $prices);
            }
        }

        $product->save();

        $this->carregarPrecosPainel((int) $product->id);
        $this->resetTable();

        Notification::make()->title('Preços salvos.')->success()->send();
    }

    protected function carregarPrecosPainel(int $productId): void
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            $this->precosPainel = [];

            return;
        }

        $service = app(ProductEmpresaPrecoService::class);
        $empresas = $service->empresasParaPainelPrecos(auth()->user());

        $existing = ProductEmpresaPreco::query()
            ->where('product_id', $productId)
            ->get()
            ->keyBy(fn (ProductEmpresaPreco $row): int => (int) $row->empresa_id);

        $defaultPrices = $service->extractFromProduct($product);
        $painel = [];

        foreach ($empresas as $empresa) {
            $empresaId = (int) $empresa->id;
            $row = $existing->get($empresaId);

            if ($row instanceof ProductEmpresaPreco) {
                $prices = [];
                foreach (ProductEmpresaPrecoService::FIELDS as $field) {
                    $prices[$field] = round((float) ($row->{$field} ?? 0), 2);
                }
            } else {
                $prices = $defaultPrices;
            }

            $painel[] = $this->montarLinhaPainel(
                $product,
                $empresaId,
                (string) $empresa->nome,
                $empresa->codigo,
                $prices,
            );
        }

        // Fallback: sessão sem empresas cadastradas / acesso — ainda mostra a atual.
        if ($painel === []) {
            $currentEmpresaId = (int) (session('erp_empresa_id') ?? 0);
            $empresa = $currentEmpresaId > 0
                ? Empresa::query()->find($currentEmpresaId, ['id', 'codigo', 'nome'])
                : null;

            $painel[] = $this->montarLinhaPainel(
                $product,
                $currentEmpresaId,
                $empresa?->nome ?: 'Empresa atual',
                $empresa?->codigo,
                $defaultPrices,
            );
        }

        $this->precosPainel = $painel;
    }

    /**
     * @param  array<string, float>  $prices
     * @return array<string, mixed>
     */
    protected function montarLinhaPainel(
        Product $product,
        int $empresaId,
        string $empresaNome,
        mixed $empresaCodigo,
        array $prices,
    ): array {
        $custo = (float) ($prices['preco_custo'] ?? 0);
        $pctAtacado = $custo > 0
            ? round((((float) ($prices['preco_atacado'] ?? 0) * 100) / $custo) - 100, 2)
            : 0.0;
        $pctEspecial = $custo > 0
            ? round((((float) ($prices['preco_especial'] ?? 0) * 100) / $custo) - 100, 2)
            : 0.0;

        return [
            'product_id' => (int) $product->id,
            'empresa_id' => $empresaId,
            'empresa' => trim(($empresaCodigo ? $empresaCodigo.' — ' : '').$empresaNome),
            'pct_lucro' => number_format((float) ($prices['pct_lucro'] ?? 0), 2, '.', ''),
            'preco_venda' => number_format((float) ($prices['preco_venda'] ?? 0), 2, '.', ''),
            'pct_lucro_atacado' => number_format(max(0, $pctAtacado), 2, '.', ''),
            'preco_atacado' => number_format((float) ($prices['preco_atacado'] ?? 0), 2, '.', ''),
            'pct_lucro_especial' => number_format(max(0, $pctEspecial), 2, '.', ''),
            'preco_especial' => number_format((float) ($prices['preco_especial'] ?? 0), 2, '.', ''),
            'preco_compra' => number_format((float) ($prices['preco_compra'] ?? 0), 2, '.', ''),
            'pct_custos' => number_format((float) ($prices['pct_custos'] ?? 0), 2, '.', ''),
            'preco_custo' => number_format((float) ($prices['preco_custo'] ?? 0), 2, '.', ''),
            'origem' => (string) ($product->origem ?? ''),
            'csosn' => (string) ($product->csosn ?? ''),
            'cst_icms' => (string) ($product->cst_icms ?? ''),
            'aliq_icms' => number_format((float) ($product->aliq_icms ?? 0), 2, '.', ''),
        ];
    }

    protected function aplicarValorNoProduto(
        Product $product,
        string $campo,
        string $raw,
        int $empresaId,
        ProductEmpresaPrecoService $service,
    ): void {
        $data = [
            'preco_compra' => (float) $product->preco_compra,
            'pct_custos' => (float) $product->pct_custos,
            'preco_custo' => (float) $product->preco_custo,
            'pct_lucro' => (float) $product->pct_lucro,
            'preco_venda' => (float) $product->preco_venda,
            'preco_atacado' => (float) $product->preco_atacado,
            'preco_especial' => (float) $product->preco_especial,
        ];

        match ($campo) {
            'preco_compra' => $data = ProductPriceCalculator::recalculateFromCompra([
                ...$data,
                'preco_compra' => BrDecimal::parse($raw, 2),
            ]),
            'preco_custo' => $data = ProductPriceCalculator::recalculateFromCusto([
                ...$data,
                'preco_custo' => BrDecimal::parse($raw, 2),
            ]),
            'pct_lucro' => $data = ProductPriceCalculator::recalculateFromMargem([
                ...$data,
                'pct_lucro' => BrDecimal::parse($raw, 2),
            ]),
            'preco_venda' => $data = ProductPriceCalculator::recalculateFromVenda([
                ...$data,
                'preco_venda' => BrDecimal::parse($raw, 2),
            ]),
            'preco_atacado' => $data['preco_atacado'] = BrDecimal::parse($raw, 2),
            'preco_especial' => $data['preco_especial'] = BrDecimal::parse($raw, 2),
            'origem' => $product->origem = (int) preg_replace('/\D+/', '', $raw),
            'csosn' => $product->csosn = str_pad(preg_replace('/\D+/', '', $raw) ?: '0', 3, '0', STR_PAD_LEFT),
            'cst_icms' => $product->cst_icms = str_pad(preg_replace('/\D+/', '', $raw) ?: '0', 3, '0', STR_PAD_LEFT),
            'aliq_icms' => $product->aliq_icms = BrDecimal::parse($raw, 2),
            default => null,
        };

        if (in_array($campo, ['preco_compra', 'preco_custo', 'pct_lucro', 'preco_venda', 'preco_atacado', 'preco_especial'], true)) {
            $product->fill($data);
        }

        $product->save();

        if ($empresaId > 0 && in_array($campo, ProductEmpresaPrecoService::FIELDS, true)) {
            $service->upsert($product, $empresaId, $service->extractFromProduct($product));
        }
    }
}
