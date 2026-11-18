<?php

namespace App\Filament\Resources\RhExameResource\Pages\Concerns;

use App\Filament\Resources\RhExameResource;
use App\Models\RhExame;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use App\Support\Rh\RhExameService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesRhExameFormModal
{
    use WithFileUploads;

    public bool $exameModalOpen = false;

    public ?int $exameModalRecordId = null;

    /** @var array<string, mixed> */
    public array $exameForm = [];

    public ?TemporaryUploadedFile $exameUpload = null;

    public string $exameArquivoAtual = '';

    public function createExame(): void
    {
        if ($this->exameModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.exames.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $this->exameModalRecordId = null;
        $this->exameForm = $this->defaultExameForm();
        $this->exameUpload = null;
        $this->exameArquivoAtual = '';
        $this->exameModalOpen = true;
    }

    public function editExame(): void
    {
        if ($this->exameModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.exames.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhExame::query()->with([
            'funcionario',
            'anexos' => fn ($q) => $q->where('categoria', \App\Models\RhAnexo::CAT_EXAME)->limit(1),
        ])->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Exame não encontrado.')->warning()->send();

            return;
        }

        $this->exameModalRecordId = (int) $record->getKey();
        $this->exameForm = $this->exameFormFromRecord($record);
        $this->exameUpload = null;
        $anexo = $record->anexos->first();
        $this->exameArquivoAtual = $anexo && filled($anexo->caminho)
            ? basename((string) $anexo->caminho)
            : '';
        $this->exameModalOpen = true;
    }

    public function closeExameModal(): void
    {
        $this->exameModalOpen = false;
        $this->exameModalRecordId = null;
        $this->exameForm = [];
        $this->exameUpload = null;
        $this->exameArquivoAtual = '';
        $this->resetValidation();
    }

    public function updatedExameFormMedicoNome(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->exameForm['medico_nome'] = $upper;
            }
        }
    }

    public function saveExame(): void
    {
        $isEdit = $this->exameModalRecordId !== null;

        if (! ErpAccess::currentCan($isEdit ? 'rh.exames.update' : 'rh.exames.create')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $rules = [
            'exameForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
            'exameForm.tipo' => ['required', 'string', Rule::in(array_keys(RhExame::tipos()))],
            'exameForm.realizado_em' => ['required', 'date'],
            'exameForm.proximo_em' => ['nullable', 'date', 'after_or_equal:exameForm.realizado_em'],
            'exameForm.medico_nome' => ['nullable', 'string', 'max:120'],
            'exameForm.medico_crm' => ['nullable', 'string', 'max:40'],
            'exameForm.conclusao' => ['required', 'string', Rule::in(array_keys(RhExame::conclusoes()))],
            'exameForm.observacao' => ['nullable', 'string', 'max:500'],
            'exameForm.ativo' => ['boolean'],
            'exameUpload' => [$isEdit ? 'nullable' : 'nullable', 'file', 'max:5120'],
        ];

        $this->validate($rules, [], [
            'exameForm.funcionario_id' => 'funcionário',
            'exameForm.tipo' => 'tipo',
            'exameForm.realizado_em' => 'data do exame',
            'exameForm.proximo_em' => 'próximo exame',
            'exameForm.medico_nome' => 'médico',
            'exameForm.medico_crm' => 'CRM',
            'exameForm.conclusao' => 'conclusão',
            'exameUpload' => 'anexo ASO',
        ]);

        $payload = [
            'funcionario_id' => (int) $this->exameForm['funcionario_id'],
            'tipo' => (string) $this->exameForm['tipo'],
            'realizado_em' => $this->exameForm['realizado_em'],
            'proximo_em' => $this->exameForm['proximo_em'] ?? null,
            'medico_nome' => $this->exameForm['medico_nome'] ?? null,
            'medico_crm' => $this->exameForm['medico_crm'] ?? null,
            'conclusao' => $this->exameForm['conclusao'] ?? RhExame::CONCL_NAO_INFORMADA,
            'observacao' => $this->exameForm['observacao'] ?? null,
        ];

        $upload = $this->exameUpload instanceof TemporaryUploadedFile ? $this->exameUpload : null;
        $service = app(RhExameService::class);

        try {
            if ($isEdit) {
                $record = RhExame::query()->find($this->exameModalRecordId);
                if (! $record) {
                    Notification::make()->title('Exame não encontrado.')->warning()->send();

                    return;
                }
                $payload['ativo'] = (bool) ($this->exameForm['ativo'] ?? true);
                $service->alterar($record, $payload, $upload);
                $id = (int) $record->getKey();
                $title = 'Exame alterado.';
            } else {
                $record = $service->criar($payload, $upload);
                $id = (int) $record->getKey();
                $title = 'Exame registrado.';
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeExameModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function exameFuncionarioOptions(): array
    {
        $atual = filled($this->exameForm['funcionario_id'] ?? null) ? (int) $this->exameForm['funcionario_id'] : null;
        $rows = RhExameResource::funcionariosQuery()->get(['id', 'codigo', 'nome']);
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
     * @return array<string, mixed>
     */
    protected function defaultExameForm(): array
    {
        return [
            'funcionario_id' => '',
            'tipo' => RhExame::TIPO_PERIODICO,
            'realizado_em' => now('America/Sao_Paulo')->toDateString(),
            'proximo_em' => '',
            'medico_nome' => '',
            'medico_crm' => '',
            'conclusao' => RhExame::CONCL_NAO_INFORMADA,
            'observacao' => '',
            'ativo' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exameFormFromRecord(RhExame $record): array
    {
        return [
            'funcionario_id' => (string) $record->funcionario_id,
            'tipo' => (string) $record->tipo,
            'realizado_em' => $record->realizado_em?->toDateString() ?? '',
            'proximo_em' => $record->proximo_em?->toDateString() ?? '',
            'medico_nome' => (string) ($record->medico_nome ?? ''),
            'medico_crm' => (string) ($record->medico_crm ?? ''),
            'conclusao' => (string) ($record->conclusao ?: RhExame::CONCL_NAO_INFORMADA),
            'observacao' => (string) ($record->observacao ?? ''),
            'ativo' => (bool) $record->ativo,
        ];
    }
}
