<?php

namespace App\Filament\Resources\RhEscalaResource\Pages\Concerns;

use App\Models\RhEscalaVinculo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhEscalaService;
use Filament\Notifications\Notification;

trait ManagesRhEscalaCalendarioModal
{
    public bool $calendarioModalOpen = false;

    public ?int $calendarioFuncionarioId = null;

    public int $calendarioAno;

    public int $calendarioMes;

    /** @var list<array<string, mixed>> */
    public array $calendarioDias = [];

    public string $calendarioFuncionarioNome = '';

    public function mountCalendarioDefaults(): void
    {
        $this->calendarioAno = (int) now()->year;
        $this->calendarioMes = (int) now()->month;
    }

    public function openCalendarioModal(): void
    {
        if (! ErpAccess::currentCan('rh.escalas.access')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $funcionarioId = null;
        if ($this->highlightedRecordId) {
            $vinculo = RhEscalaVinculo::query()->find($this->highlightedRecordId);
            $funcionarioId = $vinculo?->funcionario_id;
        }

        if (! $funcionarioId) {
            Notification::make()->title('Selecione um vínculo na lista.')->warning()->send();

            return;
        }

        $this->calendarioFuncionarioId = (int) $funcionarioId;
        $func = RhFuncionario::query()->find($funcionarioId);
        $this->calendarioFuncionarioNome = $func ? trim($func->codigo.' — '.$func->nome) : '';
        $this->calendarioModalOpen = true;
        $this->recarregarCalendario();
    }

    public function closeCalendarioModal(): void
    {
        $this->calendarioModalOpen = false;
        $this->calendarioDias = [];
    }

    public function calendarioMesAnterior(): void
    {
        if ($this->calendarioMes <= 1) {
            $this->calendarioMes = 12;
            $this->calendarioAno--;
        } else {
            $this->calendarioMes--;
        }
        $this->recarregarCalendario();
    }

    public function calendarioProximoMes(): void
    {
        if ($this->calendarioMes >= 12) {
            $this->calendarioMes = 1;
            $this->calendarioAno++;
        } else {
            $this->calendarioMes++;
        }
        $this->recarregarCalendario();
    }

    public function recarregarCalendario(): void
    {
        if (! $this->calendarioFuncionarioId) {
            $this->calendarioDias = [];

            return;
        }

        $this->calendarioDias = app(RhEscalaService::class)->calendarioMensal(
            $this->calendarioFuncionarioId,
            $this->calendarioAno,
            $this->calendarioMes
        );
    }

    public function imprimirCalendarioMensal(): void
    {
        if (! ErpAccess::currentCan('rh.escalas.print')) {
            Notification::make()->title('Sem permissão para imprimir.')->warning()->send();

            return;
        }

        if (! $this->calendarioFuncionarioId) {
            Notification::make()->title('Abra o calendário de um funcionário.')->warning()->send();

            return;
        }

        $params = [
            'funcionario' => $this->calendarioFuncionarioId,
            'ano' => $this->calendarioAno,
            'mes' => $this->calendarioMes,
            'auto' => 0,
        ];

        $this->redirect(route('erp.reports.rh-escala-mensal', $params), navigate: false);
    }
}
