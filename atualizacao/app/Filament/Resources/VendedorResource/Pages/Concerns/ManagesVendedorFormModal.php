<?php

namespace App\Filament\Resources\VendedorResource\Pages\Concerns;

use App\Models\Estoque;
use App\Models\PriceTable;
use App\Models\RhCargo;
use App\Models\RhFuncionario;
use App\Models\Terminal;
use App\Models\User;
use App\Models\Vendedor;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\ErpOnboarding;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

trait ManagesVendedorFormModal
{
    public bool $vendedorModalOpen = false;

    public ?int $vendedorModalRecordId = null;

    /** @var array<string, mixed> */
    public array $vendedorForm = [];

    public function createVendedor(): void
    {
        if ($this->vendedorModalOpen) {
            return;
        }

        $this->vendedorModalRecordId = null;
        $this->vendedorForm = $this->defaultVendedorFormData();
        $this->vendedorModalOpen = true;
        $this->focusVendedorNome();
    }

    public function editVendedor(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = Vendedor::query()->with(['terminais', 'usuario'])->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()
                ->title('Operador não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->vendedorModalRecordId = $record->getKey();
        $this->vendedorForm = $this->vendedorFormDataFromRecord($record);
        $this->vendedorModalOpen = true;
        $this->focusVendedorNome();
    }

    /**
     * Força caixa alta em tempo real (Livewire).
     */
    public function updatedVendedorForm(mixed $value, string $key): void
    {
        if ($key === 'terminais' || str_starts_with($key, 'terminais.')) {
            if (! is_array($this->vendedorForm['terminais'] ?? null)) {
                $this->vendedorForm['terminais'] = [];
            }

            return;
        }

        if (! is_string($value)) {
            return;
        }

        if ($key === 'rh_funcionario_id') {
            $this->aplicarRhFuncionarioNoFormulario(
                filled($value) ? (int) $value : null
            );

            return;
        }

        if (in_array($key, [
            'comissao_av', 'comissao_ap', 'comissao_servico', 'salario', 'mobile_meta_venda',
            'usuario_id', 'tabela_venda_id', 'estoque_id', 'ativo', 'rh_funcionario_id',
            'data_nascimento', 'admissao', 'demissao',
            'cpf', 'telefone', 'whatsapp', 'cep',
        ], true)) {
            return;
        }

        $upper = ErpUppercase::uppercase($value);

        if ($upper !== $value) {
            data_set($this->vendedorForm, $key, $upper);
        }
    }

    protected function focusVendedorNome(): void
    {
        $this->js(<<<'JS'
(() => {
    const focusNome = () => {
        const modal = document.querySelector('.erp-vendedor-form-modal');
        if (modal && window.ErpMasks) {
            window.ErpMasks.init(modal);
        }

        const rh = document.getElementById('vendedor-rh-funcionario');
        const el = rh || document.getElementById('vendedor-nome');
        if (!el) {
            return false;
        }
        el.focus({ preventScroll: true });
        return document.activeElement === el;
    };

    let tries = 0;
    const tick = () => {
        if (focusNome() || ++tries > 30) {
            return;
        }
        setTimeout(tick, 16);
    };

    queueMicrotask(tick);
    setTimeout(tick, 50);
    setTimeout(tick, 150);
    setTimeout(() => {
        const modal = document.querySelector('.erp-vendedor-form-modal');
        if (modal && window.ErpMasks?.init) {
            window.ErpMasks.init(modal);
        }
    }, 80);
})();
JS);
    }

    public function closeVendedorModal(): void
    {
        if (ErpOnboarding::step() === ErpOnboarding::STEP_COLABORADOR) {
            Notification::make()
                ->title('Cadastre o operador (vinculado a um Funcionário RH) para concluir o primeiro acesso.')
                ->warning()
                ->send();

            return;
        }

        $this->vendedorModalOpen = false;
        $this->vendedorModalRecordId = null;
        $this->vendedorForm = [];
    }

    public function saveVendedor(): void
    {
        $isCreate = $this->vendedorModalRecordId === null;

        if ($isCreate) {
            $this->vendedorForm['codigo'] = Vendedor::nextCodigo();
        }

        foreach (['comissao_av', 'comissao_ap', 'comissao_servico', 'salario', 'mobile_meta_venda'] as $field) {
            $this->vendedorForm[$field] = (string) $this->parseVendedorComissao($this->vendedorForm[$field] ?? 0);
        }

        $this->validate(
            [
                'vendedorForm.codigo' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('vendedores', 'codigo')->ignore($this->vendedorModalRecordId),
                ],
                'vendedorForm.rh_funcionario_id' => [
                    'required',
                    'integer',
                    Rule::exists('rh_funcionarios', 'id'),
                ],
                'vendedorForm.cargo' => ['required', 'string', 'max:80'],
                'vendedorForm.nome' => ['required', 'string', 'max:120'],
                'vendedorForm.cpf' => ['nullable', 'string', 'max:20', new DocumentoBrasileiroValido(cpfOnly: true)],
                'vendedorForm.telefone' => ['nullable', 'string', 'max:20'],
                'vendedorForm.whatsapp' => ['nullable', 'string', 'max:20'],
                'vendedorForm.ativo' => ['required', 'in:S,N'],
                'vendedorForm.terminais' => ['array'],
                'vendedorForm.terminais.*' => ['integer', Rule::exists('terminais', 'id')],
                'vendedorForm.tabela_venda_id' => ['nullable', 'integer', Rule::exists('price_tables', 'id')],
                'vendedorForm.usuario_id' => ['required', 'integer', Rule::exists('users', 'id')],
                'vendedorForm.estoque_id' => ['required', 'integer', Rule::exists('estoques', 'id')],
                'vendedorForm.email' => ['nullable', 'email', 'max:120'],
                'vendedorForm.comissao_av' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'vendedorForm.comissao_ap' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'vendedorForm.comissao_servico' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            ],
            [],
            [
                'vendedorForm.codigo' => 'código',
                'vendedorForm.rh_funcionario_id' => 'funcionário',
                'vendedorForm.cargo' => 'cargo',
                'vendedorForm.nome' => 'nome',
                'vendedorForm.cpf' => 'CPF',
                'vendedorForm.ativo' => 'ativo',
                'vendedorForm.terminais' => 'PDVs liberados',
                'vendedorForm.tabela_venda_id' => 'tabela de venda',
                'vendedorForm.usuario_id' => 'usuário',
                'vendedorForm.estoque_id' => 'estoque',
                'vendedorForm.email' => 'e-mail',
                'vendedorForm.comissao_av' => 'comissão AV',
                'vendedorForm.comissao_ap' => 'comissão AP',
                'vendedorForm.comissao_servico' => 'comissão de serviço',
            ],
        );

        $rhFuncionarioId = $this->vendedorForm['rh_funcionario_id'] ?? null;
        $rhFuncionarioId = $rhFuncionarioId !== null && $rhFuncionarioId !== ''
            ? (int) $rhFuncionarioId
            : null;

        if ($rhFuncionarioId) {
            $this->aplicarRhFuncionarioNoFormulario($rhFuncionarioId);
        }

        $usuarioId = $this->vendedorForm['usuario_id'] ?? null;
        $usuarioId = $usuarioId !== null && $usuarioId !== '' ? (int) $usuarioId : null;

        $syncPayload = $this->empresaVendedorSyncFromUsuario($usuarioId);

        if ($syncPayload === []) {
            $this->addError(
                'vendedorForm.usuario_id',
                'Este usuário não tem empresas liberadas. Configure em Permissões / Usuários → Empresas.',
            );

            return;
        }

        $data = $this->normalizeVendedorFormData($this->vendedorForm);
        $data['empresa_id'] = array_key_first($syncPayload);

        if ($this->vendedorModalRecordId) {
            $record = Vendedor::query()->find($this->vendedorModalRecordId);

            if (! $record) {
                Notification::make()
                    ->title('Operador não encontrado.')
                    ->warning()
                    ->send();

                return;
            }

            $record->update($data);

            Notification::make()
                ->title('Operador alterado.')
                ->success()
                ->send();
        } else {
            $record = Vendedor::query()->create($data);

            Notification::make()
                ->title('Operador incluído.')
                ->success()
                ->send();
        }

        $record->empresas()->sync($syncPayload);

        $terminalIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($this->vendedorForm['terminais'] ?? []))
        )));
        $record->terminais()->sync($terminalIds);

        $this->syncVendedorUsuario((int) $record->getKey(), $usuarioId);
        $this->syncVendedorRhFuncionario((int) $record->getKey(), $rhFuncionarioId);

        $onboardingColaborador = $isCreate
            && ErpOnboarding::step() === ErpOnboarding::STEP_COLABORADOR;

        if ($onboardingColaborador) {
            ErpOnboarding::complete();
            $this->vendedorModalOpen = false;
            $this->vendedorModalRecordId = null;
            $this->vendedorForm = [];
            Notification::make()
                ->title('Operador cadastrado. Sistema pronto para uso.')
                ->success()
                ->send();
            $this->redirect(filament()->getUrl());

            return;
        }

        $this->closeVendedorModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord((int) $record->getKey());
    }

    /**
     * Busca automática de endereço pelo CEP (ViaCEP).
     */
    public function buscarCep(): void
    {
        $cep = preg_replace('/\D/', '', (string) ($this->vendedorForm['cep'] ?? ''));

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
            Notification::make()
                ->title('CEP não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $logradouro = (string) ($data['logradouro'] ?? '');

        $this->vendedorForm['endereco'] = mb_strtoupper($logradouro, 'UTF-8');
        $this->vendedorForm['bairro'] = mb_strtoupper((string) ($data['bairro'] ?? ''), 'UTF-8');
        $this->vendedorForm['cidade_nome'] = mb_strtoupper((string) ($data['localidade'] ?? ''), 'UTF-8');
        $this->vendedorForm['uf'] = mb_strtoupper((string) ($data['uf'] ?? ''), 'UTF-8');
        $this->vendedorForm['cidade_codigo'] = (string) ($data['ibge'] ?? '');

        foreach ($this->logradouroOptions() as $opt) {
            if (mb_stripos($logradouro, $opt, 0, 'UTF-8') === 0) {
                $this->vendedorForm['logradouro'] = $opt;
                $this->vendedorForm['endereco'] = trim(mb_substr($logradouro, mb_strlen($opt, 'UTF-8'), null, 'UTF-8'));
                $this->vendedorForm['endereco'] = mb_strtoupper(ltrim($this->vendedorForm['endereco'], ' .-'), 'UTF-8');
                break;
            }
        }
    }

    /**
     * Vincula (ou desvincula) o funcionário RH ao colaborador e mantém o nome sincronizado.
     */
    protected function syncVendedorRhFuncionario(int $vendedorId, ?int $rhFuncionarioId): void
    {
        if (! Schema::hasTable('rh_funcionarios')) {
            return;
        }

        RhFuncionario::query()
            ->where('vendedor_id', $vendedorId)
            ->when(
                $rhFuncionarioId,
                fn ($q) => $q->whereKeyNot($rhFuncionarioId)
            )
            ->update(['vendedor_id' => null]);

        if (! $rhFuncionarioId) {
            return;
        }

        $funcionario = RhFuncionario::query()->with('cargo')->find($rhFuncionarioId);

        if (! $funcionario) {
            return;
        }

        $funcionario->vendedor_id = $vendedorId;
        $funcionario->save();

        $cargoNome = trim((string) ($funcionario->cargo?->nome ?? ''));

        Vendedor::query()->whereKey($vendedorId)->update([
            'nome' => (string) $funcionario->nome,
            'cargo' => $cargoNome !== '' ? ErpUppercase::uppercase($cargoNome) : null,
        ]);
    }

    protected function aplicarRhFuncionarioNoFormulario(?int $rhFuncionarioId): void
    {
        $this->vendedorForm['rh_funcionario_id'] = $rhFuncionarioId ? (string) $rhFuncionarioId : '';

        if (! $rhFuncionarioId) {
            return;
        }

        $funcionario = RhFuncionario::query()->with('cargo')->find($rhFuncionarioId);

        if (! $funcionario) {
            return;
        }

        $this->vendedorForm['nome'] = (string) $funcionario->nome;
        $cargoNome = trim((string) ($funcionario->cargo?->nome ?? ''));

        if ($cargoNome !== '') {
            $this->vendedorForm['cargo'] = ErpUppercase::uppercase($cargoNome);
        }
    }

    /**
     * @return array<int, string>
     */
    public function rhFuncionarioOptions(): array
    {
        if (! Schema::hasTable('rh_funcionarios')) {
            return [];
        }

        $atualId = filled($this->vendedorForm['rh_funcionario_id'] ?? null)
            ? (int) $this->vendedorForm['rh_funcionario_id']
            : null;

        return RhFuncionario::query()
            ->where(function ($q) use ($atualId): void {
                $q->where('ativo', true)
                    ->where(function ($q2): void {
                        $q2->whereNull('vendedor_id');
                        if ($this->vendedorModalRecordId) {
                            $q2->orWhere('vendedor_id', (int) $this->vendedorModalRecordId);
                        }
                    });

                if ($atualId) {
                    $q->orWhere('id', $atualId);
                }
            })
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome'])
            ->mapWithKeys(fn (RhFuncionario $f): array => [
                $f->id => trim($f->codigo.' — '.$f->nome),
            ])
            ->all();
    }

    /**
     * Vincula (ou desvincula) o usuário ao colaborador via users.vendedor_id,
     * garantindo que apenas um usuário aponte para este colaborador.
     */
    protected function syncVendedorUsuario(int $vendedorId, ?int $usuarioId): void
    {
        User::query()
            ->where('vendedor_id', $vendedorId)
            ->when($usuarioId !== null, fn ($query) => $query->where('id', '!=', $usuarioId))
            ->update(['vendedor_id' => null]);

        if ($usuarioId !== null) {
            User::query()->whereKey($usuarioId)->update(['vendedor_id' => $vendedorId]);
        }
    }

    /**
     * Espelha empresas/caixas do usuário (Permissões) no vínculo do operador.
     * Evita conflito com os campos antigos do formulário.
     *
     * @return array<int, array{caixa_conta_id: int|null}>
     */
    protected function empresaVendedorSyncFromUsuario(?int $usuarioId): array
    {
        if (! $usuarioId) {
            return [];
        }

        $user = User::query()->find($usuarioId);

        if (! $user) {
            return [];
        }

        $empresaIds = $user->accessibleEmpresaIds();

        if ($empresaIds === []) {
            return [];
        }

        // Preferência: empresa padrão do usuário primeiro.
        if (filled($user->empresa_id)) {
            $padrao = (int) $user->empresa_id;
            $empresaIds = array_values(array_unique([
                $padrao,
                ...array_filter($empresaIds, static fn (int $id): bool => $id !== $padrao),
            ]));
        }

        $payload = [];
        foreach ($empresaIds as $empresaId) {
            $caixaId = $user->defaultCaixaContaId($empresaId);
            $payload[(int) $empresaId] = [
                'caixa_conta_id' => $caixaId && $caixaId > 0 ? $caixaId : null,
            ];
        }

        return $payload;
    }

    /**
     * Terminais/PDVs disponíveis para liberar ao colaborador.
     *
     * @return array<int|string, string>
     */
    public function terminalOptions(): array
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
    public function tabelaVendaOptions(): array
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
        $query = PriceTable::query()
            ->when(
                Schema::hasColumn('price_tables', 'ativo'),
                fn ($q) => $q->where('ativo', true)
            );

        $id = (clone $query)->where('codigo', '1')->value('id')
            ?? (clone $query)->orderByRaw('CAST(codigo AS UNSIGNED) ASC')->value('id');

        return $id ? (string) $id : '';
    }

    /**
     * @return array<int|string, string>
     */
    public function usuarioOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Cargos cadastrados no Mini RH (RH → Cargos).
     *
     * @return list<string>
     */
    public function cargoOptions(): array
    {
        if (! Schema::hasTable('rh_cargos')) {
            return [];
        }

        $options = RhCargo::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome')
            ->map(fn ($nome): string => mb_strtoupper(trim((string) $nome), 'UTF-8'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $atual = mb_strtoupper(trim((string) ($this->vendedorForm['cargo'] ?? '')), 'UTF-8');
        if ($atual !== '' && ! in_array($atual, $options, true)) {
            array_unshift($options, $atual);
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    public function estoqueOptions(): array
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
     * @return array<int, string>
     */
    public function tipoSalarioOptions(): array
    {
        return ['MENSALISTA', 'HORISTA', 'COMISSIONADO', 'DIARISTA', 'AUTÔNOMO'];
    }

    /**
     * @return array<int, string>
     */
    public function logradouroOptions(): array
    {
        return ['RUA', 'AVENIDA', 'TRAVESSA', 'RODOVIA', 'ALAMEDA', 'PRAÇA', 'ESTRADA', 'LOTEAMENTO'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultVendedorFormData(): array
    {
        return [
            'codigo' => Vendedor::nextCodigo(),
            'rh_funcionario_id' => '',
            'nome' => '',
            'ativo' => 'S',
            'terminais' => [],
            'cargo' => '',
            'cpf' => '',
            'rg' => '',
            'pis_pasep' => '',
            'data_nascimento' => '',
            'cep' => '',
            'logradouro' => '',
            'endereco' => '',
            'numero' => '',
            'bairro' => '',
            'complemento' => '',
            'cidade_codigo' => '',
            'cidade_nome' => '',
            'uf' => '',
            'telefone' => '',
            'whatsapp' => '',
            'email' => '',
            'ctps' => '',
            'admissao' => '',
            'demissao' => '',
            'tipo_salario' => '',
            'salario' => '0,00',
            'inss' => '',
            'estoque' => '',
            'estoque_id' => '',
            'usar_agendamento' => false,
            'usuario_id' => '',
            'setor_vendas' => true,
            'tabela_venda_id' => $this->defaultTabelaVendaId(),
            'comissao_av' => '0,00',
            'comissao_ap' => '0,00',
            'ganha_comissao_todas_vendas' => false,
            'mobile_meta_venda' => '0,00',
            'setor_servicos' => false,
            'comissao_servico' => '0,00',
            'ganha_comissao_todos_servicos' => false,
            'efetua_venda' => true,
            'motorista' => false,
            'ajudante' => false,
            'observacoes' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function vendedorFormDataFromRecord(Vendedor $record): array
    {
        $rhId = Schema::hasTable('rh_funcionarios')
            ? RhFuncionario::query()->where('vendedor_id', $record->getKey())->value('id')
            : null;

        return [
            'codigo' => (string) $record->codigo,
            'rh_funcionario_id' => $rhId ? (string) $rhId : '',
            'nome' => (string) $record->nome,
            'ativo' => $record->ativo ? 'S' : 'N',
            'terminais' => $record->terminais->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'cargo' => (string) $record->cargo,
            'cpf' => (string) $record->cpf,
            'rg' => (string) $record->rg,
            'pis_pasep' => (string) $record->pis_pasep,
            'data_nascimento' => optional($record->data_nascimento)->format('Y-m-d') ?? '',
            'cep' => (string) $record->cep,
            'logradouro' => (string) $record->logradouro,
            'endereco' => (string) $record->endereco,
            'numero' => (string) $record->numero,
            'bairro' => (string) $record->bairro,
            'complemento' => (string) $record->complemento,
            'cidade_codigo' => (string) $record->cidade_codigo,
            'cidade_nome' => (string) $record->cidade_nome,
            'uf' => (string) $record->uf,
            'telefone' => (string) $record->telefone,
            'whatsapp' => (string) $record->whatsapp,
            'email' => (string) $record->email,
            'ctps' => (string) $record->ctps,
            'admissao' => optional($record->admissao)->format('Y-m-d') ?? '',
            'demissao' => optional($record->demissao)->format('Y-m-d') ?? '',
            'tipo_salario' => (string) $record->tipo_salario,
            'salario' => $this->formatVendedorComissao($record->salario),
            'inss' => (string) $record->inss,
            'estoque' => (string) $record->estoque,
            'estoque_id' => $record->estoque_id ? (string) $record->estoque_id : '',
            'usar_agendamento' => (bool) $record->usar_agendamento,
            'usuario_id' => optional($record->usuario)->id ? (string) $record->usuario->id : '',
            'setor_vendas' => (bool) $record->setor_vendas,
            'tabela_venda_id' => $record->tabela_venda_id ? (string) $record->tabela_venda_id : '',
            'comissao_av' => $this->formatVendedorComissao($record->comissao_av),
            'comissao_ap' => $this->formatVendedorComissao($record->comissao_ap),
            'ganha_comissao_todas_vendas' => (bool) $record->ganha_comissao_todas_vendas,
            'mobile_meta_venda' => $this->formatVendedorComissao($record->mobile_meta_venda),
            'setor_servicos' => (bool) $record->setor_servicos,
            'comissao_servico' => $this->formatVendedorComissao($record->comissao_servico),
            'ganha_comissao_todos_servicos' => (bool) $record->ganha_comissao_todos_servicos,
            'efetua_venda' => (bool) $record->efetua_venda,
            'motorista' => (bool) $record->motorista,
            'ajudante' => (bool) $record->ajudante,
            'observacoes' => (string) $record->observacoes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeVendedorFormData(array $data): array
    {
        $email = trim((string) ($data['email'] ?? ''));
        unset(
            $data['usuario_id'],
            $data['rh_funcionario_id'],
            $data['terminais']
        );

        $data = ErpUppercase::normalizeFormData($data);

        $data['codigo'] = trim((string) ($data['codigo'] ?? ''));
        $data['nome'] = trim((string) ($data['nome'] ?? ''));
        $data['ativo'] = strtoupper(trim((string) ($data['ativo'] ?? 'S'))) === 'S';
        $data['email'] = $email !== '' ? ErpUppercase::uppercase($email) : null;

        $data['tabela_venda_id'] = ($data['tabela_venda_id'] ?? '') !== '' ? (int) $data['tabela_venda_id'] : null;
        $data['estoque_id'] = ($data['estoque_id'] ?? '') !== '' ? (int) $data['estoque_id'] : null;

        foreach (['data_nascimento', 'admissao', 'demissao'] as $dateField) {
            $data[$dateField] = ($data[$dateField] ?? '') !== '' ? $data[$dateField] : null;
        }

        foreach (['comissao_av', 'comissao_ap', 'comissao_servico', 'salario', 'mobile_meta_venda'] as $money) {
            $data[$money] = $this->parseVendedorComissao($data[$money] ?? 0);
        }

        foreach ([
            'usar_agendamento', 'setor_vendas', 'ganha_comissao_todas_vendas',
            'setor_servicos', 'ganha_comissao_todos_servicos', 'efetua_venda',
            'motorista', 'ajudante',
        ] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        return $data;
    }

    protected function formatVendedorComissao(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    protected function parseVendedorComissao(mixed $value): float
    {
        $normalized = str_replace(['.', ' '], '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return round((float) $normalized, 2);
    }
}
