<?php

namespace App\Filament\Resources\RhEscalaResource\Pages\Concerns;

use App\Models\RhEscalaAjuste;
use App\Models\RhEscalaVinculo;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhEscalaService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesRhEscalaAjustes
{
    public bool $ajusteModalOpen = false;

    /** @var array<string, mixed> */
    public array $ajusteForm = [];

    public function openAjusteModal(): void
    {
        if (! ErpAccess::currentCan('rh.escalas.update')) {
            Notification::make()->title('Sem permissão para ajustar dia.')->warning()->send();

            return;
        }

        $funcionarioId = null;
        $vinculoId = null;

        if ($this->highlightedRecordId) {
            $vinculo = RhEscalaVinculo::query()->find($this->highlightedRecordId);
            if ($vinculo) {
                $funcionarioId = (int) $vinculo->funcionario_id;
                $vinculoId = (int) $vinculo->getKey();
            }
        }

        $this->ajusteForm = [
            'funcionario_id' => $funcionarioId,
            'vinculo_id' => $vinculoId,
            'data_ref' => now()->toDateString(),
            'tipo' => RhEscalaAjuste::TIPO_TRABALHO,
            'hora_inicio' => null,
            'hora_fim' => null,
            'motivo' => '',
        ];
        $this->ajusteModalOpen = true;
    }

    public function closeAjusteModal(): void
    {
        $this->ajusteModalOpen = false;
        $this->ajusteForm = [];
        $this->resetValidation();
    }

    public function saveAjusteDia(): void
    {
        if (! ErpAccess::currentCan('rh.escalas.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $this->validate([
            'ajusteForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
            'ajusteForm.data_ref' => ['required', 'date'],
            'ajusteForm.tipo' => ['required', Rule::in(array_keys(RhEscalaAjuste::tipos()))],
            'ajusteForm.hora_inicio' => ['nullable', 'date_format:H:i'],
            'ajusteForm.hora_fim' => ['nullable', 'date_format:H:i'],
            'ajusteForm.motivo' => ['nullable', 'string', 'max:255'],
        ], [], [
            'ajusteForm.data_ref' => 'data',
            'ajusteForm.tipo' => 'tipo',
        ]);

        try {
            app(RhEscalaService::class)->ajustarDia([
                'funcionario_id' => (int) $this->ajusteForm['funcionario_id'],
                'vinculo_id' => filled($this->ajusteForm['vinculo_id'] ?? null) ? (int) $this->ajusteForm['vinculo_id'] : null,
                'data_ref' => (string) $this->ajusteForm['data_ref'],
                'tipo' => (string) $this->ajusteForm['tipo'],
                'hora_inicio' => $this->ajusteForm['hora_inicio'] ?? null,
                'hora_fim' => $this->ajusteForm['hora_fim'] ?? null,
                'motivo' => $this->ajusteForm['motivo'] ?? null,
            ]);

            Notification::make()->title('Ajuste registrado.')->success()->send();
            $this->closeAjusteModal();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }
}
