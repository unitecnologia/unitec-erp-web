<?php

namespace App\Filament\Resources\ContaReceberResource\Pages\Concerns;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\ContaReceber;
use App\Models\PlanoConta;
use App\Models\Person;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaReceberCadastroService;
use App\Support\Erp\Financeiro\ContaReceberExclusaoService;
use App\Support\Erp\Financeiro\ContaReceberJurosCarteira;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

trait ManagesContaReceberFormModal
{
    public bool $contaFormModalOpen = false;

    public ?int $contaFormRecordId = null;

    public string $contaFormNumero = '';

    public string $contaFormEmissao = '';

    public string $contaFormForma = ContaReceber::FORMA_CARTEIRA;

    public string $contaFormDocumento = '';

    public string $contaFormEmpresa = '';

    public string $contaFormClienteId = '';

    public string $contaFormClienteBusca = '';

    public bool $contaFormClienteLookupOpen = false;

    /** @var array<int, array{id: int, codigo: string, nome: string, cpf_cnpj: string}> */
    public array $contaFormClienteResults = [];

    public ?int $contaFormClienteIndex = null;

    public string $contaFormVencimento = '';

    public string $contaFormVencimentoOriginal = '';

    public string $contaFormHistorico = '';

    public string $contaFormPlanoContaId = '';

    /** @var list<array{id: int, label: string}> */
    public array $contaFormPlanosOptions = [];

    public string $contaFormValor = '0,00';

    public string $contaFormParcelas = '1';

    public string $contaFormJurosDiarioPct = '0,00';

    public string $contaFormCarenciaJurosDias = '0';

    public string $contaFormMultaPct = '0,00';

    /** CR de pedido: formulário abre, mas só o vencimento é editável. */
    public bool $contaFormSomenteVencimento = false;

    /** Quando true, o save pode sincronizar vencimento no banco (mostra progresso). */
    public bool $contaFormExpectBoletoSync = false;

    public string $contaFormBoletoBancoNome = '';

    public bool $contaBoletoVencimentoSucessoOpen = false;

    public string $contaBoletoVencimentoSucessoDetalhe = '';

    public bool $contaBoletoVencimentoErroOpen = false;

    public string $contaBoletoVencimentoErroTitulo = '';

    public string $contaBoletoVencimentoErroMensagem = '';

    public function createConta(): void
    {
        if ($this->contaFormModalOpen) {
            return;
        }

        $this->fillContaFormForCreate();
        $this->resetErrorBag();
        $this->contaFormModalOpen = true;
        $this->dispatch('erp-masks-refresh');
    }

    public function editConta(): void
    {
        if ($this->contaFormModalOpen) {
            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $conta = ContaReceber::query()
            ->with('cliente')
            ->whereKey((int) $this->highlightedRecordId)
            ->first();

        if (! $conta) {
            Notification::make()
                ->title('Conta não encontrada.')
                ->warning()
                ->send();

            return;
        }

        if ((float) $conta->valor_recebido > 0) {
            Notification::make()
                ->title('Conta já possui baixa')
                ->body('Estorne o recebimento antes de alterar o título.')
                ->warning()
                ->send();

            return;
        }

        $exclusao = app(ContaReceberExclusaoService::class);

        if (! $exclusao->podeAlterar($conta)) {
            Notification::make()
                ->title('Não é possível alterar')
                ->body($exclusao->motivoBloqueioAlteracao($conta) ?? 'Esta conta não é um lançamento avulso.')
                ->warning()
                ->send();

            return;
        }

        $this->fillContaFormFromRecord($conta, $exclusao->podeAlterarSomenteVencimento($conta));
        $this->resetErrorBag();
        $this->contaFormModalOpen = true;
        $this->dispatch('erp-masks-refresh');
    }

    public function closeContaFormModal(): void
    {
        $this->contaFormModalOpen = false;
        $this->contaFormRecordId = null;
        $this->contaFormSomenteVencimento = false;
        $this->contaFormVencimentoOriginal = '';
        $this->contaFormExpectBoletoSync = false;
        $this->contaFormBoletoBancoNome = '';
        $this->closeContaFormClienteLookup();
        $this->resetErrorBag();
    }

    public function updatedContaFormVencimento(): void
    {
        $this->refreshContaFormExpectBoletoSync();
    }

    public function updatedContaFormForma(): void
    {
        if (mb_strtolower(trim($this->contaFormForma), 'UTF-8') !== ContaReceber::FORMA_CARTEIRA) {
            return;
        }

        // Se ainda zerado, aplica padrão da empresa ao mudar para Carteira.
        $pct = ErpMoney::parseBr($this->contaFormJurosDiarioPct);
        $carencia = (int) $this->contaFormCarenciaJurosDias;
        if ($pct > 0 || $carencia > 0) {
            return;
        }

        $defaults = ContaReceberJurosCarteira::defaultsDaEmpresa();
        $this->contaFormJurosDiarioPct = number_format($defaults['juros_diario_pct'], 2, ',', '.');
        $this->contaFormCarenciaJurosDias = (string) $defaults['carencia_juros_dias'];
        $this->contaFormMultaPct = number_format($defaults['multa_pct'], 2, ',', '.');
    }

    public function acknowledgeContaBoletoVencimentoSucesso(): void
    {
        $this->contaBoletoVencimentoSucessoOpen = false;
        $this->contaBoletoVencimentoSucessoDetalhe = '';
        $this->contaFormModalOpen = false;
    }

    public function closeContaBoletoVencimentoErro(): void
    {
        $this->contaBoletoVencimentoErroOpen = false;
        $this->contaBoletoVencimentoErroTitulo = '';
        $this->contaBoletoVencimentoErroMensagem = '';
    }

    public function handleContaFormEscape(): void
    {
        if ($this->contaFormClienteLookupOpen) {
            $this->closeContaFormClienteLookup();

            return;
        }

        $this->closeContaFormModal();
    }

    public function updatedContaFormClienteBusca(): void
    {
        if ($this->contaFormSomenteVencimento) {
            return;
        }

        $upper = mb_strtoupper(trim($this->contaFormClienteBusca), 'UTF-8');
        $this->contaFormClienteBusca = $upper;
        $this->contaFormClienteId = '';
        $this->contaFormClienteLookupOpen = true;
        $this->refreshContaFormClienteResults();
    }

    public function openContaFormClienteLookup(): void
    {
        if ($this->contaFormSomenteVencimento) {
            return;
        }

        $this->contaFormClienteLookupOpen = true;

        if (filled(trim($this->contaFormClienteBusca))) {
            $this->refreshContaFormClienteResults();
        }
    }

    public function refreshContaFormClienteResults(): void
    {
        $term = trim($this->contaFormClienteBusca);

        if ($term === '') {
            $this->contaFormClienteResults = [];
            $this->contaFormClienteIndex = null;

            return;
        }

        $this->contaFormClienteResults = $this->searchContaFormClientes($term);
        $this->contaFormClienteIndex = $this->contaFormClienteResults === [] ? null : 0;
    }

    /**
     * @return array<int, array{id: int, codigo: string, nome: string, cpf_cnpj: string}>
     */
    protected function searchContaFormClientes(string $term): array
    {
        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term) ?? '';

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true)
            ->where(function ($sub) use ($like, $digits): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        ['%'.$digits.'%']
                    );
                }
            });

        return $query
            ->orderBy('nome_razao')
            ->limit(50)
            ->get()
            ->map(fn (Person $person): array => [
                'id' => (int) $person->id,
                'codigo' => (string) ($person->codigo ?? ''),
                'nome' => mb_strtoupper((string) $person->nome_razao, 'UTF-8'),
                'cpf_cnpj' => (string) ($person->cpf_cnpj ?? ''),
            ])
            ->all();
    }

    public function moveContaFormClienteSelection(int $delta): void
    {
        if ($this->contaFormClienteResults === []) {
            return;
        }

        $index = ($this->contaFormClienteIndex ?? 0) + $delta;
        $count = count($this->contaFormClienteResults);
        $this->contaFormClienteIndex = max(0, min($count - 1, $index));
    }

    public function highlightContaFormClienteResult(int $index): void
    {
        if (! isset($this->contaFormClienteResults[$index])) {
            return;
        }

        $this->contaFormClienteIndex = $index;
    }

    public function selectContaFormClienteResult(int $index): void
    {
        if (! isset($this->contaFormClienteResults[$index])) {
            return;
        }

        $this->contaFormClienteIndex = $index;
        $this->confirmContaFormClienteSelection();
    }

    public function confirmContaFormClienteSelection(): void
    {
        $index = $this->contaFormClienteIndex;

        if ($index === null || ! isset($this->contaFormClienteResults[$index])) {
            $this->contaFormClienteLookupOpen = false;

            return;
        }

        $row = $this->contaFormClienteResults[$index];
        $this->contaFormClienteId = (string) $row['id'];
        $this->contaFormClienteBusca = $row['nome'];
        $this->resetErrorBag('contaFormClienteId');
        $this->closeContaFormClienteLookup();
    }

    public function handleContaFormClienteEnter(): void
    {
        if (! $this->contaFormClienteLookupOpen) {
            return;
        }

        if ($this->contaFormClienteResults === []) {
            $this->contaFormClienteLookupOpen = false;

            return;
        }

        $this->confirmContaFormClienteSelection();
    }

    public function closeContaFormClienteLookup(): void
    {
        $this->contaFormClienteLookupOpen = false;
        $this->contaFormClienteResults = [];
        $this->contaFormClienteIndex = null;
    }

    public function salvarContaForm(): void
    {
        $tipos = implode(',', array_keys(ContaReceberCadastroService::tiposAvulso()));
        $somenteVencimento = $this->contaFormSomenteVencimento && $this->contaFormRecordId !== null;

        if ($somenteVencimento) {
            $this->validate(
                [
                    'contaFormVencimento' => ['required', 'date'],
                    'contaFormForma' => ['required', 'in:'.$tipos],
                    'contaFormJurosDiarioPct' => ['nullable', 'string'],
                    'contaFormCarenciaJurosDias' => ['nullable', 'integer', 'min:0', 'max:3650'],
                    'contaFormMultaPct' => ['nullable', 'string'],
                ],
                [
                    'contaFormVencimento.required' => 'Informe o vencimento.',
                    'contaFormForma.in' => 'Tipo inválido.',
                ],
                [
                    'contaFormVencimento' => 'vencimento',
                    'contaFormForma' => 'tipo',
                    'contaFormJurosDiarioPct' => '% juros diário',
                    'contaFormCarenciaJurosDias' => 'carência juros',
                ],
            );
        } else {
            $rules = [
                'contaFormEmissao' => ['required', 'date'],
                'contaFormForma' => ['required', 'in:'.$tipos],
                'contaFormDocumento' => ['nullable', 'string', 'max:40'],
                'contaFormClienteId' => ['required', 'integer', 'exists:people,id'],
                'contaFormVencimento' => ['required', 'date'],
                'contaFormHistorico' => ['nullable', 'string', 'max:500'],
                'contaFormValor' => ['required', 'string'],
                'contaFormJurosDiarioPct' => ['nullable', 'string'],
                'contaFormCarenciaJurosDias' => ['nullable', 'integer', 'min:0', 'max:3650'],
                'contaFormMultaPct' => ['nullable', 'string'],
            ];

            if ($this->contaFormRecordId === null) {
                $rules['contaFormParcelas'] = ['required', 'integer', 'min:1', 'max:120'];
                $rules['contaFormPlanoContaId'] = [
                    'required',
                    'integer',
                    Rule::exists('planos_contas', 'id')->where(fn ($query) => $query->where('dc', 'C')->where('ativo', true)),
                ];
            }

            $this->validate(
                $rules,
                [
                    'contaFormClienteId.required' => 'Selecione o cliente.',
                    'contaFormPlanoContaId.required' => 'Selecione o plano de contas.',
                    'contaFormPlanoContaId.exists' => 'Selecione um plano de crédito.',
                    'contaFormEmissao.required' => 'Informe a emissão.',
                    'contaFormVencimento.required' => 'Informe o vencimento.',
                    'contaFormForma.in' => 'Tipo inválido.',
                ],
                [
                    'contaFormEmissao' => 'emissão',
                    'contaFormForma' => 'tipo',
                    'contaFormDocumento' => 'documento',
                    'contaFormClienteId' => 'cliente',
                    'contaFormVencimento' => 'vencimento',
                    'contaFormHistorico' => 'histórico',
                    'contaFormPlanoContaId' => 'plano de contas',
                    'contaFormValor' => 'valor',
                    'contaFormParcelas' => 'repetir por',
                ],
            );
        }

        $valor = $somenteVencimento ? 0.0 : ErpMoney::parseBr($this->contaFormValor);

        if (! $somenteVencimento && $valor <= 0) {
            Notification::make()
                ->title('Informe um valor maior que zero.')
                ->warning()
                ->send();

            return;
        }

        $this->refreshContaFormExpectBoletoSync();

        try {
            $boletosVencimentoApi = 0;
            if ($this->contaFormRecordId !== null) {
                $payload = $somenteVencimento
                    ? [
                        'vencimento' => $this->contaFormVencimento,
                        'forma' => $this->contaFormForma,
                        'juros_diario_pct' => $this->contaFormJurosDiarioPct,
                        'carencia_juros_dias' => $this->contaFormCarenciaJurosDias,
                        'multa_pct' => $this->contaFormMultaPct,
                    ]
                    : [
                        'emissao' => $this->contaFormEmissao,
                        'documento' => $this->contaFormDocumento,
                        'cliente_id' => (int) $this->contaFormClienteId,
                        'vencimento' => $this->contaFormVencimento,
                        'historico' => $this->contaFormHistorico,
                        'valor' => $valor,
                        'forma' => $this->contaFormForma,
                        'juros_diario_pct' => $this->contaFormJurosDiarioPct,
                        'carencia_juros_dias' => $this->contaFormCarenciaJurosDias,
                        'multa_pct' => $this->contaFormMultaPct,
                    ];
                $resultado = app(ContaReceberCadastroService::class)->atualizar(
                    (int) $this->contaFormRecordId,
                    $payload,
                );
                $boletosVencimentoApi = (int) ($resultado['boletos_vencimento_api'] ?? 0);
                $mensagem = $somenteVencimento ? 'Vencimento alterado.' : 'Conta alterada.';
            } else {
                $criadas = app(ContaReceberCadastroService::class)->criar([
                    'emissao' => $this->contaFormEmissao,
                    'documento' => $this->contaFormDocumento,
                    'cliente_id' => (int) $this->contaFormClienteId,
                    'vencimento' => $this->contaFormVencimento,
                    'historico' => $this->contaFormHistorico,
                    'valor' => $valor,
                    'forma' => $this->contaFormForma,
                    'parcelas' => (int) $this->contaFormParcelas,
                    'plano_conta_id' => (int) $this->contaFormPlanoContaId,
                    'juros_diario_pct' => $this->contaFormJurosDiarioPct,
                    'carencia_juros_dias' => $this->contaFormCarenciaJurosDias,
                    'multa_pct' => $this->contaFormMultaPct,
                ]);
                $qtd = count($criadas);
                $mensagem = $qtd === 1 ? 'Conta cadastrada.' : "{$qtd} parcelas cadastradas.";
            }
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if ($this->contaFormExpectBoletoSync && str_contains($msg, 'vencimento no banco')) {
                $this->contaBoletoVencimentoErroOpen = true;
                $this->contaBoletoVencimentoErroTitulo = 'Falha ao atualizar vencimento no banco';
                $this->contaBoletoVencimentoErroMensagem = $msg;

                return;
            }

            Notification::make()
                ->title($msg)
                ->warning()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Não foi possível salvar a conta.')
                ->danger()
                ->send();

            return;
        }

        $bancoParaSucesso = $this->contaFormBoletoBancoNome;
        $vencimentoParaSucesso = $this->contaFormVencimento;

        $this->closeContaFormModal();
        $this->situacaoFilter = 'a_receber';
        $this->clearListSelection();

        if ($boletosVencimentoApi > 0) {
            $banco = $bancoParaSucesso !== '' ? $bancoParaSucesso : 'banco';
            $dataBr = $this->formatContaFormVencimentoBr($vencimentoParaSucesso);
            $qtdLabel = $boletosVencimentoApi === 1
                ? '1 boleto'
                : $boletosVencimentoApi.' boletos';

            $this->contaBoletoVencimentoSucessoDetalhe = mb_strtoupper(
                $banco.' · '.$qtdLabel.' · novo vencimento '.$dataBr,
                'UTF-8'
            );
            $this->contaBoletoVencimentoSucessoOpen = true;
            // Precisa redesenhar a página: resetTable() usava skipRender e o OK sumia.
            $this->pushContaReceberListRefresh(skipPageRender: false);

            return;
        }

        $this->pushContaReceberListRefresh(skipPageRender: false);

        Notification::make()
            ->title($mensagem)
            ->success()
            ->send();
    }

    protected function fillContaFormForCreate(): void
    {
        $hoje = ErpTimezone::toLocal()->toDateString();

        $this->contaFormRecordId = null;
        $this->contaFormNumero = ContaReceber::nextNumero();
        $this->contaFormEmissao = $hoje;
        $this->contaFormForma = ContaReceber::FORMA_CARTEIRA;
        $this->contaFormDocumento = '';
        $this->contaFormEmpresa = $this->contaFormEmpresaAtual();
        $this->contaFormClienteId = '';
        $this->contaFormClienteBusca = '';
        $this->closeContaFormClienteLookup();
        $this->contaFormVencimento = $hoje;
        $this->contaFormVencimentoOriginal = '';
        $this->contaFormHistorico = '';
        $this->contaFormPlanoContaId = '';
        $this->contaFormPlanosOptions = $this->planosCreditoOptions();
        $this->contaFormValor = '0,00';
        $this->contaFormParcelas = '1';
        $defaults = ContaReceberJurosCarteira::defaultsDaEmpresa();
        $this->contaFormJurosDiarioPct = number_format($defaults['juros_diario_pct'], 2, ',', '.');
        $this->contaFormCarenciaJurosDias = (string) $defaults['carencia_juros_dias'];
        $this->contaFormMultaPct = number_format($defaults['multa_pct'], 2, ',', '.');
        $this->contaFormSomenteVencimento = false;
        $this->contaFormExpectBoletoSync = false;
        $this->contaFormBoletoBancoNome = '';
    }

    protected function fillContaFormFromRecord(ContaReceber $conta, bool $somenteVencimento = false): void
    {
        $this->contaFormRecordId = (int) $conta->id;
        $this->contaFormSomenteVencimento = $somenteVencimento;
        $this->contaFormNumero = (string) ($conta->numero ?? '');
        $this->contaFormEmissao = optional($conta->emissao)?->format('Y-m-d') ?? ErpTimezone::toLocal()->toDateString();
        $forma = mb_strtolower(trim((string) ($conta->forma ?? '')), 'UTF-8');
        $this->contaFormForma = array_key_exists($forma, ContaReceberCadastroService::tiposAvulso())
            ? $forma
            : ContaReceber::FORMA_CARTEIRA;
        $this->contaFormDocumento = (string) ($conta->documento ?? '');
        $this->contaFormEmpresa = $this->contaFormEmpresaAtual();
        $this->contaFormClienteId = (string) ($conta->cliente_id ?? '');
        $this->contaFormClienteBusca = mb_strtoupper(trim((string) ($conta->cliente?->nome_razao ?? '')), 'UTF-8');
        $this->closeContaFormClienteLookup();
        $vencimento = optional($conta->vencimento)?->format('Y-m-d') ?? ErpTimezone::toLocal()->toDateString();
        $this->contaFormVencimento = $vencimento;
        $this->contaFormVencimentoOriginal = $vencimento;
        $this->contaFormHistorico = (string) ($conta->historico ?? '');
        $this->contaFormPlanosOptions = $this->planosCreditoOptions();
        $this->contaFormPlanoContaId = filled($conta->plano_conta_id) ? (string) $conta->plano_conta_id : '';
        $this->contaFormValor = ErpMoney::formatBr((float) $conta->valor);
        $this->contaFormParcelas = '1';
        $pct = round((float) ($conta->juros_diario_pct ?? 0), 4);
        $carencia = max(0, (int) ($conta->carencia_juros_dias ?? 0));
        if ($pct <= 0 && $carencia <= 0 && $this->contaFormForma === ContaReceber::FORMA_CARTEIRA) {
            $defaults = ContaReceberJurosCarteira::defaultsDaEmpresa(
                $conta->empresa_id ? (int) $conta->empresa_id : null
            );
            $pct = $defaults['juros_diario_pct'];
            $carencia = $defaults['carencia_juros_dias'];
        }
        $this->contaFormJurosDiarioPct = number_format($pct, 2, ',', '.');
        $this->contaFormCarenciaJurosDias = (string) $carencia;
        $this->contaFormMultaPct = number_format((float) ($conta->multa_pct ?? 0), 2, ',', '.');
        $this->hydrateContaFormBoletoSyncContext((int) $conta->id);
        $this->refreshContaFormExpectBoletoSync();
    }

    protected function hydrateContaFormBoletoSyncContext(int $contaId): void
    {
        $this->contaFormBoletoBancoNome = '';

        $boleto = Boleto::query()
            ->where('conta_receber_id', $contaId)
            ->where('status', Boleto::STATUS_ABERTO)
            ->with('boletoContaApi')
            ->orderBy('id')
            ->first();

        if (! $boleto instanceof Boleto) {
            return;
        }

        $contaApi = $boleto->boletoContaApi;
        if (! $contaApi instanceof BoletoContaApi && $boleto->boleto_conta_api_id) {
            $contaApi = BoletoContaApi::query()->find($boleto->boleto_conta_api_id);
        }

        if ($contaApi instanceof BoletoContaApi) {
            $compe = $contaApi->bancoCompe();
            $this->contaFormBoletoBancoNome = match ($compe) {
                EmpresaParametros::BOLETO_BANCO_AILOS => 'Ailos',
                EmpresaParametros::BOLETO_BANCO_SICREDI => 'Sicredi',
                default => 'Banco',
            };

            return;
        }

        $this->contaFormBoletoBancoNome = 'Banco';
    }

    protected function refreshContaFormExpectBoletoSync(): void
    {
        $this->contaFormExpectBoletoSync = $this->contaFormRecordId !== null
            && $this->contaFormBoletoBancoNome !== ''
            && trim($this->contaFormVencimento) !== ''
            && trim($this->contaFormVencimentoOriginal) !== ''
            && trim($this->contaFormVencimento) !== trim($this->contaFormVencimentoOriginal);
    }

    protected function formatContaFormVencimentoBr(string $ymd): string
    {
        $ymd = trim($ymd);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
            return $ymd;
        }

        return substr($ymd, 8, 2).'/'.substr($ymd, 5, 2).'/'.substr($ymd, 0, 4);
    }

    protected function contaFormEmpresaAtual(): string
    {
        $empresa = ErpContext::currentEmpresa();
        $empresaNome = trim((string) (
            $empresa?->fantasia
            ?: $empresa?->nome
            ?: $empresa?->razao_social
            ?: ''
        ));

        return $empresaNome !== '' ? mb_strtoupper($empresaNome, 'UTF-8') : '—';
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    protected function planosCreditoOptions(): array
    {
        return PlanoConta::query()
            ->where('ativo', true)
            ->where('dc', 'C')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao'])
            ->map(fn (PlanoConta $plano): array => [
                'id' => (int) $plano->id,
                'label' => trim((string) $plano->codigo).' — '.mb_strtoupper((string) $plano->descricao, 'UTF-8'),
            ])
            ->values()
            ->all();
    }
}
