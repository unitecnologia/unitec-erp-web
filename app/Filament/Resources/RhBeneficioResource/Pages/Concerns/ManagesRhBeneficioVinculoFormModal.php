<?php

namespace App\Filament\Resources\RhBeneficioResource\Pages\Concerns;

use App\Filament\Resources\RhBeneficioResource;
use App\Models\RhBeneficio;
use App\Models\RhBeneficioVinculo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhBeneficioService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesRhBeneficioVinculoFormModal
{
    public bool $vinculoModalOpen = false;

    public ?int $vinculoModalRecordId = null;

    /** @var array<string, mixed> */
    public array $vinculoForm = [];

    public function createVinculo(): void
    {
        if ($this->vinculoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.beneficios.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $this->vinculoModalRecordId = null;
        $this->vinculoForm = $this->defaultVinculoForm();
        $this->vinculoModalOpen = true;
    }

    public function editVinculo(): void
    {
        if ($this->vinculoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.beneficios.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhBeneficioVinculo::query()->with(['funcionario', 'beneficio'])->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Vínculo não encontrado.')->warning()->send();

            return;
        }

        if ($record->situacao === RhBeneficioVinculo::SIT_CANCELADO) {
            Notification::make()->title('Vínculo cancelado não pode ser alterado.')->warning()->send();

            return;
        }

        $this->vinculoModalRecordId = (int) $record->getKey();
        $this->vinculoForm = $this->vinculoFormFromRecord($record);
        $this->vinculoModalOpen = true;
    }

    public function closeVinculoModal(): void
    {
        $this->vinculoModalOpen = false;
        $this->vinculoModalRecordId = null;
        $this->vinculoForm = [];
        $this->resetValidation();
    }

    public function updatedVinculoFormBeneficioId(mixed $value): void
    {
        if ($this->vinculoModalRecordId !== null) {
            return;
        }

        if (! filled($value)) {
            return;
        }

        $beneficio = RhBeneficio::query()->find((int) $value);
        if ($beneficio && blank($this->vinculoForm['valor'] ?? null) && $beneficio->valor_padrao !== null) {
            $this->vinculoForm['valor'] = number_format((float) $beneficio->valor_padrao, 2, '.', '');
        }
    }

    public function saveVinculo(): void
    {
        $isEdit = $this->vinculoModalRecordId !== null;

        if (! ErpAccess::currentCan($isEdit ? 'rh.beneficios.update' : 'rh.beneficios.create')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $this->validate([
            'vinculoForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
            'vinculoForm.beneficio_id' => ['required', 'integer', Rule::exists('rh_beneficios', 'id')],
            'vinculoForm.inicio_em' => ['required', 'date'],
            'vinculoForm.termino_em' => ['nullable', 'date', 'after_or_equal:vinculoForm.inicio_em'],
            'vinculoForm.valor' => ['required', 'numeric', 'min:0'],
            'vinculoForm.participacao_funcionario' => ['nullable', 'numeric', 'min:0'],
            'vinculoForm.observacao' => ['nullable', 'string', 'max:500'],
            'vinculoForm.motivo_alteracao' => ['nullable', 'string', 'max:255'],
        ], [], [
            'vinculoForm.funcionario_id' => 'funcionário',
            'vinculoForm.beneficio_id' => 'benefício',
            'vinculoForm.inicio_em' => 'início',
            'vinculoForm.termino_em' => 'término',
            'vinculoForm.valor' => 'valor',
            'vinculoForm.participacao_funcionario' => 'participação do funcionário',
        ]);

        $service = app(RhBeneficioService::class);

        try {
            if ($isEdit) {
                $record = RhBeneficioVinculo::query()->find($this->vinculoModalRecordId);
                if (! $record) {
                    Notification::make()->title('Vínculo não encontrado.')->warning()->send();

                    return;
                }
                $service->alterarVinculo($record, [
                    'funcionario_id' => (int) $this->vinculoForm['funcionario_id'],
                    'beneficio_id' => (int) $this->vinculoForm['beneficio_id'],
                    'inicio_em' => $this->vinculoForm['inicio_em'],
                    'termino_em' => $this->vinculoForm['termino_em'] ?? null,
                    'valor' => $this->vinculoForm['valor'],
                    'participacao_funcionario' => $this->vinculoForm['participacao_funcionario'] ?? null,
                    'observacao' => $this->vinculoForm['observacao'] ?? null,
                ], $this->vinculoForm['motivo_alteracao'] ?? null);
                $id = (int) $record->getKey();
                $title = 'Vínculo alterado.';
            } else {
                $record = $service->vincular([
                    'funcionario_id' => (int) $this->vinculoForm['funcionario_id'],
                    'beneficio_id' => (int) $this->vinculoForm['beneficio_id'],
                    'inicio_em' => $this->vinculoForm['inicio_em'],
                    'termino_em' => $this->vinculoForm['termino_em'] ?? null,
                    'valor' => $this->vinculoForm['valor'],
                    'participacao_funcionario' => $this->vinculoForm['participacao_funcionario'] ?? null,
                    'observacao' => $this->vinculoForm['observacao'] ?? null,
                ]);
                $id = (int) $record->getKey();
                $title = 'Vínculo registrado.';
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeVinculoModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);
        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function vinculoFuncionarioOptions(): array
    {
        $atual = filled($this->vinculoForm['funcionario_id'] ?? null) ? (int) $this->vinculoForm['funcionario_id'] : null;
        $rows = RhBeneficioResource::funcionariosQuery()->get(['id', 'codigo', 'nome']);
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
    public function vinculoBeneficioOptions(): array
    {
        $atual = filled($this->vinculoForm['beneficio_id'] ?? null) ? (int) $this->vinculoForm['beneficio_id'] : null;
        $rows = RhBeneficioResource::beneficiosQuery(true)->get();
        if ($atual && ! $rows->contains(fn (RhBeneficio $b): bool => (int) $b->id === $atual)) {
            $extra = RhBeneficio::query()->find($atual);
            if ($extra) {
                $rows->push($extra);
            }
        }

        return $rows->map(fn (RhBeneficio $b): array => [
            'id' => (int) $b->id,
            'label' => $b->labelSelect(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultVinculoForm(): array
    {
        return [
            'funcionario_id' => '',
            'beneficio_id' => '',
            'inicio_em' => now('America/Sao_Paulo')->toDateString(),
            'termino_em' => '',
            'valor' => '',
            'participacao_funcionario' => '',
            'observacao' => '',
            'motivo_alteracao' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function vinculoFormFromRecord(RhBeneficioVinculo $record): array
    {
        return [
            'funcionario_id' => (string) $record->funcionario_id,
            'beneficio_id' => (string) $record->beneficio_id,
            'inicio_em' => $record->inicio_em?->toDateString() ?? '',
            'termino_em' => $record->termino_em?->toDateString() ?? '',
            'valor' => number_format((float) $record->valor, 2, '.', ''),
            'participacao_funcionario' => $record->participacao_funcionario !== null
                ? number_format((float) $record->participacao_funcionario, 2, '.', '')
                : '',
            'observacao' => (string) ($record->observacao ?? ''),
            'motivo_alteracao' => '',
        ];
    }
}
