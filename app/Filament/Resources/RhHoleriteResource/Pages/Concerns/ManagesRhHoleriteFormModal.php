<?php

namespace App\Filament\Resources\RhHoleriteResource\Pages\Concerns;

use App\Filament\Resources\RhHoleriteResource;
use App\Models\RhFuncionario;
use App\Models\RhHolerite;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhHoleriteService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesRhHoleriteFormModal
{
    use WithFileUploads;

    public bool $holeriteModalOpen = false;

    public ?int $holeriteModalRecordId = null;

    /** @var array<string, mixed> */
    public array $holeriteForm = [];

    public ?TemporaryUploadedFile $holeriteUpload = null;

    public string $holeriteArquivoAtual = '';

    public bool $holeriteDuplicataAviso = false;

    public function createHolerite(): void
    {
        if ($this->holeriteModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.holerites.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $this->holeriteModalRecordId = null;
        $this->holeriteForm = $this->defaultHoleriteForm();
        $this->holeriteUpload = null;
        $this->holeriteArquivoAtual = '';
        $this->holeriteDuplicataAviso = false;
        $this->holeriteModalOpen = true;
    }

    public function editHolerite(): void
    {
        if ($this->holeriteModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.holerites.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhHolerite::query()->with([
            'funcionario',
            'anexos' => fn ($q) => $q->where('categoria', \App\Models\RhAnexo::CAT_HOLERITE)->limit(1),
        ])->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Holerite não encontrado.')->warning()->send();

            return;
        }

        $this->holeriteModalRecordId = (int) $record->getKey();
        $this->holeriteForm = $this->holeriteFormFromRecord($record);
        $this->holeriteUpload = null;
        $anexo = $record->anexos->first();
        $this->holeriteArquivoAtual = $anexo && filled($anexo->caminho)
            ? basename((string) $anexo->caminho)
            : '';
        $this->holeriteDuplicataAviso = false;
        $this->holeriteModalOpen = true;
    }

    public function closeHoleriteModal(): void
    {
        $this->holeriteModalOpen = false;
        $this->holeriteModalRecordId = null;
        $this->holeriteForm = [];
        $this->holeriteUpload = null;
        $this->holeriteArquivoAtual = '';
        $this->holeriteDuplicataAviso = false;
        $this->resetValidation();
    }

    public function saveHolerite(): void
    {
        $isEdit = $this->holeriteModalRecordId !== null;

        if (! ErpAccess::currentCan($isEdit ? 'rh.holerites.update' : 'rh.holerites.create')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $substituir = (bool) ($this->holeriteForm['substituir_duplicata'] ?? false);

        $rules = [
            'holeriteForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
            'holeriteForm.competencia_ano' => ['required', 'integer', 'min:2000', 'max:2100'],
            'holeriteForm.competencia_mes' => ['required', 'integer', 'min:1', 'max:12'],
            'holeriteForm.pago_em' => ['nullable', 'date'],
            'holeriteForm.observacao' => ['nullable', 'string', 'max:500'],
            'holeriteForm.ativo' => ['boolean'],
            'holeriteUpload' => [$isEdit ? 'nullable' : 'required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'holeriteForm.substituir_motivo' => [$substituir ? 'required' : 'nullable', 'string', 'max:255'],
        ];

        $this->validate($rules, [], [
            'holeriteForm.funcionario_id' => 'funcionário',
            'holeriteForm.competencia_ano' => 'ano',
            'holeriteForm.competencia_mes' => 'mês',
            'holeriteForm.pago_em' => 'pago em',
            'holeriteUpload' => 'PDF do holerite',
            'holeriteForm.substituir_motivo' => 'motivo da substituição',
        ]);

        $payload = [
            'funcionario_id' => (int) $this->holeriteForm['funcionario_id'],
            'competencia_ano' => (int) $this->holeriteForm['competencia_ano'],
            'competencia_mes' => (int) $this->holeriteForm['competencia_mes'],
            'pago_em' => $this->holeriteForm['pago_em'] ?? null,
            'observacao' => $this->holeriteForm['observacao'] ?? null,
        ];

        $upload = $this->holeriteUpload instanceof TemporaryUploadedFile ? $this->holeriteUpload : null;
        $service = app(RhHoleriteService::class);

        try {
            if ($isEdit) {
                $record = RhHolerite::query()->find($this->holeriteModalRecordId);
                if (! $record) {
                    Notification::make()->title('Holerite não encontrado.')->warning()->send();

                    return;
                }
                $payload['ativo'] = (bool) ($this->holeriteForm['ativo'] ?? true);
                $service->alterar($record, $payload, $upload);
                $id = (int) $record->getKey();
                $title = 'Holerite alterado.';
            } else {
                $existente = RhHolerite::query()
                    ->where('funcionario_id', $payload['funcionario_id'])
                    ->where('competencia_ano', $payload['competencia_ano'])
                    ->where('competencia_mes', $payload['competencia_mes'])
                    ->first();

                if ($existente && ! $substituir) {
                    $this->holeriteDuplicataAviso = true;
                    $this->holeriteForm['substituir_duplicata'] = false;
                    Notification::make()
                        ->title('Já existe holerite para esta competência. Marque substituição e informe o motivo.')
                        ->warning()
                        ->send();

                    return;
                }

                if ($existente && $substituir) {
                    if (! $upload instanceof TemporaryUploadedFile) {
                        Notification::make()->title('Envie o PDF para substituir o holerite.')->warning()->send();

                        return;
                    }
                    $motivo = trim((string) ($this->holeriteForm['substituir_motivo'] ?? ''));
                    $record = $service->substituir($existente, $payload, $upload, $motivo);
                    $id = (int) $record->getKey();
                    $title = 'Holerite substituído.';
                } else {
                    if (! $upload instanceof TemporaryUploadedFile) {
                        Notification::make()->title('PDF do holerite é obrigatório.')->warning()->send();

                        return;
                    }
                    $record = $service->criar($payload, $upload);
                    $id = (int) $record->getKey();
                    $title = 'Holerite registrado.';
                }
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeHoleriteModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function holeriteFuncionarioOptions(): array
    {
        $atual = filled($this->holeriteForm['funcionario_id'] ?? null) ? (int) $this->holeriteForm['funcionario_id'] : null;
        $rows = RhHoleriteResource::funcionariosQuery()->get(['id', 'codigo', 'nome']);
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
    protected function defaultHoleriteForm(): array
    {
        $agora = now('America/Sao_Paulo');

        return [
            'funcionario_id' => '',
            'competencia_ano' => (string) $agora->year,
            'competencia_mes' => (string) $agora->month,
            'pago_em' => '',
            'observacao' => '',
            'ativo' => true,
            'substituir_duplicata' => false,
            'substituir_motivo' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function holeriteFormFromRecord(RhHolerite $record): array
    {
        return [
            'funcionario_id' => (string) $record->funcionario_id,
            'competencia_ano' => (string) $record->competencia_ano,
            'competencia_mes' => (string) $record->competencia_mes,
            'pago_em' => $record->pago_em?->toDateString() ?? '',
            'observacao' => (string) ($record->observacao ?? ''),
            'ativo' => (bool) $record->ativo,
            'substituir_duplicata' => false,
            'substituir_motivo' => '',
        ];
    }
}
