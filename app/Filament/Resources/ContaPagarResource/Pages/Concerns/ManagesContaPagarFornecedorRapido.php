<?php

namespace App\Filament\Resources\ContaPagarResource\Pages\Concerns;

use App\Models\Person;
use App\Rules\DocumentoBrasileiroValido;
use App\Rules\PersonDocumentoUnico;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\CnpjLookupService;
use App\Support\Erp\PersonCnpjFieldDiff;
use App\Support\Erp\PersonCpfCnpjUnicidade;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;

trait ManagesContaPagarFornecedorRapido
{
    public bool $fornecedorRapidoModalOpen = false;

    public bool $fornecedorRapidoSalvando = false;

    public bool $fornecedorRapidoConsultando = false;

    public string $fornecedorRapidoNome = '';

    public string $fornecedorRapidoFantasia = '';

    public string $fornecedorRapidoTipo = Person::PESSOA_JURIDICA;

    public string $fornecedorRapidoCpfCnpj = '';

    public string $fornecedorRapidoIe = '';

    public string $fornecedorRapidoTipoContribuinte = 'nao_contribuinte';

    public string $fornecedorRapidoFone = '';

    public string $fornecedorRapidoEmail = '';

    public string $fornecedorRapidoCep = '';

    public string $fornecedorRapidoEndereco = '';

    public string $fornecedorRapidoNumero = '';

    public string $fornecedorRapidoComplemento = '';

    public string $fornecedorRapidoBairro = '';

    public string $fornecedorRapidoCidadeNome = '';

    public string $fornecedorRapidoCidadeCodigo = '';

    public string $fornecedorRapidoUf = '';

    public ?int $fornecedorRapidoDuplicadoId = null;

    public string $fornecedorRapidoDuplicadoLabel = '';

    public bool $fornecedorRapidoDuplicadoEhFornecedor = false;

    public function openFornecedorRapidoModal(): void
    {
        if (! $this->contaFormModalOpen || $this->contaFormRecordId !== null) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('pessoas.create')) {
            return;
        }

        $this->resetFornecedorRapidoForm();
        $this->closeContaFormFornecedorLookup();
        $this->fornecedorRapidoModalOpen = true;
        $this->dispatch('erp-pagar-fornecedor-rapido-focus');
        $this->dispatch('erp-masks-refresh');
    }

    public function closeFornecedorRapidoModal(): void
    {
        $this->fornecedorRapidoModalOpen = false;
        $this->fornecedorRapidoSalvando = false;
        $this->fornecedorRapidoConsultando = false;
        $this->resetFornecedorRapidoForm();
    }

    public function updatedFornecedorRapidoTipo(string $value): void
    {
        $this->fornecedorRapidoTipo = $value === Person::PESSOA_FISICA
            ? Person::PESSOA_FISICA
            : Person::PESSOA_JURIDICA;

        if ($this->fornecedorRapidoTipo === Person::PESSOA_FISICA) {
            $this->fornecedorRapidoIe = '';
            $this->fornecedorRapidoTipoContribuinte = 'nao_contribuinte';
        }

        $this->limparFornecedorRapidoDuplicado();
        $this->dispatch('erp-masks-refresh');
    }

    public function updatedFornecedorRapidoIe(): void
    {
        if ($this->fornecedorRapidoTipo === Person::PESSOA_FISICA) {
            return;
        }

        $ie = mb_strtoupper(trim($this->fornecedorRapidoIe), 'UTF-8');
        $this->fornecedorRapidoIe = $ie;

        // Edição manual: IE vazia ⇒ NÃO CONTRIBUINTE (mesmo padrão de Pessoas).
        if ($ie === '' || $ie === '-') {
            $this->fornecedorRapidoTipoContribuinte = 'nao_contribuinte';

            return;
        }

        $tipo = PersonCnpjFieldDiff::tipoFromIe($ie);

        if ($tipo !== null) {
            $this->fornecedorRapidoTipoContribuinte = $tipo;
        }
    }

    public function updatedFornecedorRapidoCpfCnpj(): void
    {
        $this->limparFornecedorRapidoDuplicado();
    }

    public function consultarCnpjFornecedorRapido(): void
    {
        if (! $this->fornecedorRapidoModalOpen || $this->fornecedorRapidoConsultando) {
            return;
        }

        $digits = preg_replace('/\D/', '', $this->fornecedorRapidoCpfCnpj) ?? '';

        if (strlen($digits) === 14) {
            $this->fornecedorRapidoTipo = Person::PESSOA_JURIDICA;
        } elseif ($this->fornecedorRapidoTipo !== Person::PESSOA_JURIDICA) {
            Notification::make()
                ->title('Selecione Pessoa Jurídica.')
                ->warning()
                ->send();

            return;
        }

        if (strlen($digits) !== 14) {
            Notification::make()
                ->title('Informe um CNPJ completo.')
                ->warning()
                ->send();

            return;
        }

        $this->fornecedorRapidoConsultando = true;

        try {
            $fields = app(CnpjLookupService::class)->fetch($digits);
            $this->aplicarCamposConsultaCnpjFornecedorRapido($fields);
            $this->verificarDuplicadoFornecedorRapido(silent: true);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de CNPJ')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->fornecedorRapidoConsultando = false;
            $this->dispatch('erp-masks-refresh');
        }
    }

    public function buscarCepFornecedorRapido(bool $silentIncompleteCep = false): void
    {
        if (! $this->fornecedorRapidoModalOpen || $this->fornecedorRapidoConsultando) {
            return;
        }

        $this->fornecedorRapidoConsultando = true;

        try {
            $this->executarBuscaCepFornecedorRapido($silentIncompleteCep);
        } finally {
            $this->fornecedorRapidoConsultando = false;
            $this->dispatch('erp-masks-refresh');
        }
    }

    protected function executarBuscaCepFornecedorRapido(bool $silentIncompleteCep = false): void
    {
        $cepDigits = preg_replace('/\D/', '', $this->fornecedorRapidoCep) ?? '';

        if (strlen($cepDigits) !== 8) {
            if (! $silentIncompleteCep) {
                Notification::make()
                    ->title('Informe um CEP completo.')
                    ->warning()
                    ->send();
            }

            return;
        }

        try {
            $fields = app(CepLookupService::class)->lookup($this->fornecedorRapidoCep);
            $this->mergeFornecedorRapidoFilled([
                'cep' => $fields['cep'] ?? null,
                'endereco' => $fields['endereco'] ?? null,
                'bairro' => $fields['bairro'] ?? null,
                'cidade_nome' => $fields['cidade_nome'] ?? null,
                'uf' => $fields['uf'] ?? null,
                'cidade_codigo' => $fields['cidade_codigo'] ?? null,
            ]);

            if (! $silentIncompleteCep) {
                Notification::make()
                    ->title('Endereço preenchido pelo CEP.')
                    ->body('Confira o código IBGE da cidade e o complemento, se necessário.')
                    ->success()
                    ->send();
            }
        } catch (RuntimeException $exception) {
            if (! $silentIncompleteCep) {
                Notification::make()
                    ->title('Consulta de CEP')
                    ->body($exception->getMessage())
                    ->warning()
                    ->send();
            }
        }
    }

    public function usarFornecedorRapidoDuplicado(): void
    {
        if ($this->fornecedorRapidoDuplicadoId === null) {
            return;
        }

        $person = Person::query()->find($this->fornecedorRapidoDuplicadoId);

        if (! $person) {
            Notification::make()
                ->title('Cadastro não encontrado.')
                ->warning()
                ->send();
            $this->limparFornecedorRapidoDuplicado();

            return;
        }

        if (! $person->ativo) {
            Notification::make()
                ->title('Cadastro inativo')
                ->body('Reative a pessoa em Pessoas antes de usar neste lançamento.')
                ->warning()
                ->send();

            return;
        }

        if (! $person->is_fornecedor) {
            if (! $this->erpCan('pessoas.update')) {
                Notification::make()
                    ->title('Pessoa não marcada como fornecedor')
                    ->body('Sem permissão para alterar. Marque como fornecedor em Pessoas.')
                    ->warning()
                    ->send();

                return;
            }

            // Só a flag — não sobrescreve demais dados.
            $person->is_fornecedor = true;
            $person->save();
        }

        $this->applyContaFormFornecedor((int) $person->getKey(), (string) $person->nome_razao);
        $this->closeFornecedorRapidoModal();

        Notification::make()
            ->title('Fornecedor selecionado.')
            ->body('Cadastro existente utilizado. Nenhum dado foi sobrescrito.')
            ->success()
            ->send();
    }

    public function salvarFornecedorRapido(): void
    {
        if (! $this->fornecedorRapidoModalOpen || $this->fornecedorRapidoSalvando || $this->fornecedorRapidoConsultando) {
            return;
        }

        if (! $this->erpAuthorizeOrNotify('pessoas.create')) {
            return;
        }

        $this->fornecedorRapidoSalvando = true;

        try {
            $this->normalizarCamposFornecedorRapido();

            if ($this->verificarDuplicadoFornecedorRapido(silent: false)) {
                return;
            }

            $rules = [
                'fornecedorRapidoNome' => ['required', 'string', 'max:120'],
                'fornecedorRapidoFantasia' => ['nullable', 'string', 'max:120'],
                'fornecedorRapidoTipo' => ['required', 'in:'.Person::PESSOA_FISICA.','.Person::PESSOA_JURIDICA],
                'fornecedorRapidoCpfCnpj' => [
                    'nullable',
                    'string',
                    'max:20',
                    new DocumentoBrasileiroValido($this->fornecedorRapidoTipo),
                    new PersonDocumentoUnico(null),
                ],
                'fornecedorRapidoFone' => ['nullable', 'string', 'max:20'],
                'fornecedorRapidoEmail' => ['nullable', 'string', 'max:120', 'email:filter'],
                'fornecedorRapidoCep' => ['nullable', 'string', 'max:10'],
                'fornecedorRapidoEndereco' => ['nullable', 'string', 'max:120'],
                'fornecedorRapidoNumero' => ['nullable', 'string', 'max:20'],
                'fornecedorRapidoComplemento' => ['nullable', 'string', 'max:60'],
                'fornecedorRapidoBairro' => ['nullable', 'string', 'max:80'],
                'fornecedorRapidoCidadeNome' => ['nullable', 'string', 'max:80'],
                'fornecedorRapidoCidadeCodigo' => ['nullable', 'string', 'max:10'],
                'fornecedorRapidoUf' => ['nullable', 'string', 'max:2'],
            ];

            if ($this->fornecedorRapidoTipo === Person::PESSOA_JURIDICA) {
                $rules['fornecedorRapidoIe'] = ['nullable', 'string', 'max:30'];
                $rules['fornecedorRapidoTipoContribuinte'] = [
                    'required',
                    'in:'.implode(',', array_keys(Person::tiposContribuinte())),
                ];
            }

            if ($this->fornecedorRapidoTemEnderecoRelevante()) {
                $rules['fornecedorRapidoCidadeCodigo'] = [
                    'required',
                    'string',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! CepLookupService::isValidIbgeCode(is_string($value) ? $value : null)) {
                            $fail('Informe o CEP e clique em Pesquisar CEP para obter o código IBGE da cidade.');
                        }
                    },
                ];
            }

            $this->validate(
                $rules,
                [
                    'fornecedorRapidoNome.required' => 'Informe o nome / razão social.',
                    'fornecedorRapidoEmail.email' => 'Informe um e-mail válido.',
                    'fornecedorRapidoCidadeCodigo.required' => 'Informe o CEP e clique em Pesquisar CEP para obter o código IBGE da cidade.',
                ],
                [
                    'fornecedorRapidoNome' => 'nome / razão social',
                    'fornecedorRapidoFantasia' => 'fantasia',
                    'fornecedorRapidoTipo' => 'tipo',
                    'fornecedorRapidoCpfCnpj' => 'CPF/CNPJ',
                    'fornecedorRapidoIe' => 'inscrição estadual',
                    'fornecedorRapidoTipoContribuinte' => 'tipo de contribuinte',
                    'fornecedorRapidoFone' => 'telefone',
                    'fornecedorRapidoEmail' => 'e-mail',
                    'fornecedorRapidoCep' => 'CEP',
                    'fornecedorRapidoEndereco' => 'logradouro',
                    'fornecedorRapidoNumero' => 'número',
                    'fornecedorRapidoComplemento' => 'complemento',
                    'fornecedorRapidoBairro' => 'bairro',
                    'fornecedorRapidoCidadeNome' => 'município',
                    'fornecedorRapidoCidadeCodigo' => 'código IBGE',
                    'fornecedorRapidoUf' => 'UF',
                ],
            );

            $isPf = $this->fornecedorRapidoTipo === Person::PESSOA_FISICA;
            $cpfCnpj = $this->fornecedorRapidoCpfCnpj !== '' ? $this->fornecedorRapidoCpfCnpj : null;

            $payload = [
                'codigo' => Person::nextCodigo(),
                'pessoa_tipo' => $this->fornecedorRapidoTipo,
                'nome_razao' => $this->fornecedorRapidoNome,
                'apelido_fantasia' => $this->fornecedorRapidoFantasia !== '' ? $this->fornecedorRapidoFantasia : null,
                'cpf_cnpj' => $cpfCnpj,
                'rg_ie' => $isPf ? null : ($this->fornecedorRapidoIe !== '' ? $this->fornecedorRapidoIe : null),
                'tipo_contribuinte' => $isPf
                    ? 'nao_contribuinte'
                    : $this->fornecedorRapidoTipoContribuinte,
                'fone1' => $this->fornecedorRapidoFone !== '' ? $this->fornecedorRapidoFone : null,
                'email' => $this->fornecedorRapidoEmail !== '' ? $this->fornecedorRapidoEmail : null,
                'cep' => $this->fornecedorRapidoCep !== '' ? $this->fornecedorRapidoCep : null,
                'endereco' => $this->fornecedorRapidoEndereco !== '' ? $this->fornecedorRapidoEndereco : null,
                'numero' => $this->fornecedorRapidoNumero !== '' ? $this->fornecedorRapidoNumero : null,
                'complemento' => $this->fornecedorRapidoComplemento !== '' ? $this->fornecedorRapidoComplemento : null,
                'bairro' => $this->fornecedorRapidoBairro !== '' ? $this->fornecedorRapidoBairro : null,
                'cidade_nome' => $this->fornecedorRapidoCidadeNome !== '' ? $this->fornecedorRapidoCidadeNome : null,
                'cidade_codigo' => $this->fornecedorRapidoCidadeCodigo !== '' ? $this->fornecedorRapidoCidadeCodigo : null,
                'uf' => $this->fornecedorRapidoUf !== '' ? $this->fornecedorRapidoUf : null,
                'is_fornecedor' => true,
                'is_cliente' => false,
                'ativo' => true,
            ];

            $person = Person::query()->create($payload);

            $this->applyContaFormFornecedor((int) $person->getKey(), (string) $person->nome_razao);
            $this->closeFornecedorRapidoModal();

            Notification::make()
                ->title('Fornecedor cadastrado.')
                ->success()
                ->send();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Não foi possível cadastrar o fornecedor.')
                ->danger()
                ->send();
        } finally {
            $this->fornecedorRapidoSalvando = false;
        }
    }

    /**
     * @param  array<string, string|null>  $fields
     */
    protected function aplicarCamposConsultaCnpjFornecedorRapido(array $fields): void
    {
        $filled = array_filter(
            $fields,
            static fn (?string $value): bool => filled($value),
        );

        $this->mergeFornecedorRapidoFilled($filled);

        // IE presente ⇒ alinha tipo. Sem IE ⇒ não presume (não força NÃO CONTRIBUINTE).
        if (array_key_exists('rg_ie', $filled) && ! array_key_exists('tipo_contribuinte', $filled)) {
            $tipo = PersonCnpjFieldDiff::tipoFromIe($filled['rg_ie']);

            if ($tipo !== null) {
                $this->fornecedorRapidoTipoContribuinte = $tipo;
            }
        }

        $hasIe = filled($this->fornecedorRapidoIe);
        $missingAddress = $this->fornecedorRapidoEndereco === '' || $this->fornecedorRapidoCep === '';

        $body = match (true) {
            $hasIe && ! $missingAddress => 'Dados preenchidos automaticamente.',
            $hasIe => 'Dados preenchidos com IE. Confira endereço e complementos se necessário.',
            ! $missingAddress => 'Dados preenchidos. IE não informada nas consultas — preencha manualmente se necessário.',
            default => 'Dados parciais preenchidos. Complete endereço e IE manualmente se necessário.',
        };

        Notification::make()
            ->title('Pessoa jurídica encontrada')
            ->body($body)
            ->success()
            ->duration(3500)
            ->send();

        if ($this->fornecedorRapidoCep !== '' && ! CepLookupService::isValidIbgeCode($this->fornecedorRapidoCidadeCodigo)) {
            $this->executarBuscaCepFornecedorRapido(silentIncompleteCep: true);
        }
    }

    /**
     * @param  array<string, string|null>  $fields
     */
    protected function mergeFornecedorRapidoFilled(array $fields): void
    {
        $map = [
            'cpf_cnpj' => 'fornecedorRapidoCpfCnpj',
            'nome_razao' => 'fornecedorRapidoNome',
            'apelido_fantasia' => 'fornecedorRapidoFantasia',
            'rg_ie' => 'fornecedorRapidoIe',
            'tipo_contribuinte' => 'fornecedorRapidoTipoContribuinte',
            'fone1' => 'fornecedorRapidoFone',
            'email' => 'fornecedorRapidoEmail',
            'cep' => 'fornecedorRapidoCep',
            'endereco' => 'fornecedorRapidoEndereco',
            'numero' => 'fornecedorRapidoNumero',
            'complemento' => 'fornecedorRapidoComplemento',
            'bairro' => 'fornecedorRapidoBairro',
            'cidade_nome' => 'fornecedorRapidoCidadeNome',
            'cidade_codigo' => 'fornecedorRapidoCidadeCodigo',
            'uf' => 'fornecedorRapidoUf',
        ];

        foreach ($map as $apiKey => $prop) {
            if (! array_key_exists($apiKey, $fields) || ! filled($fields[$apiKey])) {
                continue;
            }

            $value = (string) $fields[$apiKey];

            if (in_array($prop, [
                'fornecedorRapidoNome',
                'fornecedorRapidoFantasia',
                'fornecedorRapidoIe',
                'fornecedorRapidoEndereco',
                'fornecedorRapidoComplemento',
                'fornecedorRapidoBairro',
                'fornecedorRapidoCidadeNome',
            ], true)) {
                $value = mb_strtoupper(trim($value), 'UTF-8');
            }

            if ($prop === 'fornecedorRapidoUf') {
                $value = mb_strtoupper(trim($value), 'UTF-8');
            }

            if ($prop === 'fornecedorRapidoFone' && $this->fornecedorRapidoFone !== '') {
                // Não sobrescreve telefone já digitado com retorno da API.
                continue;
            }

            $this->{$prop} = $value;
        }

        if (array_key_exists('fone1', $fields) && filled($fields['fone1']) && $this->fornecedorRapidoFone === '') {
            $this->fornecedorRapidoFone = (string) $fields['fone1'];
        } elseif (array_key_exists('fone2', $fields) && filled($fields['fone2']) && $this->fornecedorRapidoFone === '') {
            $this->fornecedorRapidoFone = (string) $fields['fone2'];
        }
    }

    protected function verificarDuplicadoFornecedorRapido(bool $silent): bool
    {
        $existente = app(PersonCpfCnpjUnicidade::class)->encontrar($this->fornecedorRapidoCpfCnpj);

        if (! $existente) {
            $this->limparFornecedorRapidoDuplicado();

            return false;
        }

        $codigo = trim((string) ($existente->codigo ?? ''));
        $nome = trim((string) ($existente->nome_razao ?? ''));
        $ref = $codigo !== '' ? 'código '.$codigo : '';
        if ($nome !== '') {
            $ref = $ref !== '' ? $ref.' — '.$nome : $nome;
        }

        $this->fornecedorRapidoDuplicadoId = (int) $existente->getKey();
        $this->fornecedorRapidoDuplicadoLabel = $ref !== '' ? $ref : 'Cadastro #'.$existente->getKey();
        $this->fornecedorRapidoDuplicadoEhFornecedor = (bool) $existente->is_fornecedor;

        if (! $silent) {
            Notification::make()
                ->title('Documento já cadastrado')
                ->body('Use o cadastro existente ou altere o CPF/CNPJ.')
                ->warning()
                ->send();
        }

        return true;
    }

    protected function limparFornecedorRapidoDuplicado(): void
    {
        $this->fornecedorRapidoDuplicadoId = null;
        $this->fornecedorRapidoDuplicadoLabel = '';
        $this->fornecedorRapidoDuplicadoEhFornecedor = false;
    }

    protected function fornecedorRapidoTemEnderecoRelevante(): bool
    {
        return $this->fornecedorRapidoCep !== ''
            || $this->fornecedorRapidoEndereco !== ''
            || $this->fornecedorRapidoCidadeNome !== '';
    }

    protected function normalizarCamposFornecedorRapido(): void
    {
        $this->fornecedorRapidoNome = mb_strtoupper(trim($this->fornecedorRapidoNome), 'UTF-8');
        $this->fornecedorRapidoFantasia = mb_strtoupper(trim($this->fornecedorRapidoFantasia), 'UTF-8');
        $this->fornecedorRapidoCpfCnpj = trim($this->fornecedorRapidoCpfCnpj);
        $this->fornecedorRapidoIe = mb_strtoupper(trim($this->fornecedorRapidoIe), 'UTF-8');
        $this->fornecedorRapidoFone = trim($this->fornecedorRapidoFone);
        $this->fornecedorRapidoEmail = mb_strtolower(trim($this->fornecedorRapidoEmail), 'UTF-8');
        $this->fornecedorRapidoCep = trim($this->fornecedorRapidoCep);
        $this->fornecedorRapidoEndereco = mb_strtoupper(trim($this->fornecedorRapidoEndereco), 'UTF-8');
        $this->fornecedorRapidoNumero = trim($this->fornecedorRapidoNumero);
        $this->fornecedorRapidoComplemento = mb_strtoupper(trim($this->fornecedorRapidoComplemento), 'UTF-8');
        $this->fornecedorRapidoBairro = mb_strtoupper(trim($this->fornecedorRapidoBairro), 'UTF-8');
        $this->fornecedorRapidoCidadeNome = mb_strtoupper(trim($this->fornecedorRapidoCidadeNome), 'UTF-8');
        $this->fornecedorRapidoCidadeCodigo = trim($this->fornecedorRapidoCidadeCodigo);
        $this->fornecedorRapidoUf = mb_strtoupper(trim($this->fornecedorRapidoUf), 'UTF-8');
        $this->fornecedorRapidoTipo = $this->fornecedorRapidoTipo === Person::PESSOA_FISICA
            ? Person::PESSOA_FISICA
            : Person::PESSOA_JURIDICA;

        if ($this->fornecedorRapidoTipo === Person::PESSOA_FISICA) {
            $this->fornecedorRapidoIe = '';
            $this->fornecedorRapidoTipoContribuinte = 'nao_contribuinte';
        } elseif (! array_key_exists($this->fornecedorRapidoTipoContribuinte, Person::tiposContribuinte())) {
            $this->fornecedorRapidoTipoContribuinte = 'nao_contribuinte';
        }
    }

    protected function resetFornecedorRapidoForm(): void
    {
        $this->fornecedorRapidoNome = '';
        $this->fornecedorRapidoFantasia = '';
        $this->fornecedorRapidoTipo = Person::PESSOA_JURIDICA;
        $this->fornecedorRapidoCpfCnpj = '';
        $this->fornecedorRapidoIe = '';
        $this->fornecedorRapidoTipoContribuinte = 'nao_contribuinte';
        $this->fornecedorRapidoFone = '';
        $this->fornecedorRapidoEmail = '';
        $this->fornecedorRapidoCep = '';
        $this->fornecedorRapidoEndereco = '';
        $this->fornecedorRapidoNumero = '';
        $this->fornecedorRapidoComplemento = '';
        $this->fornecedorRapidoBairro = '';
        $this->fornecedorRapidoCidadeNome = '';
        $this->fornecedorRapidoCidadeCodigo = '';
        $this->fornecedorRapidoUf = '';
        $this->fornecedorRapidoSalvando = false;
        $this->fornecedorRapidoConsultando = false;
        $this->limparFornecedorRapidoDuplicado();
        $this->resetErrorBag([
            'fornecedorRapidoNome',
            'fornecedorRapidoFantasia',
            'fornecedorRapidoTipo',
            'fornecedorRapidoCpfCnpj',
            'fornecedorRapidoIe',
            'fornecedorRapidoTipoContribuinte',
            'fornecedorRapidoFone',
            'fornecedorRapidoEmail',
            'fornecedorRapidoCep',
            'fornecedorRapidoEndereco',
            'fornecedorRapidoNumero',
            'fornecedorRapidoComplemento',
            'fornecedorRapidoBairro',
            'fornecedorRapidoCidadeNome',
            'fornecedorRapidoCidadeCodigo',
            'fornecedorRapidoUf',
        ]);
    }
}
