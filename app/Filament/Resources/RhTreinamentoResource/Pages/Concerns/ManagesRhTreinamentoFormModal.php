<?php

namespace App\Filament\Resources\RhTreinamentoResource\Pages\Concerns;

use App\Filament\Resources\RhTreinamentoResource;
use App\Models\RhFuncionario;
use App\Models\RhTreinamento;
use App\Models\RhTreinamentoParticipante;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhTreinamentoService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesRhTreinamentoFormModal
{
    public bool $realizacaoModalOpen = false;

    public ?int $participanteModalRecordId = null;

    /** @var array<string, mixed> */
    public array $realizacaoForm = [];

    public string $realizacaoModalMode = 'create'; // create|edit

    public function createRealizacao(): void
    {
        if ($this->realizacaoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.treinamentos.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $this->participanteModalRecordId = null;
        $this->realizacaoModalMode = 'create';
        $this->realizacaoForm = $this->defaultRealizacaoForm();
        $this->realizacaoModalOpen = true;
    }

    public function editParticipante(): void
    {
        if ($this->realizacaoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.treinamentos.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhTreinamentoParticipante::query()
            ->with(['funcionario', 'realizacao.treinamento'])
            ->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()->title('Participante não encontrado.')->warning()->send();

            return;
        }

        $this->participanteModalRecordId = (int) $record->getKey();
        $this->realizacaoModalMode = 'edit';
        $this->realizacaoForm = $this->realizacaoFormFromRecord($record);
        $this->realizacaoModalOpen = true;
    }

    public function closeRealizacaoModal(): void
    {
        $this->realizacaoModalOpen = false;
        $this->participanteModalRecordId = null;
        $this->realizacaoModalMode = 'create';
        $this->realizacaoForm = [];
        $this->resetValidation();
    }

    public function saveRealizacao(): void
    {
        $mode = $this->realizacaoModalMode;
        $needCreate = $mode === 'create';

        if (! ErpAccess::currentCan($needCreate ? 'rh.treinamentos.create' : 'rh.treinamentos.update')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        if ($needCreate) {
            $this->validate([
                'realizacaoForm.treinamento_id' => ['required', 'integer', Rule::exists('rh_treinamentos', 'id')],
                'realizacaoForm.funcionario_ids' => ['required', 'array', 'min:1'],
                'realizacaoForm.funcionario_ids.*' => ['integer', Rule::exists('rh_funcionarios', 'id')],
                'realizacaoForm.realizado_em' => ['required', 'date'],
                'realizacaoForm.vencimento_em' => ['nullable', 'date'],
                'realizacaoForm.observacao' => ['nullable', 'string', 'max:500'],
            ], [
                'realizacaoForm.funcionario_ids.required' => 'Selecione ao menos um funcionário.',
                'realizacaoForm.funcionario_ids.min' => 'Selecione ao menos um funcionário.',
            ], [
                'realizacaoForm.treinamento_id' => 'treinamento',
                'realizacaoForm.funcionario_ids' => 'funcionários',
                'realizacaoForm.realizado_em' => 'data',
                'realizacaoForm.vencimento_em' => 'vencimento',
            ]);
        } else {
            $this->validate([
                'realizacaoForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
                'realizacaoForm.vencimento_em' => ['nullable', 'date'],
                'realizacaoForm.observacao' => ['nullable', 'string', 'max:255'],
                'realizacaoForm.motivo_correcao' => ['nullable', 'string', 'max:255'],
            ], [], [
                'realizacaoForm.funcionario_id' => 'funcionário',
                'realizacaoForm.vencimento_em' => 'vencimento',
            ]);
        }

        $service = app(RhTreinamentoService::class);

        try {
            if ($mode === 'edit') {
                $record = RhTreinamentoParticipante::query()->find($this->participanteModalRecordId);
                if (! $record) {
                    Notification::make()->title('Participante não encontrado.')->warning()->send();

                    return;
                }
                $service->corrigirParticipante($record, [
                    'funcionario_id' => (int) $this->realizacaoForm['funcionario_id'],
                    'vencimento_em' => $this->realizacaoForm['vencimento_em'] ?? null,
                    'observacao' => $this->realizacaoForm['observacao'] ?? null,
                ], $this->realizacaoForm['motivo_correcao'] ?? null);
                $id = (int) $record->getKey();
                $title = 'Participante corrigido.';
            } else {
                $ids = array_map('intval', $this->realizacaoForm['funcionario_ids'] ?? []);
                $realizacao = $service->realizar([
                    'treinamento_id' => (int) $this->realizacaoForm['treinamento_id'],
                    'funcionario_ids' => $ids,
                    'realizado_em' => $this->realizacaoForm['realizado_em'],
                    'vencimento_em' => $this->realizacaoForm['vencimento_em'] ?? null,
                    'observacao' => $this->realizacaoForm['observacao'] ?? null,
                ]);
                $primeiro = $realizacao->participantes->first();
                $id = $primeiro ? (int) $primeiro->getKey() : 0;
                $title = 'Realização registrada.';
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeRealizacaoModal();
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
    public function realizacaoFuncionarioOptions(): array
    {
        $rows = RhTreinamentoResource::funcionariosQuery()->get(['id', 'codigo', 'nome']);

        $extras = [];
        if ($this->realizacaoModalMode === 'edit' && filled($this->realizacaoForm['funcionario_id'] ?? null)) {
            $extras[] = (int) $this->realizacaoForm['funcionario_id'];
        }
        foreach (($this->realizacaoForm['funcionario_ids'] ?? []) as $fid) {
            $extras[] = (int) $fid;
        }

        foreach (array_unique($extras) as $atual) {
            if ($atual && ! $rows->contains(fn (RhFuncionario $f): bool => (int) $f->id === $atual)) {
                $extra = RhFuncionario::query()->find($atual, ['id', 'codigo', 'nome']);
                if ($extra) {
                    $rows->push($extra);
                }
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
    public function realizacaoTreinamentoOptions(): array
    {
        $atual = filled($this->realizacaoForm['treinamento_id'] ?? null) ? (int) $this->realizacaoForm['treinamento_id'] : null;
        $rows = RhTreinamentoResource::treinamentosQuery(true)->get();
        if ($atual && ! $rows->contains(fn (RhTreinamento $t): bool => (int) $t->id === $atual)) {
            $extra = RhTreinamento::query()->find($atual);
            if ($extra) {
                $rows->push($extra);
            }
        }

        return $rows->map(fn (RhTreinamento $t): array => [
            'id' => (int) $t->id,
            'label' => $t->labelSelect(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultRealizacaoForm(): array
    {
        return [
            'treinamento_id' => '',
            'funcionario_ids' => [],
            'funcionario_id' => '',
            'realizado_em' => now('America/Sao_Paulo')->toDateString(),
            'vencimento_em' => '',
            'observacao' => '',
            'motivo_correcao' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function realizacaoFormFromRecord(RhTreinamentoParticipante $record): array
    {
        return [
            'treinamento_id' => (string) ($record->realizacao?->treinamento_id ?? ''),
            'funcionario_ids' => [],
            'funcionario_id' => (string) $record->funcionario_id,
            'realizado_em' => $record->realizacao?->realizado_em?->toDateString() ?? '',
            'vencimento_em' => $record->vencimento_em?->toDateString() ?? '',
            'observacao' => (string) ($record->observacao ?? ''),
            'motivo_correcao' => '',
            'treinamento_nome' => (string) ($record->realizacao?->treinamento?->nome ?? ''),
        ];
    }
}
