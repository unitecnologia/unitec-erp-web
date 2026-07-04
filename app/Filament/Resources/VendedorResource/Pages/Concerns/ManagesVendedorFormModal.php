<?php

namespace App\Filament\Resources\VendedorResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\PriceTable;
use App\Models\User;
use App\Models\Vendedor;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
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
    }

    public function editVendedor(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = Vendedor::query()->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()
                ->title('Colaborador não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->vendedorModalRecordId = $record->getKey();
        $this->vendedorForm = $this->vendedorFormDataFromRecord($record);
        $this->vendedorModalOpen = true;
    }

    public function closeVendedorModal(): void
    {
        $this->vendedorModalOpen = false;
        $this->vendedorModalRecordId = null;
        $this->vendedorForm = [];
    }

    public function saveVendedor(): void
    {
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
                'vendedorForm.nome' => ['required', 'string', 'max:120'],
                'vendedorForm.cpf' => ['nullable', 'string', 'max:20', new DocumentoBrasileiroValido(cpfOnly: true)],
                'vendedorForm.ativo' => ['required', 'in:S,N'],
                'vendedorForm.empresas' => ['array'],
                'vendedorForm.empresas.*' => ['integer', Rule::exists('empresas', 'id')],
                'vendedorForm.tabela_venda_id' => ['nullable', 'integer', Rule::exists('price_tables', 'id')],
                'vendedorForm.usuario_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
                'vendedorForm.email' => ['nullable', 'email', 'max:120'],
                'vendedorForm.comissao_av' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'vendedorForm.comissao_ap' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'vendedorForm.comissao_servico' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            ],
            [],
            [
                'vendedorForm.codigo' => 'código',
                'vendedorForm.nome' => 'nome',
                'vendedorForm.cpf' => 'CPF',
                'vendedorForm.ativo' => 'ativo',
                'vendedorForm.empresas' => 'empresas',
                'vendedorForm.tabela_venda_id' => 'tabela de venda',
                'vendedorForm.usuario_id' => 'usuário',
                'vendedorForm.email' => 'e-mail',
                'vendedorForm.comissao_av' => 'comissão AV',
                'vendedorForm.comissao_ap' => 'comissão AP',
                'vendedorForm.comissao_servico' => 'comissão de serviço',
            ],
        );

        $usuarioId = $this->vendedorForm['usuario_id'] ?? null;
        $usuarioId = $usuarioId !== null && $usuarioId !== '' ? (int) $usuarioId : null;

        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($this->vendedorForm['empresas'] ?? []))
        )));

        $data = $this->normalizeVendedorFormData($this->vendedorForm);
        $data['empresa_id'] = $empresaIds[0] ?? null;

        if ($this->vendedorModalRecordId) {
            $record = Vendedor::query()->find($this->vendedorModalRecordId);

            if (! $record) {
                Notification::make()
                    ->title('Colaborador não encontrado.')
                    ->warning()
                    ->send();

                return;
            }

            $record->update($data);

            Notification::make()
                ->title('Colaborador alterado.')
                ->success()
                ->send();
        } else {
            $record = Vendedor::query()->create($data);

            Notification::make()
                ->title('Colaborador incluído.')
                ->success()
                ->send();
        }

        $record->empresas()->sync($empresaIds);

        $this->syncVendedorUsuario((int) $record->getKey(), $usuarioId);

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
     * @return array<int|string, string>
     */
    public function empresaOptions(): array
    {
        return Empresa::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function empresaCodigos(): array
    {
        return Empresa::query()
            ->orderBy('codigo')
            ->pluck('codigo', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function tabelaVendaOptions(): array
    {
        return PriceTable::query()
            ->orderBy('descricao')
            ->get(['id', 'codigo', 'descricao'])
            ->mapWithKeys(fn (PriceTable $t): array => [
                $t->id => trim(($t->codigo ? $t->codigo.' - ' : '').(string) $t->descricao),
            ])
            ->all();
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
            'nome' => '',
            'ativo' => 'S',
            'empresas' => [],
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
            'usar_agendamento' => false,
            'usuario_id' => '',
            'setor_vendas' => true,
            'tabela_venda_id' => '',
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
        return [
            'codigo' => (string) $record->codigo,
            'nome' => (string) $record->nome,
            'ativo' => $record->ativo ? 'S' : 'N',
            'empresas' => $record->empresas->pluck('id')->map(fn ($id): int => (int) $id)->all(),
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
        unset($data['usuario_id'], $data['empresas']);

        $data = ErpUppercase::normalizeFormData($data);

        $data['codigo'] = trim((string) ($data['codigo'] ?? ''));
        $data['nome'] = trim((string) ($data['nome'] ?? ''));
        $data['ativo'] = strtoupper(trim((string) ($data['ativo'] ?? 'S'))) === 'S';
        $data['email'] = $email !== '' ? mb_strtolower($email, 'UTF-8') : null;

        $data['tabela_venda_id'] = ($data['tabela_venda_id'] ?? '') !== '' ? (int) $data['tabela_venda_id'] : null;

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
