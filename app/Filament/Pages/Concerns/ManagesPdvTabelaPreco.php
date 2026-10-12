<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PriceTable;
use App\Models\Product;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ProductTabelaPrecoService;
use Filament\Notifications\Notification;

trait ManagesPdvTabelaPreco
{
    public ?int $pdvPriceTableId = null;

    /** @var array<int, array<string, mixed>> */
    public array $tabelaPrecoResults = [];

    public ?int $selectedTabelaPrecoIndex = null;

    /** Modo item: exige escolha ao lançar produto com usa_tab_preco. */
    public bool $pdvTabelaPrecoItemMode = false;

    public ?int $pdvPendingTabPrecoProductId = null;

    public ?float $pdvPendingTabPrecoQtd = null;

    public ?float $pdvPendingTabPrecoPrecoInformado = null;

    public ?int $pdvPendingTabPrecoGradeId = null;

    public ?int $pdvPendingTabPrecoSerialId = null;

    public ?string $pdvPendingTabPrecoDescricao = null;

    /** Após escolher tabela do item, continua o fluxo normal (qtd/preço) em vez de lançar. */
    public bool $pdvPendingTabPrecoContinuarLaunch = false;

    /** Tabela já confirmada no lançamento atual (evita pedir de novo na confirmação de preço). */
    public ?string $pdvLaunchTabelaNivel = null;

    public ?int $pdvLaunchPriceTableId = null;

    protected function loadPdvPriceTableFromSession(): void
    {
        $sessionId = session('erp.pdv.price_table_id');

        if (filled($sessionId)) {
            $this->pdvPriceTableId = (int) $sessionId;

            return;
        }

        $this->pdvPriceTableId = app(ProductTabelaPrecoService::class)->tabelaIdDoNivel(
            ProductTabelaPrecoService::NIVEL_VAREJO
        );
    }

    public function getPdvHabilitarTabelaPrecoProperty(): bool
    {
        return $this->pdvConfig()->habilitarTabelaPreco();
    }

    public function getPdvTabelaPrecoLabelProperty(): string
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $nivel = $tabSvc->nivelDaTabelaId($this->pdvPriceTableId);

        if (! $nivel) {
            return 'VAREJO';
        }

        return mb_strtoupper($tabSvc->labelNivel($nivel), 'UTF-8');
    }

    public function openTabelaPrecoModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if (! $this->pdvConfig()->habilitarTabelaPreco()) {
            $this->notifyPdvError('Tabela de preço não habilitada nos parâmetros.');

            return;
        }

        $this->pdvTabelaPrecoItemMode = false;
        $this->refreshTabelaPrecoResults();
        $this->openPdvModal('tabela_preco');
        $this->dispatch('erp-pdv-focus-tabela-preco');
    }

    /**
     * Exige escolha de tabela ao lançar produto com usa_tab_preco.
     *
     * @return bool true se o modal foi aberto (lançamento adiado)
     */
    protected function maybeRequireTabelaPrecoForProduct(
        Product $product,
        float $quantidade,
        ?float $preco = null,
        ?int $productGradeId = null,
        ?int $productSerialId = null,
        ?string $descricaoOverride = null,
        bool $continuarLaunch = false,
    ): bool {
        if (! ($product->usa_tab_preco ?? false)) {
            return false;
        }

        $tabSvc = app(ProductTabelaPrecoService::class);
        $tabSvc->carregarItens($product);
        $opcoes = $tabSvc->opcoesParaProduto($product, max(0.001, $quantidade));

        if ($opcoes === []) {
            $this->notifyPdvError('Produto com Tab. Preço sem tabelas habilitadas. Revise o cadastro.');

            return true;
        }

        $this->pdvTabelaPrecoItemMode = true;
        $this->pdvPendingTabPrecoProductId = (int) $product->id;
        $this->pdvPendingTabPrecoQtd = max(0.001, $quantidade);
        $this->pdvPendingTabPrecoPrecoInformado = $preco;
        $this->pdvPendingTabPrecoGradeId = $productGradeId;
        $this->pdvPendingTabPrecoSerialId = $productSerialId;
        $this->pdvPendingTabPrecoDescricao = $descricaoOverride;
        $this->pdvPendingTabPrecoContinuarLaunch = $continuarLaunch;

        $this->tabelaPrecoResults = collect($opcoes)
            ->map(fn (array $op): array => [
                'price_table_id' => $op['price_table_id'],
                'nivel' => $op['nivel'],
                'codigo' => $op['codigo'],
                'descricao' => $op['descricao'],
                'preco' => $op['preco'],
                'label' => $op['label'].' — R$ '.ErpMoney::formatBr($op['preco']),
            ])
            ->values()
            ->all();

        $selectedIndex = 0;

        foreach ($this->tabelaPrecoResults as $index => $row) {
            if ((int) ($row['price_table_id'] ?? 0) === (int) $this->pdvPriceTableId) {
                $selectedIndex = $index;
                break;
            }
        }

        $this->selectedTabelaPrecoIndex = $selectedIndex;
        $this->openPdvModal('tabela_preco');
        $this->dispatch('erp-pdv-focus-tabela-preco');

        return true;
    }

    public function refreshTabelaPrecoResults(): void
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $ids = $tabSvc->tabelasPadraoIds();

        $this->tabelaPrecoResults = PriceTable::query()
            ->whereIn('id', array_values($ids))
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get()
            ->map(fn (PriceTable $table): array => [
                'price_table_id' => $table->id,
                'nivel' => $tabSvc->nivelDaTabela($table),
                'codigo' => $table->codigo,
                'descricao' => mb_strtoupper($table->descricao, 'UTF-8'),
                'label' => trim($table->codigo.' — '.mb_strtoupper($table->descricao, 'UTF-8')),
            ])
            ->values()
            ->all();

        $selectedIndex = null;

        foreach ($this->tabelaPrecoResults as $index => $row) {
            if ((int) ($row['price_table_id'] ?? 0) === (int) $this->pdvPriceTableId) {
                $selectedIndex = $index;
                break;
            }
        }

        $this->selectedTabelaPrecoIndex = $selectedIndex ?? ($this->tabelaPrecoResults === [] ? null : 0);
    }

    public function selectTabelaPrecoRow(int $index): void
    {
        if (isset($this->tabelaPrecoResults[$index])) {
            $this->selectedTabelaPrecoIndex = $index;
        }
    }

    public function moveTabelaPrecoSelection(int $delta): void
    {
        if ($this->tabelaPrecoResults === []) {
            return;
        }

        $count = count($this->tabelaPrecoResults);
        $index = ($this->selectedTabelaPrecoIndex ?? 0) + $delta;
        $this->selectedTabelaPrecoIndex = max(0, min($count - 1, $index));
        // Setas no modal usam navegação local (JS); este método fica como fallback.
        $this->skipRender();
    }

    /** Confirma a linha escolhida no cliente (setas sem round-trip). */
    public function confirmTabelaPrecoAt(int $index): void
    {
        $this->selectTabelaPrecoRow($index);
        $this->confirmTabelaPreco();
    }

    public function confirmTabelaPreco(): void
    {
        $index = $this->selectedTabelaPrecoIndex;

        if ($index === null || ! isset($this->tabelaPrecoResults[$index])) {
            $this->notifyPdvError('Selecione uma tabela de preço.');

            return;
        }

        $row = $this->tabelaPrecoResults[$index];
        $tableId = (int) ($row['price_table_id'] ?? 0);
        $nivel = (string) ($row['nivel'] ?? '');

        if ($tableId <= 0) {
            $this->notifyPdvError('Tabela de preço inválida.');

            return;
        }

        if ($this->pdvTabelaPrecoItemMode) {
            $this->confirmTabelaPrecoItem($tableId, $nivel, $row);

            return;
        }

        $this->pdvPriceTableId = $tableId;
        session(['erp.pdv.price_table_id' => $tableId]);
        $this->recheckCupomPricesForTable();
        $this->closePdvModal();

        Notification::make()
            ->title('Tabela de preço alterada.')
            ->body($this->pdvTabelaPrecoLabel)
            ->success()
            ->send();

        $this->dispatch('erp-pdv-focus-search');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function confirmTabelaPrecoItem(int $tableId, string $nivel, array $row): void
    {
        $productId = (int) ($this->pdvPendingTabPrecoProductId ?? 0);
        $product = $productId > 0 ? Product::query()->find($productId) : null;

        if (! $product) {
            $this->clearPendingTabelaPrecoItem();
            $this->closePdvModal();
            $this->notifyPdvError('Produto não encontrado.');

            return;
        }

        $quantidade = (float) ($this->pdvPendingTabPrecoQtd ?? 1);
        $preco = (float) ($row['preco'] ?? 0);

        if ($preco <= 0) {
            $preco = app(ProductTabelaPrecoService::class)
                ->resolvePrecoNivel($product, $nivel, $quantidade);
        }

        $this->pdvPriceTableId = $tableId;
        session(['erp.pdv.price_table_id' => $tableId]);

        $continuar = $this->pdvPendingTabPrecoContinuarLaunch;
        $gradeId = $this->pdvPendingTabPrecoGradeId;
        $serialId = $this->pdvPendingTabPrecoSerialId;
        $descricao = $this->pdvPendingTabPrecoDescricao;

        $this->clearPendingTabelaPrecoItem();
        $this->closePdvModal();

        $this->pdvLaunchTabelaNivel = $nivel;
        $this->pdvLaunchPriceTableId = $tableId;

        if ($continuar) {
            $this->pdvPendingLaunchProductId = (int) $product->id;
            $this->pdvLaunchPreco = ErpMoney::formatBr($preco);
            $this->beginPdvLaunchQuantityConfirmation($product);

            return;
        }

        $this->confirmAddProduct(
            $product,
            $quantidade,
            $preco,
            $gradeId,
            $serialId,
            $descricao,
            $tableId,
            $nivel,
            skipTabelaPrecoCheck: true,
        );
    }

    protected function clearPendingTabelaPrecoItem(): void
    {
        $this->pdvTabelaPrecoItemMode = false;
        $this->pdvPendingTabPrecoProductId = null;
        $this->pdvPendingTabPrecoQtd = null;
        $this->pdvPendingTabPrecoPrecoInformado = null;
        $this->pdvPendingTabPrecoGradeId = null;
        $this->pdvPendingTabPrecoSerialId = null;
        $this->pdvPendingTabPrecoDescricao = null;
        $this->pdvPendingTabPrecoContinuarLaunch = false;
    }

    protected function recheckCupomPricesForTable(): void
    {
        if ($this->cupomItens === []) {
            return;
        }

        $tabSvc = app(ProductTabelaPrecoService::class);
        $nivel = $tabSvc->nivelDaTabelaId($this->pdvPriceTableId);

        foreach ($this->cupomItens as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()->find($productId);

            if (! $product || ! ($product->usa_tab_preco ?? false)) {
                continue;
            }

            if (! $nivel || ! $tabSvc->habilitaNivel($product, $nivel)) {
                continue;
            }

            $quantidade = (float) ($item['quantidade'] ?? 1);
            $preco = $tabSvc->resolvePrecoNivel($product, $nivel, $quantidade);
            $this->cupomItens[$index]['preco'] = $preco;
            $this->cupomItens[$index]['preco_base'] = $preco;
            $this->cupomItens[$index]['price_table_id'] = $this->pdvPriceTableId;
            $this->cupomItens[$index]['tabela_nivel'] = $nivel;
            $this->cupomItens[$index]['total'] = round($quantidade * $preco, 2);
        }

        $this->persistCupomToSession();
    }

    public function cancelTabelaPreco(): void
    {
        $this->clearPendingTabelaPrecoItem();
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }
}
