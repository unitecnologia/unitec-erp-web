<?php

namespace App\Filament\Resources\CargaResource\Pages\Concerns;

use App\Models\Carga;
use App\Models\CargaEntrega;
use App\Models\CargaPedido;
use App\Models\Transportadora;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Filament\Resources\CargaResource\Pages\ListCargas;
use App\Support\Erp\CargaNumeroService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;

trait ManagesCargaFormModal
{
    public bool $cargaModalOpen = false;

    public ?int $cargaModalRecordId = null;

    public bool $cargaModalReadonly = false;

    /** @var array{numero: string, data: string, motorista_id: ?int, entregador_user_id: ?int, veiculo_id: ?int, observacao: string, status: string} */
    public array $cargaForm = [
        'numero' => '',
        'data' => '',
        'motorista_id' => null,
        'entregador_user_id' => null,
        'veiculo_id' => null,
        'observacao' => '',
        'status' => Carga::STATUS_ABERTA,
    ];

    /** @var list<int> */
    public array $cargaPedidoIds = [];

    /** @var list<string> */
    public array $pedidosDisponiveisSelecionados = [];

    /** @var list<string> */
    public array $pedidosCargaSelecionados = [];

    /** Limite da grade "Pedidos disponíveis". */
    public int $pedidosDisponiveisLimite = 200;

    /** Início do filtro de pedidos disponíveis (Y-m-d). */
    public string $pedidosDisponiveisFiltroDe = '';

    /** Fim do filtro de pedidos disponíveis (Y-m-d). */
    public string $pedidosDisponiveisFiltroAte = '';

    /** Número digitado para incluir pedido na carga (Enter). */
    public string $cargaPedidoNumeroDigitar = '';

    /** @var list<int> */
    private const PEDIDOS_DISPONIVEIS_LIMITES = [200, 500, 1000, 2000, 5000];

    /** @var list<array{id: int, numero: string, cliente: string, data: string, valor: float}>|null */
    private ?array $cargaCacheDisponiveisRows = null;

    /** @var list<array{id: int, numero: string, cliente: string, data: string, valor: float, entrega_status: string}>|null */
    private ?array $cargaCacheNaCargaRows = null;

    /** @var list<array{id: int, label: string}>|null */
    private ?array $cargaCacheMotoristaOptions = null;

    /** @var list<array{id: int, label: string}>|null */
    private ?array $cargaCacheEntregadorOptions = null;

    /** @var list<array{id: int, label: string}>|null */
    private ?array $cargaCacheVeiculoOptions = null;

    private ?float $cargaCacheValorTotal = null;

    private ?float $cargaCachePesoTotal = null;

    /** @var array{entregues: int, parciais: int, nao_entregues: int, pendentes: int}|null */
    private ?array $cargaCacheResumoEntregas = null;

    public function createCarga(): void
    {
        if ($this->cargaModalOpen) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($empresaId <= 0) {
            Notification::make()
                ->title('Selecione uma empresa para criar a carga.')
                ->warning()
                ->send();

            return;
        }

        $this->cargaModalRecordId = null;
        $this->cargaModalReadonly = false;
        $this->cargaForm = [
            'numero' => Carga::nextNumero($empresaId),
            'data' => ErpTimezone::today(),
            'motorista_id' => null,
            'entregador_user_id' => auth()->id() ? (int) auth()->id() : null,
            'veiculo_id' => null,
            'observacao' => '',
            'status' => Carga::STATUS_ABERTA,
        ];
        $this->cargaPedidoIds = [];
        $this->pedidosDisponiveisSelecionados = [];
        $this->pedidosCargaSelecionados = [];
        $this->pedidosDisponiveisLimite = 200;
        $this->cargaPedidoNumeroDigitar = '';
        $this->resetPedidosDisponiveisFiltro(ErpTimezone::today());
        $this->forgetCargaModalCaches();
        $this->cargaModalOpen = true;
    }

    public function editCarga(): void
    {
        if ($this->cargaModalOpen) {
            return;
        }

        $id = $this->resolveCargaIdOrNotify('edit');

        if (! $id) {
            return;
        }

        $record = $this->findCargaForEmpresa($id);

        if (! $record) {
            Notification::make()
                ->title('Carga não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->openCargaModal($record);
    }

    public function closeCargaModal(): void
    {
        $this->cargaModalOpen = false;
        $this->cargaModalRecordId = null;
        $this->cargaModalReadonly = false;
        $this->cargaForm = [
            'numero' => '',
            'data' => '',
            'motorista_id' => null,
            'entregador_user_id' => null,
            'veiculo_id' => null,
            'observacao' => '',
            'status' => Carga::STATUS_ABERTA,
        ];
        $this->cargaPedidoIds = [];
        $this->pedidosDisponiveisSelecionados = [];
        $this->pedidosCargaSelecionados = [];
        $this->pedidosDisponiveisLimite = 200;
        $this->pedidosDisponiveisFiltroDe = '';
        $this->pedidosDisponiveisFiltroAte = '';
        $this->cargaPedidoNumeroDigitar = '';
        $this->forgetCargaModalCaches();
    }

    public function saveCarga(): void
    {
        if ($this->cargaModalReadonly) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($empresaId <= 0) {
            Notification::make()
                ->title('Selecione uma empresa para gravar a carga.')
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'cargaForm.data' => ['required', 'date'],
            'cargaForm.motorista_id' => ['nullable', 'integer', 'exists:transportadoras,id'],
            'cargaForm.entregador_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'cargaForm.veiculo_id' => ['nullable', 'integer', 'exists:veiculos,id'],
            'cargaForm.observacao' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'cargaForm.data' => 'data',
            'cargaForm.motorista_id' => 'motorista',
            'cargaForm.entregador_user_id' => 'entregador',
            'cargaForm.veiculo_id' => 'veículo',
            'cargaForm.observacao' => 'observação',
        ]);

        $pedidoIds = array_values(array_unique(array_map('intval', $this->cargaPedidoIds)));

        $payload = [
            'empresa_id' => $empresaId,
            'data' => $this->cargaForm['data'],
            'motorista_id' => $this->cargaForm['motorista_id'] ?: null,
            'entregador_user_id' => $this->cargaForm['entregador_user_id'] ?: null,
            'veiculo_id' => $this->cargaForm['veiculo_id'] ?: null,
            'observacao' => filled(trim((string) ($this->cargaForm['observacao'] ?? '')))
                ? trim((string) $this->cargaForm['observacao'])
                : null,
        ];

        try {
            $record = DB::transaction(function () use ($empresaId, $payload, $pedidoIds): Carga {
                // Serializa gravações concorrentes do mesmo pedido (sem UNIQUE global).
                if ($pedidoIds !== []) {
                    Venda::query()
                        ->whereIn('id', $pedidoIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get(['id']);
                }

                $this->assertPedidosDisponiveis($pedidoIds, $empresaId, $this->cargaModalRecordId);

                if ($this->cargaModalRecordId) {
                    $record = $this->findCargaForEmpresa($this->cargaModalRecordId);

                    if (! $record) {
                        throw ValidationException::withMessages([
                            'cargaForm.numero' => 'Carga não encontrada.',
                        ]);
                    }

                    if (! $record->isAberta()) {
                        throw ValidationException::withMessages([
                            'cargaForm.status' => 'Somente cargas abertas podem ser alteradas.',
                        ]);
                    }

                    $record->update($payload);

                    $this->assertPedidosComEntregaNaoRemovidos((int) $record->getKey(), $pedidoIds);
                } else {
                    $record = Carga::query()->create([
                        ...$payload,
                        'numero' => app(CargaNumeroService::class)->proximo($empresaId),
                        'status' => Carga::STATUS_ABERTA,
                    ]);
                }

                $record->pedidos()->sync($pedidoIds);

                return $record->fresh() ?? $record;
            });
        } catch (ValidationException $e) {
            Notification::make()
                ->title(collect($e->errors())->flatten()->first() ?: 'Não foi possível gravar a carga.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($this->cargaModalRecordId ? 'Carga alterada.' : 'Carga incluída.')
            ->success()
            ->send();

        $id = (int) $record->getKey();
        $this->closeCargaModal();
        $this->clearListSelection();
        // Não usar highlightRecord(): ele faz skipRender() e o modal não some no browser.
        $this->highlightedRecordId = $id;
        if (property_exists($this, 'selecionados')) {
            $this->selecionados = [(string) $id];
        }
        $this->resetTable();
    }

    public function adicionarPedidosSelecionados(): void
    {
        if ($this->cargaModalReadonly) {
            return;
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->pedidosDisponiveisSelecionados),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            Notification::make()
                ->title('Marque ao menos um pedido disponível.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        try {
            $this->assertPedidosDisponiveis($ids, $empresaId, $this->cargaModalRecordId);
        } catch (ValidationException $e) {
            Notification::make()
                ->title(collect($e->errors())->flatten()->first() ?: 'Pedido indisponível.')
                ->danger()
                ->send();

            return;
        }

        $this->cargaPedidoIds = array_values(array_unique([
            ...array_map('intval', $this->cargaPedidoIds),
            ...$ids,
        ]));
        $this->pedidosDisponiveisSelecionados = [];
        $this->forgetCargaModalCaches();
    }

    public function adicionarPedidoPorNumero(): void
    {
        if ($this->cargaModalReadonly) {
            return;
        }

        $raw = trim($this->cargaPedidoNumeroDigitar);

        if ($raw === '') {
            Notification::make()
                ->title('Informe o número do pedido.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($empresaId <= 0) {
            Notification::make()
                ->title('Selecione uma empresa.')
                ->warning()
                ->send();

            return;
        }

        $normalizado = ltrim($raw, '0') ?: '0';
        // Grade exibe ltrim(zeros); no banco o número costuma ser str_pad 6 (ex.: 000052).
        $numerosCandidatos = array_values(array_unique(array_filter([
            $raw,
            $normalizado,
            ctype_digit($normalizado) ? str_pad($normalizado, 6, '0', STR_PAD_LEFT) : null,
        ], static fn (?string $n): bool => $n !== null && $n !== '')));

        $pedido = Venda::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', Venda::TIPO_PEDIDO)
            ->where('status', '!=', Venda::STATUS_CANCELADO)
            ->whereIn('numero', $numerosCandidatos)
            ->orderByDesc('id')
            ->first(['id', 'numero']);

        if (! $pedido) {
            Notification::make()
                ->title("Pedido {$normalizado} não encontrado ou indisponível.")
                ->danger()
                ->send();
            $this->focusCargaPedidoNumeroInput();

            return;
        }

        $pedidoId = (int) $pedido->id;
        $jaNaCarga = in_array($pedidoId, array_map('intval', $this->cargaPedidoIds), true);

        if ($jaNaCarga) {
            Notification::make()
                ->title("Pedido {$normalizado} já está na carga.")
                ->warning()
                ->send();
            $this->cargaPedidoNumeroDigitar = '';
            $this->focusCargaPedidoNumeroInput();

            return;
        }

        try {
            $this->assertPedidosDisponiveis([$pedidoId], $empresaId, $this->cargaModalRecordId);
        } catch (ValidationException $e) {
            Notification::make()
                ->title(collect($e->errors())->flatten()->first() ?: 'Pedido indisponível.')
                ->danger()
                ->send();
            $this->focusCargaPedidoNumeroInput();

            return;
        }

        $this->cargaPedidoIds = array_values(array_unique([
            ...array_map('intval', $this->cargaPedidoIds),
            $pedidoId,
        ]));
        $this->cargaPedidoNumeroDigitar = '';
        $this->forgetCargaModalCaches();
        $this->focusCargaPedidoNumeroInput();
    }

    protected function focusCargaPedidoNumeroInput(): void
    {
        $this->js(<<<'JS'
            queueMicrotask(() => {
                const el = document.getElementById('carga-pedido-numero-digitar');
                if (el) {
                    el.focus();
                    el.select();
                }
            });
        JS);
    }

    public function removerPedidosSelecionados(): void
    {
        if ($this->cargaModalReadonly) {
            return;
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->pedidosCargaSelecionados),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            Notification::make()
                ->title('Marque ao menos um pedido da carga.')
                ->warning()
                ->send();

            return;
        }

        $bloqueados = [];
        if ($this->cargaModalRecordId) {
            $bloqueados = $this->pedidoIdsComEntregaNaCarga((int) $this->cargaModalRecordId, $ids);
        }

        if ($bloqueados !== []) {
            Notification::make()
                ->title('Este pedido possui registro de entrega e não pode ser removido da carga.')
                ->warning()
                ->send();
        }

        $remover = array_values(array_diff($ids, $bloqueados));

        if ($remover === []) {
            $this->pedidosCargaSelecionados = [];

            return;
        }

        $this->cargaPedidoIds = array_values(array_filter(
            array_map('intval', $this->cargaPedidoIds),
            fn (int $id): bool => ! in_array($id, $remover, true),
        ));
        $this->pedidosCargaSelecionados = [];
        $this->forgetCargaModalCaches();
    }

    public function syncPedidoDisponivelSelecionado(int $id, bool $checked): void
    {
        if ($this->cargaModalReadonly || $id <= 0) {
            $this->skipRender();

            return;
        }

        $key = (string) $id;
        $marcados = array_map('strval', $this->pedidosDisponiveisSelecionados);
        $has = in_array($key, $marcados, true);

        if ($checked && ! $has) {
            $this->pedidosDisponiveisSelecionados[] = $key;
        } elseif (! $checked && $has) {
            $this->pedidosDisponiveisSelecionados = array_values(array_filter(
                $marcados,
                static fn (string $item): bool => $item !== $key,
            ));
        }

        $this->skipRender();
    }

    public function syncPedidoCargaSelecionado(int $id, bool $checked): void
    {
        if ($this->cargaModalReadonly || $id <= 0) {
            $this->skipRender();

            return;
        }

        $key = (string) $id;
        $marcados = array_map('strval', $this->pedidosCargaSelecionados);
        $has = in_array($key, $marcados, true);

        if ($checked && ! $has) {
            $this->pedidosCargaSelecionados[] = $key;
        } elseif (! $checked && $has) {
            $this->pedidosCargaSelecionados = array_values(array_filter(
                $marcados,
                static fn (string $item): bool => $item !== $key,
            ));
        }

        $this->skipRender();
    }

    public function toggleTodosPedidosDisponiveis(): void
    {
        if ($this->cargaModalReadonly) {
            return;
        }

        $ids = $this->pedidoIdsFromRows($this->pedidosDisponiveisRows());

        if ($ids === []) {
            return;
        }

        $this->pedidosDisponiveisSelecionados = $this->todosIdsSelecionados($ids, $this->pedidosDisponiveisSelecionados)
            ? []
            : $ids;
    }

    public function toggleTodosPedidosCarga(): void
    {
        if ($this->cargaModalReadonly) {
            return;
        }

        $ids = $this->pedidoIdsFromRows($this->pedidosNaCargaRows());

        if ($ids === []) {
            return;
        }

        $this->pedidosCargaSelecionados = $this->todosIdsSelecionados($ids, $this->pedidosCargaSelecionados)
            ? []
            : $ids;
    }

    public function todosPedidosDisponiveisMarcados(): bool
    {
        $ids = $this->pedidoIdsFromRows($this->pedidosDisponiveisRows());

        return $this->todosIdsSelecionados($ids, $this->pedidosDisponiveisSelecionados);
    }

    public function todosPedidosCargaMarcados(): bool
    {
        $ids = $this->pedidoIdsFromRows($this->pedidosNaCargaRows());

        return $this->todosIdsSelecionados($ids, $this->pedidosCargaSelecionados);
    }

    /**
     * @param  list<array{id: int}>  $rows
     * @return list<string>
     */
    protected function pedidoIdsFromRows(array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['id'],
            $rows,
        ));
    }

    /**
     * @param  list<string>  $ids
     * @param  list<int|string>  $selecionados
     */
    protected function todosIdsSelecionados(array $ids, array $selecionados): bool
    {
        if ($ids === []) {
            return false;
        }

        $marcados = array_values(array_unique(array_map('strval', $selecionados)));

        if (count($marcados) < count($ids)) {
            return false;
        }

        return count(array_diff($ids, $marcados)) === 0;
    }

    public function fecharCargaSelecionada(): void
    {
        if ($this->cargaModalOpen) {
            return;
        }

        $id = $this->resolveCargaIdOrNotify('edit');

        if (! $id) {
            return;
        }

        $record = $this->findCargaForEmpresa($id);

        if (! $record) {
            Notification::make()->title('Carga não encontrada.')->warning()->send();

            return;
        }

        if (! $record->isAberta()) {
            Notification::make()->title('Somente cargas abertas podem ser fechadas.')->warning()->send();

            return;
        }

        if ($record->pedidos()->count() === 0) {
            Notification::make()->title('Inclua ao menos um pedido antes de fechar a carga.')->warning()->send();

            return;
        }

        if (! $record->entregador_user_id) {
            Notification::make()
                ->title('Informe o Entregador / Usuário do App antes de fechar.')
                ->warning()
                ->send();

            return;
        }

        $record->update(['status' => Carga::STATUS_FECHADA]);

        Notification::make()->title('Carga fechada.')->success()->send();
        // 1 refresh após sucesso: status na grade + limpa seleção (checkbox/key).
        $this->refreshTable();
    }

    public function cancelarCargaSelecionada(): void
    {
        if ($this->cargaModalOpen) {
            return;
        }

        $id = $this->resolveCargaIdOrNotify('edit');

        if (! $id) {
            return;
        }

        $record = $this->findCargaForEmpresa($id);

        if (! $record) {
            Notification::make()->title('Carga não encontrada.')->warning()->send();

            return;
        }

        if ($record->isCancelada()) {
            Notification::make()->title('Esta carga já está cancelada.')->warning()->send();

            return;
        }

        $record->update(['status' => Carga::STATUS_CANCELADA]);

        Notification::make()
            ->title('Carga cancelada. Pedidos liberados.')
            ->success()
            ->send();

        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }

    public function imprimirRomaneioSelecionado(): void
    {
        if ($this->cargaModalOpen) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $this->selecionados))));

        if ($ids === []) {
            Notification::make()
                ->title('Marque ao menos uma carga para imprimir.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = Carga::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', Carga::STATUS_CANCELADA);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $validIds = $query->orderBy('numero')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($validIds === []) {
            Notification::make()
                ->title('Nenhuma carga válida para impressão.')
                ->warning()
                ->send();

            return;
        }

        $firstId = $validIds[0];
        $url = route('erp.reports.carga-romaneio', [
            'carga' => $firstId,
            'ids' => implode(',', $validIds),
            'cliente' => 0,
            'valor' => 0,
            'ord' => 'quantidade',
        ]);

        $this->rememberSelecionadosParaRetornoImpressao();
        $this->redirect($url, navigate: false);
    }

    public function imprimirPedidosSelecionados(): void
    {
        if ($this->cargaModalOpen) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $this->selecionados))));

        if ($ids === []) {
            Notification::make()
                ->title('Marque ao menos uma carga para imprimir os pedidos.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = Carga::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', Carga::STATUS_CANCELADA);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $validCargas = $query->orderBy('numero')->get(['id', 'numero']);
        $validIds = $validCargas->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($validIds === []) {
            Notification::make()
                ->title('Nenhuma carga válida para impressão.')
                ->warning()
                ->send();

            return;
        }

        $pedidoIds = CargaPedido::query()
            ->whereIn('carga_id', $validIds)
            ->orderBy('id')
            ->pluck('pedido_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($pedidoIds === []) {
            Notification::make()
                ->title('Nenhum pedido encontrado nas cargas selecionadas.')
                ->warning()
                ->send();

            return;
        }

        $url = route('erp.reports.monitor-pedidos', [
            'venda_ids' => implode(',', $pedidoIds),
            'carga_ids' => implode(',', $validIds),
            'from' => 'cargas',
            'auto' => 1,
        ]);

        $this->rememberSelecionadosParaRetornoImpressao();
        $this->js('window.location.assign('.Js::from($url).');');
    }

    /**
     * Guarda $selecionados na sessão só para o retorno F4/F6 (mesma aba).
     */
    protected function rememberSelecionadosParaRetornoImpressao(): void
    {
        if (! property_exists($this, 'selecionados')) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => (string) (int) $id,
            $this->selecionados,
        ))));

        session([ListCargas::SESSION_SELECAO_RETORNO_IMPRESSAO => $ids]);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function motoristaOptions(): array
    {
        if ($this->cargaCacheMotoristaOptions !== null) {
            return $this->cargaCacheMotoristaOptions;
        }

        return $this->cargaCacheMotoristaOptions = Transportadora::query()
            ->where('ativo', true)
            ->orderBy('proprietario')
            ->get(['id', 'proprietario', 'apelido'])
            ->map(fn (Transportadora $item): array => [
                'id' => (int) $item->id,
                'label' => trim((string) ($item->apelido ?: $item->proprietario)) ?: ('#'.$item->id),
            ])
            ->all();
    }

    /**
     * Usuários cujo operador (RH) está marcado como entregador.
     *
     * @return list<array{id: int, label: string}>
     */
    public function entregadorOptions(): array
    {
        if ($this->cargaCacheEntregadorOptions !== null) {
            return $this->cargaCacheEntregadorOptions;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $options = User::query()
            ->where('ativo', true)
            ->whereHas('vendedor', function ($q): void {
                $q->where('ativo', true)->where('entregador', true);
            })
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u): array => [
                'id' => (int) $u->id,
                'label' => (string) $u->name,
            ])
            ->all();

        // Garante que o usuário logado (padrão na Nova Carga) apareça na lista.
        $authId = (int) (auth()->id() ?? 0);
        if ($authId > 0 && ! collect($options)->contains(fn (array $opt): bool => $opt['id'] === $authId)) {
            $authUser = User::query()
                ->whereKey($authId)
                ->where('ativo', true)
                ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
                ->first(['id', 'name']);

            if ($authUser) {
                $options[] = [
                    'id' => (int) $authUser->id,
                    'label' => (string) $authUser->name,
                ];
                usort($options, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));
            }
        }

        return $this->cargaCacheEntregadorOptions = $options;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function veiculoOptions(): array
    {
        if ($this->cargaCacheVeiculoOptions !== null) {
            return $this->cargaCacheVeiculoOptions;
        }

        return $this->cargaCacheVeiculoOptions = Veiculo::query()
            ->where('ativo', true)
            ->orderBy('placa')
            ->get(['id', 'placa', 'descricao'])
            ->map(function (Veiculo $item): array {
                $label = (string) $item->placa;
                $descricao = trim((string) ($item->descricao ?? ''));

                if ($descricao !== '') {
                    $label .= ' — '.$descricao;
                }

                return [
                    'id' => (int) $item->id,
                    'label' => $label,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{id: int, numero: string, cliente: string, data: string, valor: float, entrega_status: string}>
     */
    public function pedidosNaCargaRows(): array
    {
        if ($this->cargaCacheNaCargaRows !== null) {
            return $this->cargaCacheNaCargaRows;
        }

        if ($this->cargaPedidoIds === []) {
            return $this->cargaCacheNaCargaRows = [];
        }

        $rows = $this->mapPedidoRows(
            Venda::query()
                ->with('cliente:id,nome_razao')
                ->whereIn('id', $this->cargaPedidoIds)
                ->orderBy('data')
                ->orderBy('numero')
                ->get(['id', 'numero', 'data', 'total', 'cliente_id'])
        );

        $statusPorPedido = [];
        if ($this->cargaModalRecordId) {
            $statusPorPedido = CargaEntrega::query()
                ->where('carga_id', $this->cargaModalRecordId)
                ->whereIn('pedido_id', $this->cargaPedidoIds)
                ->pluck('status', 'pedido_id')
                ->all();
        }

        foreach ($rows as &$row) {
            $row['entrega_status'] = (string) ($statusPorPedido[$row['id']] ?? 'pendente');
        }
        unset($row);

        return $this->cargaCacheNaCargaRows = $rows;
    }

    /**
     * @return list<array{id: int, numero: string, cliente: string, data: string, valor: float}>
     */
    public function pedidosDisponiveisRows(): array
    {
        if ($this->cargaCacheDisponiveisRows !== null) {
            return $this->cargaCacheDisponiveisRows;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($empresaId <= 0) {
            return $this->cargaCacheDisponiveisRows = [];
        }

        $excetoCargaId = $this->cargaModalRecordId;
        $excluidosLocais = array_values(array_unique(array_filter(
            array_map('intval', $this->cargaPedidoIds),
            static fn (int $id): bool => $id > 0,
        )));

        $query = Venda::query()
            ->with('cliente:id,nome_razao')
            ->where('empresa_id', $empresaId)
            ->where('tipo', Venda::TIPO_PEDIDO)
            ->where('status', '!=', Venda::STATUS_CANCELADO)
            ->whereNotExists(function ($sub) use ($excetoCargaId): void {
                $sub->selectRaw('1')
                    ->from('carga_pedidos')
                    ->join('cargas', 'cargas.id', '=', 'carga_pedidos.carga_id')
                    ->whereColumn('carga_pedidos.pedido_id', 'vendas.id')
                    ->whereIn('cargas.status', [Carga::STATUS_ABERTA, Carga::STATUS_FECHADA]);

                if ($excetoCargaId) {
                    $sub->where('cargas.id', '!=', $excetoCargaId);
                }
            });

        [$dataDe, $dataAte] = $this->pedidosDisponiveisPeriodo();

        if ($dataDe !== null && $dataAte !== null) {
            $query->whereDate('data', '>=', $dataDe)
                ->whereDate('data', '<=', $dataAte);
        }

        $query->orderBy('data')
            ->orderBy('numero')
            ->limit($this->normalizePedidosDisponiveisLimite($this->pedidosDisponiveisLimite));

        if ($excluidosLocais !== []) {
            $query->whereNotIn('id', $excluidosLocais);
        }

        return $this->cargaCacheDisponiveisRows = $this->mapPedidoRows(
            $query->get(['id', 'numero', 'data', 'total', 'cliente_id'])
        );
    }

    /**
     * Pedidos disponíveis: entre De e Até (inclusivos).
     *
     * @return array{0: ?string, 1: ?string} Y-m-d
     */
    protected function pedidosDisponiveisPeriodo(): array
    {
        $de = trim((string) $this->pedidosDisponiveisFiltroDe);
        $ate = trim((string) $this->pedidosDisponiveisFiltroAte);

        if ($de === '' && $ate === '') {
            $fallback = trim((string) ($this->cargaForm['data'] ?? ''));
            if ($fallback === '') {
                return [null, null];
            }

            try {
                $ref = \Carbon\Carbon::parse($fallback)->startOfDay();
            } catch (\Throwable) {
                return [null, null];
            }

            return [
                $ref->copy()->startOfMonth()->toDateString(),
                $ref->toDateString(),
            ];
        }

        if ($de === '') {
            $de = $ate;
        }

        if ($ate === '') {
            $ate = $de;
        }

        try {
            $deC = \Carbon\Carbon::parse($de)->startOfDay();
            $ateC = \Carbon\Carbon::parse($ate)->startOfDay();
        } catch (\Throwable) {
            return [null, null];
        }

        if ($deC->gt($ateC)) {
            [$deC, $ateC] = [$ateC, $deC];
        }

        return [
            $deC->toDateString(),
            $ateC->toDateString(),
        ];
    }

    protected function resetPedidosDisponiveisFiltro(?string $ref = null): void
    {
        $refRaw = trim((string) ($ref ?: ErpTimezone::today()));

        try {
            $refC = \Carbon\Carbon::parse($refRaw)->startOfDay();
        } catch (\Throwable) {
            $refC = \Carbon\Carbon::parse(ErpTimezone::today())->startOfDay();
        }

        $this->pedidosDisponiveisFiltroDe = $refC->copy()->startOfMonth()->toDateString();
        $this->pedidosDisponiveisFiltroAte = $refC->copy()->endOfMonth()->toDateString();
    }

    protected function normalizePedidosDisponiveisFiltroRange(): void
    {
        $de = trim($this->pedidosDisponiveisFiltroDe);
        $ate = trim($this->pedidosDisponiveisFiltroAte);

        if ($de === '' || $ate === '') {
            return;
        }

        try {
            $deC = \Carbon\Carbon::parse($de)->startOfDay();
            $ateC = \Carbon\Carbon::parse($ate)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($deC->gt($ateC)) {
            $this->pedidosDisponiveisFiltroDe = $ateC->toDateString();
            $this->pedidosDisponiveisFiltroAte = $deC->toDateString();
        }
    }

    public function cargaQtdPedidos(): int
    {
        return count($this->cargaPedidoIds);
    }

    /**
     * @return array{entregues: int, parciais: int, nao_entregues: int, pendentes: int}
     */
    public function cargaResumoEntregas(): array
    {
        if ($this->cargaCacheResumoEntregas !== null) {
            return $this->cargaCacheResumoEntregas;
        }

        $total = count($this->cargaPedidoIds);
        $entregues = 0;
        $parciais = 0;
        $naoEntregues = 0;

        if ($this->cargaModalRecordId && $this->cargaPedidoIds !== []) {
            $counts = CargaEntrega::query()
                ->where('carga_id', $this->cargaModalRecordId)
                ->whereIn('pedido_id', $this->cargaPedidoIds)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $entregues = (int) ($counts[CargaEntrega::STATUS_ENTREGUE] ?? 0);
            $parciais = (int) ($counts[CargaEntrega::STATUS_PARCIAL] ?? 0);
            $naoEntregues = (int) ($counts[CargaEntrega::STATUS_NAO_ENTREGUE] ?? 0);
        }

        return $this->cargaCacheResumoEntregas = [
            'entregues' => $entregues,
            'parciais' => $parciais,
            'nao_entregues' => $naoEntregues,
            'pendentes' => max(0, $total - $entregues - $parciais - $naoEntregues),
        ];
    }

    public function cargaQtdEntregas(): int
    {
        $resumo = $this->cargaResumoEntregas();

        return $resumo['entregues'] + $resumo['parciais'] + $resumo['nao_entregues'];
    }

    public function cargaValorTotal(): float
    {
        if ($this->cargaCacheValorTotal !== null) {
            return $this->cargaCacheValorTotal;
        }

        if ($this->cargaPedidoIds === []) {
            return $this->cargaCacheValorTotal = 0.0;
        }

        return $this->cargaCacheValorTotal = (float) Venda::query()
            ->whereIn('id', $this->cargaPedidoIds)
            ->sum('total');
    }

    public function cargaPesoTotal(): float
    {
        if ($this->cargaCachePesoTotal !== null) {
            return $this->cargaCachePesoTotal;
        }

        if ($this->cargaPedidoIds === []) {
            return $this->cargaCachePesoTotal = 0.0;
        }

        $prefix = DB::connection()->getTablePrefix();
        $itens = $prefix.'venda_itens';
        $prods = $prefix.'products';

        return $this->cargaCachePesoTotal = (float) VendaItem::query()
            ->join('products', 'products.id', '=', 'venda_itens.product_id')
            ->whereIn('venda_itens.venda_id', $this->cargaPedidoIds)
            ->selectRaw("COALESCE(SUM(`{$itens}`.quantidade * COALESCE(`{$prods}`.peso_kg, 0)), 0) as peso")
            ->value('peso');
    }

    protected function openCargaModal(Carga $record): void
    {
        $this->cargaModalRecordId = (int) $record->getKey();
        $this->cargaModalReadonly = ! $record->isAberta();
        $this->cargaForm = [
            'numero' => (string) $record->numero,
            'data' => optional($record->data)->format('Y-m-d') ?: ErpTimezone::today(),
            'motorista_id' => $record->motorista_id ? (int) $record->motorista_id : null,
            'entregador_user_id' => $record->entregador_user_id ? (int) $record->entregador_user_id : null,
            'veiculo_id' => $record->veiculo_id ? (int) $record->veiculo_id : null,
            'observacao' => (string) ($record->observacao ?? ''),
            'status' => (string) $record->status,
        ];
        $this->cargaPedidoIds = $record->pedidos()->pluck('vendas.id')->map(fn ($id): int => (int) $id)->all();
        $this->pedidosDisponiveisSelecionados = [];
        $this->pedidosCargaSelecionados = [];
        $this->pedidosDisponiveisLimite = 200;
        $this->cargaPedidoNumeroDigitar = '';
        $this->resetPedidosDisponiveisFiltro(optional($record->data)->format('Y-m-d') ?: ErpTimezone::today());
        $this->forgetCargaModalCaches();
        $this->cargaModalOpen = true;
    }

    public function updatedPedidosDisponiveisLimite(mixed $value): void
    {
        $this->pedidosDisponiveisLimite = $this->normalizePedidosDisponiveisLimite($value);
        $this->pedidosDisponiveisSelecionados = [];
        $this->cargaCacheDisponiveisRows = null;
    }

    public function updatedPedidosDisponiveisFiltroDe(mixed $value): void
    {
        $this->pedidosDisponiveisFiltroDe = is_string($value) ? trim($value) : '';
        $this->aplicarFiltroPedidosDisponiveis();
    }

    public function updatedPedidosDisponiveisFiltroAte(mixed $value): void
    {
        $this->pedidosDisponiveisFiltroAte = is_string($value) ? trim($value) : '';
        $this->aplicarFiltroPedidosDisponiveis();
    }

    public function aplicarFiltroPedidosDisponiveis(): void
    {
        $this->normalizePedidosDisponiveisFiltroRange();
        $this->pedidosDisponiveisSelecionados = [];
        $this->cargaCacheDisponiveisRows = null;
    }

    /**
     * @return list<int>
     */
    public function pedidosDisponiveisLimiteOptions(): array
    {
        return self::PEDIDOS_DISPONIVEIS_LIMITES;
    }

    protected function normalizePedidosDisponiveisLimite(mixed $value): int
    {
        $n = (int) $value;

        return in_array($n, self::PEDIDOS_DISPONIVEIS_LIMITES, true) ? $n : 200;
    }

    protected function forgetCargaModalCaches(): void
    {
        $this->cargaCacheDisponiveisRows = null;
        $this->cargaCacheNaCargaRows = null;
        $this->cargaCacheMotoristaOptions = null;
        $this->cargaCacheEntregadorOptions = null;
        $this->cargaCacheVeiculoOptions = null;
        $this->cargaCacheValorTotal = null;
        $this->cargaCachePesoTotal = null;
        $this->cargaCacheResumoEntregas = null;
    }

    /**
     * Seleção oficial: somente checkboxes ($selecionados). Highlight de linha é visual.
     */
    protected function resolveCargaIdOrNotify(string $action): ?int
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            property_exists($this, 'selecionados') ? $this->selecionados : [],
        ))));

        if ($ids === []) {
            Notification::make()
                ->title('Selecione uma carga.')
                ->warning()
                ->send();

            return null;
        }

        if (count($ids) > 1) {
            Notification::make()
                ->title('Selecione apenas uma carga para realizar esta operação.')
                ->warning()
                ->send();

            return null;
        }

        return $ids[0];
    }

    protected function findCargaForEmpresa(int $id): ?Carga
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = Carga::query()->whereKey($id);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->first();
    }

    /**
     * @param  list<int>  $pedidoIds
     */
    protected function assertPedidosDisponiveis(array $pedidoIds, int $empresaId, ?int $exceptCargaId): void
    {
        if ($pedidoIds === []) {
            return;
        }

        $validCount = Venda::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', Venda::TIPO_PEDIDO)
            ->where('status', '!=', Venda::STATUS_CANCELADO)
            ->whereIn('id', $pedidoIds)
            ->count();

        if ($validCount !== count($pedidoIds)) {
            throw ValidationException::withMessages([
                'cargaPedidoIds' => 'Um ou mais pedidos são inválidos para esta empresa.',
            ]);
        }

        $ocupados = CargaPedido::query()
            ->whereIn('pedido_id', $pedidoIds)
            ->whereHas('carga', function ($query) use ($exceptCargaId): void {
                $query->whereIn('status', [Carga::STATUS_ABERTA, Carga::STATUS_FECHADA]);

                if ($exceptCargaId) {
                    $query->where('id', '!=', $exceptCargaId);
                }
            })
            ->exists();

        if ($ocupados) {
            throw ValidationException::withMessages([
                'cargaPedidoIds' => 'Um ou mais pedidos já pertencem a outra carga aberta ou fechada.',
            ]);
        }
    }

    /**
     * Impede sync que remova pedido já com CargaEntrega nesta carga.
     *
     * @param  list<int>  $pedidoIdsNovos
     */
    protected function assertPedidosComEntregaNaoRemovidos(int $cargaId, array $pedidoIdsNovos): void
    {
        $atuais = CargaPedido::query()
            ->where('carga_id', $cargaId)
            ->pluck('pedido_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $removendo = array_values(array_diff($atuais, array_map('intval', $pedidoIdsNovos)));

        if ($removendo === []) {
            return;
        }

        if ($this->pedidoIdsComEntregaNaCarga($cargaId, $removendo) !== []) {
            throw ValidationException::withMessages([
                'cargaPedidoIds' => 'Este pedido possui registro de entrega e não pode ser removido da carga.',
            ]);
        }
    }

    /**
     * @param  list<int>  $pedidoIds
     * @return list<int>
     */
    protected function pedidoIdsComEntregaNaCarga(int $cargaId, array $pedidoIds): array
    {
        if ($cargaId <= 0 || $pedidoIds === []) {
            return [];
        }

        return CargaEntrega::query()
            ->where('carga_id', $cargaId)
            ->whereIn('pedido_id', $pedidoIds)
            ->pluck('pedido_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Venda>  $pedidos
     * @return list<array{id: int, numero: string, cliente: string, data: string, valor: float}>
     */
    protected function mapPedidoRows($pedidos): array
    {
        return $pedidos->map(function (Venda $pedido): array {
            $numero = ltrim((string) $pedido->numero, '0');

            return [
                'id' => (int) $pedido->id,
                'numero' => $numero !== '' ? $numero : '0',
                'cliente' => (string) ($pedido->cliente?->nome_razao ?: 'CONSUMIDOR'),
                'data' => optional($pedido->data)->format('d/m/Y') ?: '—',
                'valor' => (float) ($pedido->total ?? 0),
            ];
        })->all();
    }
}
