<?php

namespace App\Filament\Resources\CaixaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\CaixaResource;
use App\Filament\Resources\CaixaResource\Pages\Concerns\ManagesCaixaLivroMovimentoModal;
use App\Filament\Resources\CaixaResource\Pages\Concerns\ManagesCaixaTransferenciaModal;
use App\Filament\Resources\CaixaResource\Pages\Concerns\ManagesCaixaViewModal;
use App\Support\Erp\Financeiro\CaixaLivroAberturaDisplay;
use App\Support\Erp\Financeiro\CaixaLivroMovimentoService;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use App\Livewire\Erp\CaixaListTable;
use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\FormaPagamento;
use App\Models\PlanoConta;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use Illuminate\Support\Facades\DB;
use App\Support\Erp\Financeiro\CaixaTransferencia;
use App\Support\Erp\Pdv\PdvCaixaFechamentoService;
use App\Support\Erp\Queries\CaixaLancamentoMeiosResumo;
use App\Support\Erp\Queries\CaixaListQueryBuilder;
use App\Support\Erp\Queries\CaixaPdvMovimentacaoQuery;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class ListCaixa extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesCaixaLivroMovimentoModal;
    use ManagesCaixaTransferenciaModal;
    use ManagesCaixaViewModal;

    protected static string $resource = CaixaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'documento';

    public string $searchColumn2 = '';

    public string $localSearch2 = '';

    public string $searchSignatureApplied = '';

    public string $contaFilter = '';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public bool $caixaFormOpen = false;

    public ?int $caixaFormLancamentoId = null;

    /** @var array<string, string> */
    public array $caixaForm = [];

    public string $caixaFormAlert = '';

    /** @var array<string, string> chave do campo => mensagem */
    public array $caixaFormErrors = [];

    public bool $caixaDeleteConfirmOpen = false;

    public ?int $caixaDeleteConfirmId = null;

    public string $caixaAttentionMessage = '';

    public function mount(): void
    {
        parent::mount();

        if ($this->searchColumn === 'codigo') {
            $this->searchColumn = 'documento';
        }

        ErpScreen::set('Caixa');

        $this->contaFilter = $this->contaCaixaGeralId();

        // Sessões PDV fechadas antes da correção: gera o lançamento faltante no Livro Caixa.
        app(PdvCaixaFechamentoService::class)->backfillSessoesRecentes();

        if ($this->periodoDe === '' && $this->periodoAte === '') {
            $inicioMes = now()->startOfMonth()->toDateString();
            $fimMes = now()->endOfMonth()->toDateString();
            $this->periodoDe = $inicioMes;
            $this->periodoAte = $fimMes;
            $this->periodoDeApplied = $inicioMes;
            $this->periodoAteApplied = $fimMes;
        }

        $this->searchSignatureApplied = $this->searchSignature();
    }

    public function mountInteractsWithTable(): void
    {
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-caixa-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um lançamento';
    }

    protected function customErpListKeyboardConfig(): array
    {
        $extraKeys = [
            'F6' => ['method' => 'imprimirMovimentoCaixa'],
        ];

        $pdvConsulta = $this->contaFiltroEhPdv();

        if (! $pdvConsulta) {
            $extraKeys['F7'] = ['method' => 'openCaixaTransferencia'];
        }

        if (! $this->contaFiltroEhBancaria() && ! $pdvConsulta) {
            $extraKeys['F8'] = ['method' => 'openCaixaF8'];
        }

        $config = [
            'searchInput' => '.erp-caixa__input',
            'extraKeys' => $extraKeys,
        ];

        if (! $pdvConsulta) {
            $config['create'] = 'createLancamento';
            $config['edit'] = 'editLancamento';
            $config['delete'] = 'deleteLancamento';
        }

        return $config;
    }

    /**
     * Bloqueia mutação na retaguarda quando a conta filtrada (ou destino) é PDV.
     */
    protected function assertRetaguardaPodeMutarConta(int $contaId, string $titulo = 'Conta PDV — somente consulta'): bool
    {
        try {
            app(CaixaLivroMovimentoService::class)->assertContaRetaguardaMutavel($contaId);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title($titulo)
                ->body($e->getMessage())
                ->warning()
                ->send();

            return false;
        }

        return true;
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CaixaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function listQueryBuilder(): CaixaListQueryBuilder
    {
        return new CaixaListQueryBuilder(
            contaFilter: $this->contaFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            searchColumn2: $this->searchColumn2,
            localSearch2: $this->localSearch2,
        );
    }

    protected function buildListQuery(): Builder
    {
        return $this->listQueryBuilder()->buildForList();
    }

    #[Computed]
    public function contasOptions(): array
    {
        $options = [];

        foreach ($this->caixaContasCatalogo() as $id => $row) {
            $options[$id] = $row['nome'];
        }

        // Sessões antigas sem caixa_conta_id: visão separada, fora das contas PDV.
        $options[CaixaPdvMovimentacaoQuery::FILTRO_SESSOES_SEM_CONTA]
            = CaixaPdvMovimentacaoQuery::LABEL_SESSOES_SEM_CONTA;

        return $options;
    }

    #[Computed]
    public function saldoAnterior(): float
    {
        return $this->listQueryBuilder()->sumSaldoAnterior();
    }

    #[Computed]
    public function totalEntrada(): float
    {
        return $this->listQueryBuilder()->sumEntradaFiltered();
    }

    #[Computed]
    public function totalSaida(): float
    {
        return $this->listQueryBuilder()->sumSaidaFiltered();
    }

    #[Computed]
    public function saldoAtual(): float
    {
        return $this->saldoAnterior + $this->totalEntrada - $this->totalSaida;
    }

    /**
     * @return array<string, float|bool>
     */
    #[Computed]
    public function caixaStatusBanner(): array
    {
        $contaId = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        return CaixaLivroAberturaDisplay::statusBanner(
            $contaId,
            $empresaId > 0 ? $empresaId : null,
        );
    }

    #[Computed]
    public function caixaRodape(): array
    {
        $contaId = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $saldoFin = $contaId > 0
            ? ErpFinanceiroMetricas::saldoCaixa($contaId, $empresaId > 0 ? $empresaId : null)
            : $this->saldoAtual;

        $mov = CaixaLivroAberturaDisplay::movimentoReferenciaNaConta(
            $contaId,
            $empresaId > 0 ? $empresaId : null,
        );

        if ($mov && $mov->isAberto() && $empresaId > 0) {
            $totais = app(CaixaLivroMovimentoService::class)->totaisDoMovimento($mov);

            return [
                'tem_movimento' => true,
                'status_aberto' => true,
                'troco_inicial' => round((float) $mov->troco_inicial, 2),
                'entradas' => $totais['entradas'],
                'saidas' => $totais['saidas'],
                'saldo_movimento' => $totais['saldo_movimento'],
                'saldo_financeiro' => $saldoFin,
                'saldo_abertura_financeiro' => round((float) $mov->saldo_abertura, 2),
                'valor_contado' => null,
                'diferenca' => null,
                'diferenca_conferencia' => round(
                    $saldoFin - $totais['saldo_financeiro'],
                    2
                ),
            ];
        }

        return CaixaLivroAberturaDisplay::metricasRodape(
            $contaId,
            $this->totalEntrada,
            $this->totalSaida,
            $saldoFin,
            $empresaId > 0 ? $empresaId : null,
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.caixa.screen'),
                View::make('filament.components.erp.caixa.table-host')
                    ->columnSpanFull(),
                View::make('filament.components.erp.caixa.meios-resumo'),
                View::make('filament.components.erp.caixa.footer-summary'),
                View::make('filament.components.erp.caixa.action-bar'),
                View::make('filament.components.erp.caixa.view-modal'),
                View::make('filament.components.erp.caixa.form-modal'),
                View::make('filament.components.erp.caixa.transfer-modal'),
                View::make('filament.components.erp.caixa.movimento-modal'),
                View::make('filament.components.erp.caixa.fechamento-preview-overlay'),
            ]);
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Período filtrado.')
            ->success()
            ->send();
    }

    /**
     * A tela sempre abre no CAIXA GERAL; não existe visão "todas as contas".
     */
    protected function contaCaixaGeralId(): string
    {
        return (string) CaixaConta::ensureCaixaGeral()->id;
    }

    public function updatedContaFilter(): void
    {
        if (! array_key_exists((int) $this->contaFilter, $this->contasOptions)) {
            $this->contaFilter = $this->contaCaixaGeralId();
        }

        $this->clearListSelection();
        $this->pushCaixaListRefresh();
        $this->patchCaixaLivroAcoes();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'documento';
        $this->searchColumn2 = '';
        $this->localSearch2 = '';
        $this->clearListSelection();
        $this->pushCaixaListRefresh(resetSort: true, renderPage: true);
    }

    public function updatedSearchColumn(): void
    {
        if (! in_array($this->searchColumn, $this->localSearchColumns(), true)) {
            $this->searchColumn = 'documento';
        }

        $this->localSearch = '';
        $this->searchColumn2 = '';
        $this->localSearch2 = '';
        $this->clearListSelection();
        $this->pushCaixaListRefresh(resetSort: true, renderPage: true);
    }

    /**
     * Marca/desmarca campo de pesquisa (até 2 combinados). Ao marcar um 3º, sai o mais antigo.
     */
    public function toggleSearchField(string $column): void
    {
        if (! in_array($column, $this->localSearchColumns(), true)) {
            $this->skipRender();

            return;
        }

        $active = array_values(array_filter([$this->searchColumn, $this->searchColumn2], fn (string $c): bool => $c !== ''));

        if (in_array($column, $active, true)) {
            if (count($active) < 2) {
                $this->skipRender();

                return;
            }

            $this->removeSearchField($column);
        } else {
            if (count($active) >= 2) {
                $this->removeSearchField($this->searchColumn);
            }

            $this->searchColumn2 = $column;
            $this->localSearch2 = '';
        }

        if ($this->searchSignature() === $this->searchSignatureApplied) {
            return;
        }

        $this->clearListSelection();
        $this->pushCaixaListRefresh(resetSort: true, renderPage: true);
    }

    protected function removeSearchField(string $column): void
    {
        if ($this->searchColumn2 === $column) {
            $this->searchColumn2 = '';
            $this->localSearch2 = '';

            return;
        }

        if ($this->searchColumn === $column && $this->searchColumn2 !== '') {
            $this->searchColumn = $this->searchColumn2;
            $this->localSearch = $this->localSearch2;
            $this->searchColumn2 = '';
            $this->localSearch2 = '';
        }
    }

    /**
     * @return list<string>
     */
    protected function localSearchColumns(): array
    {
        return ['emissao', 'documento', 'historico', 'plano_contas', 'conta', 'entrada', 'saida', 'meio_pagamento'];
    }

    /**
     * Formas cadastradas (ativas) para o select LOCALIZAR → MEIO DE PAGAMENTO.
     *
     * @return list<string>
     */
    #[Computed]
    public function meioPagamentoFilterOptions(): array
    {
        return \App\Support\Erp\Queries\CaixaLancamentoMeioPagamentoFiltro::opcoesSelect();
    }

    /**
     * Resumo de recebimentos por meio (conta + período + Localizar).
     *
     * @return array{
     *     linhas: list<array{forma: string, recebido: float, estorno: float, liquido: float}>,
     *     total_recebido: float,
     *     total_estorno: float,
     *     total_liquido: float,
     *     outras_saidas: float,
     *     saldo_liquido: float,
     *     total_entradas: float,
     *     sem_meio: float,
     *     visivel: bool
     * }
     */
    #[Computed]
    public function caixaMeiosResumo(): array
    {
        return (new CaixaLancamentoMeiosResumo)->build($this->listQueryBuilder()->scopedQuery());
    }

    /**
     * Filtro efetivo (campo vazio não muda o resultado, então não entra).
     */
    protected function searchSignature(): string
    {
        $terms = [];

        foreach ([[$this->searchColumn, $this->localSearch], [$this->searchColumn2, $this->localSearch2]] as [$column, $term]) {
            $term = mb_strtoupper(trim($term), 'UTF-8');

            if ($column !== '' && $term !== '') {
                $terms[] = $column.'='.$term;
            }
        }

        sort($terms);

        return json_encode([
            $terms,
            $this->periodoDeApplied,
            $this->periodoAteApplied,
        ], JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * Pesquisa automática: só consulta quando o filtro efetivo mudou.
     */
    protected function applySearchIfChanged(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;

        if ($this->searchSignature() === $this->searchSignatureApplied) {
            $this->skipRender();

            return;
        }

        $this->clearListSelection();
        $this->pushCaixaListRefresh();
    }

    public function updatedLocalSearch(): void
    {
        $this->applySearchIfChanged();
    }

    public function updatedLocalSearch2(): void
    {
        $this->applySearchIfChanged();
    }

    public function updatedPeriodoDe(): void
    {
        $this->applySearchIfChanged();
    }

    public function updatedPeriodoAte(): void
    {
        $this->applySearchIfChanged();
    }

    public function applyPeriodFilterAuto(?string $de = null, ?string $ate = null, ?int $seq = null): void
    {
        if ($de !== null) {
            $this->periodoDe = trim($de);
        }
        if ($ate !== null) {
            $this->periodoAte = trim($ate);
        }

        $this->applySearchIfChanged();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->pushCaixaListRefresh();
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->pushCaixaListRefresh();
    }

    public function createLancamento(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.create')) {
            return;
        }

        $contaId = is_numeric($this->contaFilter)
            ? (int) $this->contaFilter
            : (int) (CaixaConta::query()->visiveisNaEmpresa()->where('ativo', true)->orderBy('nome')->value('id') ?? 0);

        if (! $this->assertRetaguardaPodeMutarConta($contaId)) {
            return;
        }

        try {
            app(CaixaLivroMovimentoService::class)->assertPodeOperarConta($contaId);
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title('Caixa fechado')->body($e->getMessage())->warning()->send();

            return;
        }

        $this->caixaForm = [
            'emissao' => now()->format('Y-m-d'),
            'documento' => '',
            'plano_conta_id' => '',
            'caixa_conta_id' => (string) $contaId,
            'forma_pagamento_id' => '',
            'historico' => '',
            'entrada' => '0,00',
            'saida' => '0,00',
        ];
        $this->caixaFormLancamentoId = null;
        $this->caixaFormAlert = '';
        $this->caixaFormErrors = [];
        $this->caixaFormOpen = true;
    }

    public function closeCaixaForm(): void
    {
        $this->caixaFormOpen = false;
        $this->caixaFormLancamentoId = null;
        $this->caixaForm = [];
        $this->caixaFormAlert = '';
        $this->caixaFormErrors = [];
    }

    public function updatedCaixaFormFormaPagamentoId(): void
    {
        $formaId = (int) ($this->caixaForm['forma_pagamento_id'] ?? 0);
        $destinoId = $formaId > 0
            ? (int) (FormaPagamento::query()->whereKey($formaId)->value('conta_destino_id') ?? 0)
            : 0;

        try {
            $this->caixaForm['caixa_conta_id'] = (string) CaixaConta::resolverDestinoLivroCaixa(
                null,
                $destinoId > 0 ? $destinoId : null,
            );
        } catch (\InvalidArgumentException) {
            $this->caixaForm['caixa_conta_id'] = (string) CaixaConta::ensureCaixaGeral()->id;
        }
    }

    public function saveCaixaLancamento(): void
    {
        if (! $this->erpAuthorizeOrNotify($this->caixaFormLancamentoId ? 'caixa.update' : 'caixa.create')) {
            return;
        }

        $this->caixaFormAlert = '';
        $this->caixaFormErrors = [];

        $emissao = trim((string) ($this->caixaForm['emissao'] ?? ''));
        $entrada = max(0, BrDecimal::parse($this->caixaForm['entrada'] ?? 0, 2));
        $saida = max(0, BrDecimal::parse($this->caixaForm['saida'] ?? 0, 2));
        $selecionada = (int) ($this->caixaForm['caixa_conta_id'] ?? 0);
        $formaId = (int) ($this->caixaForm['forma_pagamento_id'] ?? 0);
        $planoId = (int) ($this->caixaForm['plano_conta_id'] ?? 0);
        $historico = trim((string) ($this->caixaForm['historico'] ?? ''));
        $documentoInformado = mb_substr(trim((string) ($this->caixaForm['documento'] ?? '')), 0, 40);

        /** @var array<string, string> $errors */
        $errors = [];

        if ($emissao === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $emissao)) {
            $errors['emissao'] = 'Informe a data de emissão.';
        }

        $plano = null;
        if ($planoId <= 0) {
            $errors['plano_conta_id'] = 'Selecione o plano de contas.';
        } else {
            $plano = PlanoConta::query()->whereKey($planoId)->where('ativo', true)->first();
            if (! $plano) {
                $errors['plano_conta_id'] = 'Plano de contas inválido ou inativo.';
            }
        }

        if ($selecionada <= 0) {
            $errors['caixa_conta_id'] = 'Selecione a conta.';
        }

        $formaValida = null;
        if ($formaId <= 0) {
            $errors['forma_pagamento_id'] = 'Selecione o meio de pagamento.';
        } else {
            $formaValida = FormaPagamento::query()
                ->whereKey($formaId)
                ->where('ativo', true)
                ->value('id');
            if (! $formaValida) {
                $errors['forma_pagamento_id'] = 'Meio de pagamento inválido ou inativo.';
            }
        }

        if ($historico === '') {
            $errors['historico'] = 'Informe o histórico do lançamento.';
        }

        if ($entrada <= 0 && $saida <= 0) {
            $errors['entrada'] = 'Informe um valor maior que zero em Entrada ou em Saída.';
            $errors['saida'] = $errors['entrada'];
        } elseif ($entrada > 0 && $saida > 0) {
            $errors['entrada'] = 'Preencha somente Entrada ou somente Saída — a outra deve permanecer zerada.';
            $errors['saida'] = $errors['entrada'];
        }

        if ($errors !== []) {
            $this->caixaFormErrors = $errors;
            $this->caixaFormAlert = (string) reset($errors);

            return;
        }

        $destinoForma = (int) (FormaPagamento::query()->whereKey((int) $formaValida)->value('conta_destino_id') ?? 0);

        try {
            $contaId = CaixaConta::resolverDestinoLivroCaixa(
                $selecionada > 0 ? $selecionada : null,
                $destinoForma > 0 ? $destinoForma : null,
            );
            app(CaixaLivroMovimentoService::class)->assertContaRetaguardaMutavel($contaId);
        } catch (\InvalidArgumentException $e) {
            $this->caixaFormErrors = ['caixa_conta_id' => $e->getMessage()];
            $this->caixaFormAlert = $e->getMessage();

            return;
        }

        if (CaixaTransferencia::isDocumentoTransferencia($documentoInformado)) {
            $this->caixaFormErrors = ['documento' => 'O prefixo TRF- é reservado para transferências entre contas. Use F7 — Transferir.'];
            $this->caixaFormAlert = $this->caixaFormErrors['documento'];

            return;
        }

        try {
            app(CaixaLivroMovimentoService::class)->assertPodeOperarConta($contaId);
            app(CaixaLivroMovimentoService::class)->assertPodeMutarLancamento($contaId, $emissao);
        } catch (\InvalidArgumentException $e) {
            $this->caixaFormAlert = $e->getMessage();

            return;
        }

        $dados = [
            'emissao' => $emissao,
            'documento' => $documentoInformado !== '' ? $documentoInformado : null,
            'historico' => mb_substr(
                str_starts_with(mb_strtoupper($historico, 'UTF-8'), '[MANUAL] ')
                    ? mb_strtoupper($historico, 'UTF-8')
                    : '[MANUAL] '.mb_strtoupper($historico, 'UTF-8'),
                0,
                500,
            ),
            'plano_conta_id' => $plano?->id,
            'plano_contas' => $plano ? mb_substr(mb_strtoupper((string) $plano->descricao, 'UTF-8'), 0, 120) : null,
            'caixa_conta_id' => $contaId,
            'forma_pagamento_id' => (int) $formaValida,
            'entrada' => $entrada,
            'saida' => $saida,
            'empresa_id' => ErpContext::currentEmpresaId(),
        ];

        if ($this->caixaFormLancamentoId) {
            // Manuais são identificados pelo histórico [MANUAL], não por documento MANUAL-%.
            $atualizados = CaixaLancamento::query()
                ->whereKey($this->caixaFormLancamentoId)
                ->whereRaw('UPPER(LEFT(TRIM(historico), 8)) = ?', ['[MANUAL]'])
                ->update($dados);

            if ($atualizados < 1) {
                $this->caixaFormAlert = 'Não foi possível alterar este lançamento manual.';

                return;
            }
        } else {
            CaixaLancamento::query()->create([
                'codigo' => CaixaLancamento::nextCodigo(),
                ...$dados,
            ]);
        }

        $this->closeCaixaForm();
        $this->clearListSelection();
        // renderPage: true — senão skipRender mantém o modal aberto no DOM.
        $this->pushCaixaListRefresh(renderPage: true);
        Notification::make()->title('Lançamento de caixa salvo.')->success()->send();
    }

    public function editLancamento(int | string | null $recordId = null): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.update')) {
            return;
        }

        if ($this->contaFiltroEhPdv() && ! $this->assertRetaguardaPodeMutarConta((int) $this->contaFilter)) {
            return;
        }

        $resolvedId = filled($recordId) ? (int) $recordId : $this->highlightedRecordId;

        if (! $resolvedId) {
            $this->highlightedRecordIdOrNotify('edit');

            return;
        }

        if (CaixaLivroAberturaDisplay::isLinhaVirtual($resolvedId)) {
            Notification::make()
                ->title('Linha de abertura')
                ->body('A abertura/troco é só conferência e não pode ser alterada como lançamento.')
                ->warning()
                ->send();

            return;
        }

        $lancamento = CaixaLancamento::query()->find($resolvedId);
        if ($lancamento && CaixaTransferencia::isLancamentoTransferencia($lancamento)) {
            Notification::make()
                ->title('Transferência não editável')
                ->body($this->mensagemBloqueioTransferenciaEdicao())
                ->warning()
                ->send();

            return;
        }

        if (! $lancamento || ! str_starts_with(mb_strtoupper((string) $lancamento->historico, 'UTF-8'), '[MANUAL] ')) {
            Notification::make()
                ->title('Lançamento não editável')
                ->body('Somente lançamentos feitos pelo botão Novo podem ser alterados aqui.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->assertRetaguardaPodeMutarConta((int) $lancamento->caixa_conta_id)) {
            return;
        }

        $this->caixaFormLancamentoId = $lancamento->id;
        $this->caixaForm = [
            'emissao' => $lancamento->emissao?->format('Y-m-d') ?? now()->toDateString(),
            'documento' => $lancamento->documento ?? '',
            'plano_conta_id' => (string) ($lancamento->plano_conta_id ?? ''),
            'caixa_conta_id' => (string) $lancamento->caixa_conta_id,
            'forma_pagamento_id' => (string) ($lancamento->forma_pagamento_id ?? ''),
            'historico' => preg_replace('/^\[MANUAL\]\s*/iu', '', (string) $lancamento->historico) ?? $lancamento->historico,
            'entrada' => number_format((float) $lancamento->entrada, 2, ',', '.'),
            'saida' => number_format((float) $lancamento->saida, 2, ',', '.'),
        ];
        $this->caixaFormAlert = '';
        $this->caixaFormErrors = [];
        $this->caixaFormOpen = true;
    }

    public function imprimirMovimentoCaixa(): void
    {
        // Com a janela de fechamento concluída aberta, F6 abre as opções do fechamento.
        if ($this->caixaFecharOpen && $this->caixaFecharConcluido) {
            $this->openCaixaFechamentoPrint();

            return;
        }

        if (! $this->erpAuthorizeOrNotify('caixa.print')) {
            return;
        }

        $params = ['slug' => 'movimento-caixa'];
        $params['return'] = url('/admin/caixa');

        if (is_numeric($this->contaFilter)) {
            $params['conta'] = (int) $this->contaFilter;
        }

        if ($this->periodoDeApplied !== '') {
            $params['de'] = $this->periodoDeApplied;
        }

        if ($this->periodoAteApplied !== '') {
            $params['ate'] = $this->periodoAteApplied;
        }

        $this->redirect(route('erp.reports.tabular', $params), navigate: false);
    }

    public function deleteLancamento(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.delete')) {
            return;
        }

        if ($this->contaFiltroEhPdv()) {
            $this->caixaAttentionMessage = 'Conta PDV operacional: na retaguarda é somente consulta. Opere pelo PDV.';

            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        if (CaixaLivroAberturaDisplay::isLinhaVirtual((int) $recordId)) {
            $this->caixaAttentionMessage = 'A linha de abertura/troco é apenas conferência do movimento e não pode ser excluída. Feche o caixa (F8) se precisar encerrar o turno.';

            return;
        }

        if ((int) $recordId < 0) {
            $this->caixaAttentionMessage = 'Este movimento pertence à sessão do caixa PDV e não pode ser excluído por aqui. Cancele a venda, sangria ou suprimento pelo próprio PDV.';

            return;
        }

        $lancamento = CaixaLancamento::query()->find($recordId);
        if ($lancamento && CaixaTransferencia::isLancamentoTransferencia($lancamento)) {
            try {
                app(CaixaLivroMovimentoService::class)->assertPodeMutarLancamento(
                    (int) $lancamento->caixa_conta_id,
                    optional($lancamento->emissao)?->toDateString() ?? now()->toDateString(),
                );
            } catch (\InvalidArgumentException $e) {
                $this->caixaAttentionMessage = $e->getMessage();

                return;
            }

            $this->openDeleteTransferenciaPar((string) $lancamento->documento);

            return;
        }

        if (! $lancamento || ! str_starts_with(mb_strtoupper((string) $lancamento->historico, 'UTF-8'), '[MANUAL] ')) {
            $this->caixaAttentionMessage = $this->caixaAutomaticoEstornoMessage($lancamento);

            return;
        }

        try {
            app(CaixaLivroMovimentoService::class)->assertContaRetaguardaMutavel((int) $lancamento->caixa_conta_id);
            app(CaixaLivroMovimentoService::class)->assertPodeMutarLancamento(
                (int) $lancamento->caixa_conta_id,
                optional($lancamento->emissao)?->toDateString() ?? now()->toDateString(),
            );
        } catch (\InvalidArgumentException $e) {
            $this->caixaAttentionMessage = $e->getMessage();

            return;
        }

        $this->caixaDeleteConfirmId = $lancamento->id;
        $this->caixaDeleteConfirmOpen = true;
    }

    public function cancelDeleteCaixaLancamento(): void
    {
        $this->caixaDeleteConfirmOpen = false;
        $this->caixaDeleteConfirmId = null;
    }

    public function closeCaixaAttention(): void
    {
        $this->caixaAttentionMessage = '';
    }

    protected function caixaAutomaticoEstornoMessage(?CaixaLancamento $lancamento): string
    {
        $texto = mb_strtoupper(implode(' ', [
            $lancamento?->documento,
            $lancamento?->historico,
            $lancamento?->plano_contas,
        ]), 'UTF-8');

        if (str_contains($texto, 'CONTA A RECEBER') || str_contains($texto, 'RECEBIMENTO')) {
            return 'Este lançamento foi gerado automaticamente por Contas a Receber e não pode ser excluído por aqui. Acesse Financeiro → Contas a Receber, localize o título e use Estornar recebimento.';
        }

        if (str_contains($texto, 'CONTA A PAGAR') || str_contains($texto, 'PAGAMENTO')) {
            return 'Este lançamento foi gerado automaticamente por Contas a Pagar e não pode ser excluído por aqui. Acesse Financeiro → Contas a Pagar, localize o título e use Estornar pagamento.';
        }

        if (str_contains($texto, 'COMPRA') || str_contains($texto, 'FORNECEDOR')) {
            return 'Este lançamento foi gerado por uma compra e não pode ser excluído por aqui. Acesse Compras, localize o lançamento e use Cancelar ou Reabrir conforme o status da compra.';
        }

        if (str_contains($texto, 'PDV') || str_contains($texto, 'VENDA')) {
            return 'Este lançamento foi gerado pelo PDV/Vendas e não pode ser excluído por aqui. Acesse Vendas ou PDV, localize a venda e realize o cancelamento ou estorno pelo fluxo de origem.';
        }

        return 'Este lançamento foi gerado automaticamente e não pode ser excluído por aqui. Localize o processo de origem e realize o cancelamento ou estorno por ele.';
    }

    public function confirmDeleteCaixaLancamento(): void
    {
        $id = $this->caixaDeleteConfirmId;
        $this->cancelDeleteCaixaLancamento();

        if (! $id) {
            return;
        }

        $lancamento = CaixaLancamento::query()
            ->whereKey($id)
            ->where('historico', 'like', '[MANUAL]%')
            ->first();

        if (! $lancamento) {
            Notification::make()
                ->title('Lançamento não encontrado ou não é manual.')
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () use ($lancamento): void {
            $snapshot = [
                'id' => (int) $lancamento->id,
                'codigo' => $lancamento->codigo,
                'emissao' => optional($lancamento->emissao)?->toDateString(),
                'documento' => $lancamento->documento,
                'historico' => $lancamento->historico,
                'entrada' => (float) $lancamento->entrada,
                'saida' => (float) $lancamento->saida,
                'caixa_conta_id' => $lancamento->caixa_conta_id ? (int) $lancamento->caixa_conta_id : null,
                'plano_conta_id' => $lancamento->plano_conta_id ? (int) $lancamento->plano_conta_id : null,
            ];

            $lancamento->delete();

            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'EXCLUIR_LANCAMENTO_CAIXA',
                resumo: 'Excluído lançamento manual de caixa #'.($snapshot['codigo'] ?: $snapshot['id']).'.',
                origem: 'caixa',
                documentoTipo: 'caixa_lancamento',
                documentoId: (int) $snapshot['id'],
                documentoNumero: (string) ($snapshot['codigo'] ?: $snapshot['id']),
                detalhes: ['antes' => $snapshot],
                empresaId: $lancamento->empresa_id ? (int) $lancamento->empresa_id : ErpContext::currentEmpresaId(),
            );
        });

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Lançamento excluído.')
            ->success()
            ->send();
    }

    #[On('erp-caixa-open-view')]
    public function onErpCaixaOpenView(int $lancamentoId, array $grupoIds = []): void
    {
        $this->openCaixaView($lancamentoId, $grupoIds);
    }

    #[On('erp-caixa-open-fechamento')]
    public function onErpCaixaOpenFechamento(int $movimentoId): void
    {
        $this->selecionarHistoricoFechamento($movimentoId);
    }

    public function refreshTable(): void
    {
        $this->pushCaixaListRefresh();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function resetTable(): void
    {
        $this->pushCaixaListRefresh();
    }

    protected function pushCaixaListRefresh(bool $resetSort = false, bool $renderPage = false): void
    {
        $builder = $this->listQueryBuilder();
        $this->searchSignatureApplied = $this->searchSignature();
        $saldoAnterior = $builder->sumSaldoAnterior();
        $totalEntrada = $builder->sumEntradaFiltered();
        $totalSaida = $builder->sumSaidaFiltered();
        $saldoAtual = $saldoAnterior + $totalEntrada - $totalSaida;
        unset(
            $this->caixaRodape,
            $this->caixaMeiosResumo,
            $this->caixaStatusBanner,
            $this->caixaMovimentoAberto,
            $this->caixaF8Label,
            $this->caixaF8PodeUsar,
            $this->caixaContaPdvSomenteConsulta,
        );

        $this->dispatch(
            'erp-caixa-list-refresh',
            contaFilter: $this->contaFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDeApplied: $this->periodoDeApplied,
            periodoAteApplied: $this->periodoAteApplied,
            perPage: (int) ($this->tableRecordsPerPage ?? 50),
            resetSort: $resetSort,
            searchColumn2: $this->searchColumn2,
            localSearch2: $this->localSearch2,
        )->to(CaixaListTable::class);

        if (! $renderPage) {
            $this->skipRender();
        }

        $rodape = $this->caixaRodape;
        $meios = $this->caixaMeiosResumo;
        $status = $this->caixaStatusBanner;

        $this->patchCaixaFooterTotals($saldoAnterior, $totalEntrada, $totalSaida, $saldoAtual, $rodape);
        $this->patchCaixaMeiosResumo($meios);
        $this->patchCaixaStatusInline($status);
    }

    /**
     * Troca de conta sem redesenhar a página: sincroniza barra inferior + atalhos no DOM.
     * Reusa contaFiltroEhPdv / caixaF8PodeUsar (sem query extra).
     */
    protected function patchCaixaLivroAcoes(): void
    {
        unset(
            $this->caixaMovimentoAberto,
            $this->caixaF8Label,
            $this->caixaF8PodeUsar,
            $this->caixaContaPdvSomenteConsulta,
        );

        $pdvConsulta = $this->contaFiltroEhPdv();
        $mostrarLivro = $this->caixaF8PodeUsar;
        $label = 'Abrir Caixa';
        $icon = "\u{25A2}";
        $title = 'Abrir movimento do Livro Caixa';

        if ($mostrarLivro) {
            $aberto = $this->caixaMovimentoAberto !== null;
            $label = $this->caixaF8Label;
            $icon = $aberto ? "\u{25A3}" : "\u{25A2}";
            $title = $aberto
                ? 'Fechar movimento do Livro Caixa'
                : 'Abrir movimento do Livro Caixa';
        }

        $keys = $this->customErpListKeyboardConfig();

        $payload = json_encode([
            'operacional' => ! $pdvConsulta,
            'livro' => $mostrarLivro,
            'label' => $label,
            'icon' => $icon,
            'title' => $title,
            'create' => $keys['create'] ?? null,
            'edit' => $keys['edit'] ?? null,
            'delete' => $keys['delete'] ?? null,
            'extraKeys' => $keys['extraKeys'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->js(<<<JS
            (() => {
                const data = {$payload};
                const root = document.querySelector('.erp-caixa-page');
                if (! root) return;

                root.querySelectorAll('[data-erp-caixa-operacional]').forEach((el) => {
                    el.hidden = !data.operacional;
                });
                root.querySelectorAll('[data-erp-caixa-livro]').forEach((el) => {
                    el.hidden = !data.livro;
                });
                if (data.livro) {
                    const label = root.querySelector('[data-erp-caixa-livro-label]');
                    const icon = root.querySelector('[data-erp-caixa-livro-icon]');
                    const button = root.querySelector('[data-erp-key="F8"]');
                    if (label) label.textContent = data.label;
                    if (icon) icon.textContent = data.icon;
                    if (button) button.title = data.title;
                }

                const config = window.__erpListConfigByClass && window.__erpListConfigByClass['erp-caixa-page'];
                if (! config) return;

                if (data.create) {
                    config.create = data.create;
                } else {
                    delete config.create;
                }
                if (data.edit) {
                    config.edit = data.edit;
                } else {
                    delete config.edit;
                }
                if (data.delete) {
                    config.delete = data.delete;
                } else {
                    delete config.delete;
                }

                config.extraKeys = Object.assign({}, data.extraKeys || {});
            })()
        JS);
    }

    /**
     * @param  array<string, float|bool>  $rodape
     */
    protected function patchCaixaFooterTotals(
        float $saldoAnterior,
        float $totalEntrada,
        float $totalSaida,
        float $saldoAtual,
        array $rodape = [],
    ): void {
        if (! empty($rodape['tem_movimento'])) {
            $this->js(sprintf(
                '(() => {
                    const items = document.querySelectorAll(".erp-caixa__summary-value");
                    if (items[0]) items[0].textContent = %s;
                    if (items[1]) items[1].textContent = %s;
                    if (items[2]) items[2].textContent = %s;
                    if (items[3]) items[3].textContent = %s;
                    if (items[4]) items[4].textContent = %s;
                })()',
                json_encode(number_format((float) $rodape['troco_inicial'], 2, ',', '.'), JSON_UNESCAPED_UNICODE),
                json_encode(number_format((float) $rodape['entradas'], 2, ',', '.'), JSON_UNESCAPED_UNICODE),
                json_encode(number_format((float) $rodape['saidas'], 2, ',', '.'), JSON_UNESCAPED_UNICODE),
                json_encode(number_format((float) $rodape['saldo_movimento'], 2, ',', '.'), JSON_UNESCAPED_UNICODE),
                json_encode(number_format((float) $rodape['saldo_financeiro'], 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            ));

            return;
        }

        $this->js(sprintf(
            '(() => {
                const items = document.querySelectorAll(".erp-caixa__summary-value");
                if (items[0]) items[0].textContent = %s;
                if (items[1]) items[1].textContent = %s;
                if (items[2]) items[2].textContent = %s;
                if (items[3]) items[3].textContent = %s;
            })()',
            json_encode(number_format($saldoAnterior, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode(number_format($totalEntrada, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode(number_format($totalSaida, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
            json_encode(number_format($saldoAtual, 2, ',', '.'), JSON_UNESCAPED_UNICODE),
        ));
    }

    /**
     * @param  array{visivel?: bool, aberto?: bool, titulo?: string, detalhe?: string}  $status
     */
    protected function patchCaixaStatusInline(array $status): void
    {
        $payload = [
            'visivel' => ! empty($status['visivel']),
            'aberto' => ! empty($status['aberto']),
            'titulo' => (string) ($status['titulo'] ?? ''),
            'detalhe' => (string) ($status['detalhe'] ?? ''),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->js(<<<JS
            (() => {
                const data = {$json};
                const root = document.querySelector('.erp-caixa-page');
                if (! root) return;
                let el = root.querySelector('[data-erp-caixa-status]');
                if (! data.visivel) {
                    if (el) el.hidden = true;
                    return;
                }
                if (! el) {
                    const pageSize = root.querySelector('.erp-caixa__page-size-group');
                    const block = root.querySelector('.erp-caixa__filter-block');
                    if (! block || ! pageSize) return;
                    el = document.createElement('div');
                    el.className = 'erp-caixa__status-inline';
                    el.setAttribute('data-erp-caixa-status', '');
                    el.setAttribute('role', 'status');
                    el.setAttribute('aria-live', 'polite');
                    el.innerHTML = '<strong data-erp-caixa-status-titulo></strong><span data-erp-caixa-status-detalhe></span>';
                    block.insertBefore(el, pageSize);
                }
                el.hidden = false;
                el.classList.toggle('is-aberto', !!data.aberto);
                el.classList.toggle('is-fechado', !data.aberto);
                el.title = (data.titulo || '') + (data.detalhe ? (' — ' + data.detalhe) : '');
                const t = el.querySelector('[data-erp-caixa-status-titulo]');
                const d = el.querySelector('[data-erp-caixa-status-detalhe]');
                if (t) t.textContent = data.titulo || '';
                if (d) d.textContent = data.detalhe || '';
            })()
        JS);
    }

    /**
     * @param  array{
     *     linhas: list<array{forma: string, recebido: float, estorno: float, liquido: float}>,
     *     total_recebido: float,
     *     total_estorno: float,
     *     total_liquido: float,
     *     outras_saidas: float,
     *     saldo_liquido: float,
     *     total_entradas: float,
     *     sem_meio: float,
     *     visivel: bool
     * }  $meios
     */
    protected function patchCaixaMeiosResumo(array $meios): void
    {
        $fmt = static fn (float $v): string => number_format($v, 2, ',', '.');
        $fmtNeg = static fn (float $v): string => $v > 0.009 ? '−'.$fmt($v) : $fmt(0.0);
        $itemsHtml = '';
        foreach ($meios['linhas'] ?? [] as $linha) {
            $forma = e((string) ($linha['forma'] ?? ''));
            $valor = e($fmt((float) ($linha['recebido'] ?? 0)));
            $extra = $forma === 'SEM MEIO' ? ' is-sem-meio' : '';
            $itemsHtml .= '<span class="erp-caixa__meios-item'.$extra.'">'
                .'<span class="erp-caixa__meios-forma">'.$forma.'</span>'
                .'<strong class="erp-caixa__meios-valor">'.$valor.'</strong>'
                .'</span>';
        }
        if ($itemsHtml === '') {
            $itemsHtml = '<span class="erp-caixa__meios-item is-empty">—</span>';
        }

        $saldo = (float) ($meios['saldo_liquido'] ?? 0);

        $payload = [
            'visivel' => ! empty($meios['visivel']),
            'itemsHtml' => $itemsHtml,
            'entradas' => $fmt((float) ($meios['total_entradas'] ?? 0)),
            'estorno' => $fmtNeg((float) ($meios['total_estorno'] ?? 0)),
            'outras' => $fmtNeg((float) ($meios['outras_saidas'] ?? 0)),
            'saldo' => $fmt($saldo),
            'saldoNeg' => $saldo < -0.009,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->js(<<<JS
            (() => {
                const data = {$json};
                const root = document.querySelector('.erp-caixa-page');
                if (! root) return;
                let bar = root.querySelector('[data-erp-caixa-meios]');
                if (! data.visivel) {
                    if (bar) bar.hidden = true;
                    return;
                }
                if (! bar) return;
                bar.hidden = false;
                const items = bar.querySelector('[data-erp-caixa-meios-items]');
                if (items) items.innerHTML = data.itemsHtml;
                const ent = bar.querySelector('[data-erp-caixa-meios-entradas]');
                if (ent) ent.textContent = data.entradas;
                const est = bar.querySelector('[data-erp-caixa-meios-estorno]');
                if (est) est.textContent = data.estorno;
                const out = bar.querySelector('[data-erp-caixa-meios-outras]');
                if (out) out.textContent = data.outras;
                const sal = bar.querySelector('[data-erp-caixa-meios-saldo]');
                if (sal) {
                    sal.textContent = data.saldo;
                    sal.classList.toggle('is-negativo', !!data.saldoNeg);
                }
            })()
        JS);
    }
}
