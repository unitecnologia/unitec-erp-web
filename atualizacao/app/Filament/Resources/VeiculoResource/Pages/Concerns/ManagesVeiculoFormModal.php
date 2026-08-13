<?php

namespace App\Filament\Resources\VeiculoResource\Pages\Concerns;

use App\Models\Veiculo;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ManagesVeiculoFormModal
{
    public bool $veiculoModalOpen = false;

    public ?int $veiculoModalRecordId = null;

    /** @var array{placa: string, descricao: string, renavam: string, rntc: string, ativo: bool} */
    public array $veiculoForm = [
        'placa' => '',
        'descricao' => '',
        'renavam' => '',
        'rntc' => '',
        'ativo' => true,
    ];

    public function createVeiculo(): void
    {
        if ($this->veiculoModalOpen) {
            return;
        }

        $this->veiculoModalRecordId = null;
        $this->veiculoForm = [
            'placa' => '',
            'descricao' => '',
            'renavam' => '',
            'rntc' => '',
            'ativo' => true,
        ];
        $this->veiculoModalOpen = true;
    }

    public function editVeiculo(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = Veiculo::query()->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()
                ->title('Veículo não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->veiculoModalRecordId = (int) $record->getKey();
        $this->veiculoForm = [
            'placa' => (string) $record->placa,
            'descricao' => (string) ($record->descricao ?? ''),
            'renavam' => (string) ($record->renavam ?? ''),
            'rntc' => (string) ($record->rntc ?? ''),
            'ativo' => (bool) $record->ativo,
        ];
        $this->veiculoModalOpen = true;
    }

    public function closeVeiculoModal(): void
    {
        $this->veiculoModalOpen = false;
        $this->veiculoModalRecordId = null;
        $this->veiculoForm = [
            'placa' => '',
            'descricao' => '',
            'renavam' => '',
            'rntc' => '',
            'ativo' => true,
        ];
    }

    public function saveVeiculo(): void
    {
        $data = [
            'placa' => mb_strtoupper(trim((string) ($this->veiculoForm['placa'] ?? '')), 'UTF-8'),
            'descricao' => mb_strtoupper(trim((string) ($this->veiculoForm['descricao'] ?? '')), 'UTF-8'),
            'renavam' => trim((string) ($this->veiculoForm['renavam'] ?? '')),
            'rntc' => mb_strtoupper(trim((string) ($this->veiculoForm['rntc'] ?? '')), 'UTF-8'),
            'ativo' => (bool) ($this->veiculoForm['ativo'] ?? true),
        ];

        $this->validate([
            'veiculoForm.placa' => [
                'required',
                'string',
                'max:10',
                Rule::unique('veiculos', 'placa')->ignore($this->veiculoModalRecordId),
            ],
            'veiculoForm.descricao' => ['nullable', 'string', 'max:120'],
            'veiculoForm.renavam' => ['nullable', 'string', 'max:20'],
            'veiculoForm.rntc' => ['nullable', 'string', 'max:20'],
        ], [], [
            'veiculoForm.placa' => 'placa',
            'veiculoForm.descricao' => 'descrição',
            'veiculoForm.renavam' => 'renavam',
            'veiculoForm.rntc' => 'RNTC',
        ]);

        $payload = [
            'placa' => $data['placa'],
            'descricao' => $data['descricao'] !== '' ? $data['descricao'] : null,
            'renavam' => $data['renavam'] !== '' ? $data['renavam'] : null,
            'rntc' => $data['rntc'] !== '' ? $data['rntc'] : null,
            'ativo' => $data['ativo'],
        ];

        if ($this->veiculoModalRecordId) {
            $record = Veiculo::query()->find($this->veiculoModalRecordId);

            if (! $record) {
                Notification::make()
                    ->title('Veículo não encontrado.')
                    ->warning()
                    ->send();

                return;
            }

            $record->update($payload);

            Notification::make()
                ->title('Veículo alterado.')
                ->success()
                ->send();
        } else {
            $record = Veiculo::query()->create($payload);

            Notification::make()
                ->title('Veículo incluído.')
                ->success()
                ->send();
        }

        $this->closeVeiculoModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }
}
