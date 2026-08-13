<?php

namespace App\Filament\Resources\DevolucaoCompraResource\Pages\Concerns;

use App\Models\Compra;
use App\Models\DevolucaoCompra;
use App\Models\DevolucaoCompraItem;
use App\Support\Erp\Compras\FinalizarDevolucaoCompraService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait ManagesDevolucaoCompraModal
{
    public bool $lancamentoModalOpen = false;

    public ?int $lancamentoId = null;

    public string $formCompraNumero = '';

    public string $formEmpresa = '';

    public string $formFornecedor = '';

    public string $formObservacoes = '';

    public string $formData = '';

    public string $formSituacao = 'aberta';

    /** @var array<int, array<string, mixed>> */
    public array $formItens = [];

    public float $formTotal = 0.0;

    public string $compraSearch = '';

    public bool $compraLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $compraLookupResults = [];

    public bool $compraSelecionarModalOpen = false;

    public string $compraSelecionarBusca = '';

    public int $compraSelecionarPagina = 1;

    public int $compraSelecionarTotal = 0;

    public int $compraSelecionarPorPagina = 15;

    /** @var array<int, array<string, mixed>> */
    public array $compraSelecionarResults = [];

    public ?int $formCompraId = null;

    public ?int $formFornecedorId = null;

    public ?int $formEmpresaId = null;

    public ?int $selectedItemIndex = null;

    public function openCreateModal(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'devolucoes_compra.create')) {
            return;
        }

        $this->resetLancamentoForm();

        $empresa = ErpContext::currentEmpresa();
        $this->formEmpresaId = $empresa?->id ? (int) $empresa->id : ErpContext::currentEmpresaId();
        $this->formEmpresa = (string) ($empresa?->fantasia ?: $empresa?->nome ?: $empresa?->razao_social ?: '—');
        $this->formData = ErpTimezone::toLocal()->format('Y-m-d');
        $this->formSituacao = DevolucaoCompra::SITUACAO_ABERTA;
        $this->lancamentoModalOpen = true;
    }

    public function openEditModal(int $id): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'devolucoes_compra.update')) {
            return;
        }

        $devolucao = DevolucaoCompra::query()
            ->with(['itens.product', 'empresa', 'fornecedor', 'compra'])
            ->find($id);

        if (! $devolucao) {
            Notification::make()->title('Devolução não encontrada.')->warning()->send();

            return;
        }

        if (! $devolucao->isEditable()) {
            Notification::make()
                ->title('Somente devolução aberta pode ser alterada.')
                ->warning()
                ->send();

            return;
        }

        $this->resetLancamentoForm();

        $this->lancamentoId = (int) $devolucao->id;
        $this->formCompraId = $devolucao->compra_id ? (int) $devolucao->compra_id : null;
        $this->formCompraNumero = (string) ($devolucao->compra_numero ?? '');
        $this->formEmpresaId = $devolucao->empresa_id ? (int) $devolucao->empresa_id : null;
        $this->formEmpresa = (string) (
            $devolucao->empresa?->fantasia
            ?: $devolucao->empresa?->nome
            ?: $devolucao->empresa?->razao_social
            ?: '—'
        );
        $this->formFornecedorId = $devolucao->fornecedor_id ? (int) $devolucao->fornecedor_id : null;
        $this->formFornecedor = $devolucao->fornecedorNome();
        $this->formObservacoes = (string) ($devolucao->observacoes ?? '');
        $this->formData = $devolucao->data?->format('Y-m-d') ?? ErpTimezone::toLocal()->format('Y-m-d');
        $this->formSituacao = (string) ($devolucao->situacao ?: DevolucaoCompra::SITUACAO_ABERTA);

        $this->formItens = $devolucao->itens
            ->sortBy('item')
            ->values()
            ->map(fn (DevolucaoCompraItem $item): array => [
                'id' => $item->id,
                'compra_item_id' => $item->compra_item_id ? (int) $item->compra_item_id : null,
                'product_id' => $item->product_id ? (int) $item->product_id : null,
                'produto_codigo' => (string) ($item->produto_codigo ?: $item->product?->codigo ?? ''),
                'produto_descricao' => (string) ($item->produto_descricao ?: $item->product?->descricao ?? ''),
                'qtd_comprada' => round((float) $item->qtd_comprada, 3),
                'qtd' => round((float) $item->qtd, 3),
                'preco' => round((float) $item->preco, 4),
                'total' => round((float) $item->total, 2),
            ])
            ->all();

        $this->recalcularTotais();
        $this->lancamentoModalOpen = true;
    }

    public function closeLancamentoModal(): void
    {
        $this->lancamentoModalOpen = false;
        $this->resetLancamentoForm();
    }

    public function updatedFormCompraNumero(): void
    {
        $this->compraSearch = $this->formCompraNumero;
        $this->searchCompras();
    }

    public function openCompraSelecionarModal(): void
    {
        if ($this->formSituacao !== DevolucaoCompra::SITUACAO_ABERTA) {
            return;
        }

        $this->compraLookupOpen = false;
        $this->compraLookupResults = [];
        $this->compraSelecionarBusca = '';
        $this->compraSelecionarPagina = 1;
        $this->loadCompraSelecionarResults();
        $this->compraSelecionarModalOpen = true;
    }

    public function closeCompraSelecionarModal(): void
    {
        $this->compraSelecionarModalOpen = false;
    }

    public function updatedCompraSelecionarBusca(): void
    {
        $this->compraSelecionarPagina = 1;
        $this->loadCompraSelecionarResults();
    }

    public function compraSelecionarPaginaAnterior(): void
    {
        if ($this->compraSelecionarPagina <= 1) {
            return;
        }

        $this->compraSelecionarPagina--;
        $this->loadCompraSelecionarResults();
    }

    public function compraSelecionarProximaPagina(): void
    {
        $totalPages = max(1, (int) ceil($this->compraSelecionarTotal / $this->compraSelecionarPorPagina));

        if ($this->compraSelecionarPagina >= $totalPages) {
            return;
        }

        $this->compraSelecionarPagina++;
        $this->loadCompraSelecionarResults();
    }

    public function selectCompraFromSelecionarModal(int $id): void
    {
        $this->closeCompraSelecionarModal();
        $this->selectCompra($id);
    }

    protected function loadCompraSelecionarResults(): void
    {
        $term = trim($this->compraSelecionarBusca);
        $empresaId = ErpContext::currentEmpresaId();
        $query = Compra::query()
            ->with('fornecedor')
            ->where('status', Compra::STATUS_FECHADA)
            ->whereDoesntHave(
                'devolucoes',
                fn ($devolucao) => $devolucao
                    ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA)
                    ->where('tipo_devolucao', DevolucaoCompra::TIPO_TOTAL),
            );

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        if ($term !== '') {
            $like = '%'.mb_strtoupper($term, 'UTF-8').'%';

            $query->where(function ($q) use ($term, $like): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('numero_nota', 'like', $like)
                    ->orWhere('chave_nfe', 'like', $like)
                    ->orWhereHas('fornecedor', fn ($fornecedor) => $fornecedor->where('nome_razao', 'like', $like));

                if (is_numeric($term)) {
                    $q->orWhere('numero', 'like', '%'.ltrim($term, '0').'%');
                }
            });
        }

        $this->compraSelecionarTotal = (int) $query->count();
        $totalPages = max(1, (int) ceil($this->compraSelecionarTotal / $this->compraSelecionarPorPagina));
        $this->compraSelecionarPagina = min(max(1, $this->compraSelecionarPagina), $totalPages);

        $this->compraSelecionarResults = $query
            ->orderByDesc('data_entrada')
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->forPage($this->compraSelecionarPagina, $this->compraSelecionarPorPagina)
            ->get()
            ->map(fn (Compra $compra): array => [
                'id' => (int) $compra->id,
                'numero' => (string) $compra->numero,
                'numero_nota' => (string) ($compra->numero_nota ?? '—'),
                'data' => $compra->data_entrada?->format('d/m/Y')
                    ?? $compra->data_emissao?->format('d/m/Y')
                    ?? '—',
                'fornecedor' => (string) ($compra->fornecedor?->nome_razao ?? '—'),
                'total' => ErpMoney::formatBr((float) $compra->total),
                'status' => Compra::statusLabels()[$compra->status] ?? mb_strtoupper((string) $compra->status, 'UTF-8'),
            ])
            ->all();
    }

    public function searchCompras(): void
    {
        $term = trim($this->compraSearch !== '' ? $this->compraSearch : $this->formCompraNumero);

        if (mb_strlen($term) < 1) {
            $this->compraLookupResults = [];
            $this->compraLookupOpen = false;

            return;
        }

        $like = '%'.mb_strtoupper($term, 'UTF-8').'%';
        $empresaId = ErpContext::currentEmpresaId();

        $query = Compra::query()
            ->with('fornecedor')
            ->where('status', Compra::STATUS_FECHADA)
            ->whereDoesntHave(
                'devolucoes',
                fn ($devolucao) => $devolucao
                    ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA)
                    ->where('tipo_devolucao', DevolucaoCompra::TIPO_TOTAL),
            )
            ->where(function ($q) use ($like, $term): void {
                $q->where('numero', 'like', $like);
                if (is_numeric($term)) {
                    $q->orWhere('numero', 'like', '%'.ltrim($term, '0').'%');
                }
                $q->orWhereHas('fornecedor', fn ($f) => $f->where('nome_razao', 'like', $like));
            })
            ->orderByDesc('data_entrada')
            ->orderByDesc('id')
            ->limit(20);

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        $this->compraLookupResults = $query->get()->map(fn (Compra $compra): array => [
            'id' => (int) $compra->id,
            'numero' => (string) $compra->numero,
            'data' => $compra->data_entrada?->format('d/m/Y')
                ?? $compra->data_emissao?->format('d/m/Y')
                ?? '',
            'fornecedor' => (string) ($compra->fornecedor?->nome_razao ?? '—'),
            'total' => ErpMoney::formatBr((float) $compra->total),
            'label' => trim($compra->numero).' — '.($compra->fornecedor?->nome_razao ?? 'SEM FORNECEDOR')
                .' — R$ '.ErpMoney::formatBr((float) $compra->total),
        ])->all();

        $this->compraLookupOpen = $this->compraLookupResults !== [];
    }

    public function selectCompra(int $id): void
    {
        $compra = Compra::query()
            ->where('id', $id)
            ->when(
                ErpContext::currentEmpresaId(),
                fn ($query, int $empresaId) => $query->where('empresa_id', $empresaId),
            )
            ->first();

        if (! $compra) {
            Notification::make()->title('Compra não encontrada.')->warning()->send();

            return;
        }

        $this->formCompraNumero = (string) $compra->numero;
        $this->compraSearch = (string) $compra->numero;
        $this->compraLookupOpen = false;
        $this->compraLookupResults = [];
        $this->closeCompraSelecionarModal();
        $this->importarCompra($id);
    }

    public function importarCompra(?int $compraId = null): void
    {
        $compra = null;

        if ($compraId) {
            $compra = Compra::query()->with(['itens.product', 'fornecedor', 'empresa'])->find($compraId);
        }

        if (! $compra && filled($this->formCompraNumero)) {
            $numero = trim($this->formCompraNumero);
            $empresaId = ErpContext::currentEmpresaId();

            $query = Compra::query()
                ->with(['itens.product', 'fornecedor', 'empresa'])
                ->where('status', Compra::STATUS_FECHADA)
                ->whereDoesntHave(
                    'devolucoes',
                    fn ($devolucao) => $devolucao
                        ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA)
                        ->where('tipo_devolucao', DevolucaoCompra::TIPO_TOTAL),
                )
                ->where(function ($q) use ($numero): void {
                    $q->where('numero', $numero);
                    if (is_numeric($numero)) {
                        $q->orWhere('numero', str_pad($numero, 6, '0', STR_PAD_LEFT))
                            ->orWhere('numero', ltrim($numero, '0'));
                    }
                })
                ->orderByDesc('id');

            if ($empresaId) {
                $query->where('empresa_id', $empresaId);
            }

            $compra = $query->first();
        }

        if (! $compra) {
            Notification::make()->title('Compra não encontrada.')->warning()->send();

            return;
        }

        if ($compra->status === Compra::STATUS_CANCELADA) {
            Notification::make()->title('Não é possível devolver compra cancelada.')->warning()->send();

            return;
        }

        $jaDevolvido = DevolucaoCompraItem::query()
            ->whereNotNull('compra_item_id')
            ->whereHas('devolucao', function ($q) use ($compra): void {
                $q->where('compra_id', $compra->id)
                    ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA);

                if ($this->lancamentoId) {
                    $q->where('id', '!=', $this->lancamentoId);
                }
            })
            ->get(['compra_item_id', 'qtd'])
            ->groupBy('compra_item_id')
            ->map(fn ($rows): float => round((float) $rows->sum('qtd'), 3));

        $this->formCompraId = (int) $compra->id;
        $this->formCompraNumero = (string) $compra->numero;
        $this->formFornecedorId = $compra->fornecedor_id ? (int) $compra->fornecedor_id : null;
        $this->formFornecedor = (string) ($compra->fornecedor?->nome_razao ?? '—');

        if ($compra->empresa) {
            $this->formEmpresaId = (int) $compra->empresa_id;
            $this->formEmpresa = (string) (
                $compra->empresa->fantasia
                ?: $compra->empresa->nome
                ?: $compra->empresa->razao_social
                ?: '—'
            );
        }

        $this->formItens = $compra->itens->values()->map(function ($item) use ($jaDevolvido): ?array {
            $qtdComprada = round((float) $item->quantidade, 3);
            $prev = (float) ($jaDevolvido[(int) $item->id] ?? 0);
            $disponivel = round(max(0, $qtdComprada - $prev), 3);

            if ($disponivel <= 0) {
                return null;
            }

            $preco = round((float) $item->valor_unitario, 4);

            return [
                'id' => null,
                'compra_item_id' => (int) $item->id,
                'product_id' => $item->product_id ? (int) $item->product_id : null,
                'produto_codigo' => (string) ($item->product?->codigo ?? ''),
                'produto_descricao' => (string) ($item->product?->descricao ?? 'ITEM'),
                'qtd_comprada' => $qtdComprada,
                'qtd' => $disponivel,
                'preco' => $preco,
                'total' => round($disponivel * $preco, 2),
            ];
        })->filter()->values()->all();

        if ($this->formItens === []) {
            Notification::make()
                ->title('Esta compra já foi totalmente devolvida.')
                ->warning()
                ->send();

            $this->formCompraId = null;
            $this->formCompraNumero = '';
            $this->formFornecedorId = null;
            $this->formFornecedor = '';
            $this->formItens = [];
            $this->formTotal = 0;

            return;
        }

        $this->recalcularTotais();
        $this->compraLookupOpen = false;
        $this->compraLookupResults = [];
        $this->selectedItemIndex = $this->formItens !== [] ? 0 : null;

        Notification::make()
            ->title('Itens da compra importados.')
            ->success()
            ->send();
    }

    public function updateItemQtd(int $index, mixed $qtd): void
    {
        if (! isset($this->formItens[$index])) {
            return;
        }

        $valor = is_numeric($qtd)
            ? (float) $qtd
            : ErpMoney::parseBr($qtd, 3);

        $max = round((float) ($this->formItens[$index]['qtd_comprada'] ?? 0), 3);
        $compraItemId = (int) ($this->formItens[$index]['compra_item_id'] ?? 0);

        if ($compraItemId > 0 && $this->formCompraId) {
            $prev = (float) DevolucaoCompraItem::query()
                ->where('compra_item_id', $compraItemId)
                ->whereHas('devolucao', function ($q): void {
                    $q->where('compra_id', $this->formCompraId)
                        ->where('situacao', DevolucaoCompra::SITUACAO_FINALIZADA);

                    if ($this->lancamentoId) {
                        $q->where('id', '!=', $this->lancamentoId);
                    }
                })
                ->sum('qtd');
            $max = round(max(0, $max - $prev), 3);
        }

        if ($valor < 0) {
            $valor = 0;
        }

        if ($max > 0 && $valor > $max) {
            $valor = $max;
        }

        $preco = round((float) ($this->formItens[$index]['preco'] ?? 0), 4);
        $this->formItens[$index]['qtd'] = round($valor, 3);
        $this->formItens[$index]['total'] = round($valor * $preco, 2);
        $this->recalcularTotais();
    }

    public function removeItem(int $index): void
    {
        if (! isset($this->formItens[$index])) {
            return;
        }

        unset($this->formItens[$index]);
        $this->formItens = array_values($this->formItens);
        $this->selectedItemIndex = $this->formItens !== []
            ? min($index, count($this->formItens) - 1)
            : null;
        $this->recalcularTotais();
    }

    public function selectItem(int $index): void
    {
        if (! isset($this->formItens[$index])) {
            return;
        }

        $this->selectedItemIndex = $index;
    }

    public function removeSelectedItem(): void
    {
        if ($this->selectedItemIndex === null) {
            return;
        }

        $this->removeItem($this->selectedItemIndex);
    }

    public function recalcularTotais(): void
    {
        $total = 0.0;

        foreach ($this->formItens as $row) {
            $total += (float) ($row['total'] ?? 0);
        }

        $this->formTotal = round($total, 2);
    }

    public function saveLancamento(bool $finalizar = false): void
    {
        $permission = $this->lancamentoId
            ? 'devolucoes_compra.update'
            : 'devolucoes_compra.create';

        if (! ErpAccess::authorizeOrNotify(Auth::user(), $permission)) {
            return;
        }

        if ($this->formSituacao !== DevolucaoCompra::SITUACAO_ABERTA && $this->lancamentoId) {
            Notification::make()
                ->title('Somente devolução aberta pode ser gravada.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->formCompraId) {
            Notification::make()->title('Informe e importe a compra de origem.')->warning()->send();

            return;
        }

        $itensValidos = collect($this->formItens)->filter(
            fn (array $row): bool => (float) ($row['qtd'] ?? 0) > 0
        );

        if ($itensValidos->isEmpty()) {
            Notification::make()->title('Informe ao menos um item com quantidade.')->warning()->send();

            return;
        }

        $this->recalcularTotais();

        $recordId = null;
        $empresaId = $this->formEmpresaId ?: ErpContext::currentEmpresaId() ?: Auth::user()?->empresa_id;

        try {
            DB::transaction(function () use ($empresaId, &$recordId): void {
                $momento = ErpTimezone::toLocal();
                $isTotal = collect($this->formItens)->every(function (array $row): bool {
                    $qtd = round((float) ($row['qtd'] ?? 0), 3);
                    $comprada = round((float) ($row['qtd_comprada'] ?? 0), 3);

                    return $comprada > 0 && abs($qtd - $comprada) < 0.0005;
                });

                $attributes = [
                    'empresa_id' => $empresaId,
                    'compra_id' => $this->formCompraId,
                    'compra_numero' => $this->formCompraNumero ?: null,
                    'fornecedor_id' => $this->formFornecedorId,
                    'fornecedor_nome' => mb_strtoupper(trim($this->formFornecedor), 'UTF-8') ?: null,
                    'usuario_id' => Auth::id(),
                    'data' => $this->formData ?: $momento->format('Y-m-d'),
                    'hora' => $momento->format('H:i:s'),
                    'tipo_devolucao' => $isTotal
                        ? DevolucaoCompra::TIPO_TOTAL
                        : DevolucaoCompra::TIPO_PARCIAL,
                    'observacoes' => trim($this->formObservacoes) ?: null,
                    'total' => $this->formTotal,
                    'situacao' => DevolucaoCompra::SITUACAO_ABERTA,
                ];

                if ($this->lancamentoId) {
                    $record = DevolucaoCompra::query()->findOrFail($this->lancamentoId);
                    $record->update($attributes);
                } else {
                    $record = DevolucaoCompra::query()->create([
                        'numero' => DevolucaoCompra::nextNumero(),
                        ...$attributes,
                    ]);
                    $this->lancamentoId = (int) $record->id;
                }

                $recordId = (int) $record->id;
                $keptIds = [];
                $itemSeq = 0;

                foreach ($this->formItens as $index => $row) {
                    $qtd = round((float) ($row['qtd'] ?? 0), 3);

                    if ($qtd <= 0) {
                        continue;
                    }

                    $itemSeq++;
                    $itemData = [
                        'item' => $itemSeq,
                        'product_id' => filled($row['product_id'] ?? null) ? (int) $row['product_id'] : null,
                        'compra_item_id' => filled($row['compra_item_id'] ?? null) ? (int) $row['compra_item_id'] : null,
                        'produto_codigo' => (string) ($row['produto_codigo'] ?? '') ?: null,
                        'produto_descricao' => mb_strtoupper((string) ($row['produto_descricao'] ?? ''), 'UTF-8') ?: null,
                        'qtd' => $qtd,
                        'qtd_comprada' => round((float) ($row['qtd_comprada'] ?? 0), 3),
                        'preco' => round((float) ($row['preco'] ?? 0), 4),
                        'total' => round((float) ($row['total'] ?? 0), 2),
                    ];

                    if (filled($row['id'] ?? null)) {
                        $item = DevolucaoCompraItem::query()->find($row['id']);

                        if ($item && (int) $item->devolucao_compra_id === $record->id) {
                            $item->update($itemData);
                            $keptIds[] = $item->id;
                            $this->formItens[$index]['id'] = $item->id;

                            continue;
                        }
                    }

                    $item = $record->itens()->create($itemData);
                    $keptIds[] = $item->id;
                    $this->formItens[$index]['id'] = $item->id;
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

            return;
        }

        if ($finalizar && $recordId) {
            try {
                $record = DevolucaoCompra::query()->findOrFail($recordId);
                (new FinalizarDevolucaoCompraService())->finalizar($record);
                $this->formSituacao = DevolucaoCompra::SITUACAO_FINALIZADA;
            } catch (DomainException $exception) {
                Notification::make()
                    ->title('Devolução gravada, mas não finalizada.')
                    ->body($exception->getMessage())
                    ->warning()
                    ->send();

                $this->resetTable();

                return;
            } catch (\Throwable $exception) {
                report($exception);

                Notification::make()
                    ->title('Devolução gravada, mas falhou ao finalizar.')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();

                $this->resetTable();

                return;
            }

            Notification::make()
                ->title('Devolução finalizada.')
                ->body('Estoque baixado com sucesso.')
                ->success()
                ->send();

            $this->closeLancamentoModal();
            $this->clearListSelection();
            $this->resetTable();

            return;
        }

        Notification::make()
            ->title('Devolução gravada com sucesso!')
            ->success()
            ->send();

        $this->closeLancamentoModal();
        $this->clearListSelection();
        $this->resetTable();
    }

    public function finalizeLancamento(): void
    {
        $this->saveLancamento(true);
    }

    protected function resetLancamentoForm(): void
    {
        $this->lancamentoId = null;
        $this->formCompraNumero = '';
        $this->formEmpresa = '';
        $this->formFornecedor = '';
        $this->formObservacoes = '';
        $this->formData = '';
        $this->formSituacao = DevolucaoCompra::SITUACAO_ABERTA;
        $this->formItens = [];
        $this->formTotal = 0.0;
        $this->compraSearch = '';
        $this->compraLookupOpen = false;
        $this->compraLookupResults = [];
        $this->compraSelecionarModalOpen = false;
        $this->compraSelecionarBusca = '';
        $this->compraSelecionarPagina = 1;
        $this->compraSelecionarTotal = 0;
        $this->compraSelecionarResults = [];
        $this->formCompraId = null;
        $this->formFornecedorId = null;
        $this->formEmpresaId = null;
        $this->selectedItemIndex = null;
    }
}
