<?php

namespace App\Filament\Resources\RhTreinamentoResource\Pages\Concerns;

use App\Models\RhTreinamento;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ManagesRhTreinamentoCatalogModal
{
    public bool $treinamentoCatalogModalOpen = false;

    public ?int $treinamentoCatalogRecordId = null;

    /** @var array<string, mixed> */
    public array $treinamentoCatalogForm = [];

    public function openTreinamentoCatalogCreate(): void
    {
        if (! ErpAccess::currentCan('rh.treinamentos.create')) {
            Notification::make()->title('Sem permissão para incluir treinamento.')->warning()->send();

            return;
        }

        $this->treinamentoCatalogRecordId = null;
        $this->treinamentoCatalogForm = $this->defaultTreinamentoCatalogForm();
        $this->treinamentoCatalogForm['codigo'] = RhTreinamento::nextCodigo();
        $this->treinamentoCatalogModalOpen = true;
    }

    public function openTreinamentoCatalogEdit(?int $treinamentoId = null): void
    {
        if (! ErpAccess::currentCan('rh.treinamentos.update')) {
            Notification::make()->title('Sem permissão para alterar treinamento.')->warning()->send();

            return;
        }

        $id = $treinamentoId;
        if (! $id) {
            Notification::make()->title('Selecione um treinamento no formulário.')->warning()->send();

            return;
        }

        $record = RhTreinamento::query()->find($id);
        if (! $record) {
            Notification::make()->title('Treinamento não encontrado.')->warning()->send();

            return;
        }

        $this->treinamentoCatalogRecordId = (int) $record->getKey();
        $this->treinamentoCatalogForm = [
            'codigo' => (string) $record->codigo,
            'nome' => (string) $record->nome,
            'descricao' => (string) ($record->descricao ?? ''),
            'tipo' => (string) ($record->tipo ?? ''),
            'carga_horaria' => $record->carga_horaria !== null ? (string) $record->carga_horaria : '',
            'instrutor' => (string) ($record->instrutor ?? ''),
            'periodicidade_dias' => $record->periodicidade_dias !== null ? (string) $record->periodicidade_dias : '',
            'ativo' => (bool) $record->ativo,
        ];
        $this->treinamentoCatalogModalOpen = true;
    }

    public function closeTreinamentoCatalogModal(): void
    {
        $this->treinamentoCatalogModalOpen = false;
        $this->treinamentoCatalogRecordId = null;
        $this->treinamentoCatalogForm = [];
        $this->resetValidation();
    }

    public function updatedTreinamentoCatalogFormNome(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->treinamentoCatalogForm['nome'] = $upper;
            }
        }
    }

    public function updatedTreinamentoCatalogFormTipo(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->treinamentoCatalogForm['tipo'] = $upper;
            }
        }
    }

    public function updatedTreinamentoCatalogFormInstrutor(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->treinamentoCatalogForm['instrutor'] = $upper;
            }
        }
    }

    public function saveTreinamentoCatalog(): void
    {
        $isEdit = $this->treinamentoCatalogRecordId !== null;
        if (! ErpAccess::currentCan($isEdit ? 'rh.treinamentos.update' : 'rh.treinamentos.create')) {
            Notification::make()->title('Sem permissão para gravar treinamento.')->warning()->send();

            return;
        }

        $this->validate([
            'treinamentoCatalogForm.codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rh_treinamentos', 'codigo')->ignore($this->treinamentoCatalogRecordId),
            ],
            'treinamentoCatalogForm.nome' => ['required', 'string', 'max:160'],
            'treinamentoCatalogForm.descricao' => ['nullable', 'string', 'max:2000'],
            'treinamentoCatalogForm.tipo' => ['nullable', 'string', 'max:60'],
            'treinamentoCatalogForm.carga_horaria' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'treinamentoCatalogForm.instrutor' => ['nullable', 'string', 'max:120'],
            'treinamentoCatalogForm.periodicidade_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'treinamentoCatalogForm.ativo' => ['boolean'],
        ], [], [
            'treinamentoCatalogForm.codigo' => 'código',
            'treinamentoCatalogForm.nome' => 'nome',
            'treinamentoCatalogForm.descricao' => 'descrição',
            'treinamentoCatalogForm.tipo' => 'tipo',
            'treinamentoCatalogForm.carga_horaria' => 'carga horária',
            'treinamentoCatalogForm.instrutor' => 'instrutor',
            'treinamentoCatalogForm.periodicidade_dias' => 'periodicidade',
        ]);

        $payload = [
            'codigo' => trim((string) $this->treinamentoCatalogForm['codigo']),
            'nome' => mb_strtoupper(trim((string) $this->treinamentoCatalogForm['nome']), 'UTF-8'),
            'descricao' => $this->blankCatalog($this->treinamentoCatalogForm['descricao'] ?? null),
            'tipo' => $this->blankCatalogUpper($this->treinamentoCatalogForm['tipo'] ?? null),
            'carga_horaria' => filled($this->treinamentoCatalogForm['carga_horaria'] ?? null)
                ? (float) $this->treinamentoCatalogForm['carga_horaria']
                : null,
            'instrutor' => $this->blankCatalogUpper($this->treinamentoCatalogForm['instrutor'] ?? null),
            'periodicidade_dias' => filled($this->treinamentoCatalogForm['periodicidade_dias'] ?? null)
                ? (int) $this->treinamentoCatalogForm['periodicidade_dias']
                : null,
            'ativo' => (bool) ($this->treinamentoCatalogForm['ativo'] ?? true),
        ];

        $duplicado = RhTreinamento::query()
            ->when($this->treinamentoCatalogRecordId, fn ($q) => $q->whereKeyNot($this->treinamentoCatalogRecordId))
            ->where('nome', $payload['nome'])
            ->when(
                $payload['tipo'] !== null,
                fn ($q) => $q->where('tipo', $payload['tipo']),
                fn ($q) => $q->where(function ($inner): void {
                    $inner->whereNull('tipo')->orWhere('tipo', '');
                })
            )
            ->exists();

        if ($duplicado) {
            Notification::make()
                ->title('Já existe treinamento com o mesmo nome e tipo.')
                ->warning()
                ->send();

            return;
        }

        if ($isEdit) {
            $record = RhTreinamento::query()->find($this->treinamentoCatalogRecordId);
            if (! $record) {
                Notification::make()->title('Treinamento não encontrado.')->warning()->send();

                return;
            }
            $record->update($payload);
            $title = 'Treinamento alterado.';
        } else {
            $record = RhTreinamento::query()->create($payload);
            $title = 'Treinamento incluído.';
        }

        if (property_exists($this, 'realizacaoForm')) {
            $this->realizacaoForm['treinamento_id'] = (string) $record->getKey();
        }

        $this->closeTreinamentoCatalogModal();
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultTreinamentoCatalogForm(): array
    {
        return [
            'codigo' => '',
            'nome' => '',
            'descricao' => '',
            'tipo' => '',
            'carga_horaria' => '',
            'instrutor' => '',
            'periodicidade_dias' => '',
            'ativo' => true,
        ];
    }

    protected function blankCatalog(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    protected function blankCatalogUpper(mixed $value): ?string
    {
        $v = $this->blankCatalog($value);

        return $v === null ? null : mb_strtoupper($v, 'UTF-8');
    }
}
