<?php

namespace App\Filament\Resources\TransportadoraResource\Pages\Concerns;

use App\Models\Contador;
use App\Models\Person;
use App\Models\Transportadora;
use App\Models\TransportadoraMotorista;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\CnpjLookupService;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use RuntimeException;

trait ManagesTransportadoraFormModal
{
    public bool $transportadoraModalOpen = false;

    public ?int $transportadoraModalRecordId = null;

    /** @var array<string, mixed> */
    public array $transportadoraForm = [];

    public function createTransportadora(): void
    {
        if ($this->transportadoraModalOpen) {
            return;
        }

        $this->transportadoraModalRecordId = null;
        $this->transportadoraForm = $this->defaultTransportadoraFormData();
        $this->transportadoraModalOpen = true;
    }

    public function editTransportadora(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = Transportadora::query()
            ->with('motoristas')
            ->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()
                ->title('Transportadora não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->transportadoraModalRecordId = $record->getKey();
        $this->transportadoraForm = $this->transportadoraFormDataFromRecord($record);
        $this->transportadoraModalOpen = true;
    }

    public function closeTransportadoraModal(): void
    {
        $this->transportadoraModalOpen = false;
        $this->transportadoraModalRecordId = null;
        $this->transportadoraForm = [];
    }

    public function addMotoristaRow(): void
    {
        $this->transportadoraForm['motoristas'][] = [
            'id' => null,
            'nome' => '',
            'cpf' => '',
        ];
    }

    public function removeMotoristaRow(int $index): void
    {
        if (! isset($this->transportadoraForm['motoristas'][$index])) {
            return;
        }

        unset($this->transportadoraForm['motoristas'][$index]);
        $this->transportadoraForm['motoristas'] = array_values($this->transportadoraForm['motoristas']);
    }

    public function searchTransportadoraCnpj(): void
    {
        if (! $this->transportadoraModalOpen) {
            return;
        }

        $tipoPessoa = strtoupper(trim((string) ($this->transportadoraForm['tipo_pessoa'] ?? 'J')));

        if ($tipoPessoa !== 'J') {
            Notification::make()
                ->title('Selecione Pessoa Jurídica.')
                ->warning()
                ->send();

            return;
        }

        $digits = preg_replace('/\D/', '', (string) ($this->transportadoraForm['cnpj_cpf'] ?? '')) ?? '';

        if (strlen($digits) !== 14) {
            Notification::make()
                ->title('Informe um CNPJ completo.')
                ->warning()
                ->send();

            return;
        }

        try {
            $fields = app(CnpjLookupService::class)->fetch($digits);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de CNPJ')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $mapped = array_filter([
            'cnpj_cpf' => $fields['cpf_cnpj'] ?? null,
            'proprietario' => $fields['nome_razao'] ?? null,
            'apelido' => $fields['apelido_fantasia'] ?? null,
            'rg_ie' => $fields['rg_ie'] ?? null,
            'cep' => $fields['cep'] ?? null,
            'endereco' => $fields['endereco'] ?? null,
            'numero' => $fields['numero'] ?? null,
            'bairro' => $fields['bairro'] ?? null,
            'cidade' => $fields['cidade_nome'] ?? null,
            'codigo_municipio' => $fields['cidade_codigo'] ?? null,
            'uf' => $fields['uf'] ?? null,
            'whatsapp' => $fields['fone1'] ?? ($fields['fone2'] ?? null),
            'tipo_pessoa' => 'J',
        ], fn (?string $value): bool => filled($value));

        $this->transportadoraForm = [
            ...$this->transportadoraForm,
            ...$mapped,
        ];

        $hasIe = filled($mapped['rg_ie'] ?? null);
        $missingAddress = blank($mapped['endereco'] ?? null) || blank($mapped['cep'] ?? null);

        $body = match (true) {
            $hasIe && ! $missingAddress => 'Dados preenchidos automaticamente.',
            $hasIe => 'Dados preenchidos com IE. Confira endereço se necessário.',
            ! $missingAddress => 'Dados preenchidos. IE não informada — complete manualmente se necessário.',
            default => 'Dados parciais preenchidos. Complete endereço e IE se necessário.',
        };

        Notification::make()
            ->title('Transportadora encontrada')
            ->body($body)
            ->success()
            ->send();

        $this->dispatch('erp-masks-refresh');
    }

    public function saveTransportadora(): void
    {
        $tipoPessoa = strtoupper(trim((string) ($this->transportadoraForm['tipo_pessoa'] ?? 'J')));
        $tipoPessoa = in_array($tipoPessoa, ['F', 'J'], true) ? $tipoPessoa : 'J';

        $data = $this->normalizeTransportadoraFormData($this->transportadoraForm);

        $this->validate(
            [
                'transportadoraForm.codigo' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('transportadoras', 'codigo')->ignore($this->transportadoraModalRecordId),
                ],
                'transportadoraForm.tipo_pessoa' => ['required', 'in:F,J'],
                'transportadoraForm.cnpj_cpf' => [
                    'nullable',
                    'string',
                    'max:20',
                    new DocumentoBrasileiroValido(
                        pessoaTipo: $tipoPessoa === 'F' ? 'fisica' : 'juridica',
                    ),
                ],
                'transportadoraForm.rg_ie' => ['nullable', 'string', 'max:30'],
                'transportadoraForm.cep' => ['nullable', 'string', 'max:10'],
                'transportadoraForm.proprietario' => ['required', 'string', 'max:120'],
                'transportadoraForm.apelido' => ['nullable', 'string', 'max:120'],
                'transportadoraForm.whatsapp' => ['nullable', 'string', 'max:20'],
                'transportadoraForm.endereco' => ['nullable', 'string', 'max:120'],
                'transportadoraForm.numero' => ['nullable', 'string', 'max:20'],
                'transportadoraForm.bairro' => ['nullable', 'string', 'max:80'],
                'transportadoraForm.cidade' => ['nullable', 'string', 'max:80'],
                'transportadoraForm.codigo_municipio' => ['nullable', 'string', 'max:10'],
                'transportadoraForm.uf' => ['nullable', 'string', 'size:2'],
                'transportadoraForm.motoristas' => ['nullable', 'array'],
                'transportadoraForm.motoristas.*.nome' => ['nullable', 'string', 'max:120'],
                'transportadoraForm.motoristas.*.cpf' => [
                    'nullable',
                    'string',
                    'max:14',
                    new DocumentoBrasileiroValido(cpfOnly: true),
                ],
            ],
            [],
            [
                'transportadoraForm.codigo' => 'código',
                'transportadoraForm.tipo_pessoa' => 'tipo de pessoa',
                'transportadoraForm.cnpj_cpf' => 'CNPJ/CPF',
                'transportadoraForm.rg_ie' => 'RG/IE',
                'transportadoraForm.cep' => 'CEP',
                'transportadoraForm.proprietario' => 'proprietário',
                'transportadoraForm.apelido' => 'apelido',
                'transportadoraForm.whatsapp' => 'WhatsApp',
                'transportadoraForm.endereco' => 'endereço',
                'transportadoraForm.numero' => 'número',
                'transportadoraForm.bairro' => 'bairro',
                'transportadoraForm.cidade' => 'cidade',
                'transportadoraForm.codigo_municipio' => 'código do município',
                'transportadoraForm.uf' => 'UF',
                'transportadoraForm.motoristas.*.nome' => 'nome do motorista',
                'transportadoraForm.motoristas.*.cpf' => 'CPF do motorista',
            ],
        );

        $motoristas = $data['motoristas'] ?? [];
        unset($data['motoristas']);

        if ($this->transportadoraModalRecordId) {
            $record = Transportadora::query()->find($this->transportadoraModalRecordId);

            if (! $record) {
                Notification::make()
                    ->title('Transportadora não encontrada.')
                    ->warning()
                    ->send();

                return;
            }

            $record->update($data);
            $this->syncTransportadoraMotoristas($record, $motoristas);

            Notification::make()
                ->title('Transportadora alterada.')
                ->success()
                ->send();
        } else {
            $data['ativo'] = true;
            $record = Transportadora::query()->create($data);
            $this->syncTransportadoraMotoristas($record, $motoristas);

            Notification::make()
                ->title('Transportadora incluída.')
                ->success()
                ->send();
        }

        $this->closeTransportadoraModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultTransportadoraFormData(): array
    {
        return [
            'codigo' => Transportadora::nextCodigo(),
            'tipo_pessoa' => 'J',
            'cnpj_cpf' => '',
            'rg_ie' => '',
            'cep' => '',
            'proprietario' => '',
            'apelido' => '',
            'whatsapp' => '',
            'endereco' => '',
            'numero' => '',
            'bairro' => '',
            'cidade' => '',
            'codigo_municipio' => '',
            'uf' => 'SC',
            'motoristas' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transportadoraFormDataFromRecord(Transportadora $record): array
    {
        return [
            'codigo' => (string) $record->codigo,
            'tipo_pessoa' => strtoupper((string) ($record->tipo_pessoa ?: 'J')),
            'cnpj_cpf' => Contador::formatCnpjCpf((string) ($record->cnpj_cpf ?? '')),
            'rg_ie' => (string) ($record->rg_ie ?? ''),
            'cep' => Contador::formatCep((string) ($record->cep ?? '')),
            'proprietario' => (string) $record->proprietario,
            'apelido' => (string) ($record->apelido ?? ''),
            'whatsapp' => Contador::formatFone((string) ($record->whatsapp ?? '')),
            'endereco' => (string) ($record->endereco ?? ''),
            'numero' => (string) ($record->numero ?? ''),
            'bairro' => (string) ($record->bairro ?? ''),
            'cidade' => (string) ($record->cidade ?? ''),
            'codigo_municipio' => (string) ($record->codigo_municipio ?? ''),
            'uf' => (string) ($record->uf ?: 'SC'),
            'motoristas' => $record->motoristas
                ->map(fn (TransportadoraMotorista $motorista): array => [
                    'id' => $motorista->id,
                    'nome' => (string) $motorista->nome,
                    'cpf' => Contador::formatCnpjCpf((string) ($motorista->cpf ?? '')),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeTransportadoraFormData(array $data): array
    {
        $motoristas = is_array($data['motoristas'] ?? null) ? $data['motoristas'] : [];
        unset($data['motoristas']);

        $data = ErpUppercase::normalizeFormData($data);

        foreach (['cnpj_cpf', 'cep', 'whatsapp'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $digits = preg_replace('/\D/', '', (string) $data[$field]);
            $data[$field] = $digits !== '' ? $digits : null;
        }

        $data['codigo'] = trim((string) ($data['codigo'] ?? ''));
        $data['tipo_pessoa'] = strtoupper(trim((string) ($data['tipo_pessoa'] ?? 'J')));
        $data['tipo_pessoa'] = in_array($data['tipo_pessoa'], ['F', 'J'], true) ? $data['tipo_pessoa'] : 'J';
        $data['proprietario'] = trim((string) ($data['proprietario'] ?? ''));
        $data['uf'] = strtoupper(trim((string) ($data['uf'] ?? 'SC')));

        foreach (['rg_ie', 'apelido', 'endereco', 'numero', 'bairro', 'cidade', 'codigo_municipio'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = trim((string) $data[$field]);
            $data[$field] = $value !== '' ? $value : null;
        }

        $data['motoristas'] = collect($motoristas)
            ->map(function (mixed $row): array {
                $row = is_array($row) ? $row : [];

                return [
                    'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                    'nome' => trim((string) ($row['nome'] ?? '')),
                    'cpf' => preg_replace('/\D/', '', (string) ($row['cpf'] ?? '')) ?: null,
                ];
            })
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $motoristas
     */
    protected function syncTransportadoraMotoristas(Transportadora $transportadora, array $motoristas): void
    {
        $ids = [];
        $ordem = 1;

        foreach ($motoristas as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));

            if ($nome === '') {
                continue;
            }

            $attributes = [
                'nome' => ErpUppercase::uppercase($nome),
                'cpf' => filled($row['cpf'] ?? null) ? (string) $row['cpf'] : null,
                'ordem' => $ordem++,
            ];

            if (filled($row['id'] ?? null)) {
                TransportadoraMotorista::query()
                    ->where('transportadora_id', $transportadora->getKey())
                    ->whereKey($row['id'])
                    ->update($attributes);
                $ids[] = (int) $row['id'];
            } else {
                $created = $transportadora->motoristas()->create($attributes);
                $ids[] = $created->id;
            }
        }

        $transportadora->motoristas()->whereNotIn('id', $ids)->delete();
    }

    /**
     * @return array<string, string>
     */
    public function transportadoraUfOptions(): array
    {
        return Person::ufs();
    }

    /**
     * @return array<string, string>
     */
    public function transportadoraTipoPessoaOptions(): array
    {
        return [
            'F' => 'FÍSICA',
            'J' => 'JURÍDICA',
        ];
    }
}
