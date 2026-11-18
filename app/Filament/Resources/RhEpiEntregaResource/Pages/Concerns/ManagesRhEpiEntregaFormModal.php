<?php

namespace App\Filament\Resources\RhEpiEntregaResource\Pages\Concerns;

use App\Filament\Resources\RhEpiEntregaResource;
use App\Models\RhEpi;
use App\Models\RhEpiEntrega;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhEpiEntregaService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesRhEpiEntregaFormModal
{
    public bool $entregaModalOpen = false;

    public ?int $entregaModalRecordId = null;

    /** @var array<string, mixed> */
    public array $entregaForm = [];

    public string $entregaModalMode = 'create'; // create|edit|substituir

    public function createEntrega(): void
    {
        if ($this->entregaModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.epis.create')) {
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

        if (! ErpAccess::currentCan('rh.epis.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhEpiEntrega::query()->with(['funcionario', 'epi'])->find($this->highlightedRecordId);
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
        if (! ErpAccess::currentCan('rh.epis.create')) {
            Notification::make()->title('Sem permissão para substituir.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhEpiEntrega::query()->find($this->highlightedRecordId);
        if (! $record || $record->situacao !== RhEpiEntrega::SIT_ENTREGUE) {
            Notification::make()->title('Selecione uma entrega aberta para substituir.')->warning()->send();

            return;
        }

        $this->entregaModalRecordId = null;
        $this->entregaModalMode = 'substituir';
        $this->entregaForm = $this->defaultEntregaForm();
        $this->entregaForm['funcionario_id'] = (string) $record->funcionario_id;
        $this->entregaForm['epi_id'] = (string) $record->epi_id;
        $this->entregaForm['quantidade'] = (string) $record->quantidade;
        $this->entregaForm['substitui_id'] = (string) $record->getKey();
        $this->entregaForm['motivo_substituicao'] = '';
        $this->entregaModalOpen = true;
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
        $needCreate = $mode === 'create' || $mode === 'substituir';

        if (! ErpAccess::currentCan($needCreate ? 'rh.epis.create' : 'rh.epis.update')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $rules = [
            'entregaForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
            'entregaForm.epi_id' => ['required', 'integer', Rule::exists('rh_epis', 'id')],
            'entregaForm.quantidade' => ['required', 'integer', 'min:1', 'max:999'],
            'entregaForm.entregue_em' => ['required', 'date'],
            'entregaForm.previsao_troca' => ['nullable', 'date'],
            'entregaForm.observacao' => ['nullable', 'string', 'max:500'],
            'entregaForm.motivo_correcao' => ['nullable', 'string', 'max:255'],
            'entregaForm.motivo_substituicao' => [$mode === 'substituir' ? 'required' : 'nullable', 'string', 'max:255'],
        ];

        $this->validate($rules, [
            'entregaForm.motivo_substituicao.required' => 'Informe o motivo da substituição.',
        ], [
            'entregaForm.funcionario_id' => 'funcionário',
            'entregaForm.epi_id' => 'EPI',
            'entregaForm.quantidade' => 'quantidade',
            'entregaForm.entregue_em' => 'data da entrega',
            'entregaForm.previsao_troca' => 'previsão de troca',
            'entregaForm.motivo_substituicao' => 'motivo da substituição',
        ]);

        $epi = RhEpi::query()->find((int) $this->entregaForm['epi_id']);
        if ($epi && $epi->caVencido()) {
            Notification::make()
                ->title('Atenção: CA do EPI vencido.')
                ->body('A entrega será registrada. Verifique a NR-6 e a renovação do CA.')
                ->warning()
                ->send();
        }

        $service = app(RhEpiEntregaService::class);

        try {
            if ($mode === 'edit') {
                $record = RhEpiEntrega::query()->find($this->entregaModalRecordId);
                if (! $record) {
                    Notification::make()->title('Entrega não encontrada.')->warning()->send();

                    return;
                }
                $service->corrigir($record, [
                    'funcionario_id' => (int) $this->entregaForm['funcionario_id'],
                    'epi_id' => (int) $this->entregaForm['epi_id'],
                    'quantidade' => (int) $this->entregaForm['quantidade'],
                    'entregue_em' => $this->entregaForm['entregue_em'],
                    'previsao_troca' => $this->entregaForm['previsao_troca'] ?? null,
                    'observacao' => $this->entregaForm['observacao'] ?? null,
                ], $this->entregaForm['motivo_correcao'] ?? null);
                $id = (int) $record->getKey();
                $title = 'Entrega corrigida.';
            } else {
                $payload = [
                    'funcionario_id' => (int) $this->entregaForm['funcionario_id'],
                    'epi_id' => (int) $this->entregaForm['epi_id'],
                    'quantidade' => (int) $this->entregaForm['quantidade'],
                    'entregue_em' => $this->entregaForm['entregue_em'],
                    'previsao_troca' => $this->entregaForm['previsao_troca'] ?? null,
                    'observacao' => $this->entregaForm['observacao'] ?? null,
                ];
                if ($mode === 'substituir') {
                    $payload['substitui_id'] = (int) ($this->entregaForm['substitui_id'] ?? 0);
                    $payload['motivo_substituicao'] = $this->entregaForm['motivo_substituicao'] ?? null;
                }
                $record = $service->entregar($payload);
                $id = (int) $record->getKey();
                $title = $mode === 'substituir' ? 'Substituição registrada.' : 'Entrega registrada.';
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeEntregaModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function entregaFuncionarioOptions(): array
    {
        $atual = filled($this->entregaForm['funcionario_id'] ?? null) ? (int) $this->entregaForm['funcionario_id'] : null;
        $rows = RhEpiEntregaResource::funcionariosQuery()->get(['id', 'codigo', 'nome']);
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
    public function entregaEpiOptions(): array
    {
        $atual = filled($this->entregaForm['epi_id'] ?? null) ? (int) $this->entregaForm['epi_id'] : null;
        $rows = RhEpiEntregaResource::episQuery(true)->get();
        if ($atual && ! $rows->contains(fn (RhEpi $e): bool => (int) $e->id === $atual)) {
            $extra = RhEpi::query()->find($atual);
            if ($extra) {
                $rows->push($extra);
            }
        }

        return $rows->map(fn (RhEpi $e): array => [
            'id' => (int) $e->id,
            'label' => $e->labelSelect(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultEntregaForm(): array
    {
        return [
            'funcionario_id' => '',
            'epi_id' => '',
            'quantidade' => '1',
            'entregue_em' => now('America/Sao_Paulo')->toDateString(),
            'previsao_troca' => '',
            'observacao' => '',
            'motivo_correcao' => '',
            'motivo_substituicao' => '',
            'substitui_id' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function entregaFormFromRecord(RhEpiEntrega $record): array
    {
        return [
            'funcionario_id' => (string) $record->funcionario_id,
            'epi_id' => (string) $record->epi_id,
            'quantidade' => (string) $record->quantidade,
            'entregue_em' => $record->entregue_em?->toDateString() ?? '',
            'previsao_troca' => $record->previsao_troca?->toDateString() ?? '',
            'observacao' => (string) ($record->observacao ?? ''),
            'motivo_correcao' => '',
            'motivo_substituicao' => '',
            'substitui_id' => '',
        ];
    }
}
