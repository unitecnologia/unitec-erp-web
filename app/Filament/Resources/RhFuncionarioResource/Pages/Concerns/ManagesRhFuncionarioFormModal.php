<?php

namespace App\Filament\Resources\RhFuncionarioResource\Pages\Concerns;

use App\Models\RhCargo;
use App\Models\RhDepartamento;
use App\Models\RhFuncionario;
use App\Models\Vendedor;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

trait ManagesRhFuncionarioFormModal
{
    public bool $rhFuncionarioModalOpen = false;

    public ?int $rhFuncionarioModalRecordId = null;

    /** @var array<string, mixed> */
    public array $rhFuncionarioForm = [];

    /** @var array<int, array{id: int, nome: string}> */
    public array $rhCargoOptions = [];

    /** @var array<int, array{id: int, nome: string}> */
    public array $rhDepartamentoOptions = [];

    protected function blankRhFuncionarioForm(): array
    {
        return [
            'codigo' => '',
            'nome' => '',
            'cpf' => '',
            'rg' => '',
            'pis_pasep' => '',
            'data_nascimento' => '',
            'telefone' => '',
            'whatsapp' => '',
            'email' => '',
            'cep' => '',
            'logradouro' => '',
            'endereco' => '',
            'numero' => '',
            'bairro' => '',
            'complemento' => '',
            'cidade_codigo' => '',
            'cidade_nome' => '',
            'uf' => '',
            'cargo_id' => '',
            'departamento_id' => '',
            'ctps' => '',
            'inss' => '',
            'tipo_salario' => '',
            'salario' => '',
            'data_admissao' => '',
            'data_demissao' => '',
            'ativo' => true,
        ];
    }

    /**
     * @return list<string>
     */
    public function rhTipoSalarioOptions(): array
    {
        return ['MENSALISTA', 'HORISTA', 'COMISSIONADO', 'DIARISTA', 'AUTÔNOMO'];
    }

    /**
     * @return list<string>
     */
    public function rhLogradouroOptions(): array
    {
        return ['RUA', 'AVENIDA', 'TRAVESSA', 'RODOVIA', 'ALAMEDA', 'PRAÇA', 'ESTRADA', 'LOTEAMENTO'];
    }

    protected function loadRhLookupOptions(): void
    {
        $this->rhCargoOptions = RhCargo::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (RhCargo $c): array => ['id' => (int) $c->id, 'nome' => (string) $c->nome])
            ->all();

        $this->rhDepartamentoOptions = RhDepartamento::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (RhDepartamento $d): array => ['id' => (int) $d->id, 'nome' => (string) $d->nome])
            ->all();
    }

    public function createRhFuncionario(): void
    {
        if ($this->rhFuncionarioModalOpen) {
            return;
        }

        $this->loadRhLookupOptions();
        $this->rhFuncionarioModalRecordId = null;
        $this->rhFuncionarioForm = $this->blankRhFuncionarioForm();
        $this->rhFuncionarioForm['codigo'] = RhFuncionario::nextCodigo();
        $this->rhFuncionarioModalOpen = true;
    }

    public function editRhFuncionario(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhFuncionario::query()->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()->title('Funcionário não encontrado.')->warning()->send();

            return;
        }

        $this->loadRhLookupOptions();
        $this->rhFuncionarioModalRecordId = (int) $record->getKey();
        $this->rhFuncionarioForm = [
            'codigo' => (string) $record->codigo,
            'nome' => (string) $record->nome,
            'cpf' => (string) ($record->cpf ?? ''),
            'rg' => (string) ($record->rg ?? ''),
            'pis_pasep' => (string) ($record->pis_pasep ?? ''),
            'data_nascimento' => $record->data_nascimento?->format('Y-m-d') ?? '',
            'telefone' => (string) ($record->telefone ?? ''),
            'whatsapp' => (string) ($record->whatsapp ?? ''),
            'email' => (string) ($record->email ?? ''),
            'cep' => (string) ($record->cep ?? ''),
            'logradouro' => (string) ($record->logradouro ?? ''),
            'endereco' => (string) ($record->endereco ?? ''),
            'numero' => (string) ($record->numero ?? ''),
            'bairro' => (string) ($record->bairro ?? ''),
            'complemento' => (string) ($record->complemento ?? ''),
            'cidade_codigo' => (string) ($record->cidade_codigo ?? ''),
            'cidade_nome' => (string) ($record->cidade_nome ?? ''),
            'uf' => (string) ($record->uf ?? ''),
            'cargo_id' => $record->cargo_id ? (string) $record->cargo_id : '',
            'departamento_id' => $record->departamento_id ? (string) $record->departamento_id : '',
            'ctps' => (string) ($record->ctps ?? ''),
            'inss' => (string) ($record->inss ?? ''),
            'tipo_salario' => (string) ($record->tipo_salario ?? ''),
            'salario' => $record->salario !== null ? number_format((float) $record->salario, 2, ',', '.') : '',
            'data_admissao' => $record->data_admissao?->format('Y-m-d') ?? '',
            'data_demissao' => $record->data_demissao?->format('Y-m-d') ?? '',
            'ativo' => (bool) $record->ativo,
        ];
        $this->rhFuncionarioModalOpen = true;
    }

    public function closeRhFuncionarioModal(): void
    {
        $this->rhFuncionarioModalOpen = false;
        $this->rhFuncionarioModalRecordId = null;
        $this->rhFuncionarioForm = $this->blankRhFuncionarioForm();
    }

    public function buscarCepRhFuncionario(): void
    {
        $cep = preg_replace('/\D/', '', (string) ($this->rhFuncionarioForm['cep'] ?? ''));

        if (strlen((string) $cep) !== 8) {
            return;
        }

        try {
            $response = Http::timeout(6)->get("https://viacep.com.br/ws/{$cep}/json/");
        } catch (\Throwable) {
            return;
        }

        if (! $response->ok()) {
            return;
        }

        $data = $response->json();

        if (! is_array($data) || ($data['erro'] ?? false)) {
            Notification::make()->title('CEP não encontrado.')->warning()->send();

            return;
        }

        $this->rhFuncionarioForm['endereco'] = mb_strtoupper((string) ($data['logradouro'] ?? ''), 'UTF-8');
        $this->rhFuncionarioForm['bairro'] = mb_strtoupper((string) ($data['bairro'] ?? ''), 'UTF-8');
        $this->rhFuncionarioForm['cidade_nome'] = mb_strtoupper((string) ($data['localidade'] ?? ''), 'UTF-8');
        $this->rhFuncionarioForm['uf'] = mb_strtoupper((string) ($data['uf'] ?? ''), 'UTF-8');
        $this->rhFuncionarioForm['cidade_codigo'] = (string) ($data['ibge'] ?? '');
    }

    public function saveRhFuncionario(): void
    {
        $this->validate([
            'rhFuncionarioForm.codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('rh_funcionarios', 'codigo')->ignore($this->rhFuncionarioModalRecordId),
            ],
            'rhFuncionarioForm.nome' => ['required', 'string', 'max:120'],
            'rhFuncionarioForm.cpf' => ['nullable', 'string', 'max:14'],
            'rhFuncionarioForm.email' => ['nullable', 'email', 'max:120'],
            'rhFuncionarioForm.cargo_id' => ['required', 'integer', 'exists:rh_cargos,id'],
            'rhFuncionarioForm.departamento_id' => ['required', 'integer', 'exists:rh_departamentos,id'],
            'rhFuncionarioForm.uf' => ['nullable', 'string', 'max:2'],
        ], [
            'rhFuncionarioForm.nome.required' => 'Informe o nome.',
            'rhFuncionarioForm.cargo_id.required' => 'Selecione o cargo.',
            'rhFuncionarioForm.departamento_id.required' => 'Selecione o departamento.',
        ], [
            'rhFuncionarioForm.codigo' => 'código',
            'rhFuncionarioForm.nome' => 'nome',
            'rhFuncionarioForm.cpf' => 'CPF',
            'rhFuncionarioForm.email' => 'e-mail',
            'rhFuncionarioForm.cargo_id' => 'cargo',
            'rhFuncionarioForm.departamento_id' => 'departamento',
            'rhFuncionarioForm.uf' => 'UF',
        ]);

        $salarioRaw = trim((string) ($this->rhFuncionarioForm['salario'] ?? ''));
        $salario = $salarioRaw !== '' ? BrDecimal::parse($salarioRaw, 2) : null;

        $payload = [
            'codigo' => trim((string) $this->rhFuncionarioForm['codigo']),
            'nome' => ErpUppercase::uppercase(trim((string) $this->rhFuncionarioForm['nome'])),
            'cpf' => $this->nullableTrim($this->rhFuncionarioForm['cpf'] ?? null),
            'rg' => $this->nullableUpper($this->rhFuncionarioForm['rg'] ?? null),
            'pis_pasep' => $this->nullableTrim($this->rhFuncionarioForm['pis_pasep'] ?? null),
            'data_nascimento' => $this->nullableDate($this->rhFuncionarioForm['data_nascimento'] ?? null),
            'telefone' => $this->nullableTrim($this->rhFuncionarioForm['telefone'] ?? null),
            'whatsapp' => $this->nullableTrim($this->rhFuncionarioForm['whatsapp'] ?? null),
            'email' => $this->nullableTrim($this->rhFuncionarioForm['email'] ?? null),
            'cep' => $this->nullableTrim($this->rhFuncionarioForm['cep'] ?? null),
            'logradouro' => $this->nullableUpper($this->rhFuncionarioForm['logradouro'] ?? null),
            'endereco' => $this->nullableUpper($this->rhFuncionarioForm['endereco'] ?? null),
            'numero' => $this->nullableTrim($this->rhFuncionarioForm['numero'] ?? null),
            'bairro' => $this->nullableUpper($this->rhFuncionarioForm['bairro'] ?? null),
            'complemento' => $this->nullableUpper($this->rhFuncionarioForm['complemento'] ?? null),
            'cidade_codigo' => $this->nullableTrim($this->rhFuncionarioForm['cidade_codigo'] ?? null),
            'cidade_nome' => $this->nullableUpper($this->rhFuncionarioForm['cidade_nome'] ?? null),
            'uf' => $this->nullableUpper($this->rhFuncionarioForm['uf'] ?? null),
            'cargo_id' => filled($this->rhFuncionarioForm['cargo_id'] ?? null) ? (int) $this->rhFuncionarioForm['cargo_id'] : null,
            'departamento_id' => filled($this->rhFuncionarioForm['departamento_id'] ?? null) ? (int) $this->rhFuncionarioForm['departamento_id'] : null,
            'ctps' => $this->nullableUpper($this->rhFuncionarioForm['ctps'] ?? null),
            'inss' => $this->nullableTrim($this->rhFuncionarioForm['inss'] ?? null),
            'tipo_salario' => $this->nullableUpper($this->rhFuncionarioForm['tipo_salario'] ?? null),
            'salario' => $salario,
            'data_admissao' => $this->nullableDate($this->rhFuncionarioForm['data_admissao'] ?? null),
            'data_demissao' => $this->nullableDate($this->rhFuncionarioForm['data_demissao'] ?? null),
            'ativo' => (bool) ($this->rhFuncionarioForm['ativo'] ?? true),
        ];

        if ($this->rhFuncionarioModalRecordId) {
            $record = RhFuncionario::query()->find($this->rhFuncionarioModalRecordId);

            if (! $record) {
                Notification::make()->title('Funcionário não encontrado.')->warning()->send();

                return;
            }

            $record->update($payload);
            Notification::make()->title('Funcionário alterado.')->success()->send();
        } else {
            $record = RhFuncionario::query()->create($payload);
            Notification::make()->title('Funcionário incluído.')->success()->send();
        }

        $this->syncNomeColaboradorVinculado($record);

        $this->closeRhFuncionarioModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }

    private function syncNomeColaboradorVinculado(RhFuncionario $funcionario): void
    {
        $vendedorId = (int) ($funcionario->vendedor_id ?? 0);

        if ($vendedorId <= 0) {
            return;
        }

        $funcionario->loadMissing('cargo');
        $cargoNome = trim((string) ($funcionario->cargo?->nome ?? ''));

        Vendedor::query()->whereKey($vendedorId)->update([
            'nome' => (string) $funcionario->nome,
            'cargo' => $cargoNome !== '' ? ErpUppercase::uppercase($cargoNome) : null,
        ]);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v !== '' ? $v : null;
    }

    private function nullableUpper(mixed $value): ?string
    {
        $v = $this->nullableTrim($value);

        return $v !== null ? ErpUppercase::uppercase($v) : null;
    }

    private function nullableDate(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v !== '' ? $v : null;
    }
}
