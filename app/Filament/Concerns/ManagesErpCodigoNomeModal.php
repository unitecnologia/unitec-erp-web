<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

trait ManagesErpCodigoNomeModal
{
    public bool $erpCnModalOpen = false;

    public ?int $erpCnModalRecordId = null;

    /** @var array{codigo: string, nome: string} */
    public array $erpCnForm = [
        'codigo' => '',
        'nome' => '',
    ];

    abstract protected function erpCnModelClass(): string;

    abstract protected function erpCnEntityLabel(): string;

    abstract protected function erpCnNomeFieldLabel(): string;

    public function createErpCn(): void
    {
        if ($this->erpCnModalOpen) {
            return;
        }

        $modelClass = $this->erpCnModelClass();

        $this->erpCnModalRecordId = null;
        $this->erpCnForm = [
            'codigo' => $modelClass::nextCodigo(),
            'nome' => '',
        ];
        $this->erpCnModalOpen = true;
    }

    public function editErpCn(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $modelClass = $this->erpCnModelClass();
        $record = $modelClass::query()->find($this->highlightedRecordId);

        if (! $record instanceof Model) {
            Notification::make()
                ->title($this->erpCnEntityLabel() . ' não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->erpCnModalRecordId = (int) $record->getKey();
        $this->erpCnForm = [
            'codigo' => (string) $record->getAttribute('codigo'),
            'nome' => (string) $record->getAttribute('nome'),
        ];
        $this->erpCnModalOpen = true;
    }

    public function closeErpCnModal(): void
    {
        $this->erpCnModalOpen = false;
        $this->erpCnModalRecordId = null;
        $this->erpCnForm = ['codigo' => '', 'nome' => ''];
    }

    public function saveErpCn(): void
    {
        $modelClass = $this->erpCnModelClass();
        $data = [
            'codigo' => trim((string) ($this->erpCnForm['codigo'] ?? '')),
            'nome' => mb_strtoupper(trim((string) ($this->erpCnForm['nome'] ?? '')), 'UTF-8'),
        ];

        $this->validate([
            'erpCnForm.codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique((new $modelClass())->getTable(), 'codigo')->ignore($this->erpCnModalRecordId),
            ],
            'erpCnForm.nome' => ['required', 'string', 'max:120'],
        ], [], [
            'erpCnForm.codigo' => 'código',
            'erpCnForm.nome' => mb_strtolower($this->erpCnNomeFieldLabel(), 'UTF-8'),
        ]);

        if ($this->erpCnModalRecordId) {
            $record = $modelClass::query()->find($this->erpCnModalRecordId);

            if (! $record) {
                Notification::make()
                    ->title($this->erpCnEntityLabel() . ' não encontrado.')
                    ->warning()
                    ->send();

                return;
            }

            $record->update($data);

            Notification::make()
                ->title($this->erpCnEntityLabel() . ' alterado.')
                ->success()
                ->send();
        } else {
            $record = $modelClass::query()->create([
                ...$data,
                'ativo' => true,
            ]);

            Notification::make()
                ->title($this->erpCnEntityLabel() . ' incluído.')
                ->success()
                ->send();
        }

        $this->closeErpCnModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }

    public function deleteErpCn(): void
    {
        $this->deleteSimpleRecord($this->erpCnModelClass(), $this->erpCnEntityLabel() . ' excluído.');
    }
}
