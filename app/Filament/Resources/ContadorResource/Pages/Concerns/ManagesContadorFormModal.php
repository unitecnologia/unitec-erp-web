<?php

namespace App\Filament\Resources\ContadorResource\Pages\Concerns;

use App\Models\Contador;
use App\Models\Person;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ManagesContadorFormModal
{
    public bool $contadorModalOpen = false;

    public ?int $contadorModalRecordId = null;

    /** @var array<string, mixed> */
    public array $contadorForm = [];

    public function createContador(): void
    {
        if ($this->contadorModalOpen) {
            return;
        }

        $this->contadorModalRecordId = null;
        $this->contadorForm = $this->defaultContadorFormData();
        $this->contadorModalOpen = true;
    }

    public function editContador(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = Contador::query()->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()
                ->title('Contador não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->contadorModalRecordId = $record->getKey();
        $this->contadorForm = $this->contadorFormDataFromRecord($record);
        $this->contadorModalOpen = true;
    }

    public function closeContadorModal(): void
    {
        $this->contadorModalOpen = false;
        $this->contadorModalRecordId = null;
        $this->contadorForm = [];
    }

    public function saveContador(): void
    {
        $data = $this->normalizeContadorFormData($this->contadorForm);

        $this->validate(
            [
                'contadorForm.codigo' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('contadores', 'codigo')->ignore($this->contadorModalRecordId),
                ],
                'contadorForm.nome' => ['required', 'string', 'max:120'],
                'contadorForm.cnpj_cpf' => ['nullable', 'string', 'max:20', new DocumentoBrasileiroValido],
                'contadorForm.crc' => ['nullable', 'string', 'max:30'],
                'contadorForm.cep' => ['nullable', 'string', 'max:10'],
                'contadorForm.endereco' => ['nullable', 'string', 'max:120'],
                'contadorForm.numero' => ['nullable', 'string', 'max:20'],
                'contadorForm.bairro' => ['nullable', 'string', 'max:80'],
                'contadorForm.cidade' => ['nullable', 'string', 'max:80'],
                'contadorForm.uf' => ['nullable', 'string', 'size:2'],
                'contadorForm.email' => ['nullable', 'email', 'max:120'],
                'contadorForm.fone' => ['nullable', 'string', 'max:20'],
            ],
            [],
            [
                'contadorForm.codigo' => 'código',
                'contadorForm.nome' => 'nome',
                'contadorForm.cnpj_cpf' => 'CNPJ/CPF',
                'contadorForm.crc' => 'CRC',
                'contadorForm.cep' => 'CEP',
                'contadorForm.endereco' => 'endereço',
                'contadorForm.numero' => 'número',
                'contadorForm.bairro' => 'bairro',
                'contadorForm.cidade' => 'cidade',
                'contadorForm.uf' => 'UF',
                'contadorForm.email' => 'e-mail',
                'contadorForm.fone' => 'fone',
            ],
        );

        if ($this->contadorModalRecordId) {
            $record = Contador::query()->find($this->contadorModalRecordId);

            if (! $record) {
                Notification::make()
                    ->title('Contador não encontrado.')
                    ->warning()
                    ->send();

                return;
            }

            $record->update($data);

            Notification::make()
                ->title('Contador alterado.')
                ->success()
                ->send();
        } else {
            $record = Contador::query()->create($data);

            Notification::make()
                ->title('Contador incluído.')
                ->success()
                ->send();
        }

        $this->closeContadorModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultContadorFormData(): array
    {
        return [
            'codigo' => Contador::nextCodigo(),
            'nome' => '',
            'cnpj_cpf' => '',
            'crc' => '',
            'cep' => '',
            'endereco' => '',
            'numero' => '',
            'bairro' => '',
            'cidade' => '',
            'uf' => 'SC',
            'email' => '',
            'fone' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function contadorFormDataFromRecord(Contador $record): array
    {
        return [
            'codigo' => (string) $record->codigo,
            'nome' => (string) $record->nome,
            'cnpj_cpf' => Contador::formatCnpjCpf((string) ($record->cnpj_cpf ?? '')),
            'crc' => (string) ($record->crc ?? ''),
            'cep' => Contador::formatCep((string) ($record->cep ?? '')),
            'endereco' => (string) ($record->endereco ?? ''),
            'numero' => (string) ($record->numero ?? ''),
            'bairro' => (string) ($record->bairro ?? ''),
            'cidade' => (string) ($record->cidade ?? ''),
            'uf' => (string) ($record->uf ?: 'SC'),
            'email' => (string) ($record->email ?? ''),
            'fone' => Contador::formatFone((string) ($record->fone ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeContadorFormData(array $data): array
    {
        $data = ErpUppercase::normalizeFormData($data);

        foreach (['cnpj_cpf', 'cep', 'fone'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $digits = preg_replace('/\D/', '', (string) $data[$field]);
            $data[$field] = $digits !== '' ? $digits : null;
        }

        $data['codigo'] = trim((string) ($data['codigo'] ?? ''));
        $data['nome'] = trim((string) ($data['nome'] ?? ''));
        $data['uf'] = strtoupper(trim((string) ($data['uf'] ?? 'SC')));

        foreach (['crc', 'endereco', 'numero', 'bairro', 'cidade', 'email'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = trim((string) $data[$field]);
            $data[$field] = $value !== '' ? $value : null;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function contadorUfOptions(): array
    {
        return Person::ufs();
    }
}
