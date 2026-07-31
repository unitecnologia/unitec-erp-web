<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Cfop;
use App\Models\Empresa;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Nfe\NfeCalculoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait ManagesNfeItemGrid
{
    public string $nfeItemCodigoInput = '';

    public string $nfeItemProdutoSearch = '';

    public ?int $nfeItemPendingProductId = null;

    public string $nfeItemEntryCfop = '';

    public string $nfeItemEntryCst = '';

    public string $nfeItemEntryPreco = '';

    public string $nfeItemEntryQtd = '1,0000';

    public string $nfeItemEntryUnidade = 'UN';

    public string $nfeItemEntryTotalDisplay = '';

    public string $nfeItemEntryDesconto = '0,00';

    public string $nfeItemEntryOutros = '0,00';

    public bool $nfeDescontoModalOpen = false;

    /** @var 'form'|'grid'|null */
    public ?string $nfeItemAjusteAlvo = null;

    /** @var 'desconto'|'acrescimo' */
    public string $nfeItemAjusteTipo = 'desconto';

    /** @var 'percentual'|'valor' */
    public string $nfeItemAjusteModo = 'percentual';

    public string $nfeItemAjusteValor = '0,00';

    public bool $nfeProdutoLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $nfeProdutoResults = [];

    public ?int $nfeSelectedProdutoIndex = null;

    public ?string $nfeProdutoPreviewFotoUrl = null;

    public function selectNfeRow(int $index): void
    {
        if ($index >= 0 && $index < count($this->nfeModalRows)) {
            $this->nfeSelectedRowIndex = $index;
        }
    }

    public function handleNfeItemCodigoEnter(): void
    {
        if (blank(trim($this->nfeItemCodigoInput))) {
            $this->dispatch('erp-nfe-focus-item-produto');

            return;
        }

        $this->submitNfeItemByCodigo(confirm: false);
        $this->dispatch('erp-nfe-focus-item-quantidade');
    }

    public function confirmarNfeInclusaoProduto(?string $termFromInput = null): void
    {
        // Enter manda o valor do input (evita perder o código pelo debounce do wire:model).
        if ($termFromInput !== null) {
            $fromInput = mb_strtoupper(trim($termFromInput), 'UTF-8');

            if ($fromInput !== '') {
                $this->nfeItemProdutoSearch = $fromInput;
            }
        }

        $term = mb_strtoupper(trim($this->nfeItemProdutoSearch), 'UTF-8');

        if ($term === '') {
            Notification::make()->title('Informe o código, barras ou nome do produto.')->warning()->send();
            $this->dispatch('erp-nfe-focus-item-produto');

            return;
        }

        // Já confirmado (campo mostra a descrição): só avança para quantidade.
        if ($this->nfeItemPendingProductId !== null) {
            $pendingNome = $this->nfePendingProductNome();

            if ($pendingNome !== '' && $term === $pendingNome) {
                $this->dispatch('erp-nfe-focus-item-quantidade');

                return;
            }
        }

        $this->nfeItemProdutoSearch = $term;
        $this->nfeItemCodigoInput = $term;

        // Código/barras exato tem prioridade (leitor / Enter rápido).
        $byCodigo = $this->findNfeProductByCodigo($term);

        if ($byCodigo) {
            $this->stageNfeProductForEntry($byCodigo);

            return;
        }

        // Código numérico sem match exato: não confirma sugestão "parecida" (ex.: 13 → 133).
        if ($this->nfeTermLooksLikeProductCode($term)) {
            $this->nfeProdutoLookupOpen = true;
            $this->refreshNfeProdutoResults();

            Notification::make()
                ->title('Código não encontrado.')
                ->body('Nenhum produto com o código "'.$term.'". Digite o nome para buscar por descrição.')
                ->warning()
                ->send();
            $this->dispatch('erp-nfe-focus-item-produto');

            return;
        }

        // Lista aberta por nome: confirma a sugestão selecionada (↑ ↓ + Enter).
        if ($this->nfeProdutoLookupOpen && $this->nfeProdutoResults !== []) {
            $index = $this->nfeSelectedProdutoIndex;

            if ($index === null || ! isset($this->nfeProdutoResults[$index])) {
                $index = 0;
            }

            $this->selectNfeProdutoResult((int) $index);

            return;
        }

        $this->nfeProdutoLookupOpen = true;
        $this->refreshNfeProdutoResults();

        if ($this->nfeProdutoResults === []) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código, barras ou nome informado.')
                ->warning()
                ->send();
            $this->dispatch('erp-nfe-focus-item-produto');

            return;
        }

        if (count($this->nfeProdutoResults) === 1) {
            $this->selectNfeProdutoResult(0);

            return;
        }

        $this->nfeSelectedProdutoIndex = 0;
        $this->syncNfeProdutoPreviewFotoFromSelection();
        Notification::make()->title('Selecione o produto na lista (↑ ↓ + Enter).')->info()->send();
        $this->dispatch('erp-nfe-focus-item-produto');
    }

    public function focoNfeInclusaoPrecoAposQtd(?string $qtdFromInput = null): void
    {
        if ($qtdFromInput !== null && trim($qtdFromInput) !== '') {
            $this->nfeItemEntryQtd = trim($qtdFromInput);
        }

        if ($this->nfeItemPendingProductId === null) {
            $this->confirmarNfeInclusaoProduto();

            return;
        }

        $qtd = ErpMoney::parseBr($this->nfeItemEntryQtd, 4);

        if ($qtd <= 0) {
            Notification::make()->title('Quantidade inválida.')->warning()->send();
            $this->dispatch('erp-nfe-focus-item-quantidade');

            return;
        }

        $this->recalcNfeEntryRowPreview();
        $this->dispatch('erp-nfe-focus-item-preco');
    }

    public function confirmarNfeInclusaoPreco(?string $precoFromInput = null): void
    {
        if ($precoFromInput !== null && trim($precoFromInput) !== '') {
            $this->nfeItemEntryPreco = trim($precoFromInput);
        }

        if ($this->nfeItemPendingProductId === null) {
            $this->confirmarNfeInclusaoProduto();

            return;
        }

        $preco = ErpMoney::parseBr($this->nfeItemEntryPreco, 4);

        if ($preco <= 0) {
            Notification::make()->title('Informe o valor unitário.')->warning()->send();
            $this->dispatch('erp-nfe-focus-item-preco');

            return;
        }

        $this->confirmPendingNfeItemEntry();
    }

    public function abrirNfeModalDescontoItem(): void
    {
        if ($this->nfeDescontoModalOpen) {
            return;
        }

        $preco = ErpMoney::parseBr($this->nfeItemEntryPreco, 4);

        if ($this->nfeItemPendingProductId && $preco > 0) {
            $this->nfeItemAjusteAlvo = 'form';
        } elseif ($this->nfeModalRows !== [] && isset($this->nfeModalRows[$this->nfeSelectedRowIndex])) {
            $this->nfeItemAjusteAlvo = 'grid';
        } else {
            Notification::make()
                ->title('Informe o produto (ou selecione um item) para desconto/acréscimo.')
                ->warning()
                ->send();

            return;
        }

        $this->nfeItemAjusteTipo = 'desconto';
        $this->nfeItemAjusteModo = 'percentual';
        $this->nfeItemAjusteValor = '0,00';
        $this->nfeDescontoModalOpen = true;
        $this->dispatch('erp-nfe-focus-desconto-item');
    }

    public function fecharNfeModalDescontoItem(): void
    {
        $this->nfeDescontoModalOpen = false;
        $this->nfeItemAjusteAlvo = null;
    }

    public function handleNfeModalEscape(): void
    {
        if ($this->nfeDescontoModalOpen) {
            $this->fecharNfeModalDescontoItem();

            return;
        }

        if ($this->nfeNaturezaSugestoesOpen) {
            $this->fecharNfeSugestoesNatureza();

            return;
        }

        if ($this->nfeClienteSugestoesOpen) {
            $this->fecharNfeSugestoesCliente();

            return;
        }

        $this->closeNfeModal();
    }

    public function updatedNfeItemAjusteValor(): void
    {
        $raw = preg_replace('/[^\d,.\-]/', '', (string) $this->nfeItemAjusteValor) ?? '';
        $this->nfeItemAjusteValor = $raw === '' ? '0,00' : $raw;
    }

    public function setNfeItemAjusteTipo(string $tipo): void
    {
        $this->nfeItemAjusteTipo = $tipo === 'acrescimo' ? 'acrescimo' : 'desconto';
    }

    public function setNfeItemAjusteModo(string $modo): void
    {
        $this->nfeItemAjusteModo = $modo === 'valor' ? 'valor' : 'percentual';
    }

    /**
     * @return array{descricao: string, base: string, novoPreco: string, total: string, tipo: string, temAjuste: bool}
     */
    public function getNfeItemAjustePreviewProperty(): array
    {
        $ctx = $this->contextoNfeItemAjuste();

        if ($ctx === null) {
            return [
                'descricao' => '',
                'base' => ErpMoney::formatBr(0, 2),
                'novoPreco' => ErpMoney::formatBr(0, 2),
                'total' => ErpMoney::formatBr(0, 2),
                'tipo' => $this->nfeItemAjusteTipo,
                'temAjuste' => false,
            ];
        }

        $calc = $this->calcularNfeItemAjuste($ctx['preco'], $ctx['quantidade']);

        return [
            'descricao' => $ctx['descricao'],
            'base' => ErpMoney::formatBr($calc['base'], 2),
            'novoPreco' => ErpMoney::formatBr($calc['novoPreco'], 2),
            'total' => ErpMoney::formatBr($calc['total'], 2),
            'tipo' => $this->nfeItemAjusteTipo,
            'temAjuste' => abs($calc['deltaUnit']) > 0.0001,
        ];
    }

    public function confirmarNfeItemAjuste(): void
    {
        $ctx = $this->contextoNfeItemAjuste();

        if ($ctx === null) {
            $this->fecharNfeModalDescontoItem();

            return;
        }

        $calc = $this->calcularNfeItemAjuste($ctx['preco'], $ctx['quantidade']);
        $ajusteLinha = round(abs($calc['deltaUnit']) * $ctx['quantidade'], 2);

        if ($this->nfeItemAjusteTipo === 'desconto' && $calc['novoPreco'] < 0) {
            Notification::make()->title('Desconto inválido.')->warning()->send();

            return;
        }

        if ($this->nfeItemAjusteAlvo === 'form') {
            if ($this->nfeItemAjusteTipo === 'desconto') {
                $this->nfeItemEntryDesconto = ErpMoney::formatBr($ajusteLinha, 2);
                $this->nfeItemEntryOutros = '0,00';
            } else {
                $this->nfeItemEntryOutros = ErpMoney::formatBr($ajusteLinha, 2);
                $this->nfeItemEntryDesconto = '0,00';
            }

            $this->recalcNfeEntryRowPreview();
        } else {
            $index = (int) $this->nfeSelectedRowIndex;

            if (! isset($this->nfeModalRows[$index])) {
                $this->fecharNfeModalDescontoItem();

                return;
            }

            if ($this->nfeItemAjusteTipo === 'desconto') {
                $this->nfeModalRows[$index]['desconto'] = $ajusteLinha;
                $this->nfeModalRows[$index]['outros'] = 0.0;
            } else {
                $this->nfeModalRows[$index]['outros'] = $ajusteLinha;
                $this->nfeModalRows[$index]['desconto'] = 0.0;
            }

            $this->recalculateNfeTotais();
        }

        $tipo = $this->nfeItemAjusteTipo;
        $this->fecharNfeModalDescontoItem();
        Notification::make()
            ->title($tipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
            ->success()
            ->send();
    }

    /**
     * @return array{descricao: string, preco: float, quantidade: float}|null
     */
    protected function contextoNfeItemAjuste(): ?array
    {
        if ($this->nfeItemAjusteAlvo === 'form' && $this->nfeItemPendingProductId) {
            return [
                'descricao' => (string) $this->nfeItemProdutoSearch,
                'preco' => ErpMoney::parseBr($this->nfeItemEntryPreco, 4),
                'quantidade' => max(0.0, ErpMoney::parseBr($this->nfeItemEntryQtd, 4)),
            ];
        }

        if ($this->nfeItemAjusteAlvo === 'grid' && isset($this->nfeModalRows[$this->nfeSelectedRowIndex])) {
            $item = $this->nfeModalRows[$this->nfeSelectedRowIndex];

            return [
                'descricao' => (string) ($item['descricao'] ?? ''),
                'preco' => ErpMoney::parseBr($item['valor_unitario'] ?? 0, 4),
                'quantidade' => ErpMoney::parseBr($item['quantidade'] ?? 0, 4),
            ];
        }

        return null;
    }

    /**
     * @return array{base: float, deltaUnit: float, novoPreco: float, total: float}
     */
    protected function calcularNfeItemAjuste(float $base, float $quantidade): array
    {
        $valor = ErpMoney::parseBr($this->nfeItemAjusteValor, 2);

        if ($this->nfeItemAjusteModo === 'percentual') {
            $deltaUnit = round($base * ($valor / 100), 2);
        } else {
            $deltaUnit = round($valor, 2);
        }

        $novoPreco = $this->nfeItemAjusteTipo === 'acrescimo'
            ? round($base + $deltaUnit, 2)
            : round($base - $deltaUnit, 2);

        if ($novoPreco < 0) {
            $novoPreco = 0.0;
        }

        return [
            'base' => $base,
            'deltaUnit' => $deltaUnit,
            'novoPreco' => $novoPreco,
            'total' => round($quantidade * $novoPreco, 2),
        ];
    }

    public function handleNfeItemProdutoEnter(?string $term = null): void
    {
        if ($term !== null) {
            $term = mb_strtoupper(trim($term), 'UTF-8');

            if ($term !== '' && $term !== $this->nfeItemProdutoSearch) {
                $this->nfeItemProdutoSearch = $term;
            }
        }

        if ($this->nfeProdutoLookupOpen && $this->nfeProdutoResults !== []) {
            if (count($this->nfeProdutoResults) === 1) {
                $this->selectNfeProdutoResult(0);
                $this->confirmPendingNfeItemEntry();

                return;
            }

            if ($this->nfeSelectedProdutoIndex !== null && isset($this->nfeProdutoResults[$this->nfeSelectedProdutoIndex])) {
                $this->confirmNfeProdutoSelection();
                $this->confirmPendingNfeItemEntry();

                return;
            }

            return;
        }

        if ($this->nfeItemPendingProductId !== null) {
            $this->closeNfeProdutoLookup();
            $this->confirmPendingNfeItemEntry();

            return;
        }

        $this->submitNfeItemProdutoSearch(confirm: true);
    }

    public function advanceNfeEntryField(string $from): void
    {
        if ($this->nfeItemPendingProductId === null) {
            return;
        }

        match ($from) {
            'cfop' => $this->dispatch('erp-nfe-focus-item-cst'),
            'cst' => $this->dispatch('erp-nfe-focus-item-preco'),
            'preco' => $this->dispatch('erp-nfe-focus-item-quantidade'),
            'qtd' => $this->dispatch('erp-nfe-focus-item-unidade'),
            'unid' => $this->confirmPendingNfeItemEntry(),
            default => null,
        };
    }

    public function submitNfeItemByCodigo(bool $confirm = false): void
    {
        $codigo = mb_strtoupper(trim($this->nfeItemCodigoInput), 'UTF-8');

        if ($codigo === '') {
            return;
        }

        $product = $this->findNfeProductByCodigo($codigo);

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código informado.')
                ->warning()
                ->send();
            $this->dispatch('erp-nfe-focus-item-codigo');

            return;
        }

        $this->stageNfeProductForEntry($product);

        if ($confirm) {
            $this->confirmPendingNfeItemEntry();
        }
    }

    public function updatedNfeItemProdutoSearch(string $value): void
    {
        // Não usa trim() aqui: remove espaços enquanto digita ("HOT " → "HOT") e o cursor volta.
        $upper = mb_strtoupper($value, 'UTF-8');
        $term = trim($upper);

        if ($this->nfeItemProdutoSearch !== $upper) {
            $this->nfeItemProdutoSearch = $upper;
        }

        // Produto já confirmado e campo ainda com a descrição: não limpa preço/qtde.
        if ($this->nfeItemPendingProductId !== null) {
            $pendingNome = $this->nfePendingProductNome();

            if ($term === '' || ($pendingNome !== '' && $term === $pendingNome)) {
                $this->closeNfeProdutoLookup();

                return;
            }

            // Digitou algo diferente da descrição confirmada → nova busca.
            $this->clearNfePendingKeepSearchTerm();
        }

        if ($term === '') {
            $this->closeNfeProdutoLookup();
            $this->nfeProdutoResults = [];
            $this->nfeSelectedProdutoIndex = null;

            return;
        }

        $this->nfeProdutoLookupOpen = true;
        $this->refreshNfeProdutoResults();
    }

    public function openNfeProdutoLookup(): void
    {
        $term = trim($this->nfeItemProdutoSearch);

        if ($term === '') {
            return;
        }

        if ($this->nfeItemPendingProductId !== null) {
            $pendingNome = $this->nfePendingProductNome();

            if ($pendingNome !== '' && mb_strtoupper($term, 'UTF-8') === $pendingNome) {
                return;
            }
        }

        $this->nfeProdutoLookupOpen = true;
        $this->refreshNfeProdutoResults();
    }

    protected function clearNfePendingKeepSearchTerm(): void
    {
        $this->nfeItemPendingProductId = null;
        $this->nfeItemCodigoInput = '';
        $this->nfeItemEntryCfop = '';
        $this->nfeItemEntryCst = '';
        $this->nfeItemEntryPreco = '';
        $this->nfeItemEntryQtd = '1,0000';
        $this->nfeItemEntryUnidade = 'UN';
        $this->nfeItemEntryDesconto = '0,00';
        $this->nfeItemEntryOutros = '0,00';
        $this->nfeItemEntryTotalDisplay = '0,00';
    }

    protected function nfePendingProductNome(): string
    {
        if ($this->nfeItemPendingProductId === null) {
            return '';
        }

        $product = Product::query()->find($this->nfeItemPendingProductId);

        return $product ? mb_strtoupper(trim((string) $product->descricao), 'UTF-8') : '';
    }

    public function refreshNfeProdutoResults(): void
    {
        $term = mb_strtoupper(trim($this->nfeItemProdutoSearch), 'UTF-8');

        if ($term === '') {
            $this->nfeProdutoResults = [];
            $this->nfeSelectedProdutoIndex = null;
            $this->clearNfeProdutoPreviewFoto();

            return;
        }

        $buscaPorCodigo = $this->nfeTermLooksLikeProductCode($term);
        $tokens = $buscaPorCodigo
            ? [$term]
            : $this->nfeProdutoSearchTokens($term);
        $firstToken = $tokens[0] ?? $term;
        $reservas = app(EstoqueReservaService::class)->totaisReservadosAtivos(null);

        $produtos = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term, $buscaPorCodigo, $tokens): void {
                if ($buscaPorCodigo) {
                    // Digita só número/código: busca o código em si (não "contém" 13 em 133).
                    $query->where('codigo', $term)
                        ->orWhere('referencia', $term)
                        ->orWhere('codigo_barras', $term)
                        ->orWhere('codigo_barras_caixa', $term);

                    return;
                }

                // Busca inteligente: "HOT PETRO" casa cada palavra (AND), em qualquer ordem.
                foreach ($tokens as $token) {
                    $like = '%'.$this->escapeNfeProdutoLike($token).'%';
                    $query->where(function ($tokenQuery) use ($like): void {
                        $tokenQuery->where('codigo', 'like', $like)
                            ->orWhere('descricao', 'like', $like)
                            ->orWhere('referencia', 'like', $like)
                            ->orWhere('codigo_barras', 'like', $like)
                            ->orWhere('codigo_barras_caixa', 'like', $like);
                    });
                }
            })
            ->orderByRaw(
                'CASE WHEN codigo = ? THEN 0 WHEN codigo_barras = ? OR codigo_barras_caixa = ? OR referencia = ? THEN 1 WHEN descricao LIKE ? THEN 2 WHEN descricao LIKE ? THEN 3 ELSE 4 END',
                [$term, $term, $term, $term, $term.'%', $firstToken.'%'],
            )
            ->orderBy('descricao')
            ->limit(12)
            ->get(['id', 'codigo', 'descricao', 'preco_venda', 'estoque']);

        $this->nfeProdutoResults = $produtos
            ->map(function (Product $product) use ($reservas): array {
                $atual = (float) ($product->estoque ?? 0);
                $reservado = (float) ($reservas[$product->id] ?? 0);
                $preco = (float) ($product->preco_venda ?? 0);
                $nome = mb_strtoupper((string) ($product->descricao ?? ''), 'UTF-8');

                return [
                    'id' => (int) $product->id,
                    'codigo' => mb_strtoupper((string) ($product->codigo ?? ''), 'UTF-8'),
                    'descricao' => $nome,
                    'nome' => $nome,
                    'atual' => ErpMoney::formatBr($atual, 3),
                    'reservado' => ErpMoney::formatBr($reservado, 3),
                    'disponivel' => ErpMoney::formatBr($atual - $reservado, 3),
                    'preco' => ErpMoney::formatBr($preco, 2),
                ];
            })
            ->values()
            ->all();

        $this->nfeSelectedProdutoIndex = $this->nfeProdutoResults === [] ? null : 0;
        $this->syncNfeProdutoPreviewFotoFromSelection();
    }

    /**
     * @return list<string>
     */
    protected function nfeProdutoSearchTokens(string $term): array
    {
        $parts = preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return $tokens !== [] ? $tokens : [$term];
    }

    protected function escapeNfeProdutoLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Termo que parece código/barras (só dígitos): busca exata, sem LIKE %código%.
     */
    protected function nfeTermLooksLikeProductCode(string $term): bool
    {
        $term = trim($term);

        if ($term === '') {
            return false;
        }

        return ctype_digit($term);
    }

    public function selecionarNfeProdutoInclusao(int $id): void
    {
        $product = Product::query()->where('ativo', true)->find($id);

        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $this->stageNfeProductForEntry($product);
    }

    public function moveNfeProdutoSelection(int $delta): void
    {
        if (! $this->nfeProdutoLookupOpen || $this->nfeProdutoResults === []) {
            return;
        }

        $index = ($this->nfeSelectedProdutoIndex ?? 0) + $delta;
        $count = count($this->nfeProdutoResults);
        $this->nfeSelectedProdutoIndex = max(0, min($count - 1, $index));
        $this->syncNfeProdutoPreviewFotoFromSelection();
        $this->dispatch('erp-nfe-scroll-produto-selection');
    }

    public function selectNfeProdutoResult(int $index, bool $advanceToCfop = false): void
    {
        if (! isset($this->nfeProdutoResults[$index])) {
            return;
        }

        $this->nfeSelectedProdutoIndex = $index;
        $this->syncNfeProdutoPreviewFotoFromSelection();
        $this->confirmNfeProdutoSelection(advanceToCfop: $advanceToCfop);
    }

    public function confirmNfeProdutoSelection(bool $advanceToCfop = false): void
    {
        $index = $this->nfeSelectedProdutoIndex;

        if ($index === null || ! isset($this->nfeProdutoResults[$index])) {
            return;
        }

        $product = Product::query()->find($this->nfeProdutoResults[$index]['id']);

        if (! $product) {
            return;
        }

        $this->stageNfeProductForEntry($product, advanceToCfop: $advanceToCfop);
    }

    public function submitNfeItemProdutoSearch(bool $advanceToCfop = false, bool $confirm = false): void
    {
        $term = trim($this->nfeItemProdutoSearch);

        if ($term === '') {
            return;
        }

        $this->refreshNfeProdutoResults();

        if ($this->nfeProdutoResults === []) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código ou a descrição informada.')
                ->warning()
                ->send();
            $this->dispatch('erp-nfe-focus-item-produto');

            return;
        }

        if (count($this->nfeProdutoResults) === 1) {
            $this->selectNfeProdutoResult(0, advanceToCfop: $advanceToCfop && ! $confirm);

            if ($confirm) {
                $this->confirmPendingNfeItemEntry();
            }

            return;
        }

        if ($this->nfeSelectedProdutoIndex !== null && isset($this->nfeProdutoResults[$this->nfeSelectedProdutoIndex])) {
            $this->confirmNfeProdutoSelection(advanceToCfop: $advanceToCfop && ! $confirm);

            if ($confirm) {
                $this->confirmPendingNfeItemEntry();
            }

            return;
        }

        $this->nfeProdutoLookupOpen = true;
        $this->nfeSelectedProdutoIndex = 0;
        $this->dispatch('erp-nfe-focus-item-produto');
    }

    public function closeNfeProdutoLookup(): void
    {
        $this->nfeProdutoLookupOpen = false;
        $this->clearNfeProdutoPreviewFoto();
    }

    public function updatedNfeItemEntryQtd(): void
    {
        $this->recalcNfeEntryRowPreview();
    }

    public function updatedNfeItemEntryPreco(): void
    {
        $this->recalcNfeEntryRowPreview();
    }

    public function confirmPendingNfeItemEntry(): void
    {
        if ($this->nfeItemPendingProductId === null) {
            return;
        }

        $product = Product::query()->find($this->nfeItemPendingProductId);

        if (! $product) {
            $this->clearNfeItemEntryRow();

            return;
        }

        $qtd = ErpMoney::parseBr($this->nfeItemEntryQtd, 4);

        if ($qtd <= 0) {
            Notification::make()->title('Informe a quantidade do item.')->warning()->send();
            $this->dispatch('erp-nfe-focus-item-quantidade');

            return;
        }

        $cfop = trim($this->nfeItemEntryCfop);
        $preco = ErpMoney::parseBr($this->nfeItemEntryPreco, 4);
        $existingIndex = $this->findNfeModalRowIndexToUnify((int) $product->id, $cfop, $preco);

        if ($existingIndex !== null) {
            $existingQtd = ErpMoney::parseBr($this->nfeModalRows[$existingIndex]['quantidade'] ?? '0', 4);
            $this->nfeModalRows[$existingIndex]['quantidade'] = ErpMoney::formatBr($existingQtd + $qtd, 4);

            $existingDesconto = ErpMoney::parseBr($this->nfeModalRows[$existingIndex]['desconto'] ?? '0', 2);
            $entryDesconto = ErpMoney::parseBr($this->nfeItemEntryDesconto, 2);
            if ($entryDesconto > 0) {
                $this->nfeModalRows[$existingIndex]['desconto'] = ErpMoney::formatBr($existingDesconto + $entryDesconto, 2);
            }

            $existingOutros = ErpMoney::parseBr($this->nfeModalRows[$existingIndex]['outros'] ?? '0', 2);
            $entryOutros = ErpMoney::parseBr($this->nfeItemEntryOutros, 2);
            if ($entryOutros > 0) {
                $this->nfeModalRows[$existingIndex]['outros'] = ErpMoney::formatBr($existingOutros + $entryOutros, 2);
            }

            $this->nfeSelectedRowIndex = $existingIndex;
            $this->clearNfeItemEntryRow();
            $this->recalculateNfeTotais();
            $this->dispatch('erp-nfe-focus-item-codigo');

            return;
        }

        $this->nfeModalRows[] = [
            'key' => 'new-' . Str::uuid()->toString(),
            'product_id' => $product->id,
            'codigo' => (string) $product->codigo,
            'descricao' => mb_strtoupper(trim($this->nfeItemProdutoSearch), 'UTF-8'),
            'cfop' => $cfop,
            'cst' => trim($this->nfeItemEntryCst),
            'quantidade' => ErpMoney::formatBr($qtd, 4),
            'valor_unitario' => ErpMoney::formatBr($preco, 4),
            'unidade' => mb_strtoupper(trim($this->nfeItemEntryUnidade) ?: 'UN', 'UTF-8'),
            'desconto' => ErpMoney::parseBr($this->nfeItemEntryDesconto, 2),
            'outros' => ErpMoney::parseBr($this->nfeItemEntryOutros, 2),
        ];

        $this->nfeSelectedRowIndex = count($this->nfeModalRows) - 1;
        $this->clearNfeItemEntryRow();
        $this->recalculateNfeTotais();
        $this->dispatch('erp-nfe-focus-item-codigo');
    }

    /**
     * Mesmo produto + CFOP + preço unitário → soma quantidade na linha existente.
     */
    protected function findNfeModalRowIndexToUnify(int $productId, string $cfop, float $preco): ?int
    {
        if ($productId <= 0) {
            return null;
        }

        $cfopNorm = preg_replace('/\D/', '', $cfop) ?: '';
        $precoNorm = round($preco, 4);

        foreach ($this->nfeModalRows as $index => $row) {
            if ((int) ($row['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $rowCfop = preg_replace('/\D/', '', (string) ($row['cfop'] ?? '')) ?: '';
            if ($cfopNorm !== '' && $rowCfop !== '' && $cfopNorm !== $rowCfop) {
                continue;
            }

            $rowPreco = round(ErpMoney::parseBr($row['valor_unitario'] ?? '0', 4), 4);
            if (abs($rowPreco - $precoNorm) > 0.00005) {
                continue;
            }

            return (int) $index;
        }

        return null;
    }

    public function resolveNfeItemProductFromCodigo(int $index): void
    {
        if (! isset($this->nfeModalRows[$index])) {
            return;
        }

        $codigo = mb_strtoupper(trim((string) ($this->nfeModalRows[$index]['codigo'] ?? '')), 'UTF-8');

        if ($codigo === '') {
            return;
        }

        $product = $this->findNfeProductByCodigo($codigo);

        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        $qtd = ErpMoney::parseBr($this->nfeModalRows[$index]['quantidade'] ?? '1', 4);
        $this->applyProductToNfeRow($index, $product, max(0.0001, $qtd));
        $this->recalculateNfeTotais();
    }

    public function resolveNfeItemCfop(int $index, ?string $cfopFromInput = null): void
    {
        if (! isset($this->nfeModalRows[$index])) {
            return;
        }

        if ($cfopFromInput !== null) {
            $this->nfeModalRows[$index]['cfop'] = trim($cfopFromInput);
        }

        $raw = trim((string) ($this->nfeModalRows[$index]['cfop'] ?? ''));
        $digits = preg_replace('/\D/', '', $raw) ?: '';

        if ($digits === '') {
            Notification::make()->title('Informe o código CFOP.')->warning()->send();

            return;
        }

        $codigo = (int) $digits;
        $tipo = ($this->nfeForm['movimento'] ?? 'saida') === 'entrada'
            ? Cfop::TIPO_ENTRADA
            : Cfop::TIPO_SAIDA;

        $cfop = Cfop::query()
            ->where('codigo', $codigo)
            ->where('tipo', $tipo)
            ->first(['codigo', 'descricao']);

        if (! $cfop) {
            $cfop = Cfop::query()->where('codigo', $codigo)->first(['codigo', 'descricao']);
        }

        if (! $cfop) {
            Notification::make()->title('CFOP não encontrado.')->warning()->send();

            return;
        }

        $this->nfeModalRows[$index]['cfop'] = (string) $cfop->codigo;
        $this->recalculateNfeTotais();
    }

    public function updatedNfeModalRows(): void
    {
        foreach (array_keys($this->nfeModalRows) as $index) {
            if (isset($this->nfeModalRows[$index]['descricao'])) {
                $this->nfeModalRows[$index]['descricao'] = mb_strtoupper(
                    trim((string) $this->nfeModalRows[$index]['descricao']),
                    'UTF-8',
                );
            }

            if (isset($this->nfeModalRows[$index]['unidade'])) {
                $this->nfeModalRows[$index]['unidade'] = mb_strtoupper(
                    trim((string) $this->nfeModalRows[$index]['unidade']) ?: 'UN',
                    'UTF-8',
                );
            }
        }

        $this->recalculateNfeTotais();
    }

    public function deleteNfeSelectedItem(): void
    {
        if ($this->nfeModalRows === []) {
            return;
        }

        $index = min($this->nfeSelectedRowIndex, count($this->nfeModalRows) - 1);
        array_splice($this->nfeModalRows, $index, 1);
        $this->nfeSelectedRowIndex = max(0, $index - 1);

        if ($this->nfeModalRows === []) {
            $this->nfeSelectedRowIndex = 0;
        }

        $this->recalculateNfeTotais();
    }

    protected function stageNfeProductForEntry(Product $product, bool $advanceToCfop = false): void
    {
        $preview = $this->previewNfeProductRow($product, 1.0);

        $this->nfeItemPendingProductId = $product->id;
        $this->nfeItemCodigoInput = (string) $product->codigo;
        $this->nfeItemProdutoSearch = mb_strtoupper($product->descricao, 'UTF-8');
        $this->nfeItemEntryCfop = (string) ($preview['cfop'] ?? '');
        $this->nfeItemEntryCst = (string) (($preview['cst'] ?? '') ?: ($preview['csosn'] ?? ''));
        $preco = (float) ($preview['valor_unitario'] ?? 0);
        if ($preco <= 0) {
            $preco = (float) ($product->preco_venda ?? 0);
        }
        $this->nfeItemEntryPreco = ErpMoney::formatBr($preco, 4);
        $this->nfeItemEntryQtd = ErpMoney::formatBr(1, 4);
        $this->nfeItemEntryUnidade = mb_strtoupper((string) ($preview['unidade'] ?? $product->unidade ?: 'UN'), 'UTF-8');
        $this->nfeItemEntryDesconto = '0,00';
        $this->nfeItemEntryOutros = '0,00';
        $this->recalcNfeEntryRowPreview();
        $this->nfeProdutoLookupOpen = false;
        $this->nfeProdutoResults = [];
        $this->nfeSelectedProdutoIndex = null;
        $this->clearNfeProdutoPreviewFoto();
        $this->dispatch('erp-nfe-focus-item-quantidade');
    }

    protected function applyProductToNfeRow(int $index, Product $product, float $qtd): void
    {
        $preview = $this->previewNfeProductRow($product, $qtd);

        $this->nfeModalRows[$index]['product_id'] = $product->id;
        $this->nfeModalRows[$index]['codigo'] = (string) $product->codigo;
        $this->nfeModalRows[$index]['descricao'] = mb_strtoupper($product->descricao, 'UTF-8');
        $this->nfeModalRows[$index]['cfop'] = (string) ($preview['cfop'] ?? '');
        $this->nfeModalRows[$index]['cst'] = (string) (($preview['cst'] ?? '') ?: ($preview['csosn'] ?? ''));
        $this->nfeModalRows[$index]['quantidade'] = ErpMoney::formatBr($qtd, 4);
        $this->nfeModalRows[$index]['valor_unitario'] = ErpMoney::formatBr((float) ($preview['valor_unitario'] ?? $product->preco_venda), 4);
        $this->nfeModalRows[$index]['unidade'] = mb_strtoupper((string) ($preview['unidade'] ?? $product->unidade ?: 'UN'), 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    protected function previewNfeProductRow(Product $product, float $qtd): array
    {
        $empresaId = $this->resolveEmpresaId();
        $calculated = app(NfeCalculoService::class)->calcular(
            [[
                'product_id' => $product->id,
                'descricao' => $product->descricao,
                'quantidade' => $qtd,
                'valor_unitario' => (float) $product->preco_venda,
                'desconto' => 0.0,
            ]],
            $empresaId ? Empresa::query()->find($empresaId) : null,
            $this->nfeForm['uf'] ?? null,
        );

        return $calculated['rows'][0] ?? [];
    }

    protected function recalcNfeEntryRowPreview(): void
    {
        $qtd = ErpMoney::parseBr($this->nfeItemEntryQtd, 4);
        $preco = ErpMoney::parseBr($this->nfeItemEntryPreco, 4);
        $desconto = ErpMoney::parseBr($this->nfeItemEntryDesconto, 2);
        $outros = ErpMoney::parseBr($this->nfeItemEntryOutros, 2);

        if ($qtd <= 0) {
            $qtd = 1;
        }

        if ($preco < 0) {
            $preco = 0;
        }

        if ($desconto < 0) {
            $desconto = 0;
        }

        if ($outros < 0) {
            $outros = 0;
        }

        $this->nfeItemEntryQtd = ErpMoney::formatBr($qtd, 4);
        $this->nfeItemEntryPreco = ErpMoney::formatBr($preco, 4);
        $this->nfeItemEntryDesconto = ErpMoney::formatBr($desconto, 2);
        $this->nfeItemEntryOutros = ErpMoney::formatBr($outros, 2);
        $this->nfeItemEntryTotalDisplay = ErpMoney::formatBr(
            round(($qtd * $preco) + $outros - $desconto, 2),
            2,
        );
    }

    protected function clearNfeItemEntryRow(): void
    {
        $this->nfeItemPendingProductId = null;
        $this->nfeItemCodigoInput = '';
        $this->nfeItemProdutoSearch = '';
        $this->nfeItemEntryCfop = '';
        $this->nfeItemEntryCst = '';
        $this->nfeItemEntryPreco = '';
        $this->nfeItemEntryQtd = '1,0000';
        $this->nfeItemEntryUnidade = 'UN';
        $this->nfeItemEntryDesconto = '0,00';
        $this->nfeItemEntryOutros = '0,00';
        $this->nfeItemEntryTotalDisplay = '0,00';
        $this->nfeDescontoModalOpen = false;
        $this->nfeItemAjusteAlvo = null;
        $this->nfeProdutoLookupOpen = false;
        $this->nfeProdutoResults = [];
        $this->nfeSelectedProdutoIndex = null;
        $this->clearNfeProdutoPreviewFoto();
    }

    protected function syncNfeProdutoPreviewFotoFromSelection(): void
    {
        $index = $this->nfeSelectedProdutoIndex;

        if ($index === null || ! isset($this->nfeProdutoResults[$index])) {
            $this->clearNfeProdutoPreviewFoto();

            return;
        }

        $productId = (int) $this->nfeProdutoResults[$index]['id'];
        $this->nfeProdutoPreviewFotoUrl = Product::query()->find($productId)?->fotoUrl();
    }

    protected function clearNfeProdutoPreviewFoto(): void
    {
        $this->nfeProdutoPreviewFotoUrl = null;
    }

    protected function findNfeProductByCodigo(string $codigo): ?Product
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo', $codigo)
                    ->orWhere('referencia', $codigo)
                    ->orWhere('codigo_barras', $codigo)
                    ->orWhere('codigo_barras_caixa', $codigo);

                // Alguns cadastros gravam código numérico sem zero à esquerda / tipagem mista.
                if (ctype_digit($codigo)) {
                    $query->orWhereRaw('CAST(codigo AS CHAR) = ?', [$codigo]);
                }
            })
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function formatNfeModalRowsForDisplay(array $rows): array
    {
        $money2 = [
            'total', 'desconto', 'frete', 'seguro', 'outros',
            'base_icms', 'valor_icms', 'base_desoneracao', 'desc_desoneracao', 'valor_desoneracao',
            'base_ipi', 'valor_ipi', 'base_pis_icms', 'valor_pis_icms', 'base_cofins_icms', 'valor_cofins_icms',
            'v_ibs_mun', 'v_ibs_uf', 'v_cbs', 'bc_ibs',
        ];
        $money4 = [
            'quantidade', 'valor_unitario', 'aliq_icms', 'aliq_ipi', 'aliq_pis_icms', 'aliq_cofins_icms',
            'alq_cbs', 'alq_ibs_mun', 'alq_ibs_uf',
        ];

        return array_map(function (array $row) use ($money2, $money4): array {
            foreach ($money2 as $field) {
                $row[$field] = ErpMoney::formatBr((float) ($row[$field] ?? 0), 2);
            }

            foreach ($money4 as $field) {
                $row[$field] = ErpMoney::formatBr((float) ($row[$field] ?? 0), 4);
            }

            $row['cst'] = (string) (($row['cst'] ?? '') ?: ($row['csosn'] ?? ''));
            $row['info_adicionais'] = (string) ($row['info_adicionais'] ?? '');
            $row['motivo_desoneracao'] = (string) ($row['motivo_desoneracao'] ?? '');
            $row['class_trib'] = (string) ($row['class_trib'] ?? '');
            $row['cst_ibs_cbs'] = (string) ($row['cst_ibs_cbs'] ?? '');

            return $row;
        }, $rows);
    }
}
