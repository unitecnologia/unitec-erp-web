<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CompraResource;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\Compras\AnaliseComprasService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnaliseComprasPage extends Page
{
    private const TIMEZONE = 'America/Sao_Paulo';

    protected static ?string $slug = 'analise-compras';

    protected static ?string $routePath = 'analise-compras';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.analise-compras';

    public string $dataIni = '';

    public string $dataFim = '';

    public string $suprirAte = '';

    public int $aprovisionamentoDias = 0;

    public string $produto = '';

    public ?int $produtoId = null;

    /** @var list<array{id:int,codigo:string,nome:string,estoque:string,barras:string}> */
    public array $produtoSugestoes = [];

    public bool $produtoSugestoesOpen = false;

    public int $selectedProdutoSugestaoIndex = 0;

    public string $grupo = '';

    public string $marca = '';

    public ?int $fornecedorId = null;

    public string $fornecedorBusca = '';

    public string $fornecedorNome = '';

    /** @var list<array{id:int,nome:string,cnpj:string}> */
    public array $fornecedorResultados = [];

    public int $fornecedorSelecionadoIndex = 0;

    public bool $soEstoqueMinimo = false;

    public bool $ocultarSugestaoZerada = true;

    public bool $usarEstoqueMinimoNaSugestao = true;

    public bool $ultimaCompraTodasEmpresas = false;

    public string $localizarCampo = 'codigo';

    public string $localizarTexto = '';

    public bool $sugestaoGerada = false;

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        ErpScreen::set('Análise e Sugestão de Compra');

        $hoje = Carbon::now(self::TIMEZONE);
        $this->dataIni = $hoje->copy()->startOfMonth()->toDateString();
        $this->dataFim = $hoje->toDateString();
        $this->suprirAte = $hoje->copy()->addDays(30)->toDateString();
    }

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('compras.access');
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-os-form-page',
            'erp-analise-compras-page',
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getHeading(): string|Htmlable|null
    {
        return '';
    }

    public function coberturaDias(): int
    {
        try {
            $ate = Carbon::parse($this->suprirAte, self::TIMEZONE)->startOfDay();
            $hoje = Carbon::now(self::TIMEZONE)->startOfDay();
            $base = (int) $hoje->diffInDays($ate, false);

            return max(0, $base) + max(0, (int) $this->aprovisionamentoDias);
        } catch (\Throwable) {
            return max(0, (int) $this->aprovisionamentoDias);
        }
    }

    /**
     * @return array<string, string>
     */
    public function grupoOptions(): array
    {
        return app(AnaliseComprasService::class)->grupoOptions();
    }

    /**
     * @return array<string, string>
     */
    public function marcaOptions(): array
    {
        return app(AnaliseComprasService::class)->marcaOptions();
    }

    public function updatedProduto(): void
    {
        $term = mb_strtoupper(trim($this->produto), 'UTF-8');
        if ($this->produto !== $term) {
            $this->produto = $term;
        }

        if ($this->produtoId !== null) {
            $selectedLabel = mb_strtoupper(trim($this->labelProdutoSelecionado()), 'UTF-8');
            if ($term !== $selectedLabel) {
                $this->produtoId = null;
            } else {
                $this->fecharSugestoesProduto();

                return;
            }
        }

        if (mb_strlen($term) < 2) {
            $this->fecharSugestoesProduto();

            return;
        }

        $this->produtoSugestoes = $this->montarSugestoesProduto($term);
        $this->produtoSugestoesOpen = $this->produtoSugestoes !== [];
        $this->selectedProdutoSugestaoIndex = 0;
    }

    public function moverSugestaoProduto(int $delta): void
    {
        if (! $this->produtoSugestoesOpen || $this->produtoSugestoes === []) {
            return;
        }

        $count = count($this->produtoSugestoes);
        $this->selectedProdutoSugestaoIndex = max(0, min($count - 1, $this->selectedProdutoSugestaoIndex + $delta));
    }

    public function fecharSugestoesProduto(): void
    {
        $this->produtoSugestoes = [];
        $this->produtoSugestoesOpen = false;
        $this->selectedProdutoSugestaoIndex = 0;
    }

    public function limparProduto(): void
    {
        $this->produto = '';
        $this->produtoId = null;
        $this->fecharSugestoesProduto();
    }

    public function selecionarProduto(int $id): void
    {
        $product = Product::query()->where('ativo', true)->find($id);
        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $this->produtoId = (int) $product->id;
        $this->produto = $this->formatProdutoLabel($product);
        $this->fecharSugestoesProduto();
    }

    public function confirmarProduto(): void
    {
        if ($this->produtoSugestoesOpen && $this->produtoSugestoes !== []) {
            $index = $this->selectedProdutoSugestaoIndex;
            if (! isset($this->produtoSugestoes[$index])) {
                $index = 0;
            }
            $this->selecionarProduto((int) $this->produtoSugestoes[$index]['id']);

            return;
        }

        $term = trim($this->produto);
        if ($term === '') {
            return;
        }

        if ($this->produtoId) {
            return;
        }

        $exact = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term): void {
                $query->where('codigo', $term)
                    ->orWhere('referencia', $term)
                    ->orWhere('codigo_barras', $term)
                    ->orWhere('codigo_barras_caixa', $term);
            })
            ->first();

        if ($exact) {
            $this->selecionarProduto((int) $exact->id);

            return;
        }

        $sugestoes = $this->montarSugestoesProduto($term);
        if (count($sugestoes) === 1) {
            $this->selecionarProduto((int) $sugestoes[0]['id']);

            return;
        }

        if ($sugestoes !== []) {
            $this->produtoSugestoes = $sugestoes;
            $this->produtoSugestoesOpen = true;
            $this->selectedProdutoSugestaoIndex = 0;
            Notification::make()->title('Selecione o produto na lista (↑ ↓ + Enter).')->info()->send();

            return;
        }

        Notification::make()->title('Produto não encontrado.')->warning()->send();
    }

    public function updatedFornecedorBusca(): void
    {
        $term = trim($this->fornecedorBusca);

        if ($this->fornecedorId !== null && mb_strtoupper($term, 'UTF-8') !== mb_strtoupper($this->fornecedorNome, 'UTF-8')) {
            $this->fornecedorId = null;
            $this->fornecedorNome = '';
        }

        if (mb_strlen($term) < 2) {
            $this->fornecedorResultados = [];
            $this->fornecedorSelecionadoIndex = 0;

            return;
        }

        $digits = preg_replace('/\D+/', '', $term) ?? '';

        $this->fornecedorResultados = Person::query()
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->where(function ($q) use ($term, $digits): void {
                $q->where('nome_razao', 'like', '%'.$term.'%')
                    ->orWhere('apelido_fantasia', 'like', '%'.$term.'%');

                if ($digits !== '' && strlen($digits) >= 3) {
                    $q->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome_razao')
            ->limit(12)
            ->get(['id', 'nome_razao', 'cpf_cnpj'])
            ->map(fn (Person $pessoa): array => [
                'id' => (int) $pessoa->id,
                'nome' => (string) $pessoa->nome_razao,
                'cnpj' => (string) ($pessoa->cpf_cnpj ?? ''),
            ])
            ->all();

        $this->fornecedorSelecionadoIndex = 0;
    }

    public function moverFornecedorSelecionado(int $delta): void
    {
        $count = count($this->fornecedorResultados);
        if ($count === 0) {
            return;
        }

        $this->fornecedorSelecionadoIndex = max(0, min($count - 1, $this->fornecedorSelecionadoIndex + $delta));
    }

    public function confirmarFornecedorSelecionado(): void
    {
        $fornecedor = $this->fornecedorResultados[$this->fornecedorSelecionadoIndex] ?? null;
        if ($fornecedor) {
            $this->selecionarFornecedor((int) $fornecedor['id']);
        }
    }

    public function selecionarFornecedor(int $id): void
    {
        $fornecedor = Person::query()
            ->whereKey($id)
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->first();

        if (! $fornecedor) {
            return;
        }

        $this->fornecedorId = (int) $fornecedor->id;
        $this->fornecedorNome = (string) $fornecedor->nome_razao;
        $this->fornecedorBusca = $this->fornecedorNome;
        $this->fornecedorResultados = [];
        $this->fornecedorSelecionadoIndex = 0;
    }

    public function limparFornecedor(): void
    {
        $this->fornecedorId = null;
        $this->fornecedorNome = '';
        $this->fornecedorBusca = '';
        $this->fornecedorResultados = [];
        $this->fornecedorSelecionadoIndex = 0;
    }

    public function fecharLookupFornecedor(): void
    {
        $this->fornecedorResultados = [];
        $this->fornecedorSelecionadoIndex = 0;
    }

    public function filtrar(): void
    {
        $this->validate([
            'dataIni' => ['required', 'date'],
            'dataFim' => ['required', 'date', 'after_or_equal:dataIni'],
            'suprirAte' => ['required', 'date'],
            'aprovisionamentoDias' => ['nullable', 'integer', 'min:0', 'max:365'],
            'fornecedorId' => ['nullable', 'integer', 'exists:people,id'],
        ], [], [
            'dataIni' => 'período inicial',
            'dataFim' => 'período final',
            'suprirAte' => 'suprir até',
            'aprovisionamentoDias' => 'aprovisionamento',
            'fornecedorId' => 'fornecedor',
        ]);

        try {
            $this->rows = app(AnaliseComprasService::class)->filtrar([
                'data_ini' => $this->dataIni,
                'data_fim' => $this->dataFim,
                'produto' => $this->produtoId ? '' : $this->produto,
                'product_id' => $this->produtoId,
                'grupo' => $this->grupo,
                'marca' => $this->marca,
                'fornecedor_id' => $this->fornecedorId,
                'so_estoque_minimo' => $this->soEstoqueMinimo,
                'ultima_compra_todas_empresas' => $this->ultimaCompraTodasEmpresas,
            ]);
            $this->sugestaoGerada = false;

            Notification::make()
                ->title(count($this->rows).' produto(s) filtrado(s).')
                ->body('Use F6 para gerar a sugestão de compra.')
                ->success()
                ->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function gerarSugestao(): void
    {
        if ($this->rows === []) {
            Notification::make()
                ->title('Filtre os produtos primeiro (F3).')
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'suprirAte' => ['required', 'date'],
            'aprovisionamentoDias' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $this->rows = app(AnaliseComprasService::class)->gerarSugestao(
            $this->rows,
            $this->suprirAte,
            (int) $this->aprovisionamentoDias,
            $this->usarEstoqueMinimoNaSugestao,
        );
        $this->sugestaoGerada = true;

        $comSugestao = collect($this->rows)->filter(fn (array $r): bool => (float) ($r['sugestao'] ?? 0) > 0)->count();

        Notification::make()
            ->title('Sugestão gerada.')
            ->body($comSugestao.' produto(s) com quantidade sugerida (cobertura '.$this->coberturaDias().' dia(s)).')
            ->success()
            ->send();
    }

    public function selecionarSugestoes(): void
    {
        if (! $this->sugestaoGerada) {
            Notification::make()->title('Gere a sugestão (F6) antes de selecionar.')->warning()->send();

            return;
        }

        foreach ($this->rows as &$row) {
            $row['selected'] = (float) ($row['sugestao'] ?? 0) > 0;
        }
        unset($row);
    }

    public function excluirSelecionados(): void
    {
        $antes = count($this->rows);
        $this->rows = array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => ! (bool) ($row['selected'] ?? false)
        ));
        $removidos = $antes - count($this->rows);

        Notification::make()
            ->title($removidos > 0 ? "{$removidos} linha(s) removida(s)." : 'Nenhuma linha selecionada.')
            ->{$removidos > 0 ? 'success' : 'warning'}()
            ->send();
    }

    public function toggleSelecao(int $productId): void
    {
        foreach ($this->rows as &$row) {
            if ((int) $row['product_id'] === $productId) {
                $row['selected'] = ! (bool) ($row['selected'] ?? false);
                break;
            }
        }
        unset($row);
    }

    public function limparConsulta(): void
    {
        $this->rows = [];
        $this->sugestaoGerada = false;
        $this->localizarTexto = '';
    }

    public function redirectHome(): void
    {
        $this->redirect(url('/admin'));
    }

    public function limparLocalizar(): void
    {
        $this->localizarTexto = '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rowsVisiveis(): array
    {
        $texto = mb_strtolower(trim($this->localizarTexto), 'UTF-8');
        $campo = in_array($this->localizarCampo, ['codigo', 'descricao', 'codigo_barras'], true)
            ? $this->localizarCampo
            : 'codigo';

        return array_values(array_filter($this->rows, function (array $row) use ($texto, $campo): bool {
            if ($this->ocultarSugestaoZerada && $this->sugestaoGerada && (float) ($row['sugestao'] ?? 0) <= 0) {
                return false;
            }

            if ($texto === '') {
                return true;
            }

            $valor = mb_strtolower((string) ($row[$campo] ?? ''), 'UTF-8');

            return str_contains($valor, $texto);
        }));
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->rowsVisiveis();
        $filename = 'analise-compras-'.now(self::TIMEZONE)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Código', 'Cód. Barras', 'Descrição', 'Venda período', 'Última venda',
                'Dias sem venda', 'Média/dia', 'Estoque', 'Duração', 'Falta mínimo',
                'Última compra', 'Custo cadastro', 'Valor últ. compra', 'Último fornec.',
                'Sugestão', 'Urgência',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['codigo'] ?? '',
                    $row['codigo_barras'] ?? '',
                    $row['descricao'] ?? '',
                    $this->fmtQty($row['venda_periodo'] ?? 0),
                    $this->fmtDate($row['data_ultima_venda'] ?? null),
                    $row['dias_sem_venda'] ?? '',
                    $this->fmtQty($row['media_diaria'] ?? 0, 4),
                    $this->fmtQty($row['estoque'] ?? 0),
                    $row['duracao_estoque'] !== null ? $this->fmtQty($row['duracao_estoque'], 1) : '',
                    $this->fmtQty($row['falta_minimo'] ?? 0),
                    $this->fmtDate($row['ultima_compra_data'] ?? null),
                    $this->fmtMoney($row['ultimo_custo_cadastro'] ?? 0),
                    $this->fmtMoney($row['valor_ultima_compra'] ?? 0),
                    $row['ult_fornecedor'] ?? '',
                    $row['sugestao'] !== null ? $this->fmtQty($row['sugestao']) : '',
                    $row['urgencia'] ?? '',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function gerarPedidoCompra(): void
    {
        if (! $this->sugestaoGerada) {
            Notification::make()->title('Gere a sugestão (F6) antes de criar o pedido.')->warning()->send();

            return;
        }

        $ids = collect($this->rows)
            ->filter(fn (array $r): bool => (bool) ($r['selected'] ?? false) && (float) ($r['sugestao'] ?? 0) > 0)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($ids === []) {
            Notification::make()
                ->title('Selecione produtos com sugestão (F4).')
                ->warning()
                ->send();

            return;
        }

        try {
            $result = app(AnaliseComprasService::class)->gerarPedidoCompra(
                $this->rows,
                $ids,
                $this->fornecedorId,
            );

            Notification::make()
                ->title('Pedido de compra gerado.')
                ->body(
                    'Compra nº '.$result['compra']->numero
                    .' com '.$result['itens'].' item(ns). Abra em Lista Compras para lançar.'
                )
                ->success()
                ->send();

            $this->redirect(CompraResource::getUrl('index'));
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /**
     * @return list<array{id:int,codigo:string,nome:string,estoque:string,barras:string}>
     */
    private function montarSugestoesProduto(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $like = '%'.$term.'%';

        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $like): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like)
                    ->orWhere('descricao', 'like', $like);
            })
            ->orderByRaw('CASE WHEN codigo = ? THEN 0 WHEN descricao LIKE ? THEN 1 ELSE 2 END', [$term, $term.'%'])
            ->orderBy('descricao')
            ->limit(12)
            ->get(['id', 'codigo', 'descricao', 'codigo_barras', 'estoque'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'codigo' => (string) ($product->codigo ?? ''),
                'nome' => (string) ($product->descricao ?? ''),
                'barras' => (string) ($product->codigo_barras ?? ''),
                'estoque' => $this->fmtQty($product->estoque ?? 0),
            ])
            ->values()
            ->all();
    }

    private function formatProdutoLabel(Product $product): string
    {
        $codigo = trim((string) ($product->codigo ?? ''));
        $nome = trim((string) ($product->descricao ?? ''));

        return mb_strtoupper($codigo !== '' ? $codigo.' — '.$nome : $nome, 'UTF-8');
    }

    private function labelProdutoSelecionado(): string
    {
        if (! $this->produtoId) {
            return '';
        }

        $product = Product::query()->find($this->produtoId);

        return $product ? $this->formatProdutoLabel($product) : '';
    }

    private function fmtQty(mixed $value, int $decimals = 3): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    private function fmtMoney(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    private function fmtDate(mixed $value): string
    {
        if (! filled($value)) {
            return '';
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
