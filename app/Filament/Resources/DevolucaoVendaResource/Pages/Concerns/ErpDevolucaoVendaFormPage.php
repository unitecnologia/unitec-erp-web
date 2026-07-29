<?php

namespace App\Filament\Resources\DevolucaoVendaResource\Pages\Concerns;

use App\Filament\Resources\DevolucaoVendaResource;
use App\Models\DevolucaoVenda;
use App\Models\DevolucaoVendaItem;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Vendas\FinalizarDevolucaoVendaService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ErpDevolucaoVendaFormPage
{
    public string $numeroDisplay = '';

    public string $dataDevolucao = '';

    public string $horaDevolucao = '';

    public string $tipoDevolucao = DevolucaoVenda::TIPO_PARCIAL;

    public string $observacoes = '';

    public string $totalDisplay = '0,00';

    public ?int $vendaId = null;

    public string $vendaSearch = '';

    public bool $vendaLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $vendaResults = [];

    public ?int $selectedVendaIndex = null;

    public string $vendaNumero = '';

    public ?int $clienteId = null;

    public string $clienteNome = '';

    public ?int $vendedorId = null;

    /** @var array<int, array<string, mixed>> */
    public array $itens = [];

    public ?int $selectedItemIndex = null;

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
            'erp-devolucao-venda-form-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.devolucoes-venda.form.window'),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                    ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
            ]);
    }

    public function initializeDevolucaoFormDefaults(): void
    {
        $momento = ErpTimezone::toLocal();

        $this->numeroDisplay = DevolucaoVenda::nextNumero();
        $this->dataDevolucao = $momento->format('Y-m-d');
        $this->horaDevolucao = $momento->format('H:i');
        $this->tipoDevolucao = DevolucaoVenda::TIPO_PARCIAL;
        $this->observacoes = '';
        $this->totalDisplay = '0,00';
        $this->vendaId = null;
        $this->vendaSearch = '';
        $this->vendaNumero = '';
        $this->clienteId = null;
        $this->clienteNome = '';
        $this->vendedorId = null;
        $this->itens = [];
        $this->selectedItemIndex = null;
        $this->closeVendaLookup();
    }

    public function loadDevolucaoFormFromRecord(DevolucaoVenda $record): void
    {
        $record->loadMissing(['cliente', 'vendedor', 'itens.product', 'venda']);

        $this->numeroDisplay = (string) ($record->numero ?: $record->id);
        $this->dataDevolucao = $record->data?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->horaDevolucao = $record->horaExibicao() ?? '';
        $this->tipoDevolucao = $record->tipo_devolucao ?: DevolucaoVenda::TIPO_PARCIAL;
        $this->observacoes = (string) ($record->observacoes ?? '');
        $this->totalDisplay = ErpMoney::formatBr((float) $record->total);
        $this->vendaId = $record->venda_id;
        $this->vendaNumero = (string) ($record->venda_numero ?? '');
        $this->vendaSearch = $this->vendaNumero !== ''
            ? $this->vendaNumero.' — '.$record->clienteNome()
            : '';
        $this->clienteId = $record->cliente_id;
        $this->clienteNome = $record->clienteNome();
        $this->vendedorId = $record->vendedor_id;

        $this->itens = $record->itens
            ->sortBy('item')
            ->values()
            ->map(fn (DevolucaoVendaItem $item, int $index): array => [
                'id' => $item->id,
                'key' => 'item-'.$item->id,
                'venda_item_id' => $item->venda_item_id,
                'product_id' => $item->product_id,
                'produto_codigo' => $item->produto_codigo ?: ($item->product?->codigo ?? ''),
                'produto_descricao' => $item->produto_descricao ?: ($item->product?->descricao ?? ''),
                'qtd' => ErpMoney::formatBr((float) $item->qtd, 3),
                'qtd_vendida' => ErpMoney::formatBr((float) $item->qtd_vendida, 3),
                'preco' => ErpMoney::formatBr((float) $item->preco),
                'total' => ErpMoney::formatBr((float) $item->total),
            ])
            ->all();

        $this->recalcTotais();
        $this->closeVendaLookup();
    }

    /**
     * @return array<int, array{id: int, nome: string}>
     */
    public function vendedorOptions(): array
    {
        return Vendedor::query()
            ->orderBy('nome')
            ->limit(300)
            ->get(['id', 'nome'])
            ->map(fn (Vendedor $v): array => [
                'id' => (int) $v->id,
                'nome' => (string) $v->nome,
            ])
            ->all();
    }

    public function updatedVendaSearch(): void
    {
        $this->vendaLookupOpen = true;
        $this->selectedVendaIndex = null;
        $this->searchVendas();
    }

    public function openVendaLookup(): void
    {
        $this->vendaLookupOpen = true;
        $this->searchVendas();
    }

    public function closeVendaLookup(): void
    {
        $this->vendaLookupOpen = false;
        $this->vendaResults = [];
        $this->selectedVendaIndex = null;
    }

    public function searchVendas(): void
    {
        $term = trim($this->vendaSearch);

        if (mb_strlen($term) < 1) {
            $this->vendaResults = [];

            return;
        }

        $like = '%'.mb_strtoupper($term, 'UTF-8').'%';

        $this->vendaResults = Venda::query()
            ->with('cliente')
            ->where(function ($q) use ($like, $term): void {
                $q->where('numero', 'like', $like);
                if (is_numeric($term)) {
                    $q->orWhere('numero', 'like', '%'.ltrim($term, '0').'%');
                }
                $q->orWhereHas('cliente', fn ($c) => $c->where('nome_razao', 'like', $like));
            })
            ->whereIn('status', [Venda::STATUS_FECHADO, Venda::STATUS_GRAVADO])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Venda $venda): array => [
                'id' => (int) $venda->id,
                'numero' => (string) $venda->numero,
                'data' => $venda->data?->format('d/m/Y') ?? '',
                'cliente' => (string) ($venda->cliente?->nome_razao ?? '—'),
                'total' => ErpMoney::formatBr((float) $venda->total),
                'label' => trim($venda->numero).' — '.($venda->cliente?->nome_razao ?? 'SEM CLIENTE')
                    .' — R$ '.ErpMoney::formatBr((float) $venda->total),
            ])
            ->all();
    }

    public function moveVendaSelection(int $delta): void
    {
        if ($this->vendaResults === []) {
            return;
        }

        $count = count($this->vendaResults);
        $current = $this->selectedVendaIndex ?? -1;
        $next = $current + $delta;

        if ($next < 0) {
            $next = $count - 1;
        } elseif ($next >= $count) {
            $next = 0;
        }

        $this->selectedVendaIndex = $next;
    }

    public function handleVendaEnter(): void
    {
        if ($this->selectedVendaIndex !== null && isset($this->vendaResults[$this->selectedVendaIndex])) {
            $this->selectVenda((int) $this->vendaResults[$this->selectedVendaIndex]['id']);

            return;
        }

        if (count($this->vendaResults) === 1) {
            $this->selectVenda((int) $this->vendaResults[0]['id']);
        }
    }

    public function selectVenda(int $vendaId): void
    {
        $venda = Venda::query()->with(['cliente', 'itens.product', 'vendedor'])->find($vendaId);

        if (! $venda) {
            Notification::make()->title('Venda não encontrada.')->warning()->send();

            return;
        }

        if (! in_array($venda->status, [Venda::STATUS_FECHADO, Venda::STATUS_GRAVADO], true)) {
            Notification::make()
                ->title('Só é possível devolver venda fechada ou gravada.')
                ->warning()
                ->send();

            return;
        }

        $jaDevolvido = DevolucaoVendaItem::query()
            ->whereNotNull('venda_item_id')
            ->whereHas('devolucao', function ($q) use ($venda): void {
                $q->where('venda_id', $venda->id)
                    ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA);
            })
            ->get(['venda_item_id', 'qtd'])
            ->groupBy('venda_item_id')
            ->map(fn ($rows): float => round((float) $rows->sum('qtd'), 3));

        $this->vendaId = (int) $venda->id;
        $this->vendaNumero = (string) $venda->numero;
        $this->vendaSearch = trim($venda->numero).' — '.($venda->cliente?->nome_razao ?? '');
        $this->clienteId = $venda->cliente_id;
        $this->clienteNome = (string) ($venda->cliente?->nome_razao ?? '');
        $this->vendedorId = $venda->vendedor_id;

        $this->itens = $venda->itens->values()->map(function ($item, int $index) use ($jaDevolvido): ?array {
            $qtdVendida = (float) $item->quantidade;
            $prev = (float) ($jaDevolvido[(int) $item->id] ?? 0);
            $disponivel = round(max(0, $qtdVendida - $prev), 3);

            if ($disponivel <= 0) {
                return null;
            }

            $preco = (float) $item->valor_item;

            return [
                'id' => null,
                'key' => 'new-'.Str::uuid()->toString(),
                'venda_item_id' => (int) $item->id,
                'product_id' => $item->product_id,
                'produto_codigo' => (string) ($item->product?->codigo ?? ''),
                'produto_descricao' => (string) ($item->product?->descricao ?? 'ITEM'),
                'qtd' => ErpMoney::formatBr($disponivel, 3),
                'qtd_vendida' => ErpMoney::formatBr($qtdVendida, 3),
                'preco' => ErpMoney::formatBr($preco),
                'total' => ErpMoney::formatBr(round($disponivel * $preco, 2)),
            ];
        })->filter()->values()->all();

        if ($this->itens === []) {
            Notification::make()
                ->title('Esta venda já foi totalmente devolvida.')
                ->warning()
                ->send();

            $this->vendaId = null;
            $this->vendaNumero = '';
            $this->vendaSearch = '';
            $this->clienteId = null;
            $this->clienteNome = '';
            $this->vendedorId = null;

            return;
        }

        $this->tipoDevolucao = DevolucaoVenda::TIPO_TOTAL;
        $this->detectTipoDevolucao();
        $this->recalcTotais();
        $this->closeVendaLookup();
        $this->selectedItemIndex = $this->itens !== [] ? 0 : null;
    }

    public function updatedItens($value, ?string $key = null): void
    {
        if ($key === null || ! str_contains($key, '.')) {
            $this->recalcTotais();

            return;
        }

        [$index, $field] = explode('.', $key, 2);

        if (! is_numeric($index) || ! isset($this->itens[(int) $index])) {
            return;
        }

        $index = (int) $index;

        if (in_array($field, ['qtd', 'preco'], true)) {
            $qtd = ErpMoney::parseBr($this->itens[$index]['qtd'] ?? 0, 3);
            $qtdVendida = ErpMoney::parseBr($this->itens[$index]['qtd_vendida'] ?? 0, 3);
            $vendaItemId = (int) ($this->itens[$index]['venda_item_id'] ?? 0);
            $maxQtd = $qtdVendida;

            if ($vendaItemId > 0 && $this->vendaId) {
                $excetoId = $this->isEditingDevolucao() ? (int) $this->record->getKey() : 0;
                $prev = (float) DevolucaoVendaItem::query()
                    ->where('venda_item_id', $vendaItemId)
                    ->whereHas('devolucao', function ($q) use ($excetoId): void {
                        $q->where('venda_id', $this->vendaId)
                            ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA);

                        if ($excetoId > 0) {
                            $q->where('id', '!=', $excetoId);
                        }
                    })
                    ->sum('qtd');
                $maxQtd = round(max(0, $qtdVendida - $prev), 3);
            }

            if ($maxQtd > 0 && $qtd > $maxQtd) {
                $qtd = $maxQtd;
                $this->itens[$index]['qtd'] = ErpMoney::formatBr($qtd, 3);
            }

            $preco = ErpMoney::parseBr($this->itens[$index]['preco'] ?? 0);
            $this->itens[$index]['total'] = ErpMoney::formatBr(round($qtd * $preco, 2));
        }

        $this->detectTipoDevolucao();
        $this->recalcTotais();
    }

    public function selectItem(int $index): void
    {
        if (! isset($this->itens[$index])) {
            return;
        }

        $this->selectedItemIndex = $index;
    }

    public function removeSelectedItem(): void
    {
        if ($this->selectedItemIndex === null || ! isset($this->itens[$this->selectedItemIndex])) {
            return;
        }

        unset($this->itens[$this->selectedItemIndex]);
        $this->itens = array_values($this->itens);
        $this->selectedItemIndex = $this->itens !== [] ? min($this->selectedItemIndex, count($this->itens) - 1) : null;
        $this->detectTipoDevolucao();
        $this->recalcTotais();
    }

    protected function detectTipoDevolucao(): void
    {
        if ($this->itens === []) {
            $this->tipoDevolucao = DevolucaoVenda::TIPO_PARCIAL;

            return;
        }

        $isTotal = collect($this->itens)->every(function (array $row): bool {
            $qtd = ErpMoney::parseBr($row['qtd'] ?? 0, 3);
            $vendida = ErpMoney::parseBr($row['qtd_vendida'] ?? 0, 3);

            return $vendida > 0 && abs($qtd - $vendida) < 0.0005;
        });

        $this->tipoDevolucao = $isTotal
            ? DevolucaoVenda::TIPO_TOTAL
            : DevolucaoVenda::TIPO_PARCIAL;
    }

    public function recalcTotais(): void
    {
        $total = 0.0;

        foreach ($this->itens as $row) {
            $total += ErpMoney::parseBr($row['total'] ?? 0);
        }

        $this->totalDisplay = ErpMoney::formatBr($total);
    }

    protected function isEditingDevolucao(): bool
    {
        return $this instanceof EditRecord;
    }

    public function gravarDevolucao(): void
    {
        if (! $this->validateBeforeSave()) {
            return;
        }

        if (! $this->persistDevolucao(finalizar: false)) {
            return;
        }

        Notification::make()
            ->title('Devolução gravada com sucesso!')
            ->success()
            ->send();
    }

    public function finalizarDevolucao(): void
    {
        if (! $this->validateBeforeSave()) {
            return;
        }

        if (! $this->persistDevolucao(finalizar: true)) {
            return;
        }

        Notification::make()
            ->title('Devolução finalizada.')
            ->body('Estoque devolvido e financeiro aplicado (estorno de títulos em aberto e/ou saída no Livro Caixa).')
            ->success()
            ->send();

        ErpScreen::set('Devolução de Venda');
        $this->redirect(DevolucaoVendaResource::getUrl('index'), navigate: false);
    }

    protected function validateBeforeSave(): bool
    {
        if (! $this->vendaId) {
            Notification::make()->title('Selecione a venda de origem.')->warning()->send();

            return false;
        }

        $venda = Venda::query()->find($this->vendaId);

        if (! $venda || ! in_array($venda->status, [Venda::STATUS_FECHADO, Venda::STATUS_GRAVADO], true)) {
            Notification::make()
                ->title('Só é possível devolver venda fechada ou gravada.')
                ->warning()
                ->send();

            return false;
        }

        $itensValidos = collect($this->itens)->filter(
            fn (array $row): bool => ErpMoney::parseBr($row['qtd'] ?? 0, 3) > 0
        );

        if ($itensValidos->isEmpty()) {
            Notification::make()->title('Informe ao menos um item com quantidade.')->warning()->send();

            return false;
        }

        return true;
    }

    protected function persistDevolucao(bool $finalizar): bool
    {
        $this->recalcTotais();
        $this->detectTipoDevolucao();

        $total = ErpMoney::parseBr($this->totalDisplay);
        $createdId = null;
        $empresaId = ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id;
        $recordId = null;

        try {
            DB::transaction(function () use ($total, $empresaId, &$createdId, &$recordId): void {
                $momento = ErpTimezone::toLocal();

                $attributes = [
                    'empresa_id' => $empresaId,
                    'venda_id' => $this->vendaId,
                    'venda_numero' => $this->vendaNumero ?: null,
                    'cliente_id' => $this->clienteId,
                    'cliente_nome' => mb_strtoupper(trim($this->clienteNome), 'UTF-8') ?: null,
                    'vendedor_id' => $this->vendedorId,
                    'usuario_id' => Auth::id(),
                    'data' => $this->dataDevolucao ?: $momento->format('Y-m-d'),
                    'hora' => $this->normalizeHora($this->horaDevolucao) ?? $momento->format('H:i:s'),
                    'tipo_devolucao' => $this->tipoDevolucao ?: DevolucaoVenda::TIPO_PARCIAL,
                    'observacoes' => trim($this->observacoes) ?: null,
                    'total' => $total,
                    // Sempre grava aberta; a finalização (estoque/financeiro) sobe o status.
                    'situacao' => DevolucaoVenda::SITUACAO_ABERTA,
                ];

                if ($this->isEditingDevolucao()) {
                    /** @var DevolucaoVenda $record */
                    $record = $this->record;
                    $record->update($attributes);
                } else {
                    $record = DevolucaoVenda::query()->create([
                        'numero' => DevolucaoVenda::nextNumero(),
                        ...$attributes,
                    ]);
                    $createdId = $record->getKey();
                }

                $recordId = (int) $record->id;
                $keptIds = [];
                $itemSeq = 0;

                foreach ($this->itens as $index => $row) {
                    $qtd = ErpMoney::parseBr($row['qtd'] ?? 0, 3);

                    if ($qtd <= 0) {
                        continue;
                    }

                    $itemSeq++;
                    $itemData = [
                        'item' => $itemSeq,
                        'product_id' => filled($row['product_id'] ?? null) ? (int) $row['product_id'] : null,
                        'venda_item_id' => filled($row['venda_item_id'] ?? null) ? (int) $row['venda_item_id'] : null,
                        'produto_codigo' => (string) ($row['produto_codigo'] ?? '') ?: null,
                        'produto_descricao' => mb_strtoupper((string) ($row['produto_descricao'] ?? ''), 'UTF-8') ?: null,
                        'qtd' => $qtd,
                        'qtd_vendida' => ErpMoney::parseBr($row['qtd_vendida'] ?? 0, 3),
                        'preco' => ErpMoney::parseBr($row['preco'] ?? 0),
                        'total' => ErpMoney::parseBr($row['total'] ?? 0),
                    ];

                    if (filled($row['id'] ?? null)) {
                        $item = DevolucaoVendaItem::query()->find($row['id']);

                        if ($item && $item->devolucao_venda_id === $record->id) {
                            $item->update($itemData);
                            $keptIds[] = $item->id;
                            $this->itens[$index]['id'] = $item->id;

                            continue;
                        }
                    }

                    $item = $record->itens()->create($itemData);
                    $keptIds[] = $item->id;
                    $this->itens[$index]['id'] = $item->id;
                    $this->itens[$index]['key'] = 'item-'.$item->id;
                }

                $record->itens()->whereNotIn('id', $keptIds)->delete();
            });
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível salvar a devolução.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return false;
        }

        if ($finalizar && $recordId) {
            try {
                $record = DevolucaoVenda::query()->findOrFail($recordId);
                (new FinalizarDevolucaoVendaService())->finalizar($record);
            } catch (DomainException $exception) {
                Notification::make()
                    ->title('Não foi possível finalizar a devolução.')
                    ->body($exception->getMessage().' O documento ficou aberto para correção.')
                    ->danger()
                    ->send();

                if ($createdId !== null) {
                    $this->redirect(
                        DevolucaoVendaResource::getUrl('edit', ['record' => $createdId]),
                        navigate: false,
                    );
                }

                return false;
            } catch (\Throwable $exception) {
                report($exception);

                Notification::make()
                    ->title('Não foi possível finalizar a devolução.')
                    ->body($exception->getMessage().' O documento ficou aberto para correção.')
                    ->danger()
                    ->send();

                if ($createdId !== null) {
                    $this->redirect(
                        DevolucaoVendaResource::getUrl('edit', ['record' => $createdId]),
                        navigate: false,
                    );
                }

                return false;
            }
        }

        if ($createdId !== null && ! $finalizar) {
            $this->redirect(
                DevolucaoVendaResource::getUrl('edit', ['record' => $createdId]),
                navigate: false,
            );

            return true;
        }

        if ($this->isEditingDevolucao() && ! $finalizar) {
            $this->loadDevolucaoFormFromRecord($this->record->fresh(['cliente', 'itens.product', 'vendedor', 'venda']));
        }

        return true;
    }

    protected function normalizeHora(?string $hora): ?string
    {
        $hora = trim((string) $hora);

        if ($hora === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora) === 1) {
            return strlen($hora) === 5 ? $hora.':00' : $hora;
        }

        return null;
    }

    public function handleDevolucaoFormEscape(): void
    {
        ErpScreen::set('Devolução de Venda');
        $this->redirect(DevolucaoVendaResource::getUrl('index'), navigate: false);
    }

    public function dataDevolucaoDisplay(): string
    {
        if ($this->dataDevolucao === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($this->dataDevolucao)->format('d/m/Y');
        } catch (\Throwable) {
            return $this->dataDevolucao;
        }
    }
}
