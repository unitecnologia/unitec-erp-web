<?php

namespace App\Filament\Resources\OsVeiculoResource\Pages\Concerns;

use App\Models\OsVeiculo;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Os\OsVeiculoHistoricoService;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait ManagesOsVeiculoHistorico
{
    public bool $historicoOpen = false;

    public bool $historicoRelatorioOpen = false;

    public ?int $historicoVeiculoId = null;

    public int $historicoPage = 1;

    public int $historicoLastPage = 1;

    public int $historicoTotal = 0;

    /** @var list<string> */
    public array $historicoPlacas = [];

    /** @var array{placa: string, descricao: string, modelo: string} */
    public array $historicoResumo = [
        'placa' => '',
        'descricao' => '',
        'modelo' => '',
    ];

    /** @var list<array<string, mixed>> */
    public array $historicoLinhas = [];

    public ?int $historicoOsId = null;

    /** @var array<string, mixed> */
    public array $historicoDetalhe = [];

    public function openOsVeiculoHistorico(int $veiculoId): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $veiculo = OsVeiculo::query()
            ->where('empresa_id', $empresaId)
            ->whereKey($veiculoId)
            ->first(['id', 'placa', 'placa_alternativa', 'descricao', 'marca', 'modelo']);

        if ($veiculo === null) {
            Notification::make()->title('Veículo não encontrado.')->warning()->send();

            return;
        }

        $this->historicoVeiculoId = (int) $veiculo->id;
        $this->historicoPlacas = OsVeiculoHistoricoService::placasConsulta($veiculo);
        $this->historicoResumo = [
            'placa' => (string) $veiculo->placa,
            'descricao' => trim((string) ($veiculo->descricao ?: $veiculo->marca ?: '')),
            'modelo' => trim((string) ($veiculo->modelo ?? '')),
        ];
        $this->historicoPage = 1;
        $this->historicoOsId = null;
        $this->historicoDetalhe = [];
        $this->historicoRelatorioOpen = false;
        $this->historicoOpen = true;
        $this->carregarHistorico();
    }

    public function closeOsVeiculoHistorico(): void
    {
        $this->historicoOpen = false;
        $this->historicoRelatorioOpen = false;
        $this->historicoVeiculoId = null;
        $this->historicoPlacas = [];
        $this->historicoLinhas = [];
        $this->historicoOsId = null;
        $this->historicoDetalhe = [];
        $this->historicoPage = 1;
        $this->historicoLastPage = 1;
        $this->historicoTotal = 0;
    }

    public function historicoPagina(int $pagina): void
    {
        if (! $this->historicoOpen) {
            return;
        }

        $this->historicoPage = max(1, $pagina);
        $this->carregarHistorico();
    }

    public function abrirHistoricoOs(int $ordemId): void
    {
        if (! $this->historicoOpen) {
            return;
        }

        $detalhe = app(OsVeiculoHistoricoService::class)->detalhe(
            (int) (ErpContext::currentEmpresaId() ?? 0),
            $this->historicoPlacas,
            $ordemId,
        );

        if ($detalhe === null) {
            Notification::make()->title('Ordem de serviço não encontrada para este veículo.')->warning()->send();

            return;
        }

        $this->historicoOsId = $ordemId;
        $this->historicoDetalhe = $detalhe;
    }

    public function historicoRelatorioUrl(): string
    {
        if (! $this->historicoVeiculoId) {
            return '';
        }

        return route('erp.reports.os-veiculo-historico', ['veiculo' => $this->historicoVeiculoId]);
    }

    public function abrirHistoricoRelatorio(): void
    {
        if (! $this->historicoOpen || ! $this->historicoVeiculoId) {
            return;
        }

        $this->historicoRelatorioOpen = true;
    }

    public function fecharHistoricoOuRelatorio(): void
    {
        if ($this->historicoRelatorioOpen) {
            $this->historicoRelatorioOpen = false;

            return;
        }

        $this->closeOsVeiculoHistorico();
    }

    #[On('close-os-veiculo-historico-preview')]
    public function fecharHistoricoRelatorio(): void
    {
        $this->historicoRelatorioOpen = false;
    }

    private function carregarHistorico(): void
    {
        $pagina = app(OsVeiculoHistoricoService::class)->pagina(
            (int) (ErpContext::currentEmpresaId() ?? 0),
            $this->historicoPlacas,
            $this->historicoPage,
        );

        $this->historicoLinhas = $pagina['linhas'];
        $this->historicoPage = $pagina['pagina'];
        $this->historicoLastPage = $pagina['ultima'];
        $this->historicoTotal = $pagina['total'];
    }
}
