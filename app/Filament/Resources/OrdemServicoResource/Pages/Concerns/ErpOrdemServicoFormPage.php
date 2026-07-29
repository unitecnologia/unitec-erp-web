<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Filament\Resources\OrdemServicoResource;
use App\Filament\Resources\PersonResource;
use App\Filament\Resources\ProductResource;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\Vendedor;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ErpOrdemServicoFormPage
{
    public string $activeFormTab = 'dados';

    public string $activeItemTab = 'servicos';

    public string $clienteSearch = '';

    public bool $clienteLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $clienteResults = [];

    public ?int $selectedClienteIndex = null;

    public ?int $clienteId = null;

    public string $documento = '';

    public string $fone1 = '';

    public string $nome = '';

    public string $endereco = '';

    public string $bairro = '';

    public string $cidade = '';

    public string $uf = 'SC';

    public ?int $atendenteId = null;

    public string $dataInicio = '';

    public string $horaInicio = '';

    public string $previsaoEntrega = '';

    public string $dataTermino = '';

    public string $horaTermino = '';

    public string $numeroSerie = '';

    public string $descricao = '';

    public string $descricao2 = '';

    public string $modelo = '';

    public string $marca = '';

    public string $ano = '';

    public string $placa = '';

    public string $km = '';

    public string $modeloVeiculo = '';

    public string $marcaVeiculo = '';

    public string $placaVeiculo = '';

    public string $corVeiculo = '';

    public string $chassiVeiculo = '';

    public string $problema = '';

    public string $observacoes = '';

    public string $laudo = '';

    /** @var array<int, array<string, mixed>> */
    public array $itens = [];

    public ?int $selectedItemIndex = null;

    public string $itemCodigoInput = '';

    public string $itemProdutoSearch = '';

    public bool $produtoLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $produtoResults = [];

    public ?int $selectedProdutoIndex = null;

    public ?int $itemPendingProductId = null;

    public string $itemQtdInput = '1,000';

    public string $itemPrecoInput = '';

    public string $barcodeInput = '';

    public string $subtotalPecas = '0,00';

    public string $subtotalServicos = '0,00';

    public string $subtotalGeral = '0,00';

    public string $descPecas = '0,00';

    public string $descServicos = '0,00';

    public string $totalPecas = '0,00';

    public string $totalServicos = '0,00';

    public string $totalGeral = '0,00';

    public bool $overlayProductOpen = false;

    public bool $overlayPersonOpen = false;

    public ?int $itemDeleteConfirmIndex = null;

    public bool $isConfirmingPendingItem = false;

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
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-os-form-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.ordens-servico.form.window'),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                    ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
            ]);
    }

    public function setActiveFormTab(string $tab): void
    {
        if (in_array($tab, ['dados', 'equipamento', 'defeito', 'observacoes'], true)) {
            $this->activeFormTab = $tab;
        }
    }

    public function setActiveItemTab(string $tab): void
    {
        if (in_array($tab, ['servicos', 'pecas'], true)) {
            $this->activeItemTab = $tab;
            $this->selectedItemIndex = null;
            $this->clearItemEntryRow();
        }
    }

    public function isEditingOs(): bool
    {
        return $this instanceof EditRecord;
    }

    public function osReadOnly(): bool
    {
        return $this->isEditingOs() && ! ($this->record?->isEditable() ?? true);
    }

    public function osNumeroDisplay(): string
    {
        if ($this->isEditingOs()) {
            return (string) ($this->record?->numero ?? '');
        }

        return OrdemServico::nextNumero();
    }

    /**
     * @return array<int, array{id: int, nome: string}>
     */
    public function atendenteOptions(): array
    {
        return Vendedor::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Vendedor $vendedor): array => [
                'id' => $vendedor->id,
                'nome' => mb_strtoupper((string) $vendedor->nome, 'UTF-8'),
            ])
            ->all();
    }

    public function getProductOverlayUrlProperty(): string
    {
        return ProductResource::getUrl('create');
    }

    public function getPersonOverlayUrlProperty(): string
    {
        return PersonResource::getUrl('create') . '?tipo=clientes';
    }

    protected function initializeOsFormDefaults(): void
    {
        $momento = ErpTimezone::toLocal();

        $this->atendenteId = Auth::user()?->vendedor_id;
        $this->dataInicio = $momento->format('Y-m-d');
        $this->horaInicio = $momento->format('H:i');
        $this->previsaoEntrega = '';
        $this->dataTermino = '';
        $this->horaTermino = '';
        $this->activeFormTab = 'dados';
        $this->activeItemTab = 'servicos';
        $this->itens = [];
        $this->syncTotaisDisplay(0, 0, 0, 0);

        $this->data = [
            'numero' => OrdemServico::nextNumero(),
            'situacao' => OrdemServico::SITUACAO_ABERTA,
        ];
        $this->form->fill($this->data);
    }

    protected function loadOsFormFromRecord(OrdemServico $ordem): void
    {
        $ordem->load(['cliente', 'itens.product', 'itens.funcionario']);

        $this->data = [
            'numero' => $ordem->numero,
            'situacao' => $ordem->situacao,
        ];
        $this->form->fill($this->data);

        $this->clienteId = $ordem->cliente_id;
        $this->nome = mb_strtoupper((string) ($ordem->nome ?? ''), 'UTF-8');
        $this->clienteSearch = $this->nome !== ''
            ? $this->nome
            : mb_strtoupper((string) ($ordem->cliente?->nome_razao ?? ''), 'UTF-8');
        $this->documento = (string) ($ordem->documento ?? '');
        $this->fone1 = (string) ($ordem->fone1 ?? '');
        $this->endereco = mb_strtoupper((string) ($ordem->endereco ?? ''), 'UTF-8');
        $this->bairro = mb_strtoupper((string) ($ordem->bairro ?? ''), 'UTF-8');
        $this->cidade = mb_strtoupper((string) ($ordem->cidade ?? ''), 'UTF-8');
        $this->uf = mb_strtoupper((string) ($ordem->uf ?: 'SC'), 'UTF-8');
        $this->atendenteId = $ordem->atendente_id;

        $this->dataInicio = $ordem->data_inicio?->format('Y-m-d') ?? '';
        $this->horaInicio = $ordem->horaInicioExibicao() ?? '';
        $this->previsaoEntrega = $ordem->previsao_entrega?->format('Y-m-d\TH:i') ?? '';
        $this->dataTermino = $ordem->data_termino?->format('Y-m-d') ?? '';
        $this->horaTermino = $ordem->hora_termino
            ? substr((string) $ordem->hora_termino, 0, 5)
            : '';

        $this->numeroSerie = (string) ($ordem->numero_serie ?? '');
        $this->descricao = mb_strtoupper((string) ($ordem->descricao ?? ''), 'UTF-8');
        $this->descricao2 = mb_strtoupper((string) ($ordem->descricao2 ?? ''), 'UTF-8');
        $this->modelo = mb_strtoupper((string) ($ordem->modelo ?? ''), 'UTF-8');
        $this->marca = mb_strtoupper((string) ($ordem->marca ?? ''), 'UTF-8');
        $this->ano = (string) ($ordem->ano ?? '');
        $this->placa = mb_strtoupper((string) ($ordem->placa ?? ''), 'UTF-8');
        $this->km = (string) ($ordem->km ?? '');

        $this->modeloVeiculo = mb_strtoupper((string) ($ordem->modelo_veiculo ?? ''), 'UTF-8');
        $this->marcaVeiculo = mb_strtoupper((string) ($ordem->marca_veiculo ?? ''), 'UTF-8');
        $this->placaVeiculo = mb_strtoupper((string) ($ordem->placa_veiculo ?? ''), 'UTF-8');
        $this->corVeiculo = mb_strtoupper((string) ($ordem->cor_veiculo ?? ''), 'UTF-8');
        $this->chassiVeiculo = mb_strtoupper((string) ($ordem->chassi_veiculo ?? ''), 'UTF-8');

        $this->problema = (string) ($ordem->problema ?? '');
        $this->observacoes = (string) ($ordem->observacoes ?? '');
        $this->laudo = (string) ($ordem->laudo ?? '');

        $this->itens = $ordem->itens
            ->values()
            ->map(fn (OrdemServicoItem $item): array => $this->mapItemToRow($item))
            ->all();

        $this->descPecas = ErpMoney::formatBr((float) $ordem->vl_desc_pecas);
        $this->descServicos = ErpMoney::formatBr((float) $ordem->vl_desc_servicos);
        $this->recalcTotais();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapItemToRow(OrdemServicoItem $item): array
    {
        $concluido = '';

        if ($item->data_termino) {
            $hora = $item->hora_termino
                ? substr((string) $item->hora_termino, 0, 5)
                : '00:00';
            $concluido = $item->data_termino->format('Y-m-d') . 'T' . $hora;
        }

        return [
            'id' => $item->id,
            'key' => 'item-' . $item->id,
            'tipo' => in_array($item->tipo, ['S', 'P'], true) ? $item->tipo : 'P',
            'product_id' => $item->product_id,
            'product_codigo' => $item->product?->codigo ?? '',
            'discriminacao' => mb_strtoupper((string) ($item->discriminacao ?? $item->product?->descricao ?? ''), 'UTF-8'),
            'qtd' => ErpMoney::formatBr((float) $item->qtd, 3),
            'preco' => ErpMoney::formatBr((float) $item->preco),
            'total' => ErpMoney::formatBr((float) $item->total),
            'funcionario_id' => $item->funcionario_id,
            'concluido_em' => $concluido,
        ];
    }

    public function updatedClienteSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->clienteSearch !== $upper) {
            $this->clienteSearch = $upper;
        }

        $this->clienteLookupOpen = true;
        $this->refreshClienteResults();
    }

    public function openClienteLookup(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->clienteLookupOpen = true;

        if (filled(trim($this->clienteSearch))) {
            $this->refreshClienteResults();
        }
    }

    public function refreshClienteResults(): void
    {
        $term = trim($this->clienteSearch);

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true);

        if ($term !== '') {
            $like = '%' . $term . '%';
            $digits = preg_replace('/\D/', '', $term) ?? '';

            $query->where(function ($sub) use ($like, $digits, $term): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $digitsLike = '%' . $digits . '%';
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        [$digitsLike]
                    );
                }

                if (ctype_digit($term)) {
                    $sub->orWhere('codigo', 'like', $like);
                }
            });
        }

        $this->clienteResults = $query
            ->orderBy('nome_razao')
            ->limit(50)
            ->get()
            ->map(fn (Person $person): array => [
                'id' => $person->id,
                'nome' => mb_strtoupper($person->nome_razao, 'UTF-8'),
                'fantasia' => mb_strtoupper((string) ($person->apelido_fantasia ?? ''), 'UTF-8'),
                'cpf_cnpj' => $person->cpf_cnpj ?? '',
            ])
            ->all();

        $this->selectedClienteIndex = $this->clienteResults === [] ? null : 0;
    }

    public function moveClienteSelection(int $delta): void
    {
        if ($this->clienteResults === []) {
            return;
        }

        $index = ($this->selectedClienteIndex ?? 0) + $delta;
        $count = count($this->clienteResults);
        $this->selectedClienteIndex = max(0, min($count - 1, $index));
    }

    public function selectClienteResult(int $index): void
    {
        if (! isset($this->clienteResults[$index])) {
            return;
        }

        $this->selectedClienteIndex = $index;
        $this->confirmClienteSelection();
    }

    public function confirmClienteSelection(): void
    {
        $index = $this->selectedClienteIndex;

        if ($index === null || ! isset($this->clienteResults[$index])) {
            $this->clienteLookupOpen = false;

            return;
        }

        $person = Person::query()->find($this->clienteResults[$index]['id']);

        if (! $person) {
            return;
        }

        $this->clienteId = $person->id;
        $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
        $this->applyClienteFields($person);
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;
    }

    public function handleClienteEnter(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if ($this->clienteLookupOpen) {
            $this->confirmClienteSelection();
        }
    }

    protected function applyClienteFields(?Person $person): void
    {
        if (! $person) {
            $this->documento = '';
            $this->fone1 = '';
            $this->nome = '';
            $this->endereco = '';
            $this->bairro = '';
            $this->cidade = '';
            $this->uf = 'SC';

            return;
        }

        $this->nome = mb_strtoupper((string) $person->nome_razao, 'UTF-8');
        $this->documento = (string) ($person->cpf_cnpj ?? '');
        $this->fone1 = (string) ($person->fone1 ?? '');
        $this->endereco = mb_strtoupper((string) ($person->endereco ?? ''), 'UTF-8');
        $this->bairro = mb_strtoupper((string) ($person->bairro ?? ''), 'UTF-8');
        $this->cidade = mb_strtoupper((string) ($person->cidade_nome ?? ''), 'UTF-8');
        $this->uf = mb_strtoupper((string) ($person->uf ?: 'SC'), 'UTF-8');

        if ($person->vendedor_loja_id) {
            $this->atendenteId = $person->vendedor_loja_id;
        }
    }

    public function closeClienteLookup(): void
    {
        $this->clienteLookupOpen = false;
    }

    public function confirmClienteSelectionOnBlur(): void
    {
        if (! $this->clienteLookupOpen) {
            return;
        }

        if ($this->selectedClienteIndex !== null && isset($this->clienteResults[$this->selectedClienteIndex])) {
            $this->confirmClienteSelection();

            return;
        }

        $this->closeClienteLookup();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itensByActiveTab(): array
    {
        $tipo = $this->activeItemTab === 'servicos' ? 'S' : 'P';

        return collect($this->itens)
            ->filter(fn (array $row): bool => ($row['tipo'] ?? 'P') === $tipo)
            ->all();
    }

    public function selectItemRow(int $index): void
    {
        $this->selectedItemIndex = $index;
    }

    public function updateItemField(int $index, string $field, string $value): void
    {
        if ($this->osReadOnly() || ! isset($this->itens[$index])) {
            return;
        }

        if (! in_array($field, ['qtd', 'preco', 'discriminacao', 'funcionario_id', 'concluido_em'], true)) {
            return;
        }

        $itens = $this->itens;

        if ($field === 'discriminacao') {
            $itens[$index][$field] = mb_strtoupper(trim($value), 'UTF-8');
        } elseif ($field === 'funcionario_id') {
            $itens[$index][$field] = filled($value) ? (int) $value : null;
        } elseif ($field === 'concluido_em') {
            $itens[$index][$field] = trim($value);
        } else {
            $itens[$index][$field] = $value;
            $itens[$index] = $this->recalcItemRowData($itens[$index]);
        }

        $this->itens = $itens;

        if (in_array($field, ['qtd', 'preco'], true)) {
            $this->recalcTotais();
        }
    }

    public function blurItemFieldByKey(string $key, string $field, string $value): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index === null) {
            return;
        }

        $this->updateItemField($index, $field, $value);
    }

    protected function findItemIndexByKey(string $key): ?int
    {
        foreach ($this->itens as $index => $row) {
            if (($row['key'] ?? '') === $key) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function recalcItemRowData(array $row): array
    {
        $qtd = max(0, ErpMoney::parseBr($row['qtd'] ?? 0, 3));
        $preco = max(0, ErpMoney::parseBr($row['preco'] ?? 0));

        $row['qtd'] = ErpMoney::formatBr($qtd, 3);
        $row['preco'] = ErpMoney::formatBr($preco);
        $row['total'] = ErpMoney::formatBr(round($qtd * $preco, 2));

        return $row;
    }

    public function applyDescontoPecas(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->descPecas = ErpMoney::formatBr(max(0, ErpMoney::parseBr($this->descPecas)));
        $this->recalcTotais();
    }

    public function applyDescontoServicos(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->descServicos = ErpMoney::formatBr(max(0, ErpMoney::parseBr($this->descServicos)));
        $this->recalcTotais();
    }

    protected function recalcTotais(): void
    {
        $subPecas = 0.0;
        $subServicos = 0.0;

        foreach ($this->itens as $row) {
            $total = ErpMoney::parseBr($row['total'] ?? 0);
            if (($row['tipo'] ?? 'P') === 'S') {
                $subServicos += $total;
            } else {
                $subPecas += $total;
            }
        }

        $descPecas = max(0, ErpMoney::parseBr($this->descPecas));
        $descServicos = max(0, ErpMoney::parseBr($this->descServicos));
        $totalPecas = round(max(0, $subPecas - $descPecas), 2);
        $totalServicos = round(max(0, $subServicos - $descServicos), 2);

        $this->syncTotaisDisplay($subPecas, $subServicos, $totalPecas, $totalServicos);
    }

    protected function syncTotaisDisplay(
        float $subPecas,
        float $subServicos,
        float $totalPecas,
        float $totalServicos,
    ): void {
        $this->subtotalPecas = ErpMoney::formatBr($subPecas);
        $this->subtotalServicos = ErpMoney::formatBr($subServicos);
        $this->subtotalGeral = ErpMoney::formatBr(round($subPecas + $subServicos, 2));
        $this->totalPecas = ErpMoney::formatBr($totalPecas);
        $this->totalServicos = ErpMoney::formatBr($totalServicos);
        $this->totalGeral = ErpMoney::formatBr(round($totalPecas + $totalServicos, 2));
    }

    public function requestDeleteItem(int $index): void
    {
        if ($this->osReadOnly() || ! isset($this->itens[$index])) {
            return;
        }

        $this->selectedItemIndex = $index;
        $this->itemDeleteConfirmIndex = $index;
    }

    public function confirmDeleteItem(): void
    {
        if ($this->itemDeleteConfirmIndex === null || ! isset($this->itens[$this->itemDeleteConfirmIndex])) {
            $this->itemDeleteConfirmIndex = null;

            return;
        }

        $index = $this->itemDeleteConfirmIndex;
        $this->itemDeleteConfirmIndex = null;

        $itens = $this->itens;
        array_splice($itens, $index, 1);
        $this->itens = array_values($itens);
        $this->selectedItemIndex = null;
        $this->recalcTotais();
    }

    public function cancelDeleteItem(): void
    {
        $this->itemDeleteConfirmIndex = null;
    }

    public function deleteSelectedItem(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if ($this->selectedItemIndex === null || ! isset($this->itens[$this->selectedItemIndex])) {
            Notification::make()
                ->title('Selecione um item para excluir.')
                ->warning()
                ->send();

            return;
        }

        $this->requestDeleteItem($this->selectedItemIndex);
    }

    public function handleItemCodigoEnter(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if (blank(trim($this->itemCodigoInput))) {
            return;
        }

        $this->submitItemByCodigo();
    }

    public function submitItemByCodigo(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $codigo = mb_strtoupper(trim($this->itemCodigoInput), 'UTF-8');

        if ($codigo === '') {
            return;
        }

        $product = $this->findProductByCodigo($codigo);

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código informado.')
                ->warning()
                ->send();

            return;
        }

        $this->stageProductForEntry($product);
    }

    public function submitBarcodeItem(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $raw = trim($this->barcodeInput);

        if ($raw === '') {
            return;
        }

        $qtd = 1.0;
        $term = $raw;

        if (str_contains($raw, '*')) {
            [$qtdPart, $codePart] = explode('*', $raw, 2);
            $qtd = max(0.001, ErpMoney::parseBr($qtdPart, 3));
            $term = trim($codePart);
        }

        $product = $this->findProductByTerm(mb_strtoupper($term, 'UTF-8'));

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->appendProductItem($product, $qtd);
        $this->barcodeInput = '';
        $this->clearItemEntryRow();
    }

    public function searchItemProduto(string $value): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->itemProdutoSearch = mb_strtoupper($value, 'UTF-8');
        $this->produtoLookupOpen = true;
        $this->refreshProdutoResults();
    }

    public function openProdutoLookup(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->produtoLookupOpen = true;

        if (filled(trim($this->itemProdutoSearch))) {
            $this->refreshProdutoResults();
        }
    }

    public function refreshProdutoResults(): void
    {
        $term = trim($this->itemProdutoSearch);

        if ($term === '') {
            $this->produtoResults = [];
            $this->selectedProdutoIndex = null;

            return;
        }

        $like = '%' . $term . '%';
        $preferServico = $this->activeItemTab === 'servicos';

        $this->produtoResults = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($like, $term): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('descricao', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like);

                if (ctype_digit($term)) {
                    $query->orWhere('codigo', $term);
                }
            })
            ->orderByRaw('CASE WHEN is_servico = ? THEN 0 ELSE 1 END', [$preferServico ? 1 : 0])
            ->orderBy('descricao')
            ->limit(40)
            ->get(['id', 'codigo', 'descricao', 'is_servico'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'codigo' => $product->codigo,
                'descricao' => mb_strtoupper($product->descricao, 'UTF-8'),
            ])
            ->all();

        $this->selectedProdutoIndex = $this->produtoResults === [] ? null : 0;
    }

    public function moveProdutoSelection(int $delta): void
    {
        if ($this->produtoResults === []) {
            return;
        }

        $index = ($this->selectedProdutoIndex ?? 0) + $delta;
        $count = count($this->produtoResults);
        $this->selectedProdutoIndex = max(0, min($count - 1, $index));
    }

    public function selectProdutoResult(int $index): void
    {
        if (! isset($this->produtoResults[$index])) {
            return;
        }

        $this->selectedProdutoIndex = $index;
        $this->confirmProdutoSelection();
    }

    public function confirmProdutoSelection(): void
    {
        $index = $this->selectedProdutoIndex;

        if ($index === null || ! isset($this->produtoResults[$index])) {
            $this->produtoLookupOpen = false;

            return;
        }

        $product = Product::query()->find($this->produtoResults[$index]['id']);

        if (! $product) {
            return;
        }

        $this->stageProductForEntry($product);
    }

    public function submitItemProdutoSearch(?string $term = null): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if ($term !== null) {
            $this->itemProdutoSearch = mb_strtoupper($term, 'UTF-8');
        }

        if (trim($this->itemProdutoSearch) === '') {
            return;
        }

        $this->refreshProdutoResults();

        if ($this->produtoResults === []) {
            Notification::make()->title('Produto não encontrado.')->warning()->send();

            return;
        }

        if (count($this->produtoResults) === 1) {
            $this->selectProdutoResult(0);

            return;
        }

        $this->produtoLookupOpen = true;
        $this->selectedProdutoIndex = 0;
    }

    public function closeProdutoLookup(): void
    {
        $this->produtoLookupOpen = false;
    }

    public function confirmPendingItemEntry(): void
    {
        if ($this->osReadOnly() || $this->isConfirmingPendingItem || $this->itemPendingProductId === null) {
            return;
        }

        $product = Product::query()->find($this->itemPendingProductId);

        if (! $product) {
            $this->clearItemEntryRow();

            return;
        }

        $qtd = ErpMoney::parseBr($this->itemQtdInput, 3);

        if ($qtd <= 0) {
            Notification::make()->title('Informe a quantidade do item.')->warning()->send();

            return;
        }

        $preco = ErpMoney::parseBr($this->itemPrecoInput);

        $this->isConfirmingPendingItem = true;

        try {
            $this->appendProductItem($product, $qtd, $preco);
            $this->clearItemEntryRow();
        } finally {
            $this->isConfirmingPendingItem = false;
        }
    }

    protected function stageProductForEntry(Product $product): void
    {
        $preco = (float) ($product->preco_venda ?? 0);
        $tipo = $product->is_servico ? 'S' : 'P';

        $this->activeItemTab = $tipo === 'S' ? 'servicos' : 'pecas';
        $this->itemPendingProductId = $product->id;
        $this->itemCodigoInput = (string) $product->codigo;
        $this->itemProdutoSearch = mb_strtoupper($product->descricao, 'UTF-8');
        $this->itemQtdInput = ErpMoney::formatBr(1, 3);
        $this->itemPrecoInput = ErpMoney::formatBr($preco);
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;
    }

    protected function clearItemEntryRow(): void
    {
        $this->itemPendingProductId = null;
        $this->itemCodigoInput = '';
        $this->itemProdutoSearch = '';
        $this->itemQtdInput = '1,000';
        $this->itemPrecoInput = '';
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;
    }

    protected function appendProductItem(Product $product, float $qtd = 1.0, ?float $preco = null): void
    {
        $preco ??= (float) ($product->preco_venda ?? 0);
        $tipo = $product->is_servico ? 'S' : 'P';
        $total = round($qtd * $preco, 2);

        $this->activeItemTab = $tipo === 'S' ? 'servicos' : 'pecas';

        $itens = $this->itens;
        array_unshift($itens, [
            'id' => null,
            'key' => 'new-' . Str::uuid()->toString(),
            'tipo' => $tipo,
            'product_id' => $product->id,
            'product_codigo' => $product->codigo,
            'discriminacao' => mb_strtoupper($product->descricao, 'UTF-8'),
            'qtd' => ErpMoney::formatBr($qtd, 3),
            'preco' => ErpMoney::formatBr($preco),
            'total' => ErpMoney::formatBr($total),
            'funcionario_id' => $this->atendenteId,
            'concluido_em' => '',
        ]);

        $this->itens = array_values($itens);
        $this->selectedItemIndex = 0;
        $this->recalcTotais();
    }

    protected function findProductByCodigo(string $codigo): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo', $codigo)
                    ->orWhere('referencia', $codigo)
                    ->orWhere('codigo_barras', $codigo)
                    ->orWhere('codigo_barras_caixa', $codigo);
            })
            ->first();
    }

    protected function findProductByTerm(string $term): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($term): void {
                $query->where('codigo', $term)
                    ->orWhere('codigo_barras', $term)
                    ->orWhere('codigo_barras_caixa', $term)
                    ->orWhere('referencia', $term)
                    ->orWhere('descricao', 'like', '%' . $term . '%');
            })
            ->first();
    }

    protected function validateBeforeSave(bool $finalizar): bool
    {
        if ($this->clienteId === null || blank($this->clienteSearch)) {
            Notification::make()->title('Informe o Cliente!')->warning()->send();

            return false;
        }

        if ($this->atendenteId === null) {
            Notification::make()->title('Informe o Atendente!')->warning()->send();

            return false;
        }

        if ($finalizar && $this->itens === []) {
            Notification::make()->title('Informe os Itens da OS!')->warning()->send();

            return false;
        }

        return true;
    }

    public function gravarOs(): void
    {
        if (! $this->validateBeforeSave(finalizar: false)) {
            return;
        }

        if (! $this->persistOs(finalizar: false)) {
            return;
        }

        Notification::make()
            ->title('Ordem de serviço gravada com sucesso!')
            ->success()
            ->send();
    }

    public function finalizarOs(): void
    {
        if (! $this->validateBeforeSave(finalizar: true)) {
            return;
        }

        if (! $this->persistOs(finalizar: true)) {
            return;
        }

        Notification::make()
            ->title('Ordem de serviço finalizada.')
            ->success()
            ->send();

        ErpScreen::set('Ordem de Serviço');
        $this->redirect(OrdemServicoResource::getUrl('index'), navigate: false);
    }

    protected function persistOs(bool $finalizar): bool
    {
        $this->recalcTotais();

        $subPecas = ErpMoney::parseBr($this->subtotalPecas);
        $subServicos = ErpMoney::parseBr($this->subtotalServicos);
        $descPecas = ErpMoney::parseBr($this->descPecas);
        $descServicos = ErpMoney::parseBr($this->descServicos);
        $totalPecas = ErpMoney::parseBr($this->totalPecas);
        $totalServicos = ErpMoney::parseBr($this->totalServicos);
        $totalGeral = ErpMoney::parseBr($this->totalGeral);
        $createdId = null;
        $empresaId = Auth::user()?->empresa_id;

        try {
            DB::transaction(function () use (
                $subPecas,
                $subServicos,
                $descPecas,
                $descServicos,
                $totalPecas,
                $totalServicos,
                $totalGeral,
                $finalizar,
                $empresaId,
                &$createdId,
            ): void {
                $momento = ErpTimezone::toLocal();

                $attributes = [
                    'empresa_id' => $empresaId,
                    'cliente_id' => $this->clienteId,
                    'atendente_id' => $this->atendenteId,
                    'usuario_id' => Auth::id(),
                    'documento' => trim($this->documento) ?: null,
                    'nome' => mb_strtoupper(trim($this->nome ?: $this->clienteSearch), 'UTF-8') ?: null,
                    'fone1' => trim($this->fone1) ?: null,
                    'endereco' => mb_strtoupper(trim($this->endereco), 'UTF-8') ?: null,
                    'bairro' => mb_strtoupper(trim($this->bairro), 'UTF-8') ?: null,
                    'cidade' => mb_strtoupper(trim($this->cidade), 'UTF-8') ?: null,
                    'uf' => mb_strtoupper(trim($this->uf), 'UTF-8') ?: null,
                    'data_inicio' => $this->dataInicio ?: $momento->format('Y-m-d'),
                    'hora_inicio' => $this->normalizeHora($this->horaInicio) ?? $momento->format('H:i:s'),
                    'previsao_entrega' => filled($this->previsaoEntrega) ? str_replace('T', ' ', $this->previsaoEntrega) : null,
                    'numero_serie' => trim($this->numeroSerie) ?: null,
                    'descricao' => mb_strtoupper(trim($this->descricao), 'UTF-8') ?: null,
                    'descricao2' => mb_strtoupper(trim($this->descricao2), 'UTF-8') ?: null,
                    'modelo' => mb_strtoupper(trim($this->modelo), 'UTF-8') ?: null,
                    'marca' => mb_strtoupper(trim($this->marca), 'UTF-8') ?: null,
                    'ano' => trim($this->ano) ?: null,
                    'placa' => mb_strtoupper(trim($this->placa), 'UTF-8') ?: null,
                    'km' => trim($this->km) ?: null,
                    'modelo_veiculo' => mb_strtoupper(trim($this->modeloVeiculo), 'UTF-8') ?: null,
                    'marca_veiculo' => mb_strtoupper(trim($this->marcaVeiculo), 'UTF-8') ?: null,
                    'placa_veiculo' => mb_strtoupper(trim($this->placaVeiculo), 'UTF-8') ?: null,
                    'cor_veiculo' => mb_strtoupper(trim($this->corVeiculo), 'UTF-8') ?: null,
                    'chassi_veiculo' => mb_strtoupper(trim($this->chassiVeiculo), 'UTF-8') ?: null,
                    'problema' => trim($this->problema) ?: null,
                    'observacoes' => trim($this->observacoes) ?: null,
                    'laudo' => trim($this->laudo) ?: null,
                    'subtotal' => round($subPecas + $subServicos, 2),
                    'subtotal_pecas' => $subPecas,
                    'subtotal_servicos' => $subServicos,
                    'vl_desc_pecas' => $descPecas,
                    'vl_desc_servicos' => $descServicos,
                    'total_produtos' => $totalPecas,
                    'total_servicos' => $totalServicos,
                    'total_geral' => $totalGeral,
                    'situacao' => $finalizar
                        ? OrdemServico::SITUACAO_FINALIZADA
                        : OrdemServico::SITUACAO_ABERTA,
                ];

                if ($finalizar) {
                    $attributes['data_termino'] = $momento->format('Y-m-d');
                    $attributes['hora_termino'] = $momento->format('H:i:s');
                } else {
                    $attributes['data_termino'] = $this->dataTermino ?: null;
                    $attributes['hora_termino'] = $this->normalizeHora($this->horaTermino);
                }

                if ($this->isEditingOs()) {
                    /** @var OrdemServico $ordem */
                    $ordem = $this->record;
                    $ordem->update($attributes);
                } else {
                    $ordem = OrdemServico::query()->create([
                        'numero' => OrdemServico::nextNumero(),
                        'data_emissao' => $momento->format('Y-m-d'),
                        ...$attributes,
                    ]);
                    $createdId = $ordem->getKey();
                }

                $keptIds = [];

                foreach ($this->itens as $index => $row) {
                    [$dataTermino, $horaTermino] = $this->parseConcluidoEm((string) ($row['concluido_em'] ?? ''));

                    $itemData = [
                        'empresa_id' => $empresaId,
                        'usuario_id' => Auth::id(),
                        'product_id' => filled($row['product_id'] ?? null) ? (int) $row['product_id'] : null,
                        'funcionario_id' => filled($row['funcionario_id'] ?? null) ? (int) $row['funcionario_id'] : null,
                        'tipo' => ($row['tipo'] ?? 'P') === 'S' ? 'S' : 'P',
                        'discriminacao' => mb_strtoupper((string) ($row['discriminacao'] ?? ''), 'UTF-8') ?: null,
                        'qtd' => ErpMoney::parseBr($row['qtd'] ?? 0, 3),
                        'preco' => ErpMoney::parseBr($row['preco'] ?? 0),
                        'total' => ErpMoney::parseBr($row['total'] ?? 0),
                        'data_termino' => $dataTermino,
                        'hora_termino' => $horaTermino,
                    ];

                    if (filled($row['id'] ?? null)) {
                        $item = OrdemServicoItem::query()->find($row['id']);

                        if ($item && $item->ordem_servico_id === $ordem->id) {
                            $item->update($itemData);
                            $keptIds[] = $item->id;
                            $this->itens[$index]['id'] = $item->id;

                            continue;
                        }
                    }

                    $item = $ordem->itens()->create($itemData);
                    $keptIds[] = $item->id;
                    $this->itens[$index]['id'] = $item->id;
                    $this->itens[$index]['key'] = 'item-' . $item->id;
                }

                $ordem->itens()->whereNotIn('id', $keptIds)->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível salvar a ordem de serviço.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return false;
        }

        if ($createdId !== null && ! $finalizar) {
            $this->redirect(
                OrdemServicoResource::getUrl('edit', ['record' => $createdId]),
                navigate: false,
            );

            return true;
        }

        if ($this->isEditingOs() && ! $finalizar) {
            $this->loadOsFormFromRecord($this->record->fresh(['cliente', 'itens.product', 'itens.funcionario']));
        }

        return true;
    }

    protected function normalizeHora(?string $hora): ?string
    {
        $hora = trim((string) $hora);

        if ($hora === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $hora) === 1) {
            return $hora . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora) === 1) {
            return $hora;
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function parseConcluidoEm(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [null, null];
        }

        $value = str_replace('T', ' ', $value);

        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s+(\d{2}:\d{2})(?::\d{2})?)?$/', $value, $m) === 1) {
            $hora = isset($m[2]) ? $m[2] . (strlen($m[2]) === 5 ? ':00' : '') : null;

            return [$m[1], $hora];
        }

        return [null, null];
    }

    public function openProdutosCadastro(): void
    {
        ErpScreen::set('Cadastro de Produtos');
        $this->overlayPersonOpen = false;
        $this->overlayProductOpen = true;
    }

    public function openPessoasCadastro(): void
    {
        ErpScreen::set('Cadastro de Pessoas');
        $this->overlayProductOpen = false;
        $this->overlayPersonOpen = true;
    }

    public function closeProductOverlay(): void
    {
        if (! $this->overlayProductOpen) {
            return;
        }

        $this->overlayProductOpen = false;
        ErpScreen::set('Lançamento OS');
    }

    public function closePersonOverlay(): void
    {
        if (! $this->overlayPersonOpen) {
            return;
        }

        $this->overlayPersonOpen = false;
        ErpScreen::set('Lançamento OS');
    }

    public function applyOverlayProdutoSaved(string $codigo): void
    {
        if (filled($codigo)) {
            $this->itemCodigoInput = mb_strtoupper(trim($codigo), 'UTF-8');
        }

        $this->closeProductOverlay();
    }

    public function applyOverlayPersonSaved(int $clienteId): void
    {
        $person = Person::query()->find($clienteId);

        if ($person) {
            $this->clienteId = $person->id;
            $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
            $this->applyClienteFields($person);
        }

        $this->closePersonOverlay();
    }

    public function handleOsFormEscape(): void
    {
        if ($this->overlayProductOpen) {
            $this->closeProductOverlay();

            return;
        }

        if ($this->overlayPersonOpen) {
            $this->closePersonOverlay();

            return;
        }

        if ($this->itemDeleteConfirmIndex !== null) {
            $this->cancelDeleteItem();

            return;
        }

        $this->cancelForm();
    }

    public function cancelForm(): void
    {
        ErpScreen::set('Ordem de Serviço');
        $this->redirect(OrdemServicoResource::getUrl('index'), navigate: false);
    }
}
