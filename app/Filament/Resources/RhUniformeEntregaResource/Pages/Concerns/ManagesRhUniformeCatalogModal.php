<?php

namespace App\Filament\Resources\RhUniformeEntregaResource\Pages\Concerns;

use App\Models\RhUniforme;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ManagesRhUniformeCatalogModal
{
    public bool $uniformeCatalogModalOpen = false;

    public ?int $uniformeCatalogRecordId = null;

    /** @var array<string, mixed> */
    public array $uniformeCatalogForm = [];

    public function openUniformeCatalogCreate(): void
    {
        if (! ErpAccess::currentCan('rh.uniformes.create')) {
            Notification::make()->title('Sem permissão para incluir uniforme.')->warning()->send();

            return;
        }

        $this->uniformeCatalogRecordId = null;
        $this->uniformeCatalogForm = $this->defaultUniformeCatalogForm();
        $this->uniformeCatalogForm['codigo'] = RhUniforme::nextCodigo();
        $this->uniformeCatalogModalOpen = true;
    }

    public function openUniformeCatalogEdit(?int $uniformeId = null): void
    {
        if (! ErpAccess::currentCan('rh.uniformes.update')) {
            Notification::make()->title('Sem permissão para alterar uniforme.')->warning()->send();

            return;
        }

        $id = $uniformeId;
        if (! $id) {
            Notification::make()->title('Selecione um uniforme no formulário de entrega.')->warning()->send();

            return;
        }

        $record = RhUniforme::query()->find($id);
        if (! $record) {
            Notification::make()->title('Uniforme não encontrado.')->warning()->send();

            return;
        }

        $this->uniformeCatalogRecordId = (int) $record->getKey();
        $this->uniformeCatalogForm = [
            'codigo' => (string) $record->codigo,
            'descricao' => (string) $record->descricao,
            'tipo' => (string) ($record->tipo ?? ''),
            'tamanho' => (string) ($record->tamanho ?? ''),
            'cor' => (string) ($record->cor ?? ''),
            'ativo' => (bool) $record->ativo,
        ];
        $this->uniformeCatalogModalOpen = true;
    }

    public function closeUniformeCatalogModal(): void
    {
        $this->uniformeCatalogModalOpen = false;
        $this->uniformeCatalogRecordId = null;
        $this->uniformeCatalogForm = [];
        $this->resetValidation();
    }

    public function updatedUniformeCatalogFormDescricao(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->uniformeCatalogForm['descricao'] = $upper;
            }
        }
    }

    public function updatedUniformeCatalogFormCor(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->uniformeCatalogForm['cor'] = $upper;
            }
        }
    }

    public function saveUniformeCatalog(): void
    {
        $isEdit = $this->uniformeCatalogRecordId !== null;
        if (! ErpAccess::currentCan($isEdit ? 'rh.uniformes.update' : 'rh.uniformes.create')) {
            Notification::make()->title('Sem permissão para gravar uniforme.')->warning()->send();

            return;
        }

        $this->validate([
            'uniformeCatalogForm.codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rh_uniformes', 'codigo')->ignore($this->uniformeCatalogRecordId),
            ],
            'uniformeCatalogForm.descricao' => ['required', 'string', 'max:160'],
            'uniformeCatalogForm.tipo' => ['nullable', 'string', 'max:40'],
            'uniformeCatalogForm.tamanho' => ['nullable', 'string', 'max:20'],
            'uniformeCatalogForm.cor' => ['nullable', 'string', 'max:40'],
            'uniformeCatalogForm.ativo' => ['boolean'],
        ], [], [
            'uniformeCatalogForm.codigo' => 'código',
            'uniformeCatalogForm.descricao' => 'descrição',
            'uniformeCatalogForm.tipo' => 'tipo',
            'uniformeCatalogForm.tamanho' => 'tamanho',
            'uniformeCatalogForm.cor' => 'cor',
        ]);

        $payload = [
            'codigo' => trim((string) $this->uniformeCatalogForm['codigo']),
            'descricao' => mb_strtoupper(trim((string) $this->uniformeCatalogForm['descricao']), 'UTF-8'),
            'tipo' => $this->blank($this->uniformeCatalogForm['tipo'] ?? null),
            'tamanho' => $this->blank($this->uniformeCatalogForm['tamanho'] ?? null),
            'cor' => $this->blankUpper($this->uniformeCatalogForm['cor'] ?? null),
            'ativo' => (bool) ($this->uniformeCatalogForm['ativo'] ?? true),
        ];

        $duplicado = RhUniforme::query()
            ->when($this->uniformeCatalogRecordId, fn ($q) => $q->whereKeyNot($this->uniformeCatalogRecordId))
            ->where('descricao', $payload['descricao'])
            ->when(
                $payload['tipo'] !== null,
                fn ($q) => $q->where('tipo', $payload['tipo']),
                fn ($q) => $q->where(function ($inner): void {
                    $inner->whereNull('tipo')->orWhere('tipo', '');
                })
            )
            ->when(
                $payload['tamanho'] !== null,
                fn ($q) => $q->where('tamanho', $payload['tamanho']),
                fn ($q) => $q->where(function ($inner): void {
                    $inner->whereNull('tamanho')->orWhere('tamanho', '');
                })
            )
            ->when(
                $payload['cor'] !== null,
                fn ($q) => $q->where('cor', $payload['cor']),
                fn ($q) => $q->where(function ($inner): void {
                    $inner->whereNull('cor')->orWhere('cor', '');
                })
            )
            ->exists();

        if ($duplicado) {
            Notification::make()
                ->title('Já existe uniforme com a mesma descrição, tipo, tamanho e cor.')
                ->warning()
                ->send();

            return;
        }

        if ($isEdit) {
            $record = RhUniforme::query()->find($this->uniformeCatalogRecordId);
            if (! $record) {
                Notification::make()->title('Uniforme não encontrado.')->warning()->send();

                return;
            }
            $record->update($payload);
            $title = 'Uniforme alterado.';
        } else {
            $record = RhUniforme::query()->create($payload);
            $title = 'Uniforme incluído.';
        }

        if (property_exists($this, 'entregaForm')) {
            if (($this->entregaModalMode ?? '') === 'create' && isset($this->entregaForm['itens'][0])) {
                $this->entregaForm['itens'][0]['uniforme_id'] = (string) $record->getKey();
            } else {
                $this->entregaForm['uniforme_id'] = (string) $record->getKey();
            }
        }

        $this->closeUniformeCatalogModal();
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultUniformeCatalogForm(): array
    {
        return [
            'codigo' => '',
            'descricao' => '',
            'tipo' => '',
            'tamanho' => '',
            'cor' => '',
            'ativo' => true,
        ];
    }

    protected function blank(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    protected function blankUpper(mixed $value): ?string
    {
        $v = $this->blank($value);

        return $v === null ? null : mb_strtoupper($v, 'UTF-8');
    }
}
