<?php

namespace App\Filament\Resources\RhHoleriteResource\Pages\Concerns;

use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhHoleriteService;
use Filament\Notifications\Notification;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesRhHoleriteImportModal
{
    use WithFileUploads;

    public bool $holeriteImportOpen = false;

    /** @var list<array{funcionario_id: string, competencia_ano: string, competencia_mes: string}> */
    public array $holeriteImportRows = [];

    /** @var array<int, TemporaryUploadedFile|null> */
    public array $holeriteImportFiles = [];

    /** @var list<array<string, mixed>> */
    public array $holeriteImportPreview = [];

    public bool $holeriteImportValidado = false;

    public function openHoleriteImport(): void
    {
        if ($this->holeriteImportOpen) {
            return;
        }

        if (! ErpAccess::currentCan('rh.holerites.create')) {
            Notification::make()->title('Sem permissão para importar.')->warning()->send();

            return;
        }

        $this->holeriteImportRows = [$this->blankImportRow()];
        $this->holeriteImportFiles = [];
        $this->holeriteImportPreview = [];
        $this->holeriteImportValidado = false;
        $this->holeriteImportOpen = true;
    }

    public function closeHoleriteImport(): void
    {
        $this->holeriteImportOpen = false;
        $this->holeriteImportRows = [];
        $this->holeriteImportFiles = [];
        $this->holeriteImportPreview = [];
        $this->holeriteImportValidado = false;
        $this->resetValidation();
    }

    public function addHoleriteImportRow(): void
    {
        $this->holeriteImportRows[] = $this->blankImportRow();
        $this->holeriteImportValidado = false;
        $this->holeriteImportPreview = [];
    }

    public function removeHoleriteImportRow(int $index): void
    {
        if (! isset($this->holeriteImportRows[$index])) {
            return;
        }

        unset($this->holeriteImportRows[$index], $this->holeriteImportFiles[$index]);
        $this->holeriteImportRows = array_values($this->holeriteImportRows);
        $this->holeriteImportFiles = array_values($this->holeriteImportFiles);
        $this->holeriteImportValidado = false;
        $this->holeriteImportPreview = [];
    }

    public function validarHoleriteImport(): void
    {
        $itens = $this->montarItensImport();
        if ($itens === []) {
            Notification::make()->title('Inclua ao menos uma linha para validar.')->warning()->send();

            return;
        }

        $preview = app(RhHoleriteService::class)->conferirLote($itens);
        $this->holeriteImportPreview = $preview['linhas'];
        $this->holeriteImportValidado = (bool) $preview['valido'];

        if ($this->holeriteImportValidado) {
            Notification::make()->title('Conferência OK — pode confirmar a importação.')->success()->send();
        } else {
            Notification::make()->title('Corrija os erros antes de importar.')->danger()->send();
        }
    }

    public function confirmarHoleriteImport(): void
    {
        if (! $this->holeriteImportValidado) {
            Notification::make()->title('Valide as linhas antes de confirmar.')->warning()->send();

            return;
        }

        if (! ErpAccess::currentCan('rh.holerites.create')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $itens = $this->montarItensImport();

        try {
            $resultado = app(RhHoleriteService::class)->importarLote($itens);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        if (($resultado['erros'] ?? []) !== []) {
            Notification::make()->title('Importação não concluída — revise a conferência.')->danger()->send();
            $this->holeriteImportValidado = false;

            return;
        }

        $qtd = (int) ($resultado['importados'] ?? 0);
        $this->closeHoleriteImport();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title($qtd.' holerite(s) importado(s).')->success()->send();
    }

    /**
     * @return list<array{linha: int, funcionario_id: int, competencia_ano: int, competencia_mes: int, arquivo: TemporaryUploadedFile}>
     */
    protected function montarItensImport(): array
    {
        $itens = [];

        foreach ($this->holeriteImportRows as $index => $row) {
            $funcionarioId = (int) ($row['funcionario_id'] ?? 0);
            $ano = (int) ($row['competencia_ano'] ?? 0);
            $mes = (int) ($row['competencia_mes'] ?? 0);
            $arquivo = $this->holeriteImportFiles[$index] ?? null;

            if ($funcionarioId <= 0 && $ano <= 0 && $mes <= 0 && ! ($arquivo instanceof TemporaryUploadedFile)) {
                continue;
            }

            $itens[] = [
                'linha' => $index + 1,
                'funcionario_id' => $funcionarioId,
                'competencia_ano' => $ano,
                'competencia_mes' => $mes,
                'arquivo' => $arquivo instanceof TemporaryUploadedFile ? $arquivo : null,
            ];
        }

        return $itens;
    }

    /**
     * @return array{funcionario_id: string, competencia_ano: string, competencia_mes: string}
     */
    protected function blankImportRow(): array
    {
        $agora = now('America/Sao_Paulo');

        return [
            'funcionario_id' => '',
            'competencia_ano' => (string) $agora->year,
            'competencia_mes' => (string) $agora->month,
        ];
    }
}
