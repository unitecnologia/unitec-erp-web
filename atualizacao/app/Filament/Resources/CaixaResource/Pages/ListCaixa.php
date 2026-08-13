<?php

namespace App\Filament\Resources\CaixaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\CaixaResource;
use App\Filament\Resources\CaixaResource\Pages\Concerns\ManagesCaixaViewModal;
use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\FormaPagamento;
use App\Models\PlanoConta;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Pdv\PdvCaixaFechamentoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListCaixa extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;
    use ManagesCaixaViewModal;

    protected static string $resource = CaixaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    #[Url(as: 'conta')]
    public string $contaFilter = 'todas';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public bool $caixaFormOpen = false;

    public ?int $caixaFormLancamentoId = null;

    /** @var array<string, string> */
    public array $caixaForm = [];

    public string $caixaFormAlert = '';

    public bool $caixaDeleteConfirmOpen = false;

    public ?int $caixaDeleteConfirmId = null;

    public string $caixaAttentionMessage = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Caixa');

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
        return [
            'searchInput' => '.erp-caixa__input',
            'create' => 'createLancamento',
            'edit' => 'editLancamento',
            'delete' => 'deleteLancamento',
            'extraKeys' => [
                'F6' => ['method' => 'imprimirMovimentoCaixa'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CaixaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->with(['conta']);

        $this->applyContaFilter($query);

        if (filled($this->periodoDeApplied)) {
            $query->whereDate('emissao', '>=', $this->periodoDeApplied);
        }

        if (filled($this->periodoAteApplied)) {
            $query->whereDate('emissao', '<=', $this->periodoAteApplied);
        }

        if (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function buildSaldoQuery(): Builder
    {
        $query = CaixaLancamento::query();

        $this->applyContaFilter($query);

        return $query;
    }

    protected function applyContaFilter(Builder $query): void
    {
        if ($this->contaFilter === 'todas') {
            return;
        }

        if (is_numeric($this->contaFilter)) {
            $query->where('caixa_conta_id', (int) $this->contaFilter);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['codigo', 'emissao', 'documento', 'historico', 'plano_contas', 'conta', 'entrada', 'saida'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'codigo';

        $like = '%' . $term . '%';

        match ($column) {
            'codigo' => $this->applyLocalSearchByCodigo($query, $term),
            'emissao' => $this->applyLocalSearchByEmissao($query, $term),
            'documento' => $query->where('documento', 'like', $like),
            'historico' => $query->where('historico', 'like', $like),
            'plano_contas' => $query->where('plano_contas', 'like', $like),
            'conta' => $query->whereHas('conta', fn (Builder $contaQuery): Builder => $contaQuery->where('nome', 'like', $like)),
            'entrada' => $this->applyLocalSearchByMoney($query, $term, 'entrada'),
            'saida' => $this->applyLocalSearchByMoney($query, $term, 'saida'),
        };
    }

    protected function applyLocalSearchByCodigo(Builder $query, string $term): void
    {
        $digits = preg_replace('/\D/', '', $term) ?? '';

        if ($digits !== '' && is_numeric($digits)) {
            $query->where('codigo', 'like', '%' . $digits . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw('CAST(codigo AS TEXT) LIKE ?', ['%' . $term . '%']);

            return;
        }

        $query->whereRaw('CAST(codigo AS CHAR) LIKE ?', ['%' . $term . '%']);
    }

    protected function applyLocalSearchByEmissao(Builder $query, string $term): void
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $term, $matches)) {
            $query->whereDate('emissao', "{$matches[3]}-{$matches[2]}-{$matches[1]}");

            return;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $term)) {
            $query->whereDate('emissao', $term);

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("strftime('%d/%m/%Y', emissao) LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("DATE_FORMAT(emissao, '%d/%m/%Y') LIKE ?", ['%' . $term . '%']);
    }

    protected function applyLocalSearchByMoney(Builder $query, string $term, string $column): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw("CAST({$column} AS TEXT) LIKE ?", ['%' . $normalized . '%']);

                return;
            }

            $query->where($column, 'like', '%' . $normalized . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', {$column}), '.', ',') LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT({$column}, 2), '.', ',') LIKE ?", ['%' . $term . '%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }

    #[Computed]
    public function contasOptions(): array
    {
        return CaixaConta::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    #[Computed]
    public function saldoAnterior(): float
    {
        if (! filled($this->periodoDeApplied)) {
            return 0.0;
        }

        $query = $this->buildSaldoQuery()
            ->whereDate('emissao', '<', $this->periodoDeApplied);

        return (float) $query->sum('entrada') - (float) $query->sum('saida');
    }

    #[Computed]
    public function totalEntrada(): float
    {
        return (float) $this->buildListQuery()->sum('entrada');
    }

    #[Computed]
    public function totalSaida(): float
    {
        return (float) $this->buildListQuery()->sum('saida');
    }

    #[Computed]
    public function saldoAtual(): float
    {
        return $this->saldoAnterior + $this->totalEntrada - $this->totalSaida;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.caixa.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.caixa.footer-summary'),
                View::make('filament.components.erp.caixa.action-bar'),
                View::make('filament.components.erp.caixa.view-modal'),
                View::make('filament.components.erp.caixa.form-modal'),
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

    public function updatedContaFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function clearSearch(): void
    {
        $this->localSearch = '';
        $this->searchColumn = 'codigo';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSearchColumn(): void
    {
        $this->localSearch = '';
        $this->clearListSelection();
        $this->resetTable();
    }

    public function search(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function createLancamento(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.create')) {
            return;
        }

        $contaId = is_numeric($this->contaFilter)
            ? (int) $this->contaFilter
            : (int) (CaixaConta::query()->where('ativo', true)->orderBy('nome')->value('id') ?? 0);

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
        $this->caixaFormOpen = true;
    }

    public function closeCaixaForm(): void
    {
        $this->caixaFormOpen = false;
        $this->caixaFormLancamentoId = null;
        $this->caixaForm = [];
        $this->caixaFormAlert = '';
    }

    public function updatedCaixaFormFormaPagamentoId(): void
    {
        $formaId = (int) ($this->caixaForm['forma_pagamento_id'] ?? 0);
        $contaId = FormaPagamento::query()->whereKey($formaId)->value('conta_destino_id');

        if ($contaId) {
            $this->caixaForm['caixa_conta_id'] = (string) $contaId;
        }
    }

    public function saveCaixaLancamento(): void
    {
        if (! $this->erpAuthorizeOrNotify($this->caixaFormLancamentoId ? 'caixa.update' : 'caixa.create')) {
            return;
        }

        $entrada = max(0, BrDecimal::parse($this->caixaForm['entrada'] ?? 0, 2));
        $saida = max(0, BrDecimal::parse($this->caixaForm['saida'] ?? 0, 2));
        $contaId = (int) ($this->caixaForm['caixa_conta_id'] ?? 0);
        $planoId = (int) ($this->caixaForm['plano_conta_id'] ?? 0);
        $historico = trim((string) ($this->caixaForm['historico'] ?? ''));

        if (! $contaId || ! CaixaConta::query()->whereKey($contaId)->where('ativo', true)->exists()) {
            $this->caixaFormAlert = 'Selecione a conta de caixa para registrar o movimento.';
            return;
        }

        if ($historico === '') {
            $this->caixaFormAlert = 'Informe o histórico do lançamento.';
            return;
        }

        if (($entrada <= 0 && $saida <= 0) || ($entrada > 0 && $saida > 0)) {
            $this->caixaFormAlert = 'Informe um valor em Entrada ou em Saída, nunca nos dois campos ao mesmo tempo.';
            return;
        }

        $plano = $planoId ? PlanoConta::query()->whereKey($planoId)->where('ativo', true)->first() : null;

        $dados = [
            'emissao' => $this->caixaForm['emissao'] ?? now()->toDateString(),
            'documento' => mb_substr(trim((string) ($this->caixaForm['documento'] ?? '')), 0, 40) ?: null,
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
            'entrada' => $entrada,
            'saida' => $saida,
        ];

        if ($this->caixaFormLancamentoId) {
            CaixaLancamento::query()
                ->whereKey($this->caixaFormLancamentoId)
                ->where('documento', 'like', 'MANUAL-%')
                ->update($dados);
        } else {
            CaixaLancamento::query()->create([
                'codigo' => CaixaLancamento::nextCodigo(),
                'documento' => mb_substr(trim((string) ($this->caixaForm['documento'] ?? '')), 0, 40) ?: null,
                ...$dados,
            ]);
        }

        $this->closeCaixaForm();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Lançamento de caixa salvo.')->success()->send();
    }

    public function editLancamento(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.update')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');
        if (! $recordId) {
            return;
        }

        $lancamento = CaixaLancamento::query()->find($recordId);
        if (! $lancamento || ! str_starts_with(mb_strtoupper((string) $lancamento->historico, 'UTF-8'), '[MANUAL] ')) {
            Notification::make()
                ->title('Lançamento não editável')
                ->body('Somente lançamentos feitos pelo botão Novo podem ser alterados aqui.')
                ->warning()
                ->send();

            return;
        }

        $this->caixaFormLancamentoId = $lancamento->id;
        $this->caixaForm = [
            'emissao' => $lancamento->emissao?->format('Y-m-d') ?? now()->toDateString(),
            'documento' => $lancamento->documento ?? '',
            'plano_conta_id' => (string) ($lancamento->plano_conta_id ?? ''),
            'caixa_conta_id' => (string) $lancamento->caixa_conta_id,
            'forma_pagamento_id' => '',
            'historico' => preg_replace('/^\[MANUAL\]\s*/iu', '', (string) $lancamento->historico) ?? $lancamento->historico,
            'entrada' => number_format((float) $lancamento->entrada, 2, ',', '.'),
            'saida' => number_format((float) $lancamento->saida, 2, ',', '.'),
        ];
        $this->caixaFormAlert = '';
        $this->caixaFormOpen = true;
    }

    public function imprimirMovimentoCaixa(): void
    {
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

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $lancamento = CaixaLancamento::query()->find($recordId);
        if (! $lancamento || ! str_starts_with(mb_strtoupper((string) $lancamento->historico, 'UTF-8'), '[MANUAL] ')) {
            $this->caixaAttentionMessage = $this->caixaAutomaticoEstornoMessage($lancamento);

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

        CaixaLancamento::query()
            ->whereKey($id)
            ->where('historico', 'like', '[MANUAL]%')
            ->delete();

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Lançamento excluído.')
            ->success()
            ->send();
    }
}
