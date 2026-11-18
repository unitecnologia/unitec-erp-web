<?php

namespace App\Filament\Resources\ExtratoLancamentoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ExtratoLancamentoResource;
use App\Filament\Resources\ExtratoLancamentoResource\Pages\Concerns\ManagesConciliacaoManual;
use App\Filament\Resources\ExtratoLancamentoResource\Pages\Concerns\ManagesConciliacaoOfxImport;
use App\Models\CaixaConta;
use App\Models\ExtratoLancamento;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\Conciliacao\ExtratoLancamentoListQuery;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema as DbSchema;
use Livewire\Attributes\Url;

/**
 * Conciliação Bancária — C1 listagem + C2 OFX + C3 manual 1:1.
 * Visual: padrão RH Treinamentos (não redesenhar).
 */
class ListExtratoLancamentos extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesConciliacaoManual;
    use ManagesConciliacaoOfxImport;

    protected static string $resource = ExtratoLancamentoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'conta')]
    public string $caixaContaId = '';

    #[Url(as: 'de')]
    public string $periodoDe = '';

    #[Url(as: 'ate')]
    public string $periodoAte = '';

    #[Url(as: 'sit')]
    public string $situacao = 'todos';

    /** @var list<array{id:int,label:string}>|null */
    private ?array $contaOptionsCache = null;

    public function mount(): void
    {
        parent::mount();

        abort_unless(ExtratoLancamentoResource::canAccess(), 403);

        ErpScreen::set('Conciliação Bancária');

        $hoje = ErpTimezone::toLocal();
        if ($this->periodoDe === '') {
            $this->periodoDe = $hoje->copy()->startOfMonth()->toDateString();
        }
        if ($this->periodoAte === '') {
            $this->periodoAte = $hoje->toDateString();
        }
        $this->situacao = $this->normalizeSituacao($this->situacao);
        // Contas BANCO: sob demanda no select. Sem extrato no mount (sem conta).
    }

    protected static function erpListPageClass(): string
    {
        // Chrome visual aprovado: mesmos seletores CSS do RH Treinamentos (erp-rh-list.css).
        return 'erp-rh-treinamentos-page';
    }

    /**
     * @return array<int, string>
     */
    protected function erpListExtraPageClasses(): array
    {
        return ['erp-conciliacao-bancaria-page'];
    }

    protected function erpListEntityName(): string
    {
        return 'um lançamento de extrato';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-rh-treinamentos__search-text',
            'create' => null,
            'edit' => null,
            'delete' => null,
            'refresh' => 'consultar',
        ];
    }

    public function table(Table $table): Table
    {
        $heading = $this->caixaContaId === ''
            ? ($this->contaBancariaOptions() === []
                ? 'Nenhuma conta BANCO ativa nesta empresa.'
                : 'Selecione a conta bancária para listar o extrato.')
            : 'Nenhum lançamento de extrato neste filtro';

        $description = $this->caixaContaId === ''
            ? null
            : 'Se já importou o OFX, confira as datas do filtro ou clique em “Período do extrato”.';

        return $this->applyErpListSelection(
            ExtratoLancamentoResource::table($table)
                ->emptyStateHeading($heading)
                ->emptyStateDescription($description)
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.conciliacao-bancaria.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.conciliacao-bancaria.action-bar'),
                View::make('filament.components.erp.conciliacao-bancaria.ofx-summary'),
            ]);
    }

    public function updatedTableRecordsPerPage(): void
    {
        $this->clearListSelection();
        $this->resetPage();
    }

    public function updatedCaixaContaId(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedPeriodoDe(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedPeriodoAte(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function updatedSituacao(): void
    {
        $this->situacao = $this->normalizeSituacao($this->situacao);
        $this->clearListSelection();
        $this->resetTable();
    }

    public function consultar(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    /**
     * @return Collection<int, ExtratoLancamento>|Paginator|CursorPaginator
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        // Performance C1: sem conta selecionada, não consulta extrato_lancamentos.
        if ((int) $this->caixaContaId <= 0) {
            $perPage = (int) ($this->getTableRecordsPerPage() ?: 50);

            return new LengthAwarePaginator([], 0, max(1, $perPage), 1);
        }

        return parent::getTableRecords();
    }

    protected function getTableQuery(): Builder
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $contaId = (int) $this->caixaContaId;

        if ($empresaId <= 0 || $contaId <= 0 || ! DbSchema::hasTable('extrato_lancamentos')) {
            return ExtratoLancamento::query()->whereRaw('1 = 0');
        }

        if (! $this->contaBancariaPermitida($contaId, $empresaId)) {
            return ExtratoLancamento::query()->whereRaw('1 = 0');
        }

        return app(ExtratoLancamentoListQuery::class)->query([
            'empresa_id' => $empresaId,
            'caixa_conta_id' => $contaId,
            'periodo_de' => $this->periodoDe,
            'periodo_ate' => $this->periodoAte,
            'situacao' => $this->situacao,
        ]);
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function contaBancariaOptions(): array
    {
        if ($this->contaOptionsCache !== null) {
            return $this->contaOptionsCache;
        }

        if (! DbSchema::hasTable('caixa_contas')) {
            return $this->contaOptionsCache = [];
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $query = CaixaConta::query()
            ->select(['id', 'nome', 'banco_codigo', 'agencia', 'conta_numero'])
            ->where('tipo', CaixaConta::TIPO_BANCO)
            ->where('ativo', true)
            ->visiveisNaEmpresa($empresaId > 0 ? $empresaId : null)
            ->orderBy('nome')
            ->limit(200);

        $this->contaOptionsCache = $query->get()->map(static function (CaixaConta $conta): array {
            $detalhe = trim(implode(' ', array_filter([
                $conta->banco_codigo ? 'Bco '.$conta->banco_codigo : null,
                $conta->agencia ? 'Ag '.$conta->agencia : null,
                $conta->conta_numero ? 'C/C '.$conta->conta_numero : null,
            ])));

            return [
                'id' => (int) $conta->id,
                'label' => $detalhe !== ''
                    ? $conta->nome.' ('.$detalhe.')'
                    : (string) $conta->nome,
            ];
        })->values()->all();

        return $this->contaOptionsCache;
    }

    private function contaBancariaPermitida(int $contaId, int $empresaId): bool
    {
        return CaixaConta::query()
            ->whereKey($contaId)
            ->where('tipo', CaixaConta::TIPO_BANCO)
            ->where('ativo', true)
            ->visiveisNaEmpresa($empresaId)
            ->exists();
    }

    private function normalizeSituacao(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['todos', 'pendente', 'conciliado', 'ignorado'], true)
            ? $value
            : 'todos';
    }
}
