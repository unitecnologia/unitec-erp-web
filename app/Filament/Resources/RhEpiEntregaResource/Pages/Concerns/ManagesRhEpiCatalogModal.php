<?php

namespace App\Filament\Resources\RhEpiEntregaResource\Pages\Concerns;

use App\Models\RhEpi;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ManagesRhEpiCatalogModal
{
    public bool $epiCatalogModalOpen = false;

    public ?int $epiCatalogRecordId = null;

    /** @var array<string, mixed> */
    public array $epiCatalogForm = [];

    public function openEpiCatalogCreate(): void
    {
        if (! ErpAccess::currentCan('rh.epis.create')) {
            Notification::make()->title('Sem permissão para incluir EPI.')->warning()->send();

            return;
        }

        $this->epiCatalogRecordId = null;
        $this->epiCatalogForm = $this->defaultEpiCatalogForm();
        $this->epiCatalogForm['codigo'] = RhEpi::nextCodigo();
        $this->epiCatalogModalOpen = true;
    }

    public function openEpiCatalogEdit(?int $epiId = null): void
    {
        if (! ErpAccess::currentCan('rh.epis.update')) {
            Notification::make()->title('Sem permissão para alterar EPI.')->warning()->send();

            return;
        }

        $id = $epiId;
        if (! $id) {
            Notification::make()->title('Selecione um EPI no formulário de entrega.')->warning()->send();

            return;
        }

        $record = RhEpi::query()->find($id);
        if (! $record) {
            Notification::make()->title('EPI não encontrado.')->warning()->send();

            return;
        }

        $this->epiCatalogRecordId = (int) $record->getKey();
        $this->epiCatalogForm = [
            'codigo' => (string) $record->codigo,
            'descricao' => (string) $record->descricao,
            'ca_numero' => (string) ($record->ca_numero ?? ''),
            'ca_valido_ate' => $record->ca_valido_ate?->toDateString() ?? '',
            'fabricante' => (string) ($record->fabricante ?? ''),
            'validade_equipamento' => $record->validade_equipamento?->toDateString() ?? '',
            'intervalo_troca_dias' => $record->intervalo_troca_dias !== null ? (string) $record->intervalo_troca_dias : '',
            'ativo' => (bool) $record->ativo,
        ];
        $this->epiCatalogModalOpen = true;
    }

    public function closeEpiCatalogModal(): void
    {
        $this->epiCatalogModalOpen = false;
        $this->epiCatalogRecordId = null;
        $this->epiCatalogForm = [];
        $this->resetValidation();
    }

    public function updatedEpiCatalogFormDescricao(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->epiCatalogForm['descricao'] = $upper;
            }
        }
    }

    public function updatedEpiCatalogFormFabricante(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->epiCatalogForm['fabricante'] = $upper;
            }
        }
    }

    public function saveEpiCatalog(): void
    {
        $isEdit = $this->epiCatalogRecordId !== null;
        if (! ErpAccess::currentCan($isEdit ? 'rh.epis.update' : 'rh.epis.create')) {
            Notification::make()->title('Sem permissão para gravar EPI.')->warning()->send();

            return;
        }

        $this->validate([
            'epiCatalogForm.codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rh_epis', 'codigo')->ignore($this->epiCatalogRecordId),
            ],
            'epiCatalogForm.descricao' => ['required', 'string', 'max:160'],
            'epiCatalogForm.ca_numero' => ['nullable', 'string', 'max:40'],
            'epiCatalogForm.ca_valido_ate' => ['nullable', 'date'],
            'epiCatalogForm.fabricante' => ['nullable', 'string', 'max:120'],
            'epiCatalogForm.validade_equipamento' => ['nullable', 'date'],
            'epiCatalogForm.intervalo_troca_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'epiCatalogForm.ativo' => ['boolean'],
        ], [], [
            'epiCatalogForm.codigo' => 'código',
            'epiCatalogForm.descricao' => 'descrição',
            'epiCatalogForm.ca_numero' => 'CA',
            'epiCatalogForm.ca_valido_ate' => 'validade do CA',
            'epiCatalogForm.fabricante' => 'fabricante',
            'epiCatalogForm.validade_equipamento' => 'validade do equipamento',
            'epiCatalogForm.intervalo_troca_dias' => 'intervalo de troca',
        ]);

        $payload = [
            'codigo' => trim((string) $this->epiCatalogForm['codigo']),
            'descricao' => mb_strtoupper(trim((string) $this->epiCatalogForm['descricao']), 'UTF-8'),
            'ca_numero' => $this->blank($this->epiCatalogForm['ca_numero'] ?? null),
            'ca_valido_ate' => $this->blank($this->epiCatalogForm['ca_valido_ate'] ?? null),
            'fabricante' => $this->blankUpper($this->epiCatalogForm['fabricante'] ?? null),
            'validade_equipamento' => $this->blank($this->epiCatalogForm['validade_equipamento'] ?? null),
            'intervalo_troca_dias' => filled($this->epiCatalogForm['intervalo_troca_dias'] ?? null)
                ? (int) $this->epiCatalogForm['intervalo_troca_dias']
                : null,
            'ativo' => (bool) ($this->epiCatalogForm['ativo'] ?? true),
        ];

        $duplicado = RhEpi::query()
            ->when($this->epiCatalogRecordId, fn ($q) => $q->whereKeyNot($this->epiCatalogRecordId))
            ->where('descricao', $payload['descricao'])
            ->when(
                $payload['ca_numero'] !== null,
                fn ($q) => $q->where('ca_numero', $payload['ca_numero']),
                fn ($q) => $q->where(function ($inner): void {
                    $inner->whereNull('ca_numero')->orWhere('ca_numero', '');
                })
            )
            ->exists();

        if ($duplicado) {
            Notification::make()
                ->title('Já existe EPI com a mesma descrição e CA.')
                ->warning()
                ->send();

            return;
        }

        if ($isEdit) {
            $record = RhEpi::query()->find($this->epiCatalogRecordId);
            if (! $record) {
                Notification::make()->title('EPI não encontrado.')->warning()->send();

                return;
            }
            $record->update($payload);
            $title = 'EPI alterado.';
        } else {
            $record = RhEpi::query()->create($payload);
            $title = 'EPI incluído.';
        }

        if (property_exists($this, 'entregaForm')) {
            $this->entregaForm['epi_id'] = (string) $record->getKey();
        }

        $this->closeEpiCatalogModal();
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultEpiCatalogForm(): array
    {
        return [
            'codigo' => '',
            'descricao' => '',
            'ca_numero' => '',
            'ca_valido_ate' => '',
            'fabricante' => '',
            'validade_equipamento' => '',
            'intervalo_troca_dias' => '',
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
