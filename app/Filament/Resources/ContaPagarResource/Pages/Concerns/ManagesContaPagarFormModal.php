<?php

namespace App\Filament\Resources\ContaPagarResource\Pages\Concerns;

use App\Models\ContaPagar;
use App\Models\Person;
use App\Models\PlanoConta;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaPagarCadastroService;
use App\Support\Erp\Financeiro\ContaPagarCompetencia;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

trait ManagesContaPagarFormModal
{
    use ManagesContaPagarFornecedorRapido;

    public bool $contaFormModalOpen = false;

    public ?int $contaFormRecordId = null;

    public string $contaFormNumero = '';

    public string $contaFormEmissao = '';

    public string $contaFormDocumento = '';

    public string $contaFormEmpresa = '';

    public string $contaFormFornecedorId = '';

    public string $contaFormFornecedorBusca = '';

    public bool $contaFormFornecedorLookupOpen = false;

    /** @var array<int, array{id: int, codigo: string, nome: string, cpf_cnpj: string}> */
    public array $contaFormFornecedorResults = [];

    public ?int $contaFormFornecedorIndex = null;

    public string $contaFormVencimento = '';

    /** Competência DRE (Y-m-d); independente de vencimento/pagamento. */
    public string $contaFormCompetencia = '';

    public string $contaFormPlanoContaId = '';

    /** Conta já baixada: formulário só permite competência/plano DRE. */
    public bool $contaFormSomenteDre = false;

    public string $contaFormHistorico = '';

    public string $contaFormValor = '0,00';

    public string $contaFormParcelas = '1';

    public string $contaFormIntervalo = '1';

    public string $contaFormIntervaloUnidade = ContaPagarCadastroService::INTERVALO_MESES;

    /**
     * Prévia editável das parcelas (inclusão).
     *
     * @var list<array{n: int, vencimento: string, valor_br: string}>
     */
    public array $contaFormParcelasPreview = [];

    public bool $contaFormParcelasManuais = false;

    public bool $contaFormSalvando = false;

    public function createConta(): void
    {
        if ($this->viewTab === 'desdobramentos') {
            return;
        }

        if ($this->contaFormModalOpen) {
            return;
        }

        $this->fillContaFormForCreate();
        $this->resetErrorBag();
        $this->contaFormModalOpen = true;
        $this->dispatch('erp-masks-refresh');
        $this->dispatch('erp-pagar-form-focus');
    }

    public function editConta(): void
    {
        if ($this->viewTab === 'desdobramentos') {
            return;
        }

        if ($this->contaFormModalOpen) {
            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $conta = ContaPagar::query()
            ->with('fornecedor:id,nome_razao')
            ->whereKey((int) $this->highlightedRecordId)
            ->first();

        if (! $conta) {
            Notification::make()
                ->title('Conta não encontrada.')
                ->warning()
                ->send();

            return;
        }

        // Título baixado: abre só para competência/plano DRE (valores financeiros travados).
        $this->fillContaFormFromRecord($conta);
        $this->resetErrorBag();
        $this->contaFormModalOpen = true;
        $this->dispatch('erp-masks-refresh');
        $this->dispatch('erp-pagar-form-focus');
    }

    public function closeContaFormModal(): void
    {
        if ($this->fornecedorRapidoModalOpen) {
            $this->closeFornecedorRapidoModal();

            return;
        }

        $this->contaFormModalOpen = false;
        $this->contaFormRecordId = null;
        $this->contaFormSalvando = false;
        $this->closeContaFormFornecedorLookup();
        $this->closeFornecedorRapidoModal();
        $this->resetErrorBag();
    }

    public function handleContaFormEscape(): void
    {
        if ($this->contaFormSalvando || $this->fornecedorRapidoSalvando) {
            return;
        }

        if ($this->fornecedorRapidoModalOpen) {
            $this->closeFornecedorRapidoModal();

            return;
        }

        if ($this->contaFormFornecedorLookupOpen) {
            $this->closeContaFormFornecedorLookup();

            return;
        }

        $this->contaFormModalOpen = false;
        $this->contaFormRecordId = null;
        $this->contaFormSalvando = false;
        $this->closeContaFormFornecedorLookup();
        $this->closeFornecedorRapidoModal();
        $this->resetErrorBag();
    }

    public function updatedContaFormFornecedorBusca(): void
    {
        $upper = mb_strtoupper(trim($this->contaFormFornecedorBusca), 'UTF-8');
        $this->contaFormFornecedorBusca = $upper;
        $this->contaFormFornecedorId = '';
        $this->contaFormFornecedorLookupOpen = true;
        $this->refreshContaFormFornecedorResults();
    }

    public function openContaFormFornecedorLookup(): void
    {
        $this->contaFormFornecedorLookupOpen = true;

        if (filled(trim($this->contaFormFornecedorBusca))) {
            $this->refreshContaFormFornecedorResults();
        }
    }

    public function refreshContaFormFornecedorResults(): void
    {
        $term = trim($this->contaFormFornecedorBusca);

        if ($term === '') {
            $this->contaFormFornecedorResults = [];
            $this->contaFormFornecedorIndex = null;

            return;
        }

        $this->contaFormFornecedorResults = $this->searchContaFormFornecedores($term);
        $this->contaFormFornecedorIndex = $this->contaFormFornecedorResults === [] ? null : 0;
    }

    /**
     * @return array<int, array{id: int, codigo: string, nome: string, cpf_cnpj: string}>
     */
    protected function searchContaFormFornecedores(string $term): array
    {
        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term) ?? '';

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->where(function ($sub) use ($like, $digits, $term): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        ['%'.$digits.'%']
                    );
                }

                if (ctype_digit($term)) {
                    $sub->orWhere('codigo', 'like', $like);
                }
            });

        return $query
            ->orderBy('nome_razao')
            ->limit(40)
            ->get(['id', 'codigo', 'nome_razao', 'cpf_cnpj'])
            ->map(fn (Person $person): array => [
                'id' => (int) $person->id,
                'codigo' => (string) ($person->codigo ?? ''),
                'nome' => mb_strtoupper((string) $person->nome_razao, 'UTF-8'),
                'cpf_cnpj' => (string) ($person->cpf_cnpj ?? ''),
            ])
            ->all();
    }

    public function moveContaFormFornecedorSelection(int $delta): void
    {
        if ($this->contaFormFornecedorResults === []) {
            return;
        }

        $index = ($this->contaFormFornecedorIndex ?? 0) + $delta;
        $count = count($this->contaFormFornecedorResults);
        $this->contaFormFornecedorIndex = max(0, min($count - 1, $index));
    }

    public function highlightContaFormFornecedorResult(int $index): void
    {
        if (! isset($this->contaFormFornecedorResults[$index])) {
            return;
        }

        $this->contaFormFornecedorIndex = $index;
    }

    public function selectContaFormFornecedorResult(int $index): void
    {
        if (! isset($this->contaFormFornecedorResults[$index])) {
            return;
        }

        $this->contaFormFornecedorIndex = $index;
        $this->confirmContaFormFornecedorSelection();
    }

    public function confirmContaFormFornecedorSelection(): void
    {
        $index = $this->contaFormFornecedorIndex;

        if ($index === null || ! isset($this->contaFormFornecedorResults[$index])) {
            $this->contaFormFornecedorLookupOpen = false;

            return;
        }

        $row = $this->contaFormFornecedorResults[$index];
        $this->applyContaFormFornecedor((int) $row['id'], (string) $row['nome']);
    }

    public function handleContaFormFornecedorEnter(): void
    {
        if (! $this->contaFormFornecedorLookupOpen) {
            return;
        }

        if ($this->contaFormFornecedorResults === []) {
            $this->contaFormFornecedorLookupOpen = false;

            return;
        }

        $this->confirmContaFormFornecedorSelection();
        // Mesma resposta Livewire — o JS só move o foco para Vencimento.
        $this->dispatch('erp-pagar-fornecedor-confirmado');
    }

    public function closeContaFormFornecedorLookup(): void
    {
        $this->contaFormFornecedorLookupOpen = false;
        $this->contaFormFornecedorResults = [];
        $this->contaFormFornecedorIndex = null;
    }

    protected function applyContaFormFornecedor(int $id, string $nome): void
    {
        $this->contaFormFornecedorId = (string) $id;
        $this->contaFormFornecedorBusca = mb_strtoupper(trim($nome), 'UTF-8');
        $this->resetErrorBag('contaFormFornecedorId');
        $this->closeContaFormFornecedorLookup();
    }

    public function updatedContaFormValor(): void
    {
        if ($this->contaFormRecordId !== null) {
            return;
        }

        $this->contaFormParcelasManuais = false;
        $this->rebuildContaFormParcelasPreview();
    }

    public function updatedContaFormParcelas(): void
    {
        if ($this->contaFormRecordId !== null) {
            return;
        }

        $this->contaFormParcelasManuais = false;
        $this->rebuildContaFormParcelasPreview();
    }

    public function updatedContaFormVencimento(): void
    {
        if ($this->contaFormRecordId !== null) {
            return;
        }

        $this->contaFormParcelasManuais = false;
        $this->rebuildContaFormParcelasPreview();
        // O datepicker sincroniza no ENTER; remount devolvia o foco ao vencimento.
        $this->skipRender();
    }

    public function updatedContaFormIntervalo(): void
    {
        if ($this->contaFormRecordId !== null) {
            return;
        }

        $this->contaFormParcelasManuais = false;
        $this->rebuildContaFormParcelasPreview();
    }

    public function updatedContaFormIntervaloUnidade(): void
    {
        if ($this->contaFormRecordId !== null) {
            return;
        }

        $this->contaFormParcelasManuais = false;
        $this->rebuildContaFormParcelasPreview();
    }

    public function updatedContaFormParcelasPreview($value, ?string $key = null): void
    {
        $this->contaFormParcelasManuais = true;
    }

    protected function rebuildContaFormParcelasPreview(): void
    {
        if ($this->contaFormRecordId !== null) {
            $this->contaFormParcelasPreview = [];

            return;
        }

        $parcelas = max(1, min(120, (int) $this->contaFormParcelas));
        $this->contaFormParcelas = (string) $parcelas;

        $intervalo = max(1, min(3650, (int) $this->contaFormIntervalo));
        $this->contaFormIntervalo = (string) $intervalo;
        $this->contaFormIntervaloUnidade = app(ContaPagarCadastroService::class)
            ->normalizarIntervaloUnidade($this->contaFormIntervaloUnidade);

        $valor = ErpMoney::parseBr($this->contaFormValor);
        $vencimento = trim($this->contaFormVencimento);

        if ($valor <= 0 || $vencimento === '' || $parcelas <= 1) {
            $this->contaFormParcelasPreview = [];

            return;
        }

        $preview = app(ContaPagarCadastroService::class)->montarParcelas(
            valorTotal: $valor,
            parcelas: $parcelas,
            vencimentoBase: $vencimento,
            intervalo: $intervalo,
            unidade: $this->contaFormIntervaloUnidade,
        );

        $this->contaFormParcelasPreview = array_map(
            static fn (array $row): array => [
                'n' => (int) $row['n'],
                'vencimento' => (string) $row['vencimento'],
                'valor_br' => (string) $row['valor_br'],
            ],
            $preview,
        );
    }

    public function salvarContaForm(): void
    {
        $this->persistirContaForm(abrirNovo: false);
    }

    public function salvarContaFormENovo(): void
    {
        if ($this->contaFormRecordId !== null) {
            $this->persistirContaForm(abrirNovo: false);

            return;
        }

        $this->persistirContaForm(abrirNovo: true);
    }

    private function persistirContaForm(bool $abrirNovo): void
    {
        if (! $this->contaFormModalOpen || $this->contaFormSalvando || $this->fornecedorRapidoModalOpen) {
            return;
        }

        $this->contaFormSalvando = true;

        try {
            $this->executarPersistenciaContaForm($abrirNovo);
        } finally {
            $this->contaFormSalvando = false;
        }
    }

    private function executarPersistenciaContaForm(bool $abrirNovo): void
    {
        if ($this->contaFormSomenteDre && $this->contaFormRecordId !== null) {
            $this->persistirSomenteCompetenciaDre();

            return;
        }

        $rules = [
            'contaFormEmissao' => ['required', 'date'],
            'contaFormDocumento' => ['nullable', 'string', 'max:40'],
            'contaFormFornecedorId' => ['required', 'integer', 'exists:people,id'],
            'contaFormVencimento' => ['required', 'date'],
            'contaFormCompetencia' => $this->regraCompetenciaMesAno(),
            'contaFormPlanoContaId' => ['nullable', 'integer'],
            'contaFormHistorico' => ['nullable', 'string', 'max:500'],
            'contaFormValor' => ['required', 'string'],
        ];

        if ($this->contaFormRecordId === null) {
            $rules['contaFormParcelas'] = ['required', 'integer', 'min:1', 'max:120'];
            $rules['contaFormIntervalo'] = ['required', 'integer', 'min:1', 'max:3650'];
            $rules['contaFormIntervaloUnidade'] = ['required', 'in:meses,dias'];
        }

        $this->validate(
            $rules,
            [
                'contaFormFornecedorId.required' => 'Selecione o fornecedor.',
                'contaFormEmissao.required' => 'Informe a emissão.',
                'contaFormVencimento.required' => 'Informe o vencimento.',
                'contaFormCompetencia' => 'Informe a competência como MM/AAAA (ex.: 11/2026).',
            ],
            [
                'contaFormEmissao' => 'emissão',
                'contaFormDocumento' => 'documento',
                'contaFormFornecedorId' => 'fornecedor',
                'contaFormVencimento' => 'vencimento',
                'contaFormCompetencia' => 'competência',
                'contaFormPlanoContaId' => 'plano DRE',
                'contaFormHistorico' => 'histórico',
                'contaFormValor' => 'valor',
                'contaFormParcelas' => 'nº parcelas',
                'contaFormIntervalo' => 'intervalo',
                'contaFormIntervaloUnidade' => 'unidade do intervalo',
            ],
        );

        $valor = ErpMoney::parseBr($this->contaFormValor);

        if ($valor <= 0) {
            Notification::make()
                ->title('Informe um valor maior que zero.')
                ->warning()
                ->send();

            return;
        }

        $idSelecionar = null;

        try {
            if ($this->contaFormRecordId !== null) {
                $conta = app(ContaPagarCadastroService::class)->atualizar((int) $this->contaFormRecordId, [
                    'emissao' => $this->contaFormEmissao,
                    'documento' => $this->contaFormDocumento,
                    'fornecedor_id' => (int) $this->contaFormFornecedorId,
                    'vencimento' => $this->contaFormVencimento,
                    'competencia' => $this->contaFormCompetencia,
                    'plano_conta_id' => $this->contaFormPlanoContaId !== '' ? (int) $this->contaFormPlanoContaId : null,
                    'historico' => $this->contaFormHistorico,
                    'valor' => $valor,
                ]);
                $idSelecionar = (int) $conta->getKey();
                $mensagem = 'Conta alterada.';
            } else {
                $parcelas = max(1, min(120, (int) $this->contaFormParcelas));
                $criadas = $this->criarContasDoFormulario($valor, $parcelas);
                $qtd = count($criadas);
                $primeira = $criadas[0] ?? null;
                $idSelecionar = $primeira ? (int) $primeira->getKey() : null;
                $mensagem = $qtd === 1 ? 'Conta cadastrada.' : "{$qtd} parcelas cadastradas.";
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title($e->getMessage())
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

        $this->selecionadosParaBaixa = [];
        $this->highlightedRecordId = $idSelecionar;

        Notification::make()
            ->title($mensagem)
            ->success()
            ->send();

        if ($abrirNovo && $this->contaFormRecordId === null) {
            $this->fillContaFormForCreate();
            $this->resetErrorBag();
            $this->pushContaPagarListRefresh(renderPage: true);
            $this->contaFormModalOpen = true;
            $this->dispatch('erp-pagar-form-focus');

            return;
        }

        // Fechar + redesenhar a página: resetTable()/pushContaPagarListRefresh()
        // usava skipRender e o modal ficava aberto mesmo com contaFormModalOpen=false.
        $this->closeContaFormModal();
        $this->pushContaPagarListRefresh(renderPage: true);
    }

    private function persistirSomenteCompetenciaDre(): void
    {
        $this->validate(
            [
                'contaFormCompetencia' => $this->regraCompetenciaMesAno(),
                'contaFormPlanoContaId' => ['nullable', 'integer'],
            ],
            [
                'contaFormCompetencia' => 'Informe a competência como MM/AAAA (ex.: 11/2026).',
            ],
            [
                'contaFormCompetencia' => 'competência',
                'contaFormPlanoContaId' => 'plano DRE',
            ],
        );

        try {
            $conta = app(ContaPagarCadastroService::class)->atualizarCompetenciaDre(
                (int) $this->contaFormRecordId,
                $this->contaFormCompetencia,
                $this->contaFormPlanoContaId !== '' ? (int) $this->contaFormPlanoContaId : null,
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Não foi possível salvar a competência.')->danger()->send();

            return;
        }

        $this->highlightedRecordId = (int) $conta->getKey();
        Notification::make()->title('Competência DRE atualizada.')->success()->send();
        $this->closeContaFormModal();
        $this->pushContaPagarListRefresh(renderPage: true);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function contaFormPlanosOptions(): array
    {
        if (! Schema::hasTable('planos_contas')) {
            return [];
        }

        return PlanoConta::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao', 'dc'])
            ->map(static fn (PlanoConta $p): array => [
                'id' => (int) $p->id,
                'label' => trim(($p->codigo ? $p->codigo.' — ' : '').(string) $p->descricao
                    .(filled($p->dc) ? ' ('.$p->dc.')' : '')),
            ])
            ->all();
    }

    /**
     * @return list<ContaPagar>
     */
    private function criarContasDoFormulario(float $valor, int $parcelas): array
    {
        $service = app(ContaPagarCadastroService::class);
        $dadosBase = [
            'emissao' => $this->contaFormEmissao,
            'documento' => $this->contaFormDocumento,
            'fornecedor_id' => (int) $this->contaFormFornecedorId,
            'historico' => $this->contaFormHistorico,
            'competencia' => $this->contaFormCompetencia,
            'plano_conta_id' => $this->contaFormPlanoContaId !== '' ? (int) $this->contaFormPlanoContaId : null,
        ];

        if ($parcelas <= 1) {
            return $service->criar([
                ...$dadosBase,
                'vencimento' => $this->contaFormVencimento,
                'valor' => $valor,
                'parcelas' => 1,
                'intervalo' => 1,
                'intervalo_unidade' => ContaPagarCadastroService::INTERVALO_MESES,
            ]);
        }

        if (! $this->contaFormParcelasManuais || $this->contaFormParcelasPreview === []) {
            $this->rebuildContaFormParcelasPreview();
        }

        if ($this->contaFormParcelasPreview === []) {
            return $service->criar([
                ...$dadosBase,
                'vencimento' => $this->contaFormVencimento,
                'valor' => $valor,
                'parcelas' => $parcelas,
                'intervalo' => max(1, (int) $this->contaFormIntervalo),
                'intervalo_unidade' => $this->contaFormIntervaloUnidade,
            ]);
        }

        $lista = [];
        $somaCentavos = 0;

        foreach ($this->contaFormParcelasPreview as $i => $row) {
            $parcelaValor = ErpMoney::parseBr($row['valor_br'] ?? 0);
            $vencimento = trim((string) ($row['vencimento'] ?? ''));

            if ($parcelaValor <= 0) {
                throw new InvalidArgumentException('Parcela '.($i + 1).' com valor inválido.');
            }

            if ($vencimento === '') {
                throw new InvalidArgumentException('Parcela '.($i + 1).' sem vencimento.');
            }

            $somaCentavos += (int) round($parcelaValor * 100);
            $lista[] = [
                'vencimento' => $vencimento,
                'valor' => $parcelaValor,
            ];
        }

        $totalCentavos = (int) round($valor * 100);

        if ($somaCentavos !== $totalCentavos) {
            throw new InvalidArgumentException(
                'A soma das parcelas ('.ErpMoney::formatBr($somaCentavos / 100).') deve ser igual ao valor total ('.ErpMoney::formatBr($valor).').'
            );
        }

        return $service->criarDeLista($dadosBase, $lista);
    }

    protected function fillContaFormForCreate(): void
    {
        $hoje = ErpTimezone::toLocal()->toDateString();

        $this->contaFormRecordId = null;
        $this->contaFormNumero = ContaPagar::nextNumero();
        $this->contaFormEmissao = $hoje;
        $this->contaFormDocumento = '';
        $this->contaFormEmpresa = $this->contaFormEmpresaAtual();
        $this->contaFormFornecedorId = '';
        $this->contaFormFornecedorBusca = '';
        $this->closeContaFormFornecedorLookup();
        $this->contaFormVencimento = $hoje;
        // Competência não é presumida do vencimento — usuário informa quando souber.
        $this->contaFormCompetencia = '';
        $this->contaFormPlanoContaId = '';
        $this->contaFormSomenteDre = false;
        $this->contaFormHistorico = '';
        $this->contaFormValor = '0,00';
        $this->contaFormParcelas = '1';
        $this->contaFormIntervalo = '1';
        $this->contaFormIntervaloUnidade = ContaPagarCadastroService::INTERVALO_MESES;
        $this->contaFormParcelasPreview = [];
        $this->contaFormParcelasManuais = false;
        $this->closeFornecedorRapidoModal();
    }

    protected function fillContaFormFromRecord(ContaPagar $conta): void
    {
        $this->contaFormRecordId = (int) $conta->id;
        $this->contaFormNumero = (string) ($conta->numero ?? '');
        $this->contaFormEmissao = optional($conta->emissao)?->format('Y-m-d') ?? ErpTimezone::toLocal()->toDateString();
        $this->contaFormDocumento = (string) ($conta->documento ?? '');
        $this->contaFormEmpresa = $this->contaFormEmpresaAtual();
        $this->contaFormFornecedorId = (string) ($conta->fornecedor_id ?? '');
        $this->contaFormFornecedorBusca = mb_strtoupper(trim((string) ($conta->fornecedor?->nome_razao ?? '')), 'UTF-8');
        $this->closeContaFormFornecedorLookup();
        $this->contaFormVencimento = optional($conta->vencimento)?->format('Y-m-d') ?? ErpTimezone::toLocal()->toDateString();
        $this->contaFormCompetencia = Schema::hasColumn('contas_pagar', 'competencia')
            ? ContaPagarCompetencia::formatMesAno($conta->competencia ?? null)
            : '';
        $this->contaFormPlanoContaId = Schema::hasColumn('contas_pagar', 'plano_conta_id') && $conta->plano_conta_id
            ? (string) $conta->plano_conta_id
            : '';
        $this->contaFormSomenteDre = (float) $conta->valor_pago > 0;
        $this->contaFormHistorico = (string) ($conta->produto ?? '');
        $this->contaFormValor = ErpMoney::formatBr((float) $conta->valor);
        $this->contaFormParcelas = '1';
        $this->contaFormIntervalo = '1';
        $this->contaFormIntervaloUnidade = ContaPagarCadastroService::INTERVALO_MESES;
        $this->contaFormParcelasPreview = [];
        $this->contaFormParcelasManuais = false;
        $this->closeFornecedorRapidoModal();
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

    public function updatedContaFormCompetencia(mixed $value): void
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            $this->contaFormCompetencia = '';

            return;
        }

        // 112026 → 11/2026 (máscara leve; não reinterpretar como outra data).
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 6) {
            $this->contaFormCompetencia = substr($digits, 0, 2).'/'.substr($digits, 2, 4);

            return;
        }

        // Aceita 3/2026 → 03/2026 sem mudar o mês de referência.
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $raw, $m) === 1) {
            $this->contaFormCompetencia = sprintf('%02d/%04d', (int) $m[1], (int) $m[2]);
        }
    }

    /** @return list<mixed> */
    private function regraCompetenciaMesAno(): array
    {
        return [
            'nullable',
            'string',
            'max:7',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! ContaPagarCompetencia::isValidMesAno($value)) {
                    $fail('Informe a competência como MM/AAAA (ex.: 11/2026).');
                }
            },
        ];
    }
}
