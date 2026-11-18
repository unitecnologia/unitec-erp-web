<?php

namespace App\Filament\Resources\RhDocumentoResource\Pages\Concerns;

use App\Filament\Resources\RhDocumentoResource;
use App\Models\RhAnexo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use App\Support\Rh\RhAnexoService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesRhDocumentoFormModal
{
    use WithFileUploads;

    public bool $documentoModalOpen = false;

    public ?int $documentoModalRecordId = null;

    /** @var array<string, mixed> */
    public array $documentoForm = [];

    public ?TemporaryUploadedFile $documentoUpload = null;

    public string $documentoArquivoAtual = '';

    public function createDocumento(): void
    {
        if ($this->documentoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.documentos.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $this->documentoModalRecordId = null;
        $this->documentoForm = $this->defaultDocumentoForm();
        $this->documentoUpload = null;
        $this->documentoArquivoAtual = '';
        $this->documentoModalOpen = true;
    }

    public function editDocumento(): void
    {
        if ($this->documentoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.documentos.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhAnexo::query()->documentos()->with('anexavel')->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()->title('Documento não encontrado.')->warning()->send();

            return;
        }

        $this->documentoModalRecordId = (int) $record->getKey();
        $this->documentoForm = $this->documentoFormFromRecord($record);
        $this->documentoUpload = null;
        $this->documentoArquivoAtual = filled($record->caminho)
            ? basename((string) $record->caminho)
            : '';
        $this->documentoModalOpen = true;
    }

    public function closeDocumentoModal(): void
    {
        $this->documentoModalOpen = false;
        $this->documentoModalRecordId = null;
        $this->documentoForm = [];
        $this->documentoUpload = null;
        $this->documentoArquivoAtual = '';
        $this->resetValidation();
    }

    public function updatedDocumentoFormTitulo(mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $upper = ErpUppercase::uppercase($value);

        if ($upper !== $value) {
            $this->documentoForm['titulo'] = $upper;
        }
    }

    public function saveDocumento(): void
    {
        $isEdit = $this->documentoModalRecordId !== null;
        $permission = $isEdit ? 'rh.documentos.update' : 'rh.documentos.create';

        if (! ErpAccess::currentCan($permission)) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $rules = [
            'documentoForm.funcionario_id' => [
                'required',
                'integer',
                Rule::exists('rh_funcionarios', 'id'),
            ],
            'documentoForm.titulo' => ['required', 'string', 'max:160'],
            'documentoForm.emitido_em' => ['nullable', 'date'],
            'documentoForm.valido_ate' => ['nullable', 'date', 'after_or_equal:documentoForm.emitido_em'],
            'documentoForm.observacao' => ['nullable', 'string', 'max:500'],
            'documentoForm.ativo' => ['boolean'],
            'documentoUpload' => [
                $isEdit ? 'nullable' : 'required',
                'file',
                'max:5120',
                'mimes:'.implode(',', RhAnexoService::allowedExtensions()),
            ],
        ];

        $this->validate($rules, [
            'documentoUpload.required' => 'Selecione o arquivo do documento.',
            'documentoUpload.mimes' => 'Arquivo não permitido. Use PDF, imagem ou Word.',
            'documentoUpload.max' => 'Arquivo maior que 5 MB.',
        ], [
            'documentoForm.funcionario_id' => 'funcionário',
            'documentoForm.titulo' => 'título',
            'documentoForm.emitido_em' => 'emitido em',
            'documentoForm.valido_ate' => 'válido até',
            'documentoForm.observacao' => 'observação',
            'documentoUpload' => 'arquivo',
        ]);

        $funcionario = RhFuncionario::query()->find((int) $this->documentoForm['funcionario_id']);

        if (! $funcionario) {
            Notification::make()->title('Funcionário não encontrado.')->warning()->send();

            return;
        }

        $meta = [
            'titulo' => (string) $this->documentoForm['titulo'],
            'categoria' => RhAnexo::CAT_DOCUMENTO,
            'emitido_em' => $this->blankToNull($this->documentoForm['emitido_em'] ?? null),
            'valido_ate' => $this->blankToNull($this->documentoForm['valido_ate'] ?? null),
            'observacao' => $this->blankToNull($this->documentoForm['observacao'] ?? null),
        ];

        $service = app(RhAnexoService::class);

        try {
            if ($isEdit) {
                $record = RhAnexo::query()->documentos()->find($this->documentoModalRecordId);

                if (! $record) {
                    Notification::make()->title('Documento não encontrado.')->warning()->send();

                    return;
                }

                if ((int) $record->anexavel_id !== (int) $funcionario->getKey()
                    || $record->anexavel_type !== $funcionario->getMorphClass()) {
                    // Reassocia ao funcionário escolhido sem perder o arquivo atual.
                    $record->anexavel()->associate($funcionario);
                    $record->save();
                }

                $meta['ativo'] = (bool) ($this->documentoForm['ativo'] ?? true);
                $upload = $this->documentoUpload instanceof TemporaryUploadedFile
                    ? $this->documentoUpload
                    : null;
                $service->updateMeta($record, $meta, $upload);
                $title = 'Documento alterado.';
            } else {
                /** @var TemporaryUploadedFile $upload */
                $upload = $this->documentoUpload;
                $record = $service->store($funcionario, $upload, $meta);
                $title = 'Documento incluído.';
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $id = (int) $record->getKey();
        $this->closeDocumentoModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);

        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function documentoFuncionarioOptions(): array
    {
        $atualId = filled($this->documentoForm['funcionario_id'] ?? null)
            ? (int) $this->documentoForm['funcionario_id']
            : null;

        $rows = RhDocumentoResource::funcionariosQuery()
            ->get(['id', 'codigo', 'nome']);

        if ($atualId && ! $rows->contains(fn (RhFuncionario $f): bool => (int) $f->id === $atualId)) {
            $extra = RhFuncionario::query()->find($atualId, ['id', 'codigo', 'nome']);
            if ($extra) {
                $rows->push($extra);
            }
        }

        return $rows
            ->map(fn (RhFuncionario $f): array => [
                'id' => (int) $f->id,
                'label' => trim($f->codigo.' — '.$f->nome),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultDocumentoForm(): array
    {
        return [
            'funcionario_id' => '',
            'titulo' => '',
            'emitido_em' => '',
            'valido_ate' => '',
            'observacao' => '',
            'ativo' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function documentoFormFromRecord(RhAnexo $record): array
    {
        return [
            'funcionario_id' => (string) $record->anexavel_id,
            'titulo' => (string) $record->titulo,
            'emitido_em' => $record->emitido_em?->toDateString() ?? '',
            'valido_ate' => $record->valido_ate?->toDateString() ?? '',
            'observacao' => (string) ($record->observacao ?? ''),
            'ativo' => (bool) $record->ativo,
        ];
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
