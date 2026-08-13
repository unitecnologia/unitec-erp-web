<?php

namespace App\Filament\Resources\RhFuncionarioResource\Pages\Concerns;

use App\Models\Estoque;
use App\Models\PriceTable;
use App\Models\RhCargo;
use App\Models\RhDepartamento;
use App\Models\RhFuncionario;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\ErpOnboarding;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\Rh\OperadorFromFuncionarioSync;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

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
            ...$this->blankOperadorFormFields(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankOperadorFormFields(): array
    {
        return [
            'eh_operador' => false,
            'usuario_id' => '',
            'terminais' => [],
            'estoque_id' => $this->defaultEstoqueId(),
            'usar_agendamento' => false,
            'setor_vendas' => true,
            'tabela_venda_id' => $this->defaultTabelaVendaId(),
            'comissao_av' => '0,00',
            'comissao_ap' => '0,00',
            'mobile_meta_venda' => '0,00',
            'ganha_comissao_todas_vendas' => false,
            'setor_servicos' => false,
            'comissao_servico' => '0,00',
            'ganha_comissao_todos_servicos' => false,
            'observacoes_operador' => '',
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

        $record = RhFuncionario::query()->with(['vendedor.terminais', 'vendedor.usuario'])->find($this->highlightedRecordId);

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
            ...$this->operadorFormFieldsFromRecord($record),
        ];
        $this->rhFuncionarioModalOpen = true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function operadorFormFieldsFromRecord(RhFuncionario $record): array
    {
        $vendedor = $record->vendedor;
        $blank = $this->blankOperadorFormFields();

        if (! $vendedor) {
            $usuarioId = $record->user_id;

            return [
                ...$blank,
                'usuario_id' => $usuarioId ? (string) $usuarioId : '',
                'eh_operador' => false,
            ];
        }

        // Mantém dados do vínculo mesmo se desativado — facilita reativar sem perder config.
        return [
            'eh_operador' => (bool) $vendedor->ativo,
            'usuario_id' => optional($vendedor->usuario)->id
                ? (string) $vendedor->usuario->id
                : ($record->user_id ? (string) $record->user_id : ''),
            'terminais' => $vendedor->terminais->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'estoque_id' => $vendedor->estoque_id ? (string) $vendedor->estoque_id : $blank['estoque_id'],
            'usar_agendamento' => (bool) $vendedor->usar_agendamento,
            'setor_vendas' => (bool) $vendedor->setor_vendas,
            'tabela_venda_id' => $vendedor->tabela_venda_id
                ? (string) $vendedor->tabela_venda_id
                : $blank['tabela_venda_id'],
            'comissao_av' => number_format((float) $vendedor->comissao_av, 2, ',', '.'),
            'comissao_ap' => number_format((float) $vendedor->comissao_ap, 2, ',', '.'),
            'mobile_meta_venda' => number_format((float) $vendedor->mobile_meta_venda, 2, ',', '.'),
            'ganha_comissao_todas_vendas' => (bool) $vendedor->ganha_comissao_todas_vendas,
            'setor_servicos' => (bool) $vendedor->setor_servicos,
            'comissao_servico' => number_format((float) $vendedor->comissao_servico, 2, ',', '.'),
            'ganha_comissao_todos_servicos' => (bool) $vendedor->ganha_comissao_todos_servicos,
            'observacoes_operador' => (string) ($vendedor->observacoes ?? ''),
        ];
    }

    public function closeRhFuncionarioModal(): void
    {
        if (ErpOnboarding::step() === ErpOnboarding::STEP_COLABORADOR) {
            Notification::make()
                ->title('Cadastre o funcionário com a aba Operador para concluir o primeiro acesso.')
                ->warning()
                ->send();

            return;
        }

        $this->rhFuncionarioModalOpen = false;
        $this->rhFuncionarioModalRecordId = null;
        $this->rhFuncionarioForm = $this->blankRhFuncionarioForm();
    }

    public function updatedRhFuncionarioForm(mixed $value, string $key): void
    {
        if ($key === 'terminais' || str_starts_with($key, 'terminais.')) {
            if (! is_array($this->rhFuncionarioForm['terminais'] ?? null)) {
                $this->rhFuncionarioForm['terminais'] = [];
            }
        }
    }

    public function buscarCepRhFuncionario(): void
    {
        $cep = (string) ($this->rhFuncionarioForm['cep'] ?? '');

        if (strlen(preg_replace('/\D/', '', $cep) ?? '') !== 8) {
            return;
        }

        try {
            $fields = app(CepLookupService::class)->lookup($cep);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title($exception->getMessage() === 'CEP não encontrado.' ? 'CEP não encontrado.' : 'Consulta de CEP')
                ->body($exception->getMessage() !== 'CEP não encontrado.' ? $exception->getMessage() : null)
                ->warning()
                ->send();

            return;
        }

        $this->rhFuncionarioForm = [
            ...$this->rhFuncionarioForm,
            ...$fields,
        ];
    }

    public function saveRhFuncionario(): void
    {
        $ehOperador = (bool) ($this->rhFuncionarioForm['eh_operador'] ?? false);

        $rules = [
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
            'rhFuncionarioForm.terminais' => ['array'],
            'rhFuncionarioForm.terminais.*' => ['integer', Rule::exists('terminais', 'id')],
        ];

        if ($ehOperador) {
            $rules['rhFuncionarioForm.usuario_id'] = ['required', 'integer', Rule::exists('users', 'id')];
            $rules['rhFuncionarioForm.estoque_id'] = ['required', 'integer', Rule::exists('estoques', 'id')];
            $rules['rhFuncionarioForm.tabela_venda_id'] = ['nullable', 'integer', Rule::exists('price_tables', 'id')];
        }

        try {
            $this->validate($rules, [
                'rhFuncionarioForm.nome.required' => 'Informe o nome.',
                'rhFuncionarioForm.cargo_id.required' => 'Selecione o cargo.',
                'rhFuncionarioForm.departamento_id.required' => 'Selecione o departamento.',
                'rhFuncionarioForm.usuario_id.required' => 'Selecione o usuário do operador.',
                'rhFuncionarioForm.estoque_id.required' => 'Selecione o estoque do operador.',
            ], [
                'rhFuncionarioForm.codigo' => 'código',
                'rhFuncionarioForm.nome' => 'nome',
                'rhFuncionarioForm.cpf' => 'CPF',
                'rhFuncionarioForm.email' => 'e-mail',
                'rhFuncionarioForm.cargo_id' => 'cargo',
                'rhFuncionarioForm.departamento_id' => 'departamento',
                'rhFuncionarioForm.uf' => 'UF',
                'rhFuncionarioForm.usuario_id' => 'usuário',
                'rhFuncionarioForm.estoque_id' => 'estoque',
                'rhFuncionarioForm.terminais' => 'PDVs liberados',
                'rhFuncionarioForm.tabela_venda_id' => 'tabela de venda',
            ]);
        } catch (ValidationException $e) {
            $tab = $this->rhFuncionarioTabForErrorKeys(array_keys($e->errors()));
            $this->dispatch('erp-rh-func-goto-tab', tab: $tab);
            Notification::make()
                ->title('Não foi possível gravar.')
                ->body((string) (collect($e->errors())->flatten()->first() ?? 'Revise os campos obrigatórios.'))
                ->danger()
                ->send();

            throw $e;
        }

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

        $isCreate = $this->rhFuncionarioModalRecordId === null;
        $operadorPayload = [
            'eh_operador' => $ehOperador,
            'usuario_id' => filled($this->rhFuncionarioForm['usuario_id'] ?? null)
                ? (int) $this->rhFuncionarioForm['usuario_id']
                : null,
            'terminais' => array_values(array_map('intval', (array) ($this->rhFuncionarioForm['terminais'] ?? []))),
            'estoque_id' => filled($this->rhFuncionarioForm['estoque_id'] ?? null)
                ? (int) $this->rhFuncionarioForm['estoque_id']
                : null,
            'usar_agendamento' => (bool) ($this->rhFuncionarioForm['usar_agendamento'] ?? false),
            'setor_vendas' => (bool) ($this->rhFuncionarioForm['setor_vendas'] ?? true),
            'tabela_venda_id' => filled($this->rhFuncionarioForm['tabela_venda_id'] ?? null)
                ? (int) $this->rhFuncionarioForm['tabela_venda_id']
                : null,
            'comissao_av' => OperadorFromFuncionarioSync::parseMoney($this->rhFuncionarioForm['comissao_av'] ?? 0),
            'comissao_ap' => OperadorFromFuncionarioSync::parseMoney($this->rhFuncionarioForm['comissao_ap'] ?? 0),
            'mobile_meta_venda' => OperadorFromFuncionarioSync::parseMoney($this->rhFuncionarioForm['mobile_meta_venda'] ?? 0),
            'ganha_comissao_todas_vendas' => (bool) ($this->rhFuncionarioForm['ganha_comissao_todas_vendas'] ?? false),
            'setor_servicos' => (bool) ($this->rhFuncionarioForm['setor_servicos'] ?? false),
            'comissao_servico' => OperadorFromFuncionarioSync::parseMoney($this->rhFuncionarioForm['comissao_servico'] ?? 0),
            'ganha_comissao_todos_servicos' => (bool) ($this->rhFuncionarioForm['ganha_comissao_todos_servicos'] ?? false),
            'observacoes' => $this->nullableTrim($this->rhFuncionarioForm['observacoes_operador'] ?? null),
        ];

        try {
            $record = DB::transaction(function () use ($payload, $operadorPayload): RhFuncionario {
                if ($this->rhFuncionarioModalRecordId) {
                    $record = RhFuncionario::query()->find($this->rhFuncionarioModalRecordId);

                    if (! $record) {
                        throw new InvalidArgumentException('Funcionário não encontrado.');
                    }

                    $record->update($payload);
                } else {
                    $record = RhFuncionario::query()->create($payload);
                    $this->rhFuncionarioModalRecordId = (int) $record->getKey();
                }

                $record->refresh();
                (new OperadorFromFuncionarioSync)->sync($record, $operadorPayload);

                return $record->fresh() ?? $record;
            });
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'Funcionário não encontrado.') {
                Notification::make()->title($e->getMessage())->warning()->send();

                return;
            }

            $this->addError('rhFuncionarioForm.usuario_id', $e->getMessage());
            $this->dispatch('erp-rh-func-goto-tab', tab: 'oper');
            Notification::make()
                ->title('Não foi possível gravar o operador.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->title('Erro ao gravar funcionário.')
                ->body('Tente novamente. Se persistir, verifique o log do sistema.')
                ->danger()
                ->send();

            return;
        }

        if ($isCreate) {
            Notification::make()->title('Funcionário incluído.')->success()->send();
        } else {
            Notification::make()->title('Funcionário alterado.')->success()->send();
        }

        if (ErpOnboarding::step() === ErpOnboarding::STEP_COLABORADOR && $ehOperador) {
            ErpOnboarding::complete();
            $this->rhFuncionarioModalOpen = false;
            $this->rhFuncionarioModalRecordId = null;
            $this->rhFuncionarioForm = $this->blankRhFuncionarioForm();
            Notification::make()
                ->title('Funcionário/operador cadastrado. Sistema pronto para uso.')
                ->success()
                ->send();
            $this->redirect(filament()->getUrl());

            return;
        }

        $this->rhFuncionarioModalOpen = false;
        $this->rhFuncionarioModalRecordId = null;
        $this->rhFuncionarioForm = $this->blankRhFuncionarioForm();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }

    /**
     * @param  list<string>  $errorKeys
     */
    protected function rhFuncionarioTabForErrorKeys(array $errorKeys): string
    {
        foreach ($errorKeys as $key) {
            if (str_contains($key, 'usuario_id')
                || str_contains($key, 'estoque_id')
                || str_contains($key, 'terminais')
                || str_contains($key, 'tabela_venda')
                || str_contains($key, 'comissao')
                || str_contains($key, 'eh_operador')
            ) {
                return 'oper';
            }

            if (str_contains($key, 'email') || str_contains($key, 'telefone') || str_contains($key, 'whatsapp')) {
                return 'contato';
            }

            if (str_contains($key, 'cep') || str_contains($key, 'endereco') || str_contains($key, 'bairro')) {
                return 'end';
            }
        }

        return 'ident';
    }

    /**
     * @return array<int|string, string>
     */
    public function rhUsuarioOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function rhTerminalOptions(): array
    {
        return Terminal::query()
            ->orderBy('numero_logico_terminal')
            ->orderBy('nome')
            ->get(['id', 'nome', 'numero_logico_terminal', 'pdv', 'eh_caixa'])
            ->mapWithKeys(function (Terminal $terminal): array {
                $numero = $terminal->numero_logico_terminal;
                $prefix = filled($numero) ? str_pad((string) $numero, 2, '0', STR_PAD_LEFT).' - ' : '';
                $nome = trim((string) $terminal->nome) ?: 'TERMINAL '.$terminal->id;
                $flags = [];
                if ($terminal->pdv) {
                    $flags[] = 'PDV';
                }
                if ($terminal->eh_caixa) {
                    $flags[] = 'CAIXA';
                }
                $suffix = $flags !== [] ? ' ('.implode(', ', $flags).')' : '';

                return [$terminal->id => $prefix.$nome.$suffix];
            })
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function rhEstoqueOptions(): array
    {
        $empresaId = (int) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? 0);

        return Estoque::query()
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome'])
            ->mapWithKeys(fn (Estoque $estoque): array => [
                $estoque->id => $estoque->label(),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function rhTabelaVendaOptions(): array
    {
        return PriceTable::query()
            ->when(
                Schema::hasColumn('price_tables', 'ativo'),
                fn ($q) => $q->where('ativo', true)
            )
            ->orderByRaw('CAST(codigo AS UNSIGNED) ASC')
            ->orderBy('descricao')
            ->get(['id', 'codigo', 'descricao'])
            ->mapWithKeys(fn (PriceTable $t): array => [
                $t->id => mb_strtoupper(
                    trim(($t->codigo !== null && $t->codigo !== '' ? $t->codigo.' — ' : '').(string) $t->descricao),
                    'UTF-8'
                ),
            ])
            ->all();
    }

    protected function defaultTabelaVendaId(): string
    {
        if (! Schema::hasTable('price_tables')) {
            return '';
        }

        $query = PriceTable::query()->when(
            Schema::hasColumn('price_tables', 'ativo'),
            fn ($q) => $q->where('ativo', true)
        );

        $id = (clone $query)->where('codigo', '1')->value('id')
            ?? (clone $query)->orderByRaw('CAST(codigo AS UNSIGNED) ASC')->value('id');

        return $id ? (string) $id : '';
    }

    protected function defaultEstoqueId(): string
    {
        $empresaId = (int) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? 0);

        $id = Estoque::query()
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('ativo', true)
            ->orderBy('codigo')
            ->value('id');

        return $id ? (string) $id : '';
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
