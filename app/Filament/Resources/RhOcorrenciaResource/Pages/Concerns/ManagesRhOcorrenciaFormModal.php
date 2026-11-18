<?php

namespace App\Filament\Resources\RhOcorrenciaResource\Pages\Concerns;

use App\Filament\Resources\RhOcorrenciaResource;
use App\Models\RhFuncionario;
use App\Models\RhOcorrencia;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use App\Support\Rh\RhOcorrenciaService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesRhOcorrenciaFormModal
{
    use WithFileUploads;

    public bool $ocorrenciaModalOpen = false;

    public ?int $ocorrenciaModalRecordId = null;

    /** @var array<string, mixed> */
    public array $ocorrenciaForm = [];

    public ?TemporaryUploadedFile $ocorrenciaUpload = null;

    public string $ocorrenciaArquivoAtual = '';

    public function createOcorrencia(): void
    {
        if ($this->ocorrenciaModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.ocorrencias.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $this->ocorrenciaModalRecordId = null;
        $this->ocorrenciaForm = $this->defaultOcorrenciaForm();
        $this->ocorrenciaUpload = null;
        $this->ocorrenciaArquivoAtual = '';
        $this->ocorrenciaModalOpen = true;
    }

    public function editOcorrencia(): void
    {
        if ($this->ocorrenciaModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.ocorrencias.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhOcorrencia::query()->with([
            'funcionario',
            'anexos' => fn ($q) => $q->where('categoria', \App\Models\RhAnexo::CAT_OCORRENCIA)->limit(1),
        ])->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Ocorrência não encontrada.')->warning()->send();

            return;
        }

        if ($record->isCancelada()) {
            Notification::make()->title('Ocorrência cancelada não pode ser alterada.')->warning()->send();

            return;
        }

        $this->ocorrenciaModalRecordId = (int) $record->getKey();
        $this->ocorrenciaForm = $this->ocorrenciaFormFromRecord($record);
        $this->ocorrenciaUpload = null;
        $anexo = $record->anexos->first();
        $this->ocorrenciaArquivoAtual = $anexo && filled($anexo->caminho)
            ? basename((string) $anexo->caminho)
            : '';
        $this->ocorrenciaModalOpen = true;
    }

    public function closeOcorrenciaModal(): void
    {
        $this->ocorrenciaModalOpen = false;
        $this->ocorrenciaModalRecordId = null;
        $this->ocorrenciaForm = [];
        $this->ocorrenciaUpload = null;
        $this->ocorrenciaArquivoAtual = '';
        $this->resetValidation();
    }

    public function updatedOcorrenciaFormResponsavel(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->ocorrenciaForm['responsavel'] = $upper;
            }
        }
    }

    public function saveOcorrencia(): void
    {
        $isEdit = $this->ocorrenciaModalRecordId !== null;

        if (! ErpAccess::currentCan($isEdit ? 'rh.ocorrencias.update' : 'rh.ocorrencias.create')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $rules = [
            'ocorrenciaForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
            'ocorrenciaForm.tipo' => ['required', 'string', Rule::in(array_keys(RhOcorrencia::tipos()))],
            'ocorrenciaForm.ocorrido_em' => ['required', 'date'],
            'ocorrenciaForm.descricao' => ['required', 'string', 'max:1000'],
            'ocorrenciaForm.responsavel' => ['nullable', 'string', 'max:120'],
            'ocorrenciaForm.acesso_restrito' => ['boolean'],
            'ocorrenciaUpload' => ['nullable', 'file', 'max:5120'],
        ];

        $this->validate($rules, [], [
            'ocorrenciaForm.funcionario_id' => 'funcionário',
            'ocorrenciaForm.tipo' => 'tipo',
            'ocorrenciaForm.ocorrido_em' => 'data da ocorrência',
            'ocorrenciaForm.descricao' => 'descrição',
            'ocorrenciaForm.responsavel' => 'responsável',
            'ocorrenciaUpload' => 'anexo',
        ]);

        $payload = [
            'funcionario_id' => (int) $this->ocorrenciaForm['funcionario_id'],
            'tipo' => (string) $this->ocorrenciaForm['tipo'],
            'ocorrido_em' => $this->ocorrenciaForm['ocorrido_em'],
            'descricao' => $this->ocorrenciaForm['descricao'] ?? null,
            'responsavel' => $this->ocorrenciaForm['responsavel'] ?? null,
            'acesso_restrito' => (bool) ($this->ocorrenciaForm['acesso_restrito'] ?? false),
        ];

        $upload = $this->ocorrenciaUpload instanceof TemporaryUploadedFile ? $this->ocorrenciaUpload : null;
        $service = app(RhOcorrenciaService::class);

        try {
            if ($isEdit) {
                $record = RhOcorrencia::query()->find($this->ocorrenciaModalRecordId);
                if (! $record) {
                    Notification::make()->title('Ocorrência não encontrada.')->warning()->send();

                    return;
                }
                $service->alterar($record, $payload, $upload);
                $id = (int) $record->getKey();
                $title = 'Ocorrência alterada.';
            } else {
                $record = $service->criar($payload, $upload);
                $id = (int) $record->getKey();
                $title = 'Ocorrência registrada.';
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeOcorrenciaModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function ocorrenciaFuncionarioOptions(): array
    {
        $atual = filled($this->ocorrenciaForm['funcionario_id'] ?? null) ? (int) $this->ocorrenciaForm['funcionario_id'] : null;
        $rows = RhOcorrenciaResource::funcionariosQuery()->get(['id', 'codigo', 'nome']);
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
    protected function defaultOcorrenciaForm(): array
    {
        return [
            'funcionario_id' => '',
            'tipo' => RhOcorrencia::TIPO_ADVERTENCIA,
            'ocorrido_em' => now('America/Sao_Paulo')->toDateString(),
            'descricao' => '',
            'responsavel' => '',
            'acesso_restrito' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ocorrenciaFormFromRecord(RhOcorrencia $record): array
    {
        $podeVer = RhOcorrenciaService::podeVerConteudoRestrito();

        return [
            'funcionario_id' => (string) $record->funcionario_id,
            'tipo' => (string) $record->tipo,
            'ocorrido_em' => $record->ocorrido_em?->toDateString() ?? '',
            'descricao' => $record->acesso_restrito && ! $podeVer
                ? ''
                : (string) $record->descricao,
            'responsavel' => (string) ($record->responsavel ?? ''),
            'acesso_restrito' => (bool) $record->acesso_restrito,
        ];
    }
}
