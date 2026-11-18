<?php

namespace App\Filament\Resources\RhEscalaResource\Pages\Concerns;

use App\Filament\Resources\RhEscalaResource;
use App\Models\RhEscala;
use App\Models\RhEscalaVinculo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhEscalaService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesRhEscalaVinculoFormModal
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

        if (! ErpAccess::currentCan('rh.escalas.create')) {
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

        if (! ErpAccess::currentCan('rh.escalas.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhEscalaVinculo::query()->find($this->highlightedRecordId);
        if (! $record || ! $record->ativo) {
            Notification::make()->title('Vínculo não encontrado ou já encerrado.')->warning()->send();

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

    public function saveVinculo(): void
    {
        $isEdit = $this->vinculoModalRecordId !== null;
        if (! ErpAccess::currentCan($isEdit ? 'rh.escalas.update' : 'rh.escalas.create')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $this->validate([
            'vinculoForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
            'vinculoForm.escala_id' => ['required', 'integer', Rule::exists('rh_escalas', 'id')],
            'vinculoForm.inicio_em' => ['required', 'date'],
            'vinculoForm.termino_em' => ['nullable', 'date', 'after_or_equal:vinculoForm.inicio_em'],
            'vinculoForm.observacao' => ['nullable', 'string', 'max:500'],
        ], [], [
            'vinculoForm.funcionario_id' => 'funcionário',
            'vinculoForm.escala_id' => 'escala',
            'vinculoForm.inicio_em' => 'início',
        ]);

        $service = app(RhEscalaService::class);

        try {
            if ($isEdit) {
                $record = RhEscalaVinculo::query()->findOrFail($this->vinculoModalRecordId);
                $conflitos = $service->detectarConflitos(
                    (int) $this->vinculoForm['funcionario_id'],
                    (string) $this->vinculoForm['inicio_em'],
                    filled($this->vinculoForm['termino_em'] ?? null) ? (string) $this->vinculoForm['termino_em'] : null,
                    (int) $record->getKey()
                );
                if ($conflitos !== []) {
                    throw new InvalidArgumentException('Conflito de vigência com outro vínculo do mesmo funcionário.');
                }

                $record->fill([
                    'escala_id' => (int) $this->vinculoForm['escala_id'],
                    'funcionario_id' => (int) $this->vinculoForm['funcionario_id'],
                    'inicio_em' => $this->vinculoForm['inicio_em'],
                    'termino_em' => filled($this->vinculoForm['termino_em'] ?? null) ? $this->vinculoForm['termino_em'] : null,
                    'observacao' => filled($this->vinculoForm['observacao'] ?? null) ? trim((string) $this->vinculoForm['observacao']) : null,
                ]);
                $record->save();

                Notification::make()->title('Vínculo atualizado.')->success()->send();
            } else {
                $service->vincular([
                    'escala_id' => (int) $this->vinculoForm['escala_id'],
                    'funcionario_id' => (int) $this->vinculoForm['funcionario_id'],
                    'inicio_em' => (string) $this->vinculoForm['inicio_em'],
                    'termino_em' => $this->vinculoForm['termino_em'] ?? null,
                    'observacao' => $this->vinculoForm['observacao'] ?? null,
                ]);

                Notification::make()->title('Vínculo incluído.')->success()->send();
            }

            $this->closeVinculoModal();
            $this->resetTable();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    public function encerrarVinculoSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.escalas.delete')) {
            Notification::make()->title('Sem permissão para encerrar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('delete')) {
            return;
        }

        $record = RhEscalaVinculo::query()->find($this->highlightedRecordId);
        if (! $record || ! $record->ativo) {
            Notification::make()->title('Vínculo não encontrado ou já encerrado.')->warning()->send();

            return;
        }

        try {
            app(RhEscalaService::class)->encerrarVinculo($record, null, 'Encerrado pela tela de escalas');
            Notification::make()->title('Vínculo encerrado.')->success()->send();
            $this->clearListSelection();
            $this->resetTable();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function vinculoFuncionarioOptions(): array
    {
        return RhEscalaResource::funcionariosQuery()
            ->get(['id', 'codigo', 'nome'])
            ->map(fn (RhFuncionario $f): array => [
                'id' => (int) $f->getKey(),
                'label' => trim($f->codigo.' — '.$f->nome),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function escalasOpcoes(): array
    {
        return RhEscala::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->limit(200)
            ->get(['id', 'codigo', 'nome', 'tipo'])
            ->map(fn (RhEscala $e): array => [
                'id' => (int) $e->getKey(),
                'label' => trim($e->codigo.' — '.$e->nome.' ('.$e->tipoLabel().')'),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultVinculoForm(): array
    {
        return [
            'funcionario_id' => null,
            'escala_id' => null,
            'inicio_em' => now()->toDateString(),
            'termino_em' => null,
            'observacao' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function vinculoFormFromRecord(RhEscalaVinculo $record): array
    {
        return [
            'funcionario_id' => (int) $record->funcionario_id,
            'escala_id' => (int) $record->escala_id,
            'inicio_em' => $record->inicio_em?->toDateString(),
            'termino_em' => $record->termino_em?->toDateString(),
            'observacao' => (string) ($record->observacao ?? ''),
        ];
    }
}
