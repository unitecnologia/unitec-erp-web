<?php

namespace App\Filament\Resources\OsVeiculoResource\Pages\Concerns;

use App\Models\OrdemServico;
use App\Models\OsVeiculo;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ManagesOsVeiculoFormModal
{
    public bool $osVeiculoModalOpen = false;

    public ?int $osVeiculoModalRecordId = null;

    /** @var array<string, string> */
    public array $osVeiculoForm = [];

    public function createOsVeiculo(): void
    {
        if ($this->osVeiculoModalOpen) {
            return;
        }

        $this->osVeiculoModalRecordId = null;
        $this->osVeiculoForm = $this->osVeiculoFormVazio();
        $this->osVeiculoModalOpen = true;
    }

    public function editOsVeiculo(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = $this->osVeiculoDaEmpresa($this->highlightedRecordId);

        if (! $record) {
            Notification::make()->title('Veículo não encontrado.')->warning()->send();

            return;
        }

        $this->osVeiculoModalRecordId = (int) $record->getKey();
        $this->osVeiculoForm = $this->osVeiculoFormDoRegistro($record);
        $this->osVeiculoModalOpen = true;
    }

    public function closeOsVeiculoModal(): void
    {
        $this->osVeiculoModalOpen = false;
        $this->osVeiculoModalRecordId = null;
        $this->osVeiculoForm = $this->osVeiculoFormVazio();
    }

    public function saveOsVeiculo(): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            Notification::make()->title('Selecione a empresa para gravar o veículo.')->warning()->send();

            return;
        }

        $this->osVeiculoForm['placa'] = OsVeiculo::normalizarPlaca((string) ($this->osVeiculoForm['placa'] ?? ''));
        $this->osVeiculoForm['placa_alternativa'] = OsVeiculo::normalizarPlaca((string) ($this->osVeiculoForm['placa_alternativa'] ?? ''));

        $this->validate([
            'osVeiculoForm.placa' => [
                'required',
                'string',
                'max:10',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! OsVeiculo::placaValida((string) $value)) {
                        $fail('Informe uma placa válida (ABC1234 ou ABC1D23).');
                    }
                },
                Rule::unique('os_veiculos', 'placa')
                    ->where('empresa_id', $empresaId)
                    ->ignore($this->osVeiculoModalRecordId),
            ],
            'osVeiculoForm.placa_alternativa' => ['nullable', 'string', 'max:10'],
            'osVeiculoForm.descricao' => ['nullable', 'string', 'max:160'],
            'osVeiculoForm.marca' => ['nullable', 'string', 'max:80'],
            'osVeiculoForm.modelo' => ['nullable', 'string', 'max:80'],
            'osVeiculoForm.submodelo' => ['nullable', 'string', 'max:80'],
            'osVeiculoForm.versao' => ['nullable', 'string', 'max:80'],
            'osVeiculoForm.ano_fabricacao' => ['nullable', 'string', 'max:4'],
            'osVeiculoForm.ano_modelo' => ['nullable', 'string', 'max:4'],
            'osVeiculoForm.cidade' => ['nullable', 'string', 'max:80'],
            'osVeiculoForm.uf' => ['nullable', 'string', 'max:2'],
            'osVeiculoForm.renavam' => ['nullable', 'string', 'max:20'],
            'osVeiculoForm.chassi' => ['nullable', 'string', 'max:30'],
            'osVeiculoForm.cor' => ['nullable', 'string', 'max:40'],
            'osVeiculoForm.combustivel' => ['nullable', 'string', 'max:40'],
            'osVeiculoForm.tipo' => ['nullable', 'string', 'max:60'],
            'osVeiculoForm.especie' => ['nullable', 'string', 'max:60'],
            'osVeiculoForm.carroceria' => ['nullable', 'string', 'max:60'],
            'osVeiculoForm.origem' => ['nullable', 'string', 'max:60'],
            'osVeiculoForm.nacionalidade' => ['nullable', 'string', 'max:60'],
            'osVeiculoForm.segmento' => ['nullable', 'string', 'max:60'],
            'osVeiculoForm.subsegmento' => ['nullable', 'string', 'max:60'],
        ], [], [
            'osVeiculoForm.placa' => 'placa',
            'osVeiculoForm.placa_alternativa' => 'placa alternativa',
            'osVeiculoForm.descricao' => 'descrição',
            'osVeiculoForm.ano_fabricacao' => 'ano de fabricação',
            'osVeiculoForm.ano_modelo' => 'ano do modelo',
        ]);

        $alternativa = (string) ($this->osVeiculoForm['placa_alternativa'] ?? '');
        if ($alternativa !== '' && ! OsVeiculo::placaValida($alternativa)) {
            $this->addError('osVeiculoForm.placa_alternativa', 'Informe uma placa alternativa válida (ABC1234 ou ABC1D23).');

            return;
        }
        if ($alternativa !== '' && $alternativa === (string) ($this->osVeiculoForm['placa'] ?? '')) {
            $this->osVeiculoForm['placa_alternativa'] = '';
        }

        foreach (['ano_fabricacao' => 'ano de fabricação', 'ano_modelo' => 'ano do modelo'] as $campo => $rotulo) {
            $ano = trim((string) ($this->osVeiculoForm[$campo] ?? ''));
            if ($ano !== '' && preg_match('/^\d{4}$/', $ano) !== 1) {
                $this->addError('osVeiculoForm.'.$campo, 'Informe o '.$rotulo.' com 4 dígitos.');

                return;
            }
        }

        $payload = ['empresa_id' => $empresaId];
        foreach ([
            'placa', 'placa_alternativa', 'descricao', 'marca', 'modelo', 'submodelo', 'versao',
            'ano_fabricacao', 'ano_modelo', 'cidade', 'uf', 'renavam', 'chassi', 'cor',
            'combustivel', 'tipo', 'especie', 'carroceria', 'origem', 'nacionalidade',
            'segmento', 'subsegmento',
        ] as $campo) {
            $valor = mb_strtoupper(trim((string) ($this->osVeiculoForm[$campo] ?? '')), 'UTF-8');
            $payload[$campo] = $valor !== '' ? $valor : null;
        }
        $payload['placa'] = OsVeiculo::normalizarPlaca((string) $payload['placa']);
        if ($payload['placa_alternativa'] !== null) {
            $payload['placa_alternativa'] = OsVeiculo::normalizarPlaca((string) $payload['placa_alternativa']);
        }
        if ($payload['uf'] !== null) {
            $payload['uf'] = substr((string) $payload['uf'], 0, 2);
        }

        if ($this->osVeiculoModalRecordId) {
            $record = $this->osVeiculoDaEmpresa($this->osVeiculoModalRecordId);
            if (! $record) {
                Notification::make()->title('Veículo não encontrado.')->warning()->send();

                return;
            }

            unset($payload['empresa_id']);
            $record->update($payload);
            Notification::make()->title('Veículo alterado.')->success()->send();
        } else {
            $record = OsVeiculo::query()->create($payload);
            Notification::make()->title('Veículo incluído.')->success()->send();
        }

        $this->closeOsVeiculoModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }

    public function deleteOsVeiculo(): void
    {
        if ($this->osVeiculoModalOpen || ! empty($this->historicoOpen)) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');
        if (! $recordId) {
            return;
        }

        $record = $this->osVeiculoDaEmpresa($recordId);
        if (! $record) {
            Notification::make()->title('Veículo não encontrado.')->warning()->send();

            return;
        }

        if ($this->osVeiculoUsadoEmOs($record)) {
            Notification::make()
                ->title('Este veículo está vinculado a uma ordem de serviço e não pode ser excluído.')
                ->warning()
                ->send();

            return;
        }

        $record->delete();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()->title('Veículo excluído.')->success()->send();
    }

    private function osVeiculoDaEmpresa(mixed $id): ?OsVeiculo
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0 || ! $id) {
            return null;
        }

        return OsVeiculo::query()
            ->where('empresa_id', $empresaId)
            ->whereKey($id)
            ->first();
    }

    private function osVeiculoUsadoEmOs(OsVeiculo $veiculo): bool
    {
        $placa = OsVeiculo::normalizarPlaca((string) $veiculo->placa);
        if ($placa === '') {
            return false;
        }

        $normaliza = "REPLACE(REPLACE(UPPER(%s), '-', ''), ' ', '') = ?";

        return OrdemServico::query()
            ->where('empresa_id', $veiculo->empresa_id)
            ->where(function ($query) use ($placa, $normaliza): void {
                $query->whereRaw(sprintf($normaliza, 'placa'), [$placa])
                    ->orWhereRaw(sprintf($normaliza, 'placa_veiculo'), [$placa]);
            })
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    private function osVeiculoFormVazio(): array
    {
        return [
            'placa' => '',
            'placa_alternativa' => '',
            'descricao' => '',
            'marca' => '',
            'modelo' => '',
            'submodelo' => '',
            'versao' => '',
            'ano_fabricacao' => '',
            'ano_modelo' => '',
            'cidade' => '',
            'uf' => '',
            'renavam' => '',
            'chassi' => '',
            'cor' => '',
            'combustivel' => '',
            'tipo' => '',
            'especie' => '',
            'carroceria' => '',
            'origem' => '',
            'nacionalidade' => '',
            'segmento' => '',
            'subsegmento' => '',
            'consultado_em' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function osVeiculoFormDoRegistro(OsVeiculo $record): array
    {
        $form = $this->osVeiculoFormVazio();
        foreach (array_keys($form) as $campo) {
            if ($campo === 'consultado_em') {
                continue;
            }
            $form[$campo] = (string) ($record->{$campo} ?? '');
        }

        $form['consultado_em'] = $record->consultado_em
            ? ErpTimezone::toLocal($record->consultado_em)->format('d/m/Y H:i')
            : '';

        return $form;
    }
}
