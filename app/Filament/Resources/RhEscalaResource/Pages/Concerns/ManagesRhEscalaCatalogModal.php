<?php

namespace App\Filament\Resources\RhEscalaResource\Pages\Concerns;

use App\Models\RhEscala;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUppercase;
use App\Support\Rh\RhEscalaService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesRhEscalaCatalogModal
{
    public bool $escalaCatalogModalOpen = false;

    public ?int $escalaCatalogRecordId = null;

    /** @var array<string, mixed> */
    public array $escalaCatalogForm = [];

    public function openEscalaCatalogCreate(): void
    {
        if (! ErpAccess::currentCan('rh.escalas.create')) {
            Notification::make()->title('Sem permissão para incluir escala.')->warning()->send();

            return;
        }

        $this->escalaCatalogRecordId = null;
        $this->escalaCatalogForm = $this->defaultEscalaCatalogForm();
        $this->escalaCatalogForm['codigo'] = RhEscala::nextCodigo();
        $this->escalaCatalogModalOpen = true;
    }

    public function openEscalaCatalogEdit(?int $escalaId = null): void
    {
        if (! ErpAccess::currentCan('rh.escalas.update')) {
            Notification::make()->title('Sem permissão para alterar escala.')->warning()->send();

            return;
        }

        $id = $escalaId ?? (int) ($this->vinculoForm['escala_id'] ?? 0);
        if ($id < 1) {
            Notification::make()->title('Selecione uma escala.')->warning()->send();

            return;
        }

        $record = RhEscala::query()->find($id);
        if (! $record) {
            Notification::make()->title('Escala não encontrada.')->warning()->send();

            return;
        }

        $this->escalaCatalogRecordId = (int) $record->getKey();
        $this->escalaCatalogForm = $this->escalaCatalogFormFromRecord($record);
        $this->escalaCatalogModalOpen = true;
    }

    public function closeEscalaCatalogModal(): void
    {
        $this->escalaCatalogModalOpen = false;
        $this->escalaCatalogRecordId = null;
        $this->escalaCatalogForm = [];
        $this->resetValidation();
    }

    public function updatedEscalaCatalogFormNome(mixed $value): void
    {
        if (is_string($value)) {
            $upper = ErpUppercase::uppercase($value);
            if ($upper !== $value) {
                $this->escalaCatalogForm['nome'] = $upper;
            }
        }
    }

    public function updatedEscalaCatalogFormTipo(mixed $value): void
    {
        $tipo = is_string($value) ? $value : '';
        if (! array_key_exists($tipo, RhEscala::tipos())) {
            return;
        }

        $padrao = app(RhEscalaService::class)->diasPadraoParaTipo($tipo);
        $this->escalaCatalogForm['dias_trabalho'] = $padrao['dias_trabalho'];
        $this->escalaCatalogForm['dias_folga'] = $padrao['dias_folga'];
    }

    public function saveEscalaCatalog(): void
    {
        $isEdit = $this->escalaCatalogRecordId !== null;
        if (! ErpAccess::currentCan($isEdit ? 'rh.escalas.update' : 'rh.escalas.create')) {
            Notification::make()->title('Sem permissão para gravar escala.')->warning()->send();

            return;
        }

        $this->validate([
            'escalaCatalogForm.codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rh_escalas', 'codigo')->ignore($this->escalaCatalogRecordId),
            ],
            'escalaCatalogForm.nome' => ['required', 'string', 'max:120'],
            'escalaCatalogForm.tipo' => ['required', Rule::in(array_keys(RhEscala::tipos()))],
            'escalaCatalogForm.hora_entrada' => ['nullable', 'date_format:H:i'],
            'escalaCatalogForm.hora_saida' => ['nullable', 'date_format:H:i'],
            'escalaCatalogForm.intervalo_minutos' => ['nullable', 'integer', 'min:0', 'max:600'],
            'escalaCatalogForm.ativo' => ['boolean'],
        ], [], [
            'escalaCatalogForm.nome' => 'nome',
            'escalaCatalogForm.tipo' => 'tipo',
        ]);

        try {
            $record = $this->escalaCatalogRecordId
                ? RhEscala::query()->findOrFail($this->escalaCatalogRecordId)
                : null;

            $saved = app(RhEscalaService::class)->salvarEscala($record, array_merge($this->escalaCatalogForm, [
                'codigo' => trim((string) $this->escalaCatalogForm['codigo']),
                'dias_trabalho' => $this->escalaCatalogForm['dias_trabalho'] ?? [],
                'dias_folga' => $this->escalaCatalogForm['dias_folga'] ?? [],
            ]));

            if (! $isEdit && filled($this->vinculoForm['escala_id'] ?? null) === false) {
                $this->vinculoForm['escala_id'] = (int) $saved->getKey();
            }

            Notification::make()->title($isEdit ? 'Escala atualizada.' : 'Escala incluída.')->success()->send();
            $this->closeEscalaCatalogModal();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultEscalaCatalogForm(): array
    {
        $padrao = app(RhEscalaService::class)->diasPadraoParaTipo(RhEscala::TIPO_5X2);

        return [
            'codigo' => '',
            'nome' => '',
            'tipo' => RhEscala::TIPO_5X2,
            'hora_entrada' => '08:00',
            'hora_saida' => '18:00',
            'intervalo_minutos' => 60,
            'dias_trabalho' => $padrao['dias_trabalho'],
            'dias_folga' => $padrao['dias_folga'],
            'ativo' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function escalaCatalogFormFromRecord(RhEscala $record): array
    {
        return [
            'codigo' => (string) $record->codigo,
            'nome' => (string) $record->nome,
            'tipo' => (string) $record->tipo,
            'hora_entrada' => $record->hora_entrada ? substr((string) $record->hora_entrada, 0, 5) : null,
            'hora_saida' => $record->hora_saida ? substr((string) $record->hora_saida, 0, 5) : null,
            'intervalo_minutos' => $record->intervalo_minutos,
            'dias_trabalho' => $record->dias_trabalho ?? [],
            'dias_folga' => $record->dias_folga ?? [],
            'ativo' => (bool) $record->ativo,
        ];
    }

    public function toggleEscalaDiaTrabalho(int $dow): void
    {
        $dias = $this->escalaCatalogForm['dias_trabalho'] ?? [];
        if (in_array($dow, $dias, true)) {
            $dias = array_values(array_diff($dias, [$dow]));
        } else {
            $dias[] = $dow;
        }
        sort($dias);
        $this->escalaCatalogForm['dias_trabalho'] = $dias;
    }

    public function toggleEscalaDiaFolga(int $dow): void
    {
        $dias = $this->escalaCatalogForm['dias_folga'] ?? [];
        if (in_array($dow, $dias, true)) {
            $dias = array_values(array_diff($dias, [$dow]));
        } else {
            $dias[] = $dow;
        }
        sort($dias);
        $this->escalaCatalogForm['dias_folga'] = $dias;
    }
}
