<?php

namespace App\Filament\Resources\RhBeneficioResource\Pages\Concerns;

use App\Models\RhBeneficio;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use App\Support\Rh\RhBeneficioService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesRhBeneficioCatalogModal
{
    public bool $beneficioCatalogModalOpen = false;

    public ?int $beneficioCatalogRecordId = null;

    /** @var array<string, mixed> */
    public array $beneficioCatalogForm = [];

    public function openBeneficioCatalogCreate(): void
    {
        if (! ErpAccess::currentCan('rh.beneficios.create')) {
            Notification::make()->title('Sem permissão para incluir benefício.')->warning()->send();

            return;
        }

        $this->beneficioCatalogRecordId = null;
        $this->beneficioCatalogForm = $this->defaultBeneficioCatalogForm();
        $this->beneficioCatalogForm['codigo'] = RhBeneficio::nextCodigo();
        $this->beneficioCatalogModalOpen = true;
    }

    public function openBeneficioCatalogEdit(?int $beneficioId = null): void
    {
        if (! ErpAccess::currentCan('rh.beneficios.update')) {
            Notification::make()->title('Sem permissão para alterar benefício.')->warning()->send();

            return;
        }

        $id = $beneficioId;
        if (! $id) {
            Notification::make()->title('Selecione um benefício no formulário de vínculo.')->warning()->send();

            return;
        }

        $record = RhBeneficio::query()->find($id);
        if (! $record) {
            Notification::make()->title('Benefício não encontrado.')->warning()->send();

            return;
        }

        $this->beneficioCatalogRecordId = (int) $record->getKey();
        $this->beneficioCatalogForm = [
            'codigo' => (string) $record->codigo,
            'descricao' => (string) $record->descricao,
            'tipo' => (string) $record->tipo,
            'valor_padrao' => $record->valor_padrao !== null ? number_format((float) $record->valor_padrao, 2, '.', '') : '',
            'periodicidade' => (string) $record->periodicidade,
            'ativo' => (bool) $record->ativo,
        ];
        $this->beneficioCatalogModalOpen = true;
    }

    public function openBeneficioCatalog(): void
    {
        $this->openBeneficioCatalogCreate();
    }

    public function closeBeneficioCatalogModal(): void
    {
        $this->beneficioCatalogModalOpen = false;
        $this->beneficioCatalogRecordId = null;
        $this->beneficioCatalogForm = [];
        $this->resetValidation();
    }

    public function updatedBeneficioCatalogFormDescricao(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->beneficioCatalogForm['descricao'] = $upper;
            }
        }
    }

    public function saveBeneficioCatalog(): void
    {
        $isEdit = $this->beneficioCatalogRecordId !== null;
        if (! ErpAccess::currentCan($isEdit ? 'rh.beneficios.update' : 'rh.beneficios.create')) {
            Notification::make()->title('Sem permissão para gravar benefício.')->warning()->send();

            return;
        }

        $this->validate([
            'beneficioCatalogForm.codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rh_beneficios', 'codigo')->ignore($this->beneficioCatalogRecordId),
            ],
            'beneficioCatalogForm.descricao' => ['required', 'string', 'max:160'],
            'beneficioCatalogForm.tipo' => ['required', Rule::in(RhBeneficio::tiposValidos())],
            'beneficioCatalogForm.valor_padrao' => ['nullable', 'numeric', 'min:0'],
            'beneficioCatalogForm.periodicidade' => ['required', Rule::in(RhBeneficio::periodicidadesValidas())],
            'beneficioCatalogForm.ativo' => ['boolean'],
        ], [], [
            'beneficioCatalogForm.codigo' => 'código',
            'beneficioCatalogForm.descricao' => 'descrição',
            'beneficioCatalogForm.tipo' => 'tipo',
            'beneficioCatalogForm.valor_padrao' => 'valor padrão',
            'beneficioCatalogForm.periodicidade' => 'periodicidade',
        ]);

        $payload = [
            'codigo' => trim((string) $this->beneficioCatalogForm['codigo']),
            'descricao' => mb_strtoupper(trim((string) $this->beneficioCatalogForm['descricao']), 'UTF-8'),
            'tipo' => (string) $this->beneficioCatalogForm['tipo'],
            'valor_padrao' => $this->beneficioCatalogForm['valor_padrao'] ?? null,
            'periodicidade' => (string) $this->beneficioCatalogForm['periodicidade'],
            'ativo' => (bool) ($this->beneficioCatalogForm['ativo'] ?? true),
        ];

        $service = app(RhBeneficioService::class);

        try {
            if ($isEdit) {
                $record = RhBeneficio::query()->find($this->beneficioCatalogRecordId);
                if (! $record) {
                    Notification::make()->title('Benefício não encontrado.')->warning()->send();

                    return;
                }
                $record = $service->alterarBeneficio($record, $payload);
                $title = 'Benefício alterado.';
            } else {
                $record = $service->criarBeneficio($payload);
                $title = 'Benefício incluído.';
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        if (property_exists($this, 'vinculoForm')) {
            $this->vinculoForm['beneficio_id'] = (string) $record->getKey();
            if (blank($this->vinculoForm['valor'] ?? null) && $record->valor_padrao !== null) {
                $this->vinculoForm['valor'] = number_format((float) $record->valor_padrao, 2, '.', '');
            }
        }

        $this->closeBeneficioCatalogModal();
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBeneficioCatalogForm(): array
    {
        return [
            'codigo' => '',
            'descricao' => '',
            'tipo' => RhBeneficio::TIPO_OUTROS,
            'valor_padrao' => '',
            'periodicidade' => RhBeneficio::PER_MENSAL,
            'ativo' => true,
        ];
    }
}
