<?php

namespace App\Filament\Resources\RhUniformeEntregaResource\Pages\Concerns;

use App\Filament\Resources\RhUniformeEntregaResource;
use App\Models\RhFuncionario;
use App\Models\RhUniforme;
use App\Models\RhUniformeEntrega;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhUniformeEntregaService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesRhUniformeEntregaFormModal
{
    public bool $entregaModalOpen = false;

    public ?int $entregaModalRecordId = null;

    /** @var array<string, mixed> */
    public array $entregaForm = [];

    public string $entregaModalMode = 'create'; // create|edit|substituir|trocar

    public function createEntrega(): void
    {
        if ($this->entregaModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.uniformes.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $this->entregaModalRecordId = null;
        $this->entregaModalMode = 'create';
        $this->entregaForm = $this->defaultEntregaForm();
        $this->entregaModalOpen = true;
    }

    public function editEntrega(): void
    {
        if ($this->entregaModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.uniformes.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhUniformeEntrega::query()->with(['funcionario', 'uniforme'])->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Entrega não encontrada.')->warning()->send();

            return;
        }

        $this->entregaModalRecordId = (int) $record->getKey();
        $this->entregaModalMode = 'edit';
        $this->entregaForm = $this->entregaFormFromRecord($record);
        $this->entregaModalOpen = true;
    }

    public function substituirEntrega(): void
    {
        if (! ErpAccess::currentCan('rh.uniformes.create')) {
            Notification::make()->title('Sem permissão para substituir.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhUniformeEntrega::query()->find($this->highlightedRecordId);
        if (! $record || $record->situacao !== RhUniformeEntrega::SIT_ENTREGUE) {
            Notification::make()->title('Selecione uma entrega aberta para substituir.')->warning()->send();

            return;
        }

        $this->entregaModalRecordId = null;
        $this->entregaModalMode = 'substituir';
        $this->entregaForm = $this->defaultEntregaForm();
        $this->entregaForm['funcionario_id'] = (string) $record->funcionario_id;
        $this->entregaForm['uniforme_id'] = (string) $record->uniforme_id;
        $this->entregaForm['quantidade'] = (string) $record->quantidade;
        $this->entregaForm['substitui_id'] = (string) $record->getKey();
        $this->entregaForm['motivo_movimentacao'] = '';
        $this->entregaForm['itens'] = [];
        $this->entregaModalOpen = true;
    }

    public function trocarEntrega(): void
    {
        if (! ErpAccess::currentCan('rh.uniformes.create')) {
            Notification::make()->title('Sem permissão para trocar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhUniformeEntrega::query()->find($this->highlightedRecordId);
        if (! $record || $record->situacao !== RhUniformeEntrega::SIT_ENTREGUE) {
            Notification::make()->title('Selecione uma entrega aberta para trocar.')->warning()->send();

            return;
        }

        $this->entregaModalRecordId = null;
        $this->entregaModalMode = 'trocar';
        $this->entregaForm = $this->defaultEntregaForm();
        $this->entregaForm['funcionario_id'] = (string) $record->funcionario_id;
        $this->entregaForm['uniforme_id'] = '';
        $this->entregaForm['quantidade'] = (string) $record->quantidade;
        $this->entregaForm['substitui_id'] = (string) $record->getKey();
        $this->entregaForm['motivo_movimentacao'] = '';
        $this->entregaForm['itens'] = [];
        $this->entregaModalOpen = true;
    }

    public function addEntregaItemLine(): void
    {
        if ($this->entregaModalMode !== 'create') {
            return;
        }

        $itens = $this->entregaForm['itens'] ?? [];
        if (! is_array($itens)) {
            $itens = [];
        }

        if (count($itens) >= 10) {
            Notification::make()->title('Máximo de 10 peças por entrega.')->warning()->send();

            return;
        }

        $itens[] = ['uniforme_id' => '', 'quantidade' => '1'];
        $this->entregaForm['itens'] = $itens;
    }

    public function removeEntregaItemLine(int $index): void
    {
        if ($this->entregaModalMode !== 'create') {
            return;
        }

        $itens = $this->entregaForm['itens'] ?? [];
        if (! is_array($itens) || count($itens) <= 1) {
            return;
        }

        unset($itens[$index]);
        $this->entregaForm['itens'] = array_values($itens);
    }

    public function closeEntregaModal(): void
    {
        $this->entregaModalOpen = false;
        $this->entregaModalRecordId = null;
        $this->entregaModalMode = 'create';
        $this->entregaForm = [];
        $this->resetValidation();
    }

    public function saveEntrega(): void
    {
        $mode = $this->entregaModalMode;
        $needCreate = in_array($mode, ['create', 'substituir', 'trocar'], true);

        if (! ErpAccess::currentCan($needCreate ? 'rh.uniformes.create' : 'rh.uniformes.update')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $service = app(RhUniformeEntregaService::class);

        try {
            if ($mode === 'create') {
                $this->validate([
                    'entregaForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
                    'entregaForm.entregue_em' => ['required', 'date'],
                    'entregaForm.observacao' => ['nullable', 'string', 'max:500'],
                    'entregaForm.itens' => ['required', 'array', 'min:1', 'max:10'],
                    'entregaForm.itens.*.uniforme_id' => ['required', 'integer', Rule::exists('rh_uniformes', 'id')],
                    'entregaForm.itens.*.quantidade' => ['required', 'integer', 'min:1', 'max:999'],
                ], [], [
                    'entregaForm.funcionario_id' => 'funcionário',
                    'entregaForm.entregue_em' => 'data da entrega',
                    'entregaForm.itens.*.uniforme_id' => 'uniforme',
                    'entregaForm.itens.*.quantidade' => 'quantidade',
                ]);

                $itens = [];
                foreach ($this->entregaForm['itens'] as $item) {
                    $itens[] = [
                        'uniforme_id' => (int) $item['uniforme_id'],
                        'quantidade' => (int) $item['quantidade'],
                    ];
                }

                $criadas = $service->entregarLote([
                    'funcionario_id' => (int) $this->entregaForm['funcionario_id'],
                    'entregue_em' => $this->entregaForm['entregue_em'],
                    'observacao' => $this->entregaForm['observacao'] ?? null,
                    'itens' => $itens,
                ]);
                $id = (int) ($criadas[0]?->getKey() ?? 0);
                $title = count($criadas) > 1
                    ? count($criadas).' peças entregues.'
                    : 'Entrega registrada.';
            } elseif ($mode === 'edit') {
                $this->validate([
                    'entregaForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
                    'entregaForm.uniforme_id' => ['required', 'integer', Rule::exists('rh_uniformes', 'id')],
                    'entregaForm.quantidade' => ['required', 'integer', 'min:1', 'max:999'],
                    'entregaForm.entregue_em' => ['required', 'date'],
                    'entregaForm.observacao' => ['nullable', 'string', 'max:500'],
                    'entregaForm.motivo_correcao' => ['nullable', 'string', 'max:255'],
                ], [], [
                    'entregaForm.funcionario_id' => 'funcionário',
                    'entregaForm.uniforme_id' => 'uniforme',
                    'entregaForm.quantidade' => 'quantidade',
                    'entregaForm.entregue_em' => 'data da entrega',
                ]);

                $record = RhUniformeEntrega::query()->find($this->entregaModalRecordId);
                if (! $record) {
                    Notification::make()->title('Entrega não encontrada.')->warning()->send();

                    return;
                }
                $service->corrigir($record, [
                    'funcionario_id' => (int) $this->entregaForm['funcionario_id'],
                    'uniforme_id' => (int) $this->entregaForm['uniforme_id'],
                    'quantidade' => (int) $this->entregaForm['quantidade'],
                    'entregue_em' => $this->entregaForm['entregue_em'],
                    'observacao' => $this->entregaForm['observacao'] ?? null,
                ], $this->entregaForm['motivo_correcao'] ?? null);
                $id = (int) $record->getKey();
                $title = 'Entrega corrigida.';
            } else {
                $this->validate([
                    'entregaForm.uniforme_id' => ['required', 'integer', Rule::exists('rh_uniformes', 'id')],
                    'entregaForm.quantidade' => ['required', 'integer', 'min:1', 'max:999'],
                    'entregaForm.entregue_em' => ['required', 'date'],
                    'entregaForm.observacao' => ['nullable', 'string', 'max:500'],
                    'entregaForm.motivo_movimentacao' => [
                        $mode === 'substituir' ? 'required' : 'nullable',
                        'string',
                        'max:255',
                    ],
                ], [
                    'entregaForm.motivo_movimentacao.required' => 'Informe o motivo da substituição.',
                ], [
                    'entregaForm.uniforme_id' => 'uniforme',
                    'entregaForm.quantidade' => 'quantidade',
                    'entregaForm.entregue_em' => 'data da entrega',
                    'entregaForm.motivo_movimentacao' => 'motivo',
                ]);

                $anterior = RhUniformeEntrega::query()->find((int) ($this->entregaForm['substitui_id'] ?? 0));
                if (! $anterior) {
                    Notification::make()->title('Entrega original não encontrada.')->warning()->send();

                    return;
                }

                $payload = [
                    'uniforme_id' => (int) $this->entregaForm['uniforme_id'],
                    'quantidade' => (int) $this->entregaForm['quantidade'],
                    'entregue_em' => $this->entregaForm['entregue_em'],
                    'observacao' => $this->entregaForm['observacao'] ?? null,
                    'motivo_movimentacao' => $this->entregaForm['motivo_movimentacao'] ?? null,
                ];

                $record = $mode === 'trocar'
                    ? $service->trocar($anterior, $payload)
                    : $service->substituir($anterior, $payload);
                $id = (int) $record->getKey();
                $title = $mode === 'trocar' ? 'Troca registrada.' : 'Substituição registrada.';
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeEntregaModal();
        $this->clearListSelection();
        $this->resetTable();
        if ($id > 0) {
            $this->highlightRecord($id);
        }
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function entregaFuncionarioOptions(): array
    {
        $atual = filled($this->entregaForm['funcionario_id'] ?? null) ? (int) $this->entregaForm['funcionario_id'] : null;
        $rows = RhUniformeEntregaResource::funcionariosQuery()->get(['id', 'codigo', 'nome']);
        if ($atual && ! $rows->contains(fn (RhFuncionario $f): bool => (int) $f->id === $atual)) {
            $extra = RhFuncionario::query()->find($atual, ['id', 'codigo', 'nome']);
            if ($extra) {
                $rows->push($extra);
            }
        }

        return $rows->map(fn (RhFuncionario $f): array => [
            'id' => (int) $f->id,
            'label' => trim($f->codigo.' — '.$f->nome),
        ])->values()->all();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function entregaUniformeOptions(): array
    {
        $ids = [];
        if (filled($this->entregaForm['uniforme_id'] ?? null)) {
            $ids[] = (int) $this->entregaForm['uniforme_id'];
        }
        foreach ($this->entregaForm['itens'] ?? [] as $item) {
            if (filled($item['uniforme_id'] ?? null)) {
                $ids[] = (int) $item['uniforme_id'];
            }
        }

        $rows = RhUniformeEntregaResource::uniformesQuery(true)->get();
        foreach (array_unique($ids) as $atual) {
            if ($atual && ! $rows->contains(fn (RhUniforme $u): bool => (int) $u->id === $atual)) {
                $extra = RhUniforme::query()->find($atual);
                if ($extra) {
                    $rows->push($extra);
                }
            }
        }

        return $rows->map(fn (RhUniforme $u): array => [
            'id' => (int) $u->id,
            'label' => $u->labelSelect(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultEntregaForm(): array
    {
        return [
            'funcionario_id' => '',
            'uniforme_id' => '',
            'quantidade' => '1',
            'entregue_em' => now('America/Sao_Paulo')->toDateString(),
            'observacao' => '',
            'motivo_correcao' => '',
            'motivo_movimentacao' => '',
            'substitui_id' => '',
            'itens' => [
                ['uniforme_id' => '', 'quantidade' => '1'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function entregaFormFromRecord(RhUniformeEntrega $record): array
    {
        return [
            'funcionario_id' => (string) $record->funcionario_id,
            'uniforme_id' => (string) $record->uniforme_id,
            'quantidade' => (string) $record->quantidade,
            'entregue_em' => $record->entregue_em?->toDateString() ?? '',
            'observacao' => (string) ($record->observacao ?? ''),
            'motivo_correcao' => '',
            'motivo_movimentacao' => '',
            'substitui_id' => '',
            'itens' => [],
        ];
    }
}
