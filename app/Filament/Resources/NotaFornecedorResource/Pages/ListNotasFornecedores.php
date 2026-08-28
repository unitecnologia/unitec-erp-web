<?php

namespace App\Filament\Resources\NotaFornecedorResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\ManagesImportarXmlModal;
use App\Filament\Resources\NotaFornecedorResource;
use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Models\VendasParametro;
use App\Support\Erp\Compra\CancelarCompraService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Fiscal\DistribuicaoDfeConfig;
use App\Support\Fiscal\DistribuicaoDfeMensagens;
use App\Support\Fiscal\DistribuicaoDfeService;
use App\Support\Fiscal\NotaFornecedorConsultaChaveService;
use App\Support\Fiscal\NotaFornecedorXmlDownloadService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

class ListNotasFornecedores extends ListRecords
{
    use InteractsWithErpListPage;
    use ManagesImportarXmlModal;

    protected static string $resource = NotaFornecedorResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'data_emissao';

    #[Url(as: 'status')]
    public string $statusFilter = 'todas';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public ?string $nfFornFiscalOverlayTitulo = null;

    public ?string $nfFornFiscalOverlayMensagem = null;

    public ?string $nfFornFiscalOverlayCodigo = null;

    public ?string $nfFornFiscalOverlayOrigem = null;

    /** @var 'error'|'warning'|'info' */
    public string $nfFornFiscalOverlayTone = 'error';

    public bool $consultaChaveModalOpen = false;

    public string $consultaChaveInput = '';

    public bool $notaFornecedorDanfeModalOpen = false;

    public ?int $notaFornecedorDanfeId = null;

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Notas de Fornecedores');

        // Datas vazias = sem filtro de período (lista tudo).
        $this->periodoDe = $this->normalizePeriodDate($this->periodoDe) ?? '';
        $this->periodoAte = $this->normalizePeriodDate($this->periodoAte) ?? '';
        $this->periodoDeApplied = $this->normalizePeriodDate($this->periodoDeApplied) ?? $this->periodoDe;
        $this->periodoAteApplied = $this->normalizePeriodDate($this->periodoAteApplied) ?? $this->periodoAte;
        $this->localSearchDe = $this->normalizePeriodDate($this->localSearchDe) ?? '';
        $this->localSearchAte = $this->normalizePeriodDate($this->localSearchAte) ?? '';

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->searchColumn = $this->normalizeSearchColumn($this->searchColumn);

        CancelarCompraService::repararNotasComCompraCancelada();

        $this->dispatch(
            'erp-hydrate-nf-forn-dates',
            de: $this->periodoDe,
            ate: $this->periodoAte,
            deEmissao: $this->localSearchDe,
            ateEmissao: $this->localSearchAte,
        );
    }

    protected function normalizeSearchColumn(string $column): string
    {
        if ($column === 'data_entrada') {
            return 'periodo_entrada';
        }

        return array_key_exists($column, $this->filterFieldOptions())
            ? $column
            : 'data_emissao';
    }

    /**
     * @return array<string, string>
     */
    protected function filterFieldOptions(): array
    {
        return [
            'periodo_entrada' => 'Período (Data Entrada)',
            'data_emissao' => 'Data Emissão',
            'numero' => 'Número',
            'chave' => 'Chave',
            'cnpj' => 'CNPJ',
            'nome' => 'Fornecedor',
            'nsu' => 'NSU',
            'total' => 'Total',
        ];
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-nfe-page';
    }

    protected function erpListExtraPageClasses(): array
    {
        return ['erp-notas-fornecedores-page'];
    }

    protected function erpListEntityName(): string
    {
        return 'uma nota de fornecedor';
    }

    protected function customErpListKeyboardConfig(): array
    {
        $extraKeys = [
            'F6' => ['method' => 'openLerXmlSelecionada'],
        ];

        // Na aba Aceitas, só Ler XML / DANFE / Atualizar / Fechar ficam ativos.
        if ($this->statusFilter !== 'aceita') {
            $extraKeys = [
                'F2' => ['method' => 'openConsultaChaveModal'],
                'F3' => ['method' => 'consultarLote'],
                'F4' => ['method' => 'confirmarNota'],
                'F5' => ['method' => 'desconhecerNota'],
                ...$extraKeys,
            ];
        }

        return [
            'searchInput' => '.erp-nfe__period-from, .erp-nfe__search-text, .erp-nfe__search-date-from',
            'searchFocusKey' => 'F12',
            'extraKeys' => $extraKeys,
        ];
    }

    protected function bloquearAcaoNaAbaAceitas(string $acao): bool
    {
        if ($this->statusFilter !== 'aceita') {
            return false;
        }

        Notification::make()
            ->title("Ação \"{$acao}\" indisponível na aba Aceitas.")
            ->warning()
            ->send();

        return true;
    }

    public function table(Table $table): Table
    {
        // Seleção só pela flag da linha; clicar em outras células não marca.
        return NotaFornecedorResource::table($table)
            ->recordUrl(null)
            ->recordAction(null)
            ->recordClasses(function (\Illuminate\Database\Eloquent\Model $record): string {
                $classes = $this->erpListRecordClasses($record);

                if ((int) $this->highlightedRecordId === (int) $record->getKey()) {
                    $classes[] = 'erp-row-selected';
                }

                return implode(' ', array_filter($classes));
            });
    }

    /**
     * Marca/desmarca a nota pela flag. Só uma nota fica marcada por vez.
     */
    public function alternarSelecionado(int|string $recordId): void
    {
        $id = (int) $recordId;

        if ((int) $this->highlightedRecordId === $id) {
            $this->highlightedRecordId = null;

            return;
        }

        $this->highlightedRecordId = $id;
    }

    protected function getTableQuery(): Builder
    {
        return $this->buildListQuery();
    }

    protected function buildListQuery(): Builder
    {
        $query = parent::getTableQuery();

        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId !== null) {
            $query->where(function (Builder $empresaQuery) use ($empresaId): void {
                $empresaQuery
                    ->where('empresa_id', $empresaId)
                    ->orWhereNull('empresa_id');
            });
        }

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->isPeriodoEntradaFilter()) {
            if ($de = $this->normalizePeriodDate($this->periodoDeApplied)) {
                $query->whereDate('data_entrada', '>=', $de);
            }

            if ($ate = $this->normalizePeriodDate($this->periodoAteApplied)) {
                $query->whereDate('data_entrada', '<=', $ate);
            }
        } elseif ($this->isDateSearchColumn()) {
            $this->applyLocalSearchByDateRange($query);
        } elseif (filled($this->localSearch)) {
            $this->applyLocalSearch($query, $this->localSearch);
        }

        return $query;
    }

    protected function isDateSearchColumn(): bool
    {
        return $this->searchColumn === 'data_emissao';
    }

    protected function isPeriodoEntradaFilter(): bool
    {
        return $this->searchColumn === 'periodo_entrada';
    }

    protected function applyLocalSearchByDateRange(Builder $query): void
    {
        $de = $this->normalizePeriodDate($this->localSearchDe);
        $ate = $this->normalizePeriodDate($this->localSearchAte);

        if ($de === null && $ate === null) {
            return;
        }

        $column = 'data_emissao';

        if ($de !== null) {
            $query->whereDate($column, '>=', $de);
        }

        if ($ate !== null) {
            $query->whereDate($column, '<=', $ate);
        }
    }

    protected function normalizePeriodDate(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $this->isValidPeriodIsoDate($value) ? $value : null;
        }

        // dd/mm/yyyy (não usar Carbon::parse — interpreta m/d/Y estilo US)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            $iso = sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);

            return $this->isValidPeriodIsoDate($iso) ? $iso : null;
        }

        // ddmmyyyy (máscara sem barras / valor digitado)
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $matches) === 1) {
            $iso = sprintf('%s-%s-%s', $matches[3], $matches[2], $matches[1]);

            return $this->isValidPeriodIsoDate($iso) ? $iso : null;
        }

        return null;
    }

    protected function isValidPeriodIsoDate(string $iso): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    /**
     * @return array<int, string>
     */
    protected function localSearchColumns(): array
    {
        return ['data_entrada', 'data_emissao', 'numero', 'chave', 'cnpj', 'nome', 'nsu', 'total'];
    }

    protected function applyLocalSearch(Builder $query, string $term): void
    {
        $term = mb_strtoupper(trim($term), 'UTF-8');

        if ($term === '') {
            return;
        }

        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)
            ? $this->searchColumn
            : 'nome';

        $like = '%' . $term . '%';

        match ($column) {
            'numero', 'nome', 'nsu' => $query->where($column, 'like', $like),
            'chave', 'cnpj' => $query->where($column, 'like', '%' . preg_replace('/\D/', '', $term) . '%'),
            'total' => $this->applyLocalSearchByTotal($query, $term),
            default => null,
        };
    }

    protected function applyLocalSearchByTotal(Builder $query, string $term): void
    {
        $normalized = str_replace(['R$', ' '], '', $term);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        if (is_numeric($normalized)) {
            if ($this->databaseDriver($query) === 'sqlite') {
                $query->whereRaw('CAST(total AS TEXT) LIKE ?', ['%' . $normalized . '%']);

                return;
            }

            $query->where('total', 'like', '%' . $normalized . '%');

            return;
        }

        if ($this->databaseDriver($query) === 'sqlite') {
            $query->whereRaw("REPLACE(printf('%.2f', total), '.', ',') LIKE ?", ['%' . $term . '%']);

            return;
        }

        $query->whereRaw("REPLACE(FORMAT(total, 2), '.', ',') LIKE ?", ['%' . $term . '%']);
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($status);
        $this->clearListSelection();
        $this->resetTable();
    }

    protected function normalizeStatusFilter(string $status): string
    {
        if ($status === 'todas') {
            return 'todas';
        }

        return in_array($status, NotaFornecedor::statusKeys(), true)
            ? $status
            : 'todas';
    }

    public function search(): void
    {
        $this->resetTable();
    }

    public function applyFilter(): void
    {
        if ($this->isPeriodoEntradaFilter()) {
            $this->applyPeriodFilter();

            return;
        }

        if ($this->isDateSearchColumn()) {
            $this->localSearchDe = $this->normalizePeriodDate($this->localSearchDe) ?? '';
            $this->localSearchAte = $this->normalizePeriodDate($this->localSearchAte) ?? '';

            if (
                filled($this->localSearchDe)
                && filled($this->localSearchAte)
                && $this->localSearchDe > $this->localSearchAte
            ) {
                [$this->localSearchDe, $this->localSearchAte] = [$this->localSearchAte, $this->localSearchDe];
            }
        }

        $this->clearListSelection();
        $this->resetPage();
        $this->resetTable();
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDe = $this->normalizePeriodDate($this->periodoDe) ?? '';
        $this->periodoAte = $this->normalizePeriodDate($this->periodoAte) ?? '';

        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;

        if (
            filled($this->periodoDeApplied)
            && filled($this->periodoAteApplied)
            && $this->periodoDeApplied > $this->periodoAteApplied
        ) {
            [$this->periodoDeApplied, $this->periodoAteApplied] = [$this->periodoAteApplied, $this->periodoDeApplied];
            $this->periodoDe = $this->periodoDeApplied;
            $this->periodoAte = $this->periodoAteApplied;
        }

        $this->clearListSelection();
        $this->resetPage();
        $this->resetTable();

        $this->dispatch(
            'erp-hydrate-nf-forn-dates',
            de: $this->periodoDe,
            ate: $this->periodoAte,
            deEmissao: $this->localSearchDe,
            ateEmissao: $this->localSearchAte,
        );
    }

    public function updatedSearchColumn(): void
    {
        $this->searchColumn = $this->normalizeSearchColumn($this->searchColumn);
        $this->localSearch = '';
        $this->localSearchDe = '';
        $this->localSearchAte = '';
        $this->clearListSelection();
        $this->resetPage();
        $this->resetTable();

        if ($this->isPeriodoEntradaFilter()) {
            $this->dispatch(
                'erp-hydrate-nf-forn-dates',
                de: $this->periodoDe,
                ate: $this->periodoAte,
                deEmissao: '',
                ateEmissao: '',
            );
        }
    }

    public function openNotaFornecedorVisualizar(int $notaId): void
    {
        // Não usar highlightRecord(): ele faz skipRender() e o modal DANFE não aparece.
        $this->highlightedRecordId = (int) $notaId;

        $nota = NotaFornecedor::query()->find($notaId);

        if (! $nota) {
            Notification::make()->title('Nota não encontrada.')->danger()->send();

            return;
        }

        if (blank($nota->chave)) {
            Notification::make()
                ->title('Nota sem chave de acesso para gerar o DANFE.')
                ->warning()
                ->send();

            return;
        }

        // DANFE só com dados locais (XML completo ou resumo DF-e) — sem SEFAZ.
        $this->notaFornecedorDanfeId = (int) $nota->id;
        $this->notaFornecedorDanfeModalOpen = true;
    }

    public function closeNotaFornecedorDanfe(): void
    {
        $this->notaFornecedorDanfeModalOpen = false;
        $this->notaFornecedorDanfeId = null;
    }

    public function printNotaFornecedorDanfe(): void
    {
        if (! $this->notaFornecedorDanfeId) {
            return;
        }

        $url = route('erp.reports.nota-fornecedor-danfe', [
            'nota' => $this->notaFornecedorDanfeId,
            'auto' => 1,
        ]);

        $this->js('window.ErpNfFornPrint?.openDanfe(' . Js::from($url) . ')');
    }

    public function downloadNotaFornecedorDanfePdf(): void
    {
        if (! $this->notaFornecedorDanfeId) {
            return;
        }

        $url = route('erp.reports.nota-fornecedor-danfe', [
            'nota' => $this->notaFornecedorDanfeId,
            'pdf' => 1,
        ]);

        $this->js('window.open(' . Js::from($url) . ', "_blank")');
    }

    public function openNotaFornecedorVisualizarSelecionada(): void
    {
        $id = $this->highlightedRecordIdOrNotify('visualizar');

        if (! $id) {
            return;
        }

        $this->openNotaFornecedorVisualizar($id);
    }

    public function confirmarNota(): void
    {
        if ($this->bloquearAcaoNaAbaAceitas('Confirmar')) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('confirmar');

        if (! $id) {
            return;
        }

        $nota = NotaFornecedor::query()->find($id);

        if (! $nota) {
            Notification::make()->title('Nota não encontrada.')->danger()->send();

            return;
        }

        if ($nota->status !== NotaFornecedor::STATUS_PENDENTE) {
            Notification::make()
                ->title('Somente notas pendentes podem ser confirmadas.')
                ->warning()
                ->send();

            return;
        }

        $empresa = $this->resolveEmpresaAtiva();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não encontrada para Ciência da Operação.')
                ->danger()
                ->send();

            return;
        }

        $xmlLiberado = false;

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(120);
            }

            $nota = (new NotaFornecedorXmlDownloadService())->ensureProcNfe($nota, $empresa);
            $xmlLiberado = true;
        } catch (FiscalEngineException $exception) {
            if ($exception->sefazCodigo === '596' || str_contains(mb_strtolower($exception->getMessage(), 'UTF-8'), 'prazo de 10 dias')) {
                $this->showNfFornFiscalOverlay(
                    'XML completo indisponível (prazo de 10 dias)',
                    $exception->getMessage()
                    ."\n\nA nota será marcada como aceita. Para ler os itens, solicite o XML (procNFe) ao fornecedor.",
                    $exception->sefazCodigo,
                    'Ciência da Operação',
                    'warning',
                );
            } else {
                $this->showNfFornFiscalOverlay(
                    'Falha na Ciência da Operação',
                    $exception->getMessage(),
                    $exception->sefazCodigo,
                    'Ciência da Operação',
                );

                return;
            }
        } catch (\Throwable $exception) {
            $this->showNfFornFiscalOverlay(
                'Falha na Ciência da Operação',
                $exception->getMessage(),
                null,
                'Ciência da Operação',
            );

            return;
        }

        $nota->update(['status' => NotaFornecedor::STATUS_ACEITA]);

        if ($xmlLiberado) {
            Notification::make()
                ->title('Nota confirmada')
                ->body('Ciência da Operação enviada. XML liberado — use F6 | Ler XML na aba Aceitas.')
                ->success()
                ->send();
        }

        $this->resetTable();
    }

    public function openConsultaChaveModal(): void
    {
        if ($this->bloquearAcaoNaAbaAceitas('Consulta Chave')) {
            return;
        }

        $this->consultaChaveInput = preg_replace('/\D/', '', $this->selectedChave) ?? '';
        $this->consultaChaveModalOpen = true;
        $this->dispatch('erp-nf-forn-focus-consulta-chave');
    }

    public function closeConsultaChaveModal(): void
    {
        $this->consultaChaveModalOpen = false;
    }

    public function confirmarConsultaChave(): void
    {
        $empresa = $this->resolveEmpresaAtiva();

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não encontrada para consulta na SEFAZ.')
                ->warning()
                ->send();

            return;
        }

        $chave = preg_replace('/\D/', '', $this->consultaChaveInput) ?? '';

        if (strlen($chave) !== 44) {
            Notification::make()
                ->title('Chave inválida')
                ->body('Informe a chave de acesso da NF-e com 44 dígitos.')
                ->warning()
                ->send();

            return;
        }

        try {
            $resultado = (new NotaFornecedorConsultaChaveService())->consultar($empresa, $chave);
        } catch (FiscalEngineException $exception) {
            $consumoIndevido = DistribuicaoDfeMensagens::isConsumoIndevidoException($exception);

            if ($consumoIndevido) {
                $parametros = VendasParametro::forEmpresa((int) $empresa->id);
                DistribuicaoDfeConfig::registrarBloqueioConsumoIndevido($parametros);
                $parametros->refresh();

                $mensagem = DistribuicaoDfeMensagens::consumoIndevido();
                $proximaTentativa = DistribuicaoDfeMensagens::mensagemProximaTentativa($parametros->dfe_bloqueado_ate);

                if ($proximaTentativa !== '') {
                    $mensagem .= "\n\n" . $proximaTentativa;
                }

                $this->showNfFornFiscalOverlay(
                    'Consulta temporariamente bloqueada',
                    $mensagem,
                    DistribuicaoDfeMensagens::CSTAT_CONSUMO_INDEVIDO,
                    'Distribuição DF-e',
                );

                return;
            }

            $overlay = DistribuicaoDfeMensagens::mensagemOverlay($exception);

            $this->showNfFornFiscalOverlay(
                'Falha na consulta por chave',
                $overlay['mensagem'],
                $overlay['codigo'],
                'Distribuição DF-e',
            );

            return;
        }

        $this->closeConsultaChaveModal();
        $this->clearListSelection();
        $this->resetTable();

        $titulo = $resultado['criada'] ? 'Nota importada' : 'Nota atualizada';

        Notification::make()
            ->title($titulo)
            ->body(
                sprintf(
                    '%s Nº %s — %s',
                    $resultado['mensagem'],
                    $resultado['nota']->numero,
                    $resultado['nota']->nome,
                ),
            )
            ->success()
            ->send();
    }

    public function consultarLote(): void
    {
        if ($this->bloquearAcaoNaAbaAceitas('Consulta Lote')) {
            return;
        }

        $empresa = $this->resolveEmpresaAtiva();

        if (! $empresa) {
            $this->dispatch('erp-nf-forn-hide-consulta-progress');

            Notification::make()
                ->title('Empresa não encontrada para consulta na SEFAZ.')
                ->warning()
                ->send();

            return;
        }

        try {
            $resultado = (new DistribuicaoDfeService())->consultarLote($empresa);
        } catch (FiscalEngineException $exception) {
            $consumoIndevido = DistribuicaoDfeMensagens::isConsumoIndevidoException($exception);
            $parametros = VendasParametro::forEmpresa((int) $empresa->id);

            if ($consumoIndevido) {
                DistribuicaoDfeConfig::registrarBloqueioConsumoIndevido($parametros);
                $parametros->refresh();
            }

            $overlay = DistribuicaoDfeMensagens::mensagemOverlay($exception);

            $mensagem = $consumoIndevido
                ? DistribuicaoDfeMensagens::consumoIndevido()
                : $overlay['mensagem'];

            if ($consumoIndevido) {
                $proximaTentativa = DistribuicaoDfeMensagens::mensagemProximaTentativa($parametros->dfe_bloqueado_ate);

                if ($proximaTentativa !== '') {
                    $mensagem .= "\n\n" . $proximaTentativa;
                }
            }

            $this->showNfFornFiscalOverlay(
                $consumoIndevido ? 'Consulta temporariamente bloqueada' : 'Falha na consulta de lote',
                $mensagem,
                $consumoIndevido ? DistribuicaoDfeMensagens::CSTAT_CONSUMO_INDEVIDO : $overlay['codigo'],
                'Distribuição DF-e',
            );

            $this->dispatch('erp-nf-forn-hide-consulta-progress');

            return;
        }

        $this->dispatch('erp-nf-forn-hide-consulta-progress');

        $this->clearListSelection();
        $this->resetTable();

        $importadas = (int) $resultado['importadas'];
        $atualizadas = (int) $resultado['atualizadas'];

        if ($importadas === 0 && $atualizadas === 0) {
            Notification::make()
                ->title('Consulta de lote concluída')
                ->body($resultado['mensagem'] . ' Último NSU: ' . $resultado['ultimo_nsu'] . '.')
                ->info()
                ->send();

            return;
        }

        Notification::make()
            ->title('Consulta de lote concluída.')
            ->body(
                sprintf(
                    '%d nota(s) nova(s), %d já existente(s) atualizada(s) (mesma chave). NSU: %s',
                    $importadas,
                    $atualizadas,
                    $resultado['ultimo_nsu'],
                ),
            )
            ->success()
            ->send();
    }

    public function desconhecerNota(): void
    {
        if ($this->bloquearAcaoNaAbaAceitas('Desconhecer')) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('desconhecer');

        if (! $id) {
            return;
        }

        $nota = NotaFornecedor::query()->find($id);

        if (! $nota) {
            Notification::make()->title('Nota não encontrada.')->danger()->send();

            return;
        }

        if ($nota->status === NotaFornecedor::STATUS_GEROU_COMPRAS) {
            Notification::make()
                ->title('Nota já vinculada a uma compra.')
                ->warning()
                ->send();

            return;
        }

        $nota->update(['status' => NotaFornecedor::STATUS_DESCONHECIDA]);

        Notification::make()->title('Nota marcada como desconhecida.')->success()->send();
        $this->resetTable();
    }

    #[Computed]
    public function empresaNome(): string
    {
        $empresaId = ErpContext::currentEmpresaId();

        $empresa = $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;

        if (! $empresa) {
            return '—';
        }

        return $empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social);
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->buildListQuery()->sum('total');
    }

    #[Computed]
    public function selectedChave(): string
    {
        if (! $this->highlightedRecordId) {
            return '';
        }

        return NotaFornecedor::query()
            ->whereKey($this->highlightedRecordId)
            ->value('chave') ?? '';
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'confirmar' => 'uma nota (flag) pendente para confirmar',
            'desconhecer' => 'uma nota (flag) para desconhecer',
            'visualizar' => 'uma nota (flag) para visualizar o DANFE',
            'ler o XML' => 'uma nota (flag) para ler o XML',
            default => 'uma nota na lista (flag)',
        };
    }

    protected function resolveEmpresaAtiva(): ?Empresa
    {
        $empresaId = ErpContext::currentEmpresaId();

        return $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;
    }

    public function closeNfFornFiscalOverlay(): void
    {
        $this->nfFornFiscalOverlayTitulo = null;
        $this->nfFornFiscalOverlayMensagem = null;
        $this->nfFornFiscalOverlayCodigo = null;
        $this->nfFornFiscalOverlayOrigem = null;
        $this->nfFornFiscalOverlayTone = 'error';
    }

    protected function showNfFornFiscalOverlay(
        string $titulo,
        string $mensagem,
        ?string $codigo = null,
        string $origem = 'Distribuição DF-e',
        string $tone = 'error',
    ): void {
        $this->nfFornFiscalOverlayTitulo = mb_strtoupper($titulo, 'UTF-8');
        $this->nfFornFiscalOverlayMensagem = trim($mensagem) !== '' ? trim($mensagem) : null;
        $this->nfFornFiscalOverlayCodigo = filled($codigo) ? $codigo : null;
        $this->nfFornFiscalOverlayOrigem = $origem;
        $this->nfFornFiscalOverlayTone = in_array($tone, ['error', 'warning', 'info'], true) ? $tone : 'error';
        $this->dispatch('erp-nf-forn-focus-fiscal-overlay');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.notas-fornecedores.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.notas-fornecedores.footer-total'),
                View::make('filament.components.erp.notas-fornecedores.action-bar'),
                View::make('filament.components.erp.notas-fornecedores.fiscal-progress'),
                View::make('filament.components.erp.notas-fornecedores.fiscal-overlay'),
                View::make('filament.components.erp.notas-fornecedores.consulta-chave-modal'),
                View::make('filament.components.erp.notas-fornecedores.danfe-modal'),
                View::make('filament.components.erp.notas-fornecedores.importar-xml-modal'),
                View::make('filament.components.erp.notas-fornecedores.product-overlay'),
            ]);
    }
}
