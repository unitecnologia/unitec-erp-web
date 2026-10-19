<?php

namespace App\Filament\Resources\ContadorResource\Pages\Concerns;

use App\Models\Contador;
use App\Models\Person;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\CnpjLookupService;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\MunicipioLookupService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use RuntimeException;

trait ManagesContadorFormModal
{
    public bool $contadorModalOpen = false;

    public ?int $contadorModalRecordId = null;

    /** @var array<string, mixed> */
    public array $contadorForm = [];

    /** @var list<array{codigo: string, nome: string, uf: string}> */
    public array $contadorCidadeSugestoes = [];

    public bool $contadorCidadeSugestoesOpen = false;

    public int $contadorCidadeSugestaoIndex = -1;

    protected bool $contadorCidadeLookupLocked = false;

    public function createContador(): void
    {
        if ($this->contadorModalOpen) {
            return;
        }

        $this->contadorModalRecordId = null;
        $this->contadorForm = $this->defaultContadorFormData();
        $this->fecharContadorCidadeSugestoes();
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
        $this->fecharContadorCidadeSugestoes();
        $this->contadorModalOpen = true;
    }

    public function closeContadorModal(): void
    {
        $this->contadorModalOpen = false;
        $this->contadorModalRecordId = null;
        $this->contadorForm = [];
        $this->fecharContadorCidadeSugestoes();
    }

    public function handleContadorModalEscape(): void
    {
        if ($this->contadorCidadeSugestoesOpen) {
            $this->fecharContadorCidadeSugestoes();

            return;
        }

        $this->closeContadorModal();
    }

    /**
     * Força caixa alta em tempo real (Livewire).
     */
    public function updatedContadorForm(mixed $value, string $key): void
    {
        if (! is_string($value)) {
            return;
        }

        if (in_array($key, ['cnpj_cpf', 'cep', 'fone', 'cidade', 'uf'], true)) {
            return;
        }

        $upper = ErpUppercase::uppercase($value);

        if ($upper !== $value) {
            data_set($this->contadorForm, $key, $upper);
        }
    }

    public function updatedContadorFormCidade(?string $value): void
    {
        if ($this->contadorCidadeLookupLocked) {
            return;
        }

        $upper = ErpUppercase::uppercase((string) $value);

        if (($this->contadorForm['cidade'] ?? '') !== $upper) {
            $this->contadorForm['cidade'] = $upper;
        }

        $this->buscarMunicipiosContador($upper);
    }

    public function updatedContadorFormUf(?string $value): void
    {
        $cidade = (string) ($this->contadorForm['cidade'] ?? '');

        if (mb_strlen(trim($cidade)) >= 2) {
            $this->buscarMunicipiosContador($cidade);

            return;
        }

        $this->fecharContadorCidadeSugestoes();
    }

    public function searchContadorCnpj(bool $silentIncomplete = false): void
    {
        if (! $this->contadorModalOpen) {
            return;
        }

        $digits = preg_replace('/\D/', '', (string) ($this->contadorForm['cnpj_cpf'] ?? '')) ?? '';

        if (strlen($digits) === 11) {
            if (! $silentIncomplete) {
                Notification::make()
                    ->title('Preenchimento automático só para CNPJ.')
                    ->body('CPF não possui consulta pública de endereço. Complete os dados manualmente.')
                    ->warning()
                    ->send();
            }

            return;
        }

        if (strlen($digits) !== 14) {
            if (! $silentIncomplete) {
                Notification::make()
                    ->title('Informe um CNPJ completo.')
                    ->warning()
                    ->send();
            }

            return;
        }

        // No blur: não sobrescreve cadastro já preenchido (botão sempre consulta).
        if ($silentIncomplete) {
            $hasNome = filled(trim((string) ($this->contadorForm['nome'] ?? '')));
            $hasEndereco = filled(trim((string) ($this->contadorForm['endereco'] ?? '')));

            if ($hasNome && $hasEndereco) {
                return;
            }
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

        $nome = trim((string) ($fields['nome_razao'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($fields['apelido_fantasia'] ?? ''));
        }

        $mapped = array_filter([
            'cnpj_cpf' => Contador::formatCnpjCpf((string) ($fields['cpf_cnpj'] ?? $digits)),
            'nome' => $nome !== '' ? ErpUppercase::uppercase($nome) : null,
            'cep' => Contador::formatCep((string) ($fields['cep'] ?? '')),
            'endereco' => filled($fields['endereco'] ?? null)
                ? ErpUppercase::uppercase((string) $fields['endereco'])
                : null,
            'numero' => filled($fields['numero'] ?? null)
                ? ErpUppercase::uppercase((string) $fields['numero'])
                : null,
            'bairro' => filled($fields['bairro'] ?? null)
                ? ErpUppercase::uppercase((string) $fields['bairro'])
                : null,
            'cidade' => filled($fields['cidade_nome'] ?? null)
                ? ErpUppercase::uppercase((string) $fields['cidade_nome'])
                : null,
            'uf' => filled($fields['uf'] ?? null) ? strtoupper((string) $fields['uf']) : null,
            'email' => filled($fields['email'] ?? null) ? trim((string) $fields['email']) : null,
            'fone' => Contador::formatFone((string) ($fields['fone1'] ?? ($fields['fone2'] ?? ''))),
        ], fn (?string $value): bool => filled($value));

        $this->contadorCidadeLookupLocked = true;
        $this->contadorForm = [
            ...$this->contadorForm,
            ...$mapped,
        ];
        $this->contadorCidadeLookupLocked = false;
        $this->fecharContadorCidadeSugestoes();

        $missingAddress = blank($mapped['endereco'] ?? null) || blank($mapped['cep'] ?? null);

        Notification::make()
            ->title('Contador encontrado')
            ->body($missingAddress
                ? 'Dados parciais preenchidos. Confira endereço e contatos se necessário.'
                : 'Dados preenchidos automaticamente. Confira e complete CRC/e-mail se necessário.')
            ->success()
            ->send();

        $this->dispatch('erp-masks-refresh');
    }

    public function buscarCepContador(bool $silentIncompleteCep = false): void
    {
        $cep = (string) ($this->contadorForm['cep'] ?? '');

        if (strlen(preg_replace('/\D/', '', $cep) ?? '') !== 8) {
            if (! $silentIncompleteCep) {
                Notification::make()
                    ->title('Informe um CEP completo.')
                    ->warning()
                    ->send();
            }

            return;
        }

        try {
            $fields = app(CepLookupService::class)->lookup($cep);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de CEP')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->contadorCidadeLookupLocked = true;
        $this->contadorForm['cep'] = (string) ($fields['cep'] ?? $cep);
        $this->contadorForm['endereco'] = (string) ($fields['endereco'] ?? '');
        $this->contadorForm['bairro'] = (string) ($fields['bairro'] ?? '');
        $this->contadorForm['cidade'] = (string) ($fields['cidade_nome'] ?? '');
        $this->contadorForm['uf'] = (string) ($fields['uf'] ?? '');
        $this->contadorCidadeLookupLocked = false;

        $this->fecharContadorCidadeSugestoes();

        Notification::make()
            ->title('Endereço preenchido pelo CEP.')
            ->body('Confira o endereço, se necessário.')
            ->success()
            ->send();

        $this->dispatch('erp-masks-refresh');
    }

    public function buscarMunicipiosContador(?string $termo = null): void
    {
        $termo = trim((string) ($termo ?? ($this->contadorForm['cidade'] ?? '')));
        $uf = strtoupper(trim((string) ($this->contadorForm['uf'] ?? '')));

        if (mb_strlen($termo) < 2) {
            $this->fecharContadorCidadeSugestoes();

            return;
        }

        try {
            $this->contadorCidadeSugestoes = app(MunicipioLookupService::class)->search(
                $termo,
                strlen($uf) === 2 ? $uf : null,
                25,
            );
        } catch (RuntimeException $exception) {
            $this->fecharContadorCidadeSugestoes();
            Notification::make()
                ->title('Consulta de cidades')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->contadorCidadeSugestoesOpen = $this->contadorCidadeSugestoes !== [];
        $this->contadorCidadeSugestaoIndex = $this->contadorCidadeSugestoes !== [] ? 0 : -1;
    }

    public function confirmarContadorCidadeSugestao(): void
    {
        if ($this->contadorCidadeSugestoesOpen && $this->contadorCidadeSugestoes !== []) {
            $index = $this->contadorCidadeSugestaoIndex;

            if ($index < 0 || ! isset($this->contadorCidadeSugestoes[$index])) {
                $index = 0;
            }

            $sug = $this->contadorCidadeSugestoes[$index];
            $this->selecionarContadorCidade(
                (string) $sug['nome'],
                (string) ($sug['uf'] ?? ''),
            );

            return;
        }

        $termo = trim((string) ($this->contadorForm['cidade'] ?? ''));

        if (mb_strlen($termo) >= 2) {
            $this->buscarMunicipiosContador($termo);

            if ($this->contadorCidadeSugestoes !== []) {
                $sug = $this->contadorCidadeSugestoes[0];
                $this->selecionarContadorCidade(
                    (string) $sug['nome'],
                    (string) ($sug['uf'] ?? ''),
                );
            }
        }
    }

    public function selecionarContadorCidade(string $nome, ?string $uf = null): void
    {
        $this->contadorCidadeLookupLocked = true;
        $this->contadorForm['cidade'] = mb_strtoupper(trim($nome), 'UTF-8');

        $uf = strtoupper(trim((string) $uf));

        if (strlen($uf) === 2) {
            $this->contadorForm['uf'] = $uf;
        }

        $this->contadorCidadeLookupLocked = false;
        $this->fecharContadorCidadeSugestoes();
    }

    public function moverContadorCidadeSugestao(int $delta): void
    {
        if (! $this->contadorCidadeSugestoesOpen || $this->contadorCidadeSugestoes === []) {
            return;
        }

        $count = count($this->contadorCidadeSugestoes);
        $current = $this->contadorCidadeSugestaoIndex < 0 ? 0 : $this->contadorCidadeSugestaoIndex;
        $this->contadorCidadeSugestaoIndex = ($current + $delta + $count) % $count;
    }

    public function fecharContadorCidadeSugestoes(): void
    {
        $this->contadorCidadeSugestoes = [];
        $this->contadorCidadeSugestoesOpen = false;
        $this->contadorCidadeSugestaoIndex = -1;
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
                'contadorForm.cnpj_cpf' => ['required', 'string', 'max:20', new DocumentoBrasileiroValido],
                'contadorForm.crc' => ['nullable', 'string', 'max:30'],
                'contadorForm.cep' => ['nullable', 'string', 'max:10'],
                'contadorForm.endereco' => ['nullable', 'string', 'max:120'],
                'contadorForm.numero' => ['nullable', 'string', 'max:20'],
                'contadorForm.bairro' => ['nullable', 'string', 'max:80'],
                'contadorForm.cidade' => ['nullable', 'string', 'max:80'],
                'contadorForm.uf' => ['nullable', 'string', 'size:2'],
                'contadorForm.email' => ['required', 'email', 'max:120'],
                'contadorForm.fone' => ['required', 'string', 'max:20'],
            ],
            [
                'contadorForm.codigo.required' => 'Informe o código.',
                'contadorForm.nome.required' => 'Informe o nome.',
                'contadorForm.cnpj_cpf.required' => 'Informe o CNPJ/CPF.',
                'contadorForm.email.required' => 'Informe o e-mail.',
                'contadorForm.email.email' => 'Informe um e-mail válido.',
                'contadorForm.fone.required' => 'Informe o fone.',
            ],
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
        $this->highlightedRecordId = (int) $record->getKey();
        $this->resetTable();
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
