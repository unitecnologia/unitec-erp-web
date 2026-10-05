<?php

namespace App\Filament\Pages;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use App\Support\Erp\MunicipioLookupService;
use App\Support\Erp\Nfse\Ipm\NfseIpmImpressaoViewData;
use App\Support\Erp\Nfse\NfseImpressao;
use App\Support\Erp\Nfse\NfseFromOrdemServico;
use App\Support\Erp\Nfse\NfseGravarService;
use App\Support\Erp\Nfse\NfseNaoGravada;
use App\Support\Erp\Nfse\NfseObra;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Erp\Nfse\Ipm\NfseIpmCliente;
use App\Support\Erp\Nfse\Ipm\NfseIpmEmitirService;
use App\Support\Erp\Nfse\NfseTransmissaoResultado;
use App\Support\Erp\Nfse\NfseTransmitirService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use RuntimeException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class NfsePage extends Page
{
    use Concerns\ManagesNfseContadorEmail;
    use Concerns\ManagesNfseEspelhoModal;
    use Concerns\ManagesNfseImportOs;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $title = '';

    protected static ?string $slug = 'nfse';

    protected static ?string $routePath = 'nfse';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'tomador';

    /** @var list<string> */
    public array $searchFieldsActive = ['tomador', 'data_emissao'];

    /** @var array<string, string> */
    public array $localSearchByField = [];

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'todas';

    public int $tableRecordsPerPage = 50;

    public bool $nfseModalOpen = false;

    public bool $nfseConfirmarProducao = false;

    public ?string $nfseFiscalSucessoDetalhe = null;

    public ?int $nfseFiscalSucessoId = null;

    public ?int $nfseFiscalSucessoOsId = null;

    public bool $nfseFiscalSucessoPodeGerarNfe = false;

    public ?string $nfseFiscalErroTitulo = null;

    public ?string $nfseFiscalErroCodigo = null;

    public ?string $nfseFiscalErroMensagem = null;

    public bool $nfseFiscalErroSefin = false;

    public string $nfseFiscalErroRotulo = '';

    public string $nfseFiscalErroOrigem = '';

    public bool $nfseEnviarModalOpen = false;

    public bool $nfseDanfseModalOpen = false;

    public ?int $nfseDanfseModalId = null;

    public string $nfseEnviarEmail = '';

    public string $nfseEnviarWhatsApp = '';

    public string $nfseEnviarAssunto = '';

    public string $nfseEnviarMensagem = '';

    public ?string $nfseEnviarStatus = null;

    public ?int $nfseId = null;

    public string $nfseStatus = Nfse::STATUS_ABERTA;

    public string $nfseSefinMensagem = '';

    public ?bool $nfseCertificadoOk = null;

    public string $nfseSerieDps = '';

    public string $nfseNumeroDps = '';

    public string $nfseNumeroNfse = '';

    public ?int $highlightedRecordId = null;

    public string $nfseTomador = '';

    public ?int $nfseTomadorId = null;

    public string $nfseTomadorSelecionadoLabel = '';

    public string $nfseTomadorCpfCnpj = '';

    public string $nfseTomadorTelefone = '';

    public string $nfseTomadorEndereco = '';

    public string $nfseTomadorNumero = '';

    public string $nfseTomadorBairro = '';

    public string $nfseTomadorCep = '';

    public string $nfseTomadorCidade = '';

    public string $nfseTomadorUf = '';

    public string $nfseTomadorCidadeCodigo = '';

    public string $nfseTomadorEmail = '';

    /** @var list<array<string, mixed>> */
    public array $nfseTomadorSugestoes = [];

    public bool $nfseTomadorSugestoesOpen = false;

    public int $nfseTomadorSugestaoIndex = 0;

    public string $nfseCompetencia = '';

    public string $nfseDataEmissao = '';

    public string $nfseTribIssqn = Nfse::TRIB_ISSQN_TRIBUTAVEL;

    public string $nfseTpRetIssqn = Nfse::TP_RET_ISSQN_NAO_RETIDO;

    public string $nfseMunicipioCodigo = '';

    public string $nfseMunicipioNome = '';

    public string $nfseMunicipioUf = '';

    public string $nfseMunicipioNomeSelecionado = '';

    /** @var list<array{codigo: string, nome: string, uf: string}> */
    public array $nfseMunicipioSugestoes = [];

    public bool $nfseMunicipioSugestoesOpen = false;

    public int $nfseMunicipioSugestaoIndex = 0;

    public string $nfseServicoBusca = '';

    public string $nfseServicoCodigo = '';

    public ?int $nfseServicoId = null;

    public string $nfseServicoDescricao = '';

    public string $nfseServicoSelecionadoLabel = '';

    public string $nfseServicoUnidade = '';

    public string $nfseServicoQuantidade = '';

    public string $nfseServicoValor = '';

    public string $nfseServicoDesconto = '';

    public string $nfseServicoAcrescimo = '';

    public string $nfseServicoCtribNac = '';

    public bool $nfseDescontoModalOpen = false;

    public ?string $nfseServicoAjusteAlvo = null;

    public ?int $nfseServicoAjusteRowIndex = null;

    public string $nfseServicoAjusteTipo = 'desconto';

    public string $nfseServicoAjusteModo = 'percentual';

    public string $nfseServicoAjusteValor = '0,00';

    /** @var list<array<string, mixed>> */
    public array $nfseServicoSugestoes = [];

    public bool $nfseServicoSugestoesOpen = false;

    public int $nfseServicoSugestaoIndex = 0;

    /** @var list<array<string, mixed>> */
    public array $nfseServicos = [];

    public ?int $nfseServicoLinhaIndex = null;

    public ?int $nfseServicoExcluirIndex = null;

    public int $nfseServicoSeq = 0;

    /**
     * @return list<string>
     */
    public static function statusTabs(): array
    {
        return [
            'todas',
            'aberta',
            'autorizada',
            'transmitida',
            'cancelada',
            'substituida',
            'rejeitada',
            'contingencia',
        ];
    }

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('nfse.access');
    }

    public function mount(): void
    {
        ErpScreen::set('NFS-e');

        $this->statusFilter = $this->normalizeStatusFilter($this->statusFilter);
        $this->searchFieldsActive = $this->ensureTwoSearchFields($this->normalizedSearchFieldsActive());
        $this->searchColumn = $this->searchFieldsActive[array_key_last($this->searchFieldsActive)] ?? 'tomador';

        if ($this->activeDateSearchColumn() !== null && $this->localSearchDe === '' && $this->localSearchAte === '') {
            $this->applyCurrentMonthDateFilter();
        }

        $osId = (int) request()->query('os', 0);

        if ($osId > 0) {
            $this->abrirNfseDaOrdemServico($osId);
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-list-page',
            'erp-nfe-page',
            'erp-nfse-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.nfse.screen'),
                View::make('filament.components.erp.nfse.grid'),
                View::make('filament.components.erp.nfse.footer-total'),
                View::make('filament.components.erp.nfse.action-bar'),
                View::make('filament.components.erp.nfse.lancamento-modal'),
                View::make('filament.components.erp.nfse.import-os-modal'),
                View::make('filament.components.erp.nfse.espelho-modal'),
                View::make('filament.components.erp.nfse.fiscal-overlays'),
                View::make('filament.components.erp.nfse.enviar-modal'),
                View::make('filament.components.erp.nfse.danfse-modal'),
                View::make('filament.components.erp.nfse.email-contador-modal'),
            ]);
    }

    #[Computed]
    public function empresaNome(): string
    {
        $empresa = ErpContext::currentEmpresa();

        if (! $empresa) {
            return '—';
        }

        return $empresa->fantasia ?: ($empresa->nome ?: $empresa->razao_social);
    }

    #[Computed]
    public function nfseRegistros(): Collection
    {
        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId === null) {
            return collect();
        }

        $query = Nfse::query()
            ->with(['ordemServico:id,numero'])
            ->where('empresa_id', $empresaId);

        if ($this->statusFilter !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                $this->aplicarFiltroDataNfse($query, $column);

                continue;
            }

            $term = trim((string) ($this->localSearchByField[$column] ?? ''));

            if ($term === '') {
                continue;
            }

            $this->aplicarFiltroTextoNfse($query, $column, $term);
        }

        return $query
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->limit(max(1, (int) $this->tableRecordsPerPage))
            ->get();
    }

    #[Computed]
    public function nfseListagemTotalFormatado(): string
    {
        $soma = '0.00';

        foreach ($this->nfseRegistros as $nfse) {
            $parcela = $this->nfseNormalizarDecimal($nfse->total, 2) ?? '0.00';
            $soma = $this->nfseSomarDecimal($soma, $parcela);
        }

        return $this->nfseFormatarDecimal($soma, 2);
    }

    public function formatarNfseListagemDinheiro(mixed $value): string
    {
        return $this->nfseFormatarDecimal($this->nfseNormalizarDecimal($value, 2) ?? '0.00', 2);
    }

    public function highlightRecord(int|string $recordId): void
    {
        $this->highlightedRecordId = (int) $recordId;
        $this->skipRender();
    }

    /**
     * @return array<string, mixed>
     */
    public function getErpListKeyboardConfigForView(): array
    {
        return [
            'pageClass' => 'erp-nfe-page erp-nfse-page',
            'searchInput' => '.erp-nfe__search-text, .erp-nfe__search-date-from, .erp-field-dd__btn',
            'create' => 'createNfse',
            'edit' => 'editNfse',
            'refresh' => 'refreshTable',
            'extraKeys' => [
                'F4' => ['method' => 'cancelarNfse'],
                'F7' => ['method' => 'imprimirNfse'],
                'F9' => ['method' => 'enviarNfse'],
                'F10' => ['method' => 'printRelatorioNfse'],
                'F12' => ['method' => 'gerarPdfNfse'],
            ],
        ];
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $this->normalizeStatusFilter($filter);
    }

    public function toggleSearchField(string $column): void
    {
        $allowed = $this->localSearchColumns();

        if (! in_array($column, $allowed, true)) {
            return;
        }

        $active = $this->ensureTwoSearchFields($this->normalizedSearchFieldsActive());

        if (in_array($column, $active, true)) {
            $active = array_values(array_filter($active, fn (string $item): bool => $item !== $column));
            $active[] = $column;
            $this->searchFieldsActive = $active;
            $this->searchColumn = $column;

            return;
        }

        $active[] = $column;
        $this->searchFieldsActive = array_values(array_slice($active, -2));
        $this->searchColumn = $column;
        $this->pruneLocalSearchByField();

        if ($this->activeDateSearchColumn() !== null) {
            $this->applyCurrentMonthDateFilter();
        } else {
            $this->localSearchDe = '';
            $this->localSearchAte = '';
        }
    }

    public function search(): void
    {
        $this->skipRender();
    }

    public function createNfse(): void
    {
        if ($this->nfseModalOpen) {
            return;
        }

        $this->resetNfseModalForm();
        $this->preencherNfseMunicipioDaEmpresa();
        $this->nfseModalOpen = true;
    }

    protected function abrirNfseDaOrdemServico(int $id): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        $ordem = OrdemServico::query()
            ->with(['cliente', 'itens.product'])
            ->when($empresaId !== null, fn ($query) => $query->where(function ($query) use ($empresaId): void {
                $query->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
            }))
            ->find($id);

        if ($ordem === null || $ordem->cliente === null) {
            Notification::make()->title('Não foi possível abrir a NFS-e desta OS.')->warning()->send();

            return;
        }

        $motivo = NfseFromOrdemServico::motivoBloqueio($ordem);

        if ($motivo !== null) {
            Notification::make()->title($motivo)->warning()->send();

            return;
        }

        $existente = Nfse::query()
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->where('ordem_servico_id', $ordem->id)
            ->orderByDesc('id')
            ->first();

        if ($existente !== null) {
            $this->resetNfseModalForm();
            $this->preencherNfseMunicipioDaEmpresa();
            $this->aplicarNfseGravada($existente);
            $this->nfseModalOpen = true;

            return;
        }

        $this->resetNfseModalForm();
        $this->preencherNfseMunicipioDaEmpresa();
        $this->aplicarNfseTomadorSugestao($this->mapearNfseTomador($ordem->cliente));
        $this->nfseServicos = [];
        $seq = 0;

        foreach (NfseFromOrdemServico::servicos($ordem) as $item) {
            $produto = $item->product;

            if ($produto === null) {
                continue;
            }

            $quantidade = NfseFromOrdemServico::quantidade($item) ?? '0.000';
            $valor = NfseFromOrdemServico::valor($item) ?? '0.00';
            $total = $this->nfseMultiplicarDecimal($quantidade, $valor);
            $seq++;

            $this->nfseServicos[] = [
                'key' => 'nfse-os-'.$ordem->id.'-'.$item->id,
                'rev' => 0,
                'product_id' => (int) $produto->id,
                'codigo' => (string) ($produto->codigo ?? ''),
                'descricao' => NfseFromOrdemServico::descricao($item),
                'unidade' => (string) ($produto->unidade ?? ''),
                'quantidade' => $this->nfseFormatarDecimal($quantidade, 3),
                'valor' => $this->nfseFormatarDecimal($valor, 2),
                'desconto' => '0,00',
                'acrescimo' => '0,00',
                'total' => $this->nfseFormatarDecimal($total, 2),
                'total_decimal' => $total,
                'c_trib_nac' => $produto->c_trib_nac,
                'c_nbs' => $produto->c_nbs,
                'c_trib_mun' => $produto->c_trib_mun,
                'c_ind_op' => $produto->c_ind_op,
                'os_id' => (int) $ordem->id,
            ];
        }

        $this->nfseServicoSeq = $seq;
        $this->nfseServicoLinhaIndex = $this->nfseServicos === [] ? null : 0;
        $this->nfseOsOrigemId = (int) $ordem->id;
        $laudo = trim((string) ($ordem->laudo ?? ''));
        $this->nfseOsLaudoPreservado = $laudo;
        $this->nfseModalOpen = true;
    }

    public function gravarNfse(): void
    {
        if (! $this->nfseModalOpen || $this->nfseServicoExcluirIndex !== null || $this->nfseConfirmarProducao) {
            return;
        }

        if ($this->nfseSomenteLeitura()) {
            Notification::make()
                ->title('NFS-e autorizada não pode ser alterada.')
                ->warning()
                ->send();

            return;
        }

        try {
            $nfse = $this->persistirNfseAberta();
        } catch (NfseNaoGravada $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível gravar a NFS-e.')
                ->danger()
                ->send();

            return;
        }

        $this->aplicarNfseGravada($nfse);
        unset($this->nfseRegistros, $this->nfseListagemTotalFormatado);

        // Garante F3 Transmitir liberado após F2 (nova ou edição).
        $this->dispatch('erp-nfse-sync-transmit-btn');

        Notification::make()
            ->title('NFS-e gravada.')
            ->success()
            ->send();
    }

    public function transmitirNfse(): void
    {
        if (! $this->nfseModalOpen || $this->nfseServicoExcluirIndex !== null || $this->nfseConfirmarProducao || $this->nfseEnviarModalOpen || filled($this->nfseFiscalErroTitulo) || filled($this->nfseFiscalSucessoDetalhe)) {
            $this->dispatch('erp-nfse-hide-fiscal-progress');

            return;
        }

        if ($this->nfseSomenteLeitura()) {
            $this->dispatch('erp-nfse-hide-fiscal-progress');
            $this->mostrarNfseJaAutorizadaOuErro();

            return;
        }

        $motivo = $this->motivoBloqueioTransmissao();

        if ($motivo !== null) {
            $this->dispatch('erp-nfse-hide-fiscal-progress');
            $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL TRANSMITIR A NFS-E', $motivo);

            return;
        }

        if ($this->nfseAmbienteAtual() === NfseSefinAmbiente::Producao) {
            $this->dispatch('erp-nfse-hide-fiscal-progress');
            $this->nfseConfirmarProducao = true;
            $this->js('window.__erpNfseShowOverlay && window.__erpNfseShowOverlay("erp-nfse-fiscal-confirma")');

            return;
        }

        $this->executarTransmissaoNfse();
    }

    public function confirmarTransmissaoProducao(): void
    {
        if (! $this->nfseModalOpen || ! $this->nfseConfirmarProducao) {
            return;
        }

        $this->nfseConfirmarProducao = false;

        if ($this->nfseAmbienteAtual() !== NfseSefinAmbiente::Producao) {
            return;
        }

        $this->executarTransmissaoNfse();
    }

    public function cancelarTransmissaoProducao(): void
    {
        $this->nfseConfirmarProducao = false;
        $this->js("document.getElementById('erp-nfse-fiscal-confirma')?.style.setProperty('display','none')");
    }

    public function closeNfseFiscalSucesso(): void
    {
        $this->nfseFiscalSucessoDetalhe = null;
        $this->nfseFiscalSucessoId = null;
        $this->nfseFiscalSucessoOsId = null;
        $this->nfseFiscalSucessoPodeGerarNfe = false;
        $this->js("document.getElementById('erp-nfse-fiscal-sucesso')?.style.setProperty('display','none')");
    }

    public function closeNfseFiscalErro(): void
    {
        $this->nfseFiscalErroTitulo = null;
        $this->nfseFiscalErroCodigo = null;
        $this->nfseFiscalErroMensagem = null;
        $this->nfseFiscalErroSefin = false;
        $this->nfseFiscalErroRotulo = '';
        $this->nfseFiscalErroOrigem = '';
        $this->js("document.getElementById('erp-nfse-fiscal-erro')?.style.setProperty('display','none')");
    }

    public function imprimirNfseAutorizada(): void
    {
        $nfse = $this->nfseAutorizadaParaCompartilhar();

        if ($nfse === null) {
            return;
        }

        $this->nfseDanfseModalId = (int) $nfse->id;
        $this->nfseDanfseModalOpen = true;

        // Overlay de sucesso (z-index alto) não pode cobrir o DANFSe.
        if (filled($this->nfseFiscalSucessoDetalhe)) {
            $this->nfseFiscalSucessoDetalhe = null;
            $this->nfseFiscalSucessoOsId = null;
            $this->nfseFiscalSucessoPodeGerarNfe = false;
            $this->js("document.getElementById('erp-nfse-fiscal-sucesso')?.style.setProperty('display','none')");
        }
    }

    public function gerarNfeDasPecasOs(): void
    {
        $osId = (int) ($this->nfseFiscalSucessoOsId ?? 0);

        if ($osId <= 0 || ! $this->nfseFiscalSucessoPodeGerarNfe) {
            Notification::make()
                ->title('Esta NFS-e não possui peças da OS para gerar NF-e.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'nfe.access')) {
            return;
        }

        $url = \App\Filament\Resources\NfeResource::getUrl('index').'?ordem_servico_id='.$osId;

        $this->redirect($url, navigate: false);
    }

    public function closeNfseDanfseModal(): void
    {
        $this->nfseDanfseModalOpen = false;
        $this->nfseDanfseModalId = null;
    }

    public function downloadNfseDanfsePdf(): void
    {
        if (! $this->nfseDanfseModalId) {
            return;
        }

        $url = route('erp.reports.nfse-impressao', [
            'nfse' => $this->nfseDanfseModalId,
            'pdf' => 1,
        ]);

        $this->js('window.open('.Js::from($url).', "_blank")');
    }

    public function printNfseDanfseDocument(): void
    {
        if (! $this->nfseDanfseModalId) {
            return;
        }

        $this->js(<<<'JS'
            (() => {
                const frame = document.querySelector('[data-erp-nfse-danfse-frame]');
                const win = frame?.contentWindow;
                if (!win) {
                    return;
                }
                try {
                    win.focus();
                    win.print();
                } catch (e) {}
            })();
        JS);
    }

    public function abrirNfseEnviarFromDanfse(): void
    {
        if ($this->nfseDanfseModalId) {
            $this->nfseFiscalSucessoId = (int) $this->nfseDanfseModalId;
        }

        $this->abrirNfseEnviar();
    }

    public function abrirNfseEnviar(): void
    {
        $nfse = $this->nfseAutorizadaParaCompartilhar();

        if ($nfse === null) {
            return;
        }

        $this->nfseFiscalSucessoId = (int) $nfse->id;
        $numero = (string) $nfse->numero_dps;
        $this->nfseEnviarEmail = trim((string) ($nfse->tomador_email ?? ''));
        $this->nfseEnviarWhatsApp = trim((string) ($nfse->tomador_telefone ?? ''));
        $this->nfseEnviarAssunto = 'NFS-e DPS '.$numero;
        $this->nfseEnviarMensagem = "Segue a NFS-e DPS {$numero}.\nChave de acesso: ".trim((string) $nfse->chave_acesso);
        $this->nfseEnviarStatus = null;
        $this->nfseEnviarModalOpen = true;
    }

    public function fecharNfseEnviar(): void
    {
        $this->nfseEnviarModalOpen = false;
        $this->resetValidation();
    }

    public function enviarNfseEmail(): void
    {
        $this->validate([
            'nfseEnviarEmail' => ['required', 'email'],
            'nfseEnviarAssunto' => ['required', 'string', 'max:255'],
            'nfseEnviarMensagem' => ['required', 'string', 'max:5000'],
        ], [
            'nfseEnviarEmail.required' => 'Informe o e-mail do destinatário.',
            'nfseEnviarEmail.email' => 'Informe um e-mail válido.',
            'nfseEnviarAssunto.required' => 'Informe o assunto.',
            'nfseEnviarMensagem.required' => 'Informe a mensagem.',
        ]);

        $nfse = $this->nfseAutorizadaParaCompartilhar();

        if ($nfse === null) {
            return;
        }

        $anexos = [];

        try {
            $anexos = $this->anexosNfse($nfse);
            FiscalMailService::sendForEmpresa(
                empresaId: (int) $nfse->empresa_id,
                to: $this->nfseEnviarEmail,
                messageBody: $this->nfseEnviarMensagem,
                subjectLine: $this->nfseEnviarAssunto,
                fileAttachments: $anexos,
                fromName: $nfse->empresa?->nome,
            );
        } catch (\Throwable $exception) {
            report($exception);
            $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL ENVIAR O E-MAIL', 'Verifique a configuração de e-mail em Empresa → Parâmetros → E-mail.');

            return;
        } finally {
            $this->limparAnexosNfse($anexos);
        }

        $this->nfseEnviarStatus = 'E-mail enviado.';
    }

    public function enviarNfseWhatsApp(): void
    {
        $this->validate([
            'nfseEnviarWhatsApp' => ['required', 'string', 'max:30'],
            'nfseEnviarMensagem' => ['required', 'string', 'max:5000'],
        ], [
            'nfseEnviarWhatsApp.required' => 'Informe o WhatsApp do destinatário.',
            'nfseEnviarMensagem.required' => 'Informe a mensagem.',
        ]);

        $nfse = $this->nfseAutorizadaParaCompartilhar();

        if ($nfse === null || $nfse->empresa === null) {
            return;
        }

        $anexos = [];
        $pdf = null;

        try {
            $anexos = $this->anexosNfse($nfse);
            $pdf = collect($anexos)->first(fn (array $anexo): bool => str_ends_with(strtolower($anexo['name']), '.pdf'));
            $resultado = app(WhatsAppSender::class)->sendDocumentMessage(
                $nfse->empresa,
                WhatsAppSender::TIPO_NFE,
                $this->nfseEnviarWhatsApp,
                $this->nfseEnviarMensagem,
                (string) (is_array($pdf) ? $pdf['path'] : ($anexos[0]['path'] ?? '')),
                (string) (is_array($pdf) ? $pdf['name'] : ($anexos[0]['name'] ?? 'NFSe.xml')),
                is_array($pdf) ? 'application/pdf' : 'application/xml',
            );
        } catch (\Throwable $exception) {
            report($exception);
            $resultado = ['ok' => false, 'message' => 'Não foi possível enviar o WhatsApp.'];
        } finally {
            $this->limparAnexosNfse($anexos);
        }

        if (! ($resultado['ok'] ?? false)) {
            $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL ENVIAR O WHATSAPP', (string) ($resultado['message'] ?? 'Não foi possível enviar o WhatsApp.'));

            return;
        }

        $this->nfseEnviarStatus = 'WhatsApp enviado.';
    }

    protected function executarTransmissaoNfse(): void
    {
        if ($this->nfseSomenteLeitura()) {
            $this->dispatch('erp-nfse-hide-fiscal-progress');
            $this->mostrarNfseJaAutorizadaOuErro();

            return;
        }

        $motivo = $this->motivoBloqueioTransmissao();

        if ($motivo !== null) {
            $this->dispatch('erp-nfse-hide-fiscal-progress');
            $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL TRANSMITIR A NFS-E', $motivo);

            return;
        }

        try {
            $gravada = $this->persistirNfseAberta();
            $this->aplicarNfseGravada($gravada);
            $nota = $gravada->fresh(['empresa', 'itens']) ?? $gravada;
            $resultado = $this->nfseProvedorIpm($nota->empresa)
                ? app(NfseIpmEmitirService::class)->transmitir($nota)
                : app(NfseTransmitirService::class)->transmitir($nota);
        } catch (NfseNaoGravada|NfseNaoTransmitida $exception) {
            $this->dispatch('erp-nfse-hide-fiscal-progress');
            $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL TRANSMITIR A NFS-E', $exception->getMessage());

            return;
        } catch (\Throwable $exception) {
            report($exception);
            $this->dispatch('erp-nfse-hide-fiscal-progress');
            $ipm = $this->nfseProvedorIpm(ErpContext::currentEmpresa());
            $this->mostrarNfseFiscalErro(
                'NÃO FOI POSSÍVEL TRANSMITIR A NFS-E',
                $ipm ? 'Não foi possível transmitir a NFS-e ao IPM.' : 'Não foi possível transmitir a DPS.',
                null,
                ! $ipm,
                $ipm ? 'Esta é uma mensagem do provedor IPM.' : null,
                $ipm ? 'Código IPM' : null,
            );

            return;
        }

        unset($this->nfseRegistros, $this->nfseListagemTotalFormatado);
        $this->dispatch('erp-nfse-hide-fiscal-progress');

        if ($resultado->autorizada) {
            $this->vincularNfseAOrdemServico($resultado->nfse);
            $this->aplicarNfseGravada($resultado->nfse->fresh(['itens']) ?? $resultado->nfse);
            $this->nfseSefinMensagem = '';
            $this->mostrarNfseFiscalSucesso($resultado->nfse, $resultado->alertas);

            return;
        }

        if ($resultado->modoTeste) {
            $this->mostrarResultadoTesteIpm($resultado);

            return;
        }

        $this->nfseStatus = Nfse::STATUS_ABERTA;
        $ipm = $this->nfseProvedorIpm($resultado->nfse->empresa ?? ErpContext::currentEmpresa());
        $mensagem = $resultado->mensagemErros();
        $this->nfseSefinMensagem = $mensagem !== ''
            ? $mensagem
            : ($ipm ? 'O provedor IPM rejeitou a NFS-e.' : 'A SEFIN rejeitou a DPS.');
        $codigo = $resultado->erros[0]['codigo'] ?? null;
        $this->mostrarNfseFiscalErro(
            'NÃO FOI POSSÍVEL TRANSMITIR A NFS-E',
            $this->nfseSefinMensagem,
            is_string($codigo) && $codigo !== '' ? $codigo : null,
            ! $ipm,
            $ipm ? 'Esta é uma mensagem do provedor IPM.' : null,
            $ipm ? 'Código IPM' : null,
        );
    }

    protected function mostrarResultadoTesteIpm(NfseTransmissaoResultado $resultado): void
    {
        $this->nfseStatus = Nfse::STATUS_ABERTA;
        $aceito = $resultado->erros === [];
        $linhas = $aceito ? $resultado->alertas : [$resultado->mensagemErros()];
        $linhas[] = 'Envio com EnvioTeste=1: nenhuma NFS-e foi emitida e a nota continua aberta.';
        $codigo = $resultado->erros[0]['codigo'] ?? null;

        $this->mostrarNfseFiscalErro(
            $aceito ? 'MODO TESTE IPM: RPS ACEITO' : 'MODO TESTE IPM: RPS RECUSADO',
            implode("\n", array_values(array_filter(array_map('strval', $linhas), fn (string $linha): bool => trim($linha) !== ''))),
            is_string($codigo) && $codigo !== '' ? $codigo : null,
            false,
            'Esta é uma mensagem do provedor IPM.',
            'Código IPM',
        );
    }

    public function podeTransmitirNfse(): bool
    {
        return $this->motivoBloqueioTransmissao() === null;
    }

    public function motivoBloqueioTransmissaoUi(): ?string
    {
        return $this->motivoBloqueioTransmissao();
    }

    /**
     * Índices dos serviços cujo código nacional exige o grupo obra.
     *
     * @return list<int>
     */
    public function nfseLinhasExigemObra(): array
    {
        $indices = [];

        foreach ($this->nfseServicos as $index => $linha) {
            if (NfseObra::exige($linha['c_trib_nac'] ?? null)) {
                $indices[] = (int) $index;
            }
        }

        return $indices;
    }

    public function nfseSomenteLeitura(): bool
    {
        return $this->nfseStatus !== Nfse::STATUS_ABERTA;
    }

    public function nfseStatusLabel(): string
    {
        return Nfse::statusLabels()[$this->nfseStatus] ?? 'Aberta';
    }

    public function closeNfseModal(): void
    {
        if (filled($this->nfseFiscalErroTitulo)) {
            $this->closeNfseFiscalErro();

            return;
        }

        if ($this->nfseEnviarModalOpen) {
            $this->fecharNfseEnviar();

            return;
        }

        if (filled($this->nfseFiscalSucessoDetalhe)) {
            $this->closeNfseFiscalSucesso();

            return;
        }

        if ($this->nfseConfirmarProducao) {
            $this->cancelarTransmissaoProducao();

            return;
        }

        if ($this->nfseServicoExcluirIndex !== null) {
            $this->cancelarExclusaoNfseServico();

            return;
        }

        if ($this->nfseDescontoModalOpen) {
            $this->fecharNfseModalDescontoItem();

            return;
        }

        if ($this->nfseImportOsConfirmOpen) {
            $this->cancelarNfseImportOsDuplicada();

            return;
        }

        if ($this->nfseImportOsOpen) {
            $this->closeNfseImportOs();

            return;
        }

        if ($this->nfseEspelhoModalOpen) {
            $this->closeNfseEspelhoModal();

            return;
        }

        if ($this->nfseServicoSugestoesOpen) {
            $this->fecharNfseServicoSugestoes();

            return;
        }

        if ($this->nfseTomadorSugestoesOpen) {
            $this->fecharNfseTomadorSugestoes();

            return;
        }

        $this->nfseModalOpen = false;
        $this->resetNfseModalForm();
    }

    public function updatedNfseTomador(string $value): void
    {
        $term = trim($value);

        if ($term === '') {
            $this->limparNfseTomadorSelecao();
            $this->fecharNfseTomadorSugestoes();

            return;
        }

        if ($this->nfseTomadorSelecionadoCorresponde($term)) {
            $this->fecharNfseTomadorSugestoes();

            return;
        }

        if ($this->nfseTomadorId !== null) {
            $this->limparNfseTomadorCampos();
        }

        $this->nfseTomadorSugestoes = $this->buscarNfseTomadores($term);
        $this->nfseTomadorSugestoesOpen = $this->nfseTomadorSugestoes !== [];
        $this->nfseTomadorSugestaoIndex = 0;
    }

    public function confirmarNfseTomador(): void
    {
        $term = trim($this->nfseTomador);

        if ($term === '') {
            $this->fecharNfseTomadorSugestoes();

            return;
        }

        if ($this->nfseTomadorSelecionadoCorresponde($term)) {
            $this->fecharNfseTomadorSugestoes();
            $this->nfseFocarCampo('nfse-servico-busca');

            return;
        }

        if (preg_match('/^\d+$/', $term) === 1) {
            $codigo = ltrim($term, '0') ?: $term;

            foreach ($this->nfseTomadorSugestoes as $sugestao) {
                $codigoSugestao = ltrim((string) ($sugestao['codigo'] ?? ''), '0') ?: (string) ($sugestao['codigo'] ?? '');

                if ((string) ($sugestao['codigo'] ?? '') === $term || $codigoSugestao === $codigo) {
                    $this->aplicarNfseTomadorSugestao($sugestao);

                    return;
                }
            }

            $this->selecionarNfseTomadorPorCodigo($term);

            return;
        }

        if ($this->nfseTomadorSugestoesOpen && $this->nfseTomadorSugestoes !== []) {
            $index = $this->nfseTomadorSugestaoIndex;

            if (! isset($this->nfseTomadorSugestoes[$index])) {
                $index = 0;
            }

            $this->aplicarNfseTomadorSugestao($this->nfseTomadorSugestoes[$index]);

            return;
        }

        Notification::make()
            ->title('Tomador não encontrado.')
            ->warning()
            ->send();
    }

    public function selecionarNfseTomador(int $id): void
    {
        foreach ($this->nfseTomadorSugestoes as $sugestao) {
            if ((int) ($sugestao['id'] ?? 0) === $id) {
                $this->aplicarNfseTomadorSugestao($sugestao);

                return;
            }
        }
    }

    public function moverNfseTomadorSugestao(int $delta): void
    {
        if (! $this->nfseTomadorSugestoesOpen || $this->nfseTomadorSugestoes === []) {
            return;
        }

        $count = count($this->nfseTomadorSugestoes);
        $index = $this->nfseTomadorSugestaoIndex + $delta;

        if ($index < 0) {
            $index = $count - 1;
        } elseif ($index >= $count) {
            $index = 0;
        }

        $this->nfseTomadorSugestaoIndex = $index;
    }

    public function fecharNfseTomadorSugestoes(): void
    {
        $this->nfseTomadorSugestoes = [];
        $this->nfseTomadorSugestoesOpen = false;
        $this->nfseTomadorSugestaoIndex = 0;
    }

    protected function resetNfseModalForm(): void
    {
        $hoje = ErpTimezone::toLocal();

        $this->nfseId = null;
        $this->nfseConfirmarProducao = false;
        $this->nfseStatus = Nfse::STATUS_ABERTA;
        $this->nfseSefinMensagem = '';
        $this->nfseCertificadoOk = null;
        $this->nfseSerieDps = '';
        $this->nfseNumeroDps = '';
        $this->nfseNumeroNfse = '';
        $this->limparNfseTomadorSelecao();
        $this->fecharNfseTomadorSugestoes();
        $this->limparNfseServicoPendente();
        $this->nfseServicos = [];
        $this->nfseServicoLinhaIndex = null;
        $this->nfseServicoExcluirIndex = null;
        $this->nfseServicoSeq = 0;
        $this->limparNfseMunicipio();
        $this->nfseOsOrigemId = null;
        $this->nfseOsLaudoPreservado = '';
        $this->nfseCompetencia = $hoje->format('Y-m');
        $this->nfseDataEmissao = $hoje->toDateString();
        $this->nfseTribIssqn = Nfse::TRIB_ISSQN_TRIBUTAVEL;
        $this->nfseTpRetIssqn = Nfse::TP_RET_ISSQN_NAO_RETIDO;
    }

    public function updatedNfseMunicipioNome(string $value): void
    {
        $termo = trim($value);

        if ($termo === '') {
            $this->limparNfseMunicipio();

            return;
        }

        if ($this->nfseMunicipioSelecionadoCorresponde($termo)) {
            $this->fecharNfseMunicipioSugestoes();

            return;
        }

        $this->nfseMunicipioCodigo = '';
        $this->nfseMunicipioUf = '';

        if (mb_strlen($termo) < 2) {
            $this->fecharNfseMunicipioSugestoes();

            return;
        }

        $this->abrirNfseMunicipioSugestoes($termo);
    }

    public function moverNfseMunicipioSugestao(int $delta): void
    {
        if (! $this->nfseMunicipioSugestoesOpen || $this->nfseMunicipioSugestoes === []) {
            return;
        }

        $count = count($this->nfseMunicipioSugestoes);
        $index = $this->nfseMunicipioSugestaoIndex + $delta;

        if ($index < 0) {
            $index = $count - 1;
        } elseif ($index >= $count) {
            $index = 0;
        }

        $this->nfseMunicipioSugestaoIndex = $index;
    }

    public function confirmarNfseMunicipio(): void
    {
        if ($this->nfseMunicipioSelecionadoCorresponde($this->nfseMunicipioNome)) {
            $this->fecharNfseMunicipioSugestoes();

            return;
        }

        if ($this->nfseMunicipioSugestoesOpen && $this->nfseMunicipioSugestoes !== []) {
            $index = $this->nfseMunicipioSugestaoIndex;

            if (! isset($this->nfseMunicipioSugestoes[$index])) {
                $index = 0;
            }

            $this->selecionarNfseMunicipio(
                (string) $this->nfseMunicipioSugestoes[$index]['codigo'],
                (string) $this->nfseMunicipioSugestoes[$index]['nome'],
                (string) $this->nfseMunicipioSugestoes[$index]['uf'],
            );

            return;
        }

        $termo = trim($this->nfseMunicipioNome);

        if (mb_strlen($termo) < 2) {
            return;
        }

        $this->abrirNfseMunicipioSugestoes($termo);

        if ($this->nfseMunicipioSugestoes === []) {
            return;
        }

        $sugestao = $this->nfseMunicipioSugestoes[0];
        $this->selecionarNfseMunicipio(
            (string) $sugestao['codigo'],
            (string) $sugestao['nome'],
            (string) $sugestao['uf'],
        );
    }

    public function selecionarNfseMunicipio(string $codigo, string $nome, string $uf): void
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';

        if (! CepLookupService::isValidIbgeCode($codigo)) {
            return;
        }

        $this->nfseMunicipioCodigo = $codigo;
        $this->nfseMunicipioNome = ErpUppercase::uppercase(trim($nome));
        $this->nfseMunicipioNomeSelecionado = $this->nfseMunicipioNome;
        $uf = mb_strtoupper(trim($uf), 'UTF-8');
        $this->nfseMunicipioUf = strlen($uf) === 2 ? $uf : '';
        $this->fecharNfseMunicipioSugestoes();
    }

    public function fecharNfseMunicipioSugestoes(): void
    {
        $this->nfseMunicipioSugestoes = [];
        $this->nfseMunicipioSugestoesOpen = false;
        $this->nfseMunicipioSugestaoIndex = 0;
    }

    protected function preencherNfseMunicipioDaEmpresa(): void
    {
        $empresa = ErpContext::currentEmpresa();
        $codigo = preg_replace('/\D/', '', (string) ($empresa?->cidade_codigo ?? '')) ?? '';

        if (! CepLookupService::isValidIbgeCode($codigo)) {
            $this->limparNfseMunicipio();

            return;
        }

        $uf = mb_strtoupper(trim((string) ($empresa->uf ?? '')), 'UTF-8');

        $this->nfseMunicipioCodigo = $codigo;
        $this->nfseMunicipioNome = ErpUppercase::uppercase(trim((string) ($empresa->cidade ?? '')));
        $this->nfseMunicipioNomeSelecionado = $this->nfseMunicipioNome;
        $this->nfseMunicipioUf = strlen($uf) === 2 ? $uf : '';
        $this->fecharNfseMunicipioSugestoes();
    }

    protected function limparNfseMunicipio(): void
    {
        $this->nfseMunicipioCodigo = '';
        $this->nfseMunicipioNome = '';
        $this->nfseMunicipioUf = '';
        $this->nfseMunicipioNomeSelecionado = '';
        $this->fecharNfseMunicipioSugestoes();
    }

    protected function abrirNfseMunicipioSugestoes(string $termo): void
    {
        try {
            $this->nfseMunicipioSugestoes = app(MunicipioLookupService::class)->search($termo, null, 20);
        } catch (RuntimeException $exception) {
            $this->fecharNfseMunicipioSugestoes();
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->nfseMunicipioSugestoesOpen = $this->nfseMunicipioSugestoes !== [];
        $this->nfseMunicipioSugestaoIndex = $this->nfseMunicipioSugestoes !== [] ? 0 : 0;
    }

    protected function nfseMunicipioSelecionadoCorresponde(string $termo): bool
    {
        if (! CepLookupService::isValidIbgeCode($this->nfseMunicipioCodigo)) {
            return false;
        }

        return ErpUppercase::uppercase(trim($termo)) === $this->nfseMunicipioNomeSelecionado
            && $this->nfseMunicipioNomeSelecionado !== '';
    }

    /**
     * @return array{codigo: string, nome: ?string, uf: ?string}
     */
    protected function municipioPrestacaoParaGravar(): array
    {
        $codigo = preg_replace('/\D/', '', $this->nfseMunicipioCodigo) ?? '';

        if (! CepLookupService::isValidIbgeCode($codigo)) {
            throw new NfseNaoGravada('Falta o código IBGE do município da prestação.');
        }

        $uf = mb_strtoupper(trim($this->nfseMunicipioUf), 'UTF-8');

        return [
            'codigo' => $codigo,
            'nome' => $this->textoNfseOuNulo(ErpUppercase::uppercase(trim($this->nfseMunicipioNome)), 80),
            'uf' => strlen($uf) === 2 ? $uf : null,
        ];
    }

    protected function aplicarNfseMunicipioGravado(Nfse $nfse): void
    {
        $this->fecharNfseMunicipioSugestoes();
        $this->nfseMunicipioCodigo = (string) ($nfse->municipio_prestacao_codigo ?? '');
        $this->nfseMunicipioNome = (string) ($nfse->municipio_prestacao_nome ?? '');
        $this->nfseMunicipioNomeSelecionado = $this->nfseMunicipioNome;
        $this->nfseMunicipioUf = (string) ($nfse->municipio_prestacao_uf ?? '');
    }

    protected function limparNfseTomadorSelecao(): void
    {
        $this->nfseTomador = '';
        $this->limparNfseTomadorCampos();
    }

    protected function limparNfseTomadorCampos(): void
    {
        $this->nfseTomadorId = null;
        $this->nfseTomadorSelecionadoLabel = '';
        $this->nfseTomadorCpfCnpj = '';
        $this->nfseTomadorTelefone = '';
        $this->nfseTomadorEndereco = '';
        $this->nfseTomadorNumero = '';
        $this->nfseTomadorBairro = '';
        $this->nfseTomadorCep = '';
        $this->nfseTomadorCidade = '';
        $this->nfseTomadorUf = '';
        $this->nfseTomadorCidadeCodigo = '';
        $this->nfseTomadorEmail = '';
    }

    /**
     * @param  array<string, mixed>  $sugestao
     */
    protected function aplicarNfseTomadorSugestao(array $sugestao): void
    {
        $this->nfseTomadorId = (int) $sugestao['id'];
        $this->nfseTomadorSelecionadoLabel = (string) ($sugestao['label'] ?? '');
        $this->nfseTomador = $this->nfseTomadorSelecionadoLabel;
        $this->nfseTomadorCpfCnpj = (string) ($sugestao['cpf_cnpj'] ?? '');
        $this->nfseTomadorTelefone = (string) ($sugestao['telefone'] ?? '');
        $this->nfseTomadorEndereco = (string) ($sugestao['endereco'] ?? '');
        $this->nfseTomadorNumero = (string) ($sugestao['numero'] ?? '');
        $this->nfseTomadorBairro = (string) ($sugestao['bairro'] ?? '');
        $this->nfseTomadorCep = (string) ($sugestao['cep'] ?? '');
        $this->nfseTomadorCidade = (string) ($sugestao['cidade'] ?? '');
        $this->nfseTomadorUf = (string) ($sugestao['uf'] ?? '');
        $this->nfseTomadorCidadeCodigo = (string) ($sugestao['cidade_codigo'] ?? '');
        $this->nfseTomadorEmail = (string) ($sugestao['email'] ?? '');
        $this->fecharNfseTomadorSugestoes();
        $this->nfseFocarCampo('nfse-servico-busca');
    }

    protected function selecionarNfseTomadorPorCodigo(string $term): void
    {
        $codigo = ltrim($term, '0') ?: $term;

        $person = Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->where(function ($query) use ($term, $codigo): void {
                $query->where('codigo', $term)
                    ->orWhere('codigo', $codigo);
            })
            ->first($this->nfseTomadorSelectColumns());

        if (! $person) {
            Notification::make()
                ->title('Tomador não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->aplicarNfseTomadorSugestao($this->mapearNfseTomador($person));
    }

    protected function nfseTomadorSelecionadoCorresponde(string $term): bool
    {
        if ($this->nfseTomadorId === null || $this->nfseTomadorSelecionadoLabel === '') {
            return false;
        }

        return mb_strtoupper(trim($term), 'UTF-8') === mb_strtoupper($this->nfseTomadorSelecionadoLabel, 'UTF-8');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buscarNfseTomadores(string $term): array
    {
        $term = trim($term);
        $numeric = preg_match('/^\d+$/', $term) === 1;

        if ($term === '' || (! $numeric && mb_strlen($term) < 2)) {
            return [];
        }

        $termUpper = mb_strtoupper($term, 'UTF-8');
        $likeContains = '%'.$termUpper.'%';
        $likeStarts = $termUpper.'%';
        $digits = preg_replace('/\D/', '', $term) ?: '';

        $people = Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->where(function ($query) use ($likeStarts, $likeContains, $digits): void {
                $query->where('codigo', 'like', $likeStarts)
                    ->orWhereRaw('UPPER(nome_razao) LIKE ?', [$likeContains])
                    ->orWhereRaw('UPPER(apelido_fantasia) LIKE ?', [$likeContains]);

                if (strlen($digits) >= 2) {
                    $digitsLike = '%'.$digits.'%';
                    $query->orWhere('cpf_cnpj', 'like', $digitsLike)
                        ->orWhereRaw(
                            "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                            [$digitsLike]
                        );
                }
            })
            ->orderBy('nome_razao')
            ->limit(12)
            ->get($this->nfseTomadorSelectColumns());

        return $people
            ->map(fn (Person $person): array => $this->mapearNfseTomador($person))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function nfseTomadorSelectColumns(): array
    {
        return [
            'id',
            'codigo',
            'nome_razao',
            'apelido_fantasia',
            'cpf_cnpj',
            'fone1',
            'fone2',
            'endereco',
            'numero',
            'bairro',
            'cep',
            'cidade_nome',
            'cidade_codigo',
            'uf',
            'email',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapearNfseTomador(Person $person): array
    {
        $codigo = (string) ($person->codigo ?? '');
        $nome = trim((string) ($person->nome_razao ?? ''));
        $doc = (string) ($person->cpf_cnpj ?? '');
        $digits = preg_replace('/\D/', '', $doc) ?: '';

        return [
            'id' => (int) $person->id,
            'codigo' => $codigo,
            'nome' => $nome,
            'label' => trim(($codigo !== '' ? $codigo.' — ' : '').$nome),
            'cpf_cnpj' => $this->formatarNfseCpfCnpj($doc),
            'doc_tipo' => strlen($digits) > 11 ? 'cnpj' : (strlen($digits) >= 11 ? 'cpf' : 'outro'),
            'telefone' => (string) ($person->fone1 ?: $person->fone2 ?: ''),
            'endereco' => (string) ($person->endereco ?? ''),
            'numero' => (string) ($person->numero ?? ''),
            'bairro' => (string) ($person->bairro ?? ''),
            'cep' => (string) ($person->cep ?? ''),
            'cidade' => (string) ($person->cidade_nome ?? ''),
            'cidade_codigo' => (string) ($person->cidade_codigo ?? ''),
            'uf' => (string) ($person->uf ?? ''),
            'email' => (string) ($person->email ?? ''),
        ];
    }

    protected function formatarNfseCpfCnpj(?string $value): string
    {
        if (! filled($value)) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
        }

        return $value;
    }

    public function updatedNfseServicoBusca(string $value): void
    {
        $term = trim($value);

        if ($term === '') {
            $this->limparNfseServicoCampos();
            $this->fecharNfseServicoSugestoes();

            return;
        }

        if ($this->nfseServicoSelecionadoCorresponde($term)) {
            $this->fecharNfseServicoSugestoes();

            return;
        }

        if ($this->nfseServicoId !== null) {
            $this->limparNfseServicoCampos();
        }

        $numeric = preg_match('/^\d+$/', $term) === 1;

        if (! $numeric && mb_strlen($term) < 2) {
            $this->fecharNfseServicoSugestoes();

            return;
        }

        $this->abrirNfseServicoSugestoes($term);
    }

    public function confirmarNfseServicoBusca(): void
    {
        $term = trim($this->nfseServicoBusca);

        if ($term === '') {
            $this->fecharNfseServicoSugestoes();

            return;
        }

        if ($this->nfseServicoSelecionadoCorresponde($term)) {
            $this->fecharNfseServicoSugestoes();
            $this->nfseFocarCampo('nfse-servico-qtd');

            return;
        }

        if ($this->nfseServicoSugestoesOpen && $this->nfseServicoSugestoes !== []) {
            $index = $this->nfseServicoSugestaoIndex;

            if (! isset($this->nfseServicoSugestoes[$index])) {
                $index = 0;
            }

            $this->aplicarNfseServicoSugestao($this->nfseServicoSugestoes[$index]);

            return;
        }

        $sugestoes = $this->buscarNfseServicos($term);

        if ($sugestoes === []) {
            Notification::make()
                ->title('Serviço não encontrado.')
                ->warning()
                ->send();
            $this->fecharNfseServicoSugestoes();

            return;
        }

        if (count($sugestoes) === 1) {
            $this->aplicarNfseServicoSugestao($sugestoes[0]);

            return;
        }

        $this->nfseServicoSugestoes = $sugestoes;
        $this->nfseServicoSugestoesOpen = true;
        $this->nfseServicoSugestaoIndex = 0;
    }

    public function selecionarNfseServico(int $id): void
    {
        foreach ($this->nfseServicoSugestoes as $sugestao) {
            if ((int) ($sugestao['id'] ?? 0) === $id) {
                $this->aplicarNfseServicoSugestao($sugestao);

                return;
            }
        }
    }

    public function moverNfseServicoSugestao(int $delta): void
    {
        if (! $this->nfseServicoSugestoesOpen || $this->nfseServicoSugestoes === []) {
            return;
        }

        $count = count($this->nfseServicoSugestoes);
        $index = $this->nfseServicoSugestaoIndex + $delta;

        if ($index < 0) {
            $index = $count - 1;
        } elseif ($index >= $count) {
            $index = 0;
        }

        $this->nfseServicoSugestaoIndex = $index;
    }

    public function fecharNfseServicoSugestoes(): void
    {
        $this->nfseServicoSugestoes = [];
        $this->nfseServicoSugestoesOpen = false;
        $this->nfseServicoSugestaoIndex = 0;
    }

    public function confirmarNfseServicoInclusao(
        ?string $quantidade = null,
        ?string $valor = null,
        ?string $desconto = null,
        ?string $acrescimo = null,
    ): void {
        if ($this->nfseSomenteLeitura() || $this->nfseServicoExcluirIndex !== null) {
            return;
        }

        $this->confirmarNfseServicoInclusaoAberta($quantidade, $valor, $desconto, $acrescimo);
    }

    public function focoNfseServicoValorAposQtd(?string $quantidade = null): void
    {
        if ($this->nfseSomenteLeitura()) {
            return;
        }

        if ($this->nfseServicoId === null) {
            $this->confirmarNfseServicoBusca();

            return;
        }

        if ($quantidade !== null) {
            $this->nfseServicoQuantidade = $quantidade;
        }

        $qtd = $this->nfseNormalizarDecimal($this->nfseServicoQuantidade, 3);

        if ($qtd === null || $this->nfseCompararDecimal($qtd, '0', 3) <= 0) {
            Notification::make()
                ->title('Informe a quantidade do serviço.')
                ->warning()
                ->send();
            $this->nfseFocarCampo('nfse-servico-qtd');

            return;
        }

        $this->nfseServicoQuantidade = $this->nfseFormatarDecimal($qtd, 3);
        $this->nfseFocarCampo('nfse-servico-valor');
    }

    public function abrirNfseModalDescontoItem(?string $origem = null): void
    {
        if ($this->nfseSomenteLeitura() || $this->nfseDescontoModalOpen) {
            return;
        }

        $precoForm = $this->nfseNormalizarDecimal($this->nfseServicoValor, 2);
        $temForm = $this->nfseServicoId !== null
            && $precoForm !== null
            && $this->nfseCompararDecimal($precoForm, '0', 2) > 0;
        $temGrid = $this->nfseServicos !== []
            && $this->nfseServicoLinhaIndex !== null
            && isset($this->nfseServicos[$this->nfseServicoLinhaIndex]);

        if ($origem === 'grid') {
            if (! $temGrid) {
                Notification::make()
                    ->title('Selecione um serviço na grade para desconto/acréscimo.')
                    ->warning()
                    ->send();

                return;
            }
            $this->nfseServicoAjusteAlvo = 'grid';
            $this->nfseServicoAjusteRowIndex = (int) $this->nfseServicoLinhaIndex;
        } elseif ($origem === 'form' || $temForm) {
            if (! $temForm) {
                Notification::make()
                    ->title('Informe o serviço na barra de inclusão para aplicar desconto.')
                    ->warning()
                    ->send();

                return;
            }
            $this->nfseServicoAjusteAlvo = 'form';
            $this->nfseServicoAjusteRowIndex = null;
        } elseif ($temGrid) {
            $this->nfseServicoAjusteAlvo = 'grid';
            $this->nfseServicoAjusteRowIndex = (int) $this->nfseServicoLinhaIndex;
        } else {
            Notification::make()
                ->title('Informe o serviço (ou selecione um item) para desconto/acréscimo.')
                ->warning()
                ->send();

            return;
        }

        $this->nfseServicoAjusteTipo = 'desconto';
        $this->nfseServicoAjusteModo = 'percentual';
        $this->nfseServicoAjusteValor = '0,00';
        $this->nfseDescontoModalOpen = true;
        $this->nfseFocarCampo('erp-nfse-desconto-preco');
    }

    public function fecharNfseModalDescontoItem(): void
    {
        $this->nfseDescontoModalOpen = false;
        $this->nfseServicoAjusteAlvo = null;
        $this->nfseServicoAjusteRowIndex = null;
    }

    public function updatedNfseServicoAjusteValor(): void
    {
        $raw = preg_replace('/[^\d,.\-]/', '', (string) $this->nfseServicoAjusteValor) ?? '';
        $this->nfseServicoAjusteValor = $raw === '' ? '0,00' : $raw;
    }

    public function setNfseServicoAjusteTipo(string $tipo): void
    {
        $this->nfseServicoAjusteTipo = $tipo === 'acrescimo' ? 'acrescimo' : 'desconto';
    }

    public function setNfseServicoAjusteModo(string $modo): void
    {
        $this->nfseServicoAjusteModo = $modo === 'valor' ? 'valor' : 'percentual';
    }

    /**
     * @return array{descricao: string, base: string, novoPreco: string, total: string, ajuste: string, tipo: string, temAjuste: bool}
     */
    public function getNfseServicoAjustePreviewProperty(): array
    {
        $ctx = $this->contextoNfseServicoAjuste();

        if ($ctx === null) {
            return [
                'descricao' => '',
                'base' => '0,00',
                'novoPreco' => '0,00',
                'total' => '0,00',
                'ajuste' => '0,00',
                'tipo' => $this->nfseServicoAjusteTipo,
                'temAjuste' => false,
            ];
        }

        $calc = $this->calcularNfseServicoAjuste($ctx['preco'], $ctx['quantidade']);

        return [
            'descricao' => $ctx['descricao'],
            'base' => $this->nfseFormatarDecimal($calc['base'], 2),
            'novoPreco' => $this->nfseFormatarDecimal($calc['novoPreco'], 2),
            'total' => $this->nfseFormatarDecimal($calc['total'], 2),
            'ajuste' => $this->nfseFormatarDecimal($calc['ajusteLinha'], 2),
            'tipo' => $this->nfseServicoAjusteTipo,
            'temAjuste' => $this->nfseCompararDecimal($calc['ajusteLinha'], '0', 2) > 0,
        ];
    }

    public function confirmarNfseServicoAjuste(): void
    {
        $ctx = $this->contextoNfseServicoAjuste();

        if ($ctx === null) {
            $this->fecharNfseModalDescontoItem();

            return;
        }

        $calc = $this->calcularNfseServicoAjuste($ctx['preco'], $ctx['quantidade']);
        $ajusteLinha = $calc['ajusteLinha'];

        if ($this->nfseServicoAjusteTipo === 'desconto' && $this->nfseCompararDecimal($calc['novoPreco'], '0', 2) < 0) {
            Notification::make()->title('Desconto inválido.')->warning()->send();

            return;
        }

        if ($this->nfseCompararDecimal($ajusteLinha, '0', 2) <= 0) {
            Notification::make()->title('Informe um valor de desconto/acréscimo.')->warning()->send();

            return;
        }

        $alvo = $this->nfseServicoAjusteAlvo;
        $tipo = $this->nfseServicoAjusteTipo;
        $ajusteFormatado = $this->nfseFormatarDecimal($ajusteLinha, 2);

        if ($alvo === 'form') {
            if ($tipo === 'desconto') {
                $this->nfseServicoDesconto = $ajusteFormatado;
                $this->nfseServicoAcrescimo = '0,00';
            } else {
                $this->nfseServicoAcrescimo = $ajusteFormatado;
                $this->nfseServicoDesconto = '0,00';
            }

            $this->fecharNfseModalDescontoItem();
            $this->confirmarNfseServicoInclusaoAberta();
        } else {
            $index = $this->nfseServicoAjusteRowIndex !== null
                ? (int) $this->nfseServicoAjusteRowIndex
                : (int) $this->nfseServicoLinhaIndex;

            if (! isset($this->nfseServicos[$index])) {
                $this->fecharNfseModalDescontoItem();

                return;
            }

            $linha = $this->nfseServicos[$index];
            $qtd = $this->nfseNormalizarDecimal($linha['quantidade'] ?? '0', 3) ?? '0.000';
            $preco = $this->nfseNormalizarDecimal($linha['valor'] ?? '0', 2) ?? '0.00';

            if ($tipo === 'desconto') {
                $linha['desconto'] = $ajusteFormatado;
                $linha['acrescimo'] = '0,00';
                $this->nfseAplicarTotalServico($linha, $qtd, $preco, $ajusteLinha, '0.00');
            } else {
                $linha['acrescimo'] = $ajusteFormatado;
                $linha['desconto'] = '0,00';
                $this->nfseAplicarTotalServico($linha, $qtd, $preco, '0.00', $ajusteLinha);
            }

            $linha['rev'] = ((int) ($linha['rev'] ?? 0)) + 1;
            $this->nfseServicos[$index] = $linha;
            $this->fecharNfseModalDescontoItem();
        }

        Notification::make()
            ->title($tipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
            ->body('Valor: R$ '.$ajusteFormatado)
            ->success()
            ->send();
    }

    /**
     * @return array{descricao: string, preco: string, quantidade: string}|null
     */
    protected function contextoNfseServicoAjuste(): ?array
    {
        if ($this->nfseServicoAjusteAlvo === 'form' && $this->nfseServicoId !== null) {
            $preco = $this->nfseNormalizarDecimal($this->nfseServicoValor, 2);
            $qtd = $this->nfseNormalizarDecimal($this->nfseServicoQuantidade !== '' ? $this->nfseServicoQuantidade : '1', 3);

            if ($preco === null || $qtd === null) {
                return null;
            }

            return [
                'descricao' => $this->nfseServicoDescricao !== '' ? $this->nfseServicoDescricao : $this->nfseServicoBusca,
                'preco' => $preco,
                'quantidade' => $qtd,
            ];
        }

        if ($this->nfseServicoAjusteAlvo === 'grid') {
            $index = $this->nfseServicoAjusteRowIndex !== null
                ? (int) $this->nfseServicoAjusteRowIndex
                : (int) $this->nfseServicoLinhaIndex;

            if (! isset($this->nfseServicos[$index])) {
                return null;
            }

            $item = $this->nfseServicos[$index];
            $preco = $this->nfseNormalizarDecimal($item['valor'] ?? '0', 2);
            $qtd = $this->nfseNormalizarDecimal($item['quantidade'] ?? '0', 3);

            if ($preco === null || $qtd === null) {
                return null;
            }

            return [
                'descricao' => (string) ($item['descricao'] ?? ''),
                'preco' => $preco,
                'quantidade' => $qtd,
            ];
        }

        return null;
    }

    /**
     * @return array{base: string, ajusteLinha: string, novoPreco: string, total: string}
     */
    protected function calcularNfseServicoAjuste(string $base, string $quantidade): array
    {
        $valor = $this->nfseNormalizarDecimal($this->nfseServicoAjusteValor !== '' ? $this->nfseServicoAjusteValor : '0', 2) ?? '0.00';
        $qtd = $this->nfseCompararDecimal($quantidade, '0', 3) > 0 ? $quantidade : '0.000';

        if ($this->nfseServicoAjusteModo === 'percentual') {
            $deltaUnit = $this->nfseArredondarDecimal(bcmul($base, bcdiv($valor, '100', 8), 8), 2);
            $ajusteLinha = $this->nfseMultiplicarDecimal($deltaUnit, $qtd);
        } else {
            $ajusteLinha = $valor;
            $deltaUnit = $this->nfseCompararDecimal($qtd, '0', 3) > 0
                ? $this->nfseArredondarDecimal(bcdiv($ajusteLinha, $qtd, 8), 2)
                : $ajusteLinha;
        }

        $novoPreco = $this->nfseServicoAjusteTipo === 'acrescimo'
            ? $this->nfseSomarDecimal($base, $deltaUnit)
            : $this->nfseSubtrairDecimal($base, $deltaUnit);

        if ($this->nfseCompararDecimal($novoPreco, '0', 2) < 0) {
            $novoPreco = '0.00';
        }

        $subtotal = $this->nfseMultiplicarDecimal($qtd, $base);
        $total = $this->nfseServicoAjusteTipo === 'acrescimo'
            ? $this->nfseSomarDecimal($subtotal, $ajusteLinha)
            : $this->nfseSubtrairDecimal($subtotal, $ajusteLinha);

        if ($this->nfseCompararDecimal($total, '0', 2) < 0) {
            $total = '0.00';
        }

        return [
            'base' => $base,
            'ajusteLinha' => $ajusteLinha,
            'novoPreco' => $novoPreco,
            'total' => $total,
        ];
    }

    protected function confirmarNfseServicoInclusaoAberta(
        ?string $quantidade = null,
        ?string $valor = null,
        ?string $desconto = null,
        ?string $acrescimo = null,
    ): void {
        if ($this->nfseServicoId === null) {
            return;
        }

        if ($quantidade !== null) {
            $this->nfseServicoQuantidade = $quantidade;
        }

        if ($valor !== null) {
            $this->nfseServicoValor = $valor;
        }

        if ($desconto !== null) {
            $this->nfseServicoDesconto = $desconto;
        }

        if ($acrescimo !== null) {
            $this->nfseServicoAcrescimo = $acrescimo;
        }

        $qtd = $this->nfseNormalizarDecimal($this->nfseServicoQuantidade, 3);
        $preco = $this->nfseNormalizarDecimal($this->nfseServicoValor, 2);
        $valorDesconto = $this->nfseNormalizarDecimal($this->nfseServicoDesconto !== '' ? $this->nfseServicoDesconto : '0', 2) ?? '0.00';
        $valorAcrescimo = $this->nfseNormalizarDecimal($this->nfseServicoAcrescimo !== '' ? $this->nfseServicoAcrescimo : '0', 2) ?? '0.00';

        if ($qtd === null || $this->nfseCompararDecimal($qtd, '0', 3) <= 0) {
            Notification::make()
                ->title('Informe a quantidade do serviço.')
                ->warning()
                ->send();
            $this->nfseFocarCampo('nfse-servico-qtd');

            return;
        }

        if ($preco === null || $this->nfseCompararDecimal($preco, '0', 2) < 0) {
            Notification::make()
                ->title('Informe o valor do serviço.')
                ->warning()
                ->send();
            $this->nfseFocarCampo('nfse-servico-valor');

            return;
        }

        if ($this->nfseCompararDecimal($valorDesconto, '0', 2) < 0 || $this->nfseCompararDecimal($valorAcrescimo, '0', 2) < 0) {
            Notification::make()
                ->title('Desconto e acréscimo não podem ser negativos.')
                ->warning()
                ->send();

            return;
        }

        $bruto = $this->nfseMultiplicarDecimal($qtd, $preco);

        if ($this->nfseCompararDecimal($valorDesconto, $bruto, 2) > 0) {
            Notification::make()
                ->title('Desconto maior que o valor do serviço.')
                ->warning()
                ->send();
            $this->nfseFocarCampo('nfse-servico-valor');

            return;
        }

        $total = $this->nfseTotalLinhaServico($qtd, $preco, $valorDesconto, $valorAcrescimo);
        $this->nfseServicoSeq++;

        // Último lançado fica no topo (item 1), igual à NF-e.
        array_unshift($this->nfseServicos, [
            'key' => 'nfse-'.$this->nfseServicoSeq,
            'rev' => 0,
            'product_id' => $this->nfseServicoId,
            'codigo' => $this->nfseServicoCodigo,
            'descricao' => $this->nfseServicoDescricao,
            'unidade' => $this->nfseServicoUnidade,
            'quantidade' => $this->nfseFormatarDecimal($qtd, 3),
            'valor' => $this->nfseFormatarDecimal($preco, 2),
            'desconto' => $this->nfseFormatarDecimal($valorDesconto, 2),
            'acrescimo' => $this->nfseFormatarDecimal($valorAcrescimo, 2),
            'total' => $this->nfseFormatarDecimal($total, 2),
            'total_decimal' => $total,
            'c_trib_nac' => $this->textoFiscalNfse($this->nfseServicoCtribNac),
        ]);
        $this->nfseServicos = array_values($this->nfseServicos);
        $this->nfseServicoLinhaIndex = 0;

        $this->limparNfseServicoPendente();
        $this->nfseFocarCampo('nfse-servico-busca');
    }

    public function selecionarNfseServicoLinha(int $index): void
    {
        if (! isset($this->nfseServicos[$index])) {
            return;
        }

        $this->nfseServicoLinhaIndex = $index;
    }

    public function alterarNfseServicoLinha(int $index, string $campo, ?string $valor = null): void
    {
        if (! isset($this->nfseServicos[$index]) || ! in_array($campo, ['quantidade', 'valor', 'desconto', 'acrescimo'], true)) {
            return;
        }

        $this->nfseServicoLinhaIndex = $index;
        $linha = $this->nfseServicos[$index];
        $campoId = match ($campo) {
            'quantidade' => 'nfse-servico-'.($linha['key'] ?? $index).'-qtd',
            'valor' => 'nfse-servico-'.($linha['key'] ?? $index).'-valor',
            'desconto' => 'nfse-servico-'.($linha['key'] ?? $index).'-desconto',
            default => 'nfse-servico-'.($linha['key'] ?? $index).'-acrescimo',
        };

        $qtd = $this->nfseNormalizarDecimal($linha['quantidade'] ?? '0', 3) ?? '0.000';
        $preco = $this->nfseNormalizarDecimal($linha['valor'] ?? '0', 2) ?? '0.00';
        $desconto = $this->nfseNormalizarDecimal($linha['desconto'] ?? '0', 2) ?? '0.00';
        $acrescimo = $this->nfseNormalizarDecimal($linha['acrescimo'] ?? '0', 2) ?? '0.00';

        if ($campo === 'quantidade') {
            $qtdNova = $this->nfseNormalizarDecimal($valor, 3);

            if ($qtdNova === null || $this->nfseCompararDecimal($qtdNova, '0', 3) <= 0) {
                $this->nfseRecusarAlteracaoServico($index, 'Informe a quantidade do serviço.', $campoId);

                return;
            }

            if ($this->nfseCompararDecimal($qtdNova, $qtd, 3) === 0) {
                $this->skipRender();

                return;
            }

            $qtd = $qtdNova;
            $linha['quantidade'] = $this->nfseFormatarDecimal($qtd, 3);
        } elseif ($campo === 'valor') {
            $precoNovo = $this->nfseNormalizarDecimal($valor, 2);

            if ($precoNovo === null || $this->nfseCompararDecimal($precoNovo, '0', 2) < 0) {
                $this->nfseRecusarAlteracaoServico($index, 'Informe o valor do serviço.', $campoId);

                return;
            }

            if ($this->nfseCompararDecimal($precoNovo, $preco, 2) === 0) {
                $this->skipRender();

                return;
            }

            $preco = $precoNovo;
            $linha['valor'] = $this->nfseFormatarDecimal($preco, 2);
        } elseif ($campo === 'desconto') {
            $descNovo = $this->nfseNormalizarDecimal($valor !== null && $valor !== '' ? $valor : '0', 2);

            if ($descNovo === null || $this->nfseCompararDecimal($descNovo, '0', 2) < 0) {
                $this->nfseRecusarAlteracaoServico($index, 'Informe o desconto do serviço.', $campoId);

                return;
            }

            if ($this->nfseCompararDecimal($descNovo, $desconto, 2) === 0) {
                $this->skipRender();

                return;
            }

            $desconto = $descNovo;
            $linha['desconto'] = $this->nfseFormatarDecimal($desconto, 2);
        } else {
            $acreNovo = $this->nfseNormalizarDecimal($valor !== null && $valor !== '' ? $valor : '0', 2);

            if ($acreNovo === null || $this->nfseCompararDecimal($acreNovo, '0', 2) < 0) {
                $this->nfseRecusarAlteracaoServico($index, 'Informe o acréscimo do serviço.', $campoId);

                return;
            }

            if ($this->nfseCompararDecimal($acreNovo, $acrescimo, 2) === 0) {
                $this->skipRender();

                return;
            }

            $acrescimo = $acreNovo;
            $linha['acrescimo'] = $this->nfseFormatarDecimal($acrescimo, 2);
        }

        if ($this->nfseCompararDecimal($qtd, '0', 3) <= 0) {
            $this->nfseRecusarAlteracaoServico($index, 'Informe a quantidade do serviço.', $campoId);

            return;
        }

        $bruto = $this->nfseMultiplicarDecimal($qtd, $preco);

        if ($this->nfseCompararDecimal($desconto, $bruto, 2) > 0) {
            $this->nfseRecusarAlteracaoServico($index, 'Desconto maior que o valor do serviço.', $campoId);

            return;
        }

        $this->nfseAplicarTotalServico($linha, $qtd, $preco, $desconto, $acrescimo);

        $linha['rev'] = ((int) ($linha['rev'] ?? 0)) + 1;
        $this->nfseServicos[$index] = $linha;
        $this->nfseFocarCampo($campoId);
    }

    public function solicitarExclusaoNfseServico(?int $index = null): void
    {
        if ($this->nfseSomenteLeitura() || $this->nfseServicoExcluirIndex !== null) {
            return;
        }

        $index ??= $this->nfseServicoLinhaIndex;

        if ($index === null || ! isset($this->nfseServicos[$index])) {
            Notification::make()
                ->title('Selecione um serviço para excluir.')
                ->warning()
                ->send();

            return;
        }

        $this->nfseServicoLinhaIndex = $index;
        $this->nfseServicoExcluirIndex = $index;
    }

    public function confirmarExclusaoNfseServico(): void
    {
        $index = $this->nfseServicoExcluirIndex;

        if ($index === null || ! isset($this->nfseServicos[$index])) {
            $this->nfseServicoExcluirIndex = null;

            return;
        }

        unset($this->nfseServicos[$index]);
        $this->nfseServicos = array_values($this->nfseServicos);
        $this->nfseServicoExcluirIndex = null;

        $count = count($this->nfseServicos);
        $this->nfseServicoLinhaIndex = $count === 0 ? null : min($index, $count - 1);
    }

    public function cancelarExclusaoNfseServico(): void
    {
        $this->nfseServicoExcluirIndex = null;
    }

    public function nfseServicoTotalPendente(): string
    {
        if ($this->nfseServicoId === null) {
            return '0,00';
        }

        $quantidade = $this->nfseNormalizarDecimal($this->nfseServicoQuantidade, 3);
        $valor = $this->nfseNormalizarDecimal($this->nfseServicoValor, 2);
        $desconto = $this->nfseNormalizarDecimal($this->nfseServicoDesconto !== '' ? $this->nfseServicoDesconto : '0', 2) ?? '0.00';
        $acrescimo = $this->nfseNormalizarDecimal($this->nfseServicoAcrescimo !== '' ? $this->nfseServicoAcrescimo : '0', 2) ?? '0.00';

        if ($quantidade === null || $valor === null) {
            return '0,00';
        }

        return $this->nfseFormatarDecimal(
            $this->nfseTotalLinhaServico($quantidade, $valor, $desconto, $acrescimo),
            2,
        );
    }

    public function nfseServicosSomaFormatada(): string
    {
        return $this->nfseFormatarDecimal($this->nfseServicosSomaDecimal(), 2);
    }

    public function nfseServicosDescontoFormatado(): string
    {
        return $this->nfseFormatarDecimal($this->nfseServicosDescontoDecimal(), 2);
    }

    public function nfseServicosValorBrutoFormatado(): string
    {
        return $this->nfseFormatarDecimal($this->nfseServicosValorBrutoDecimal(), 2);
    }

    protected function nfseServicosSomaDecimal(): string
    {
        $soma = '0.00';

        foreach ($this->nfseServicos as $linha) {
            $parcela = (string) ($linha['total_decimal'] ?? '0.00');
            $soma = $this->nfseSomarDecimal($soma, $parcela);
        }

        return $soma;
    }

    protected function nfseServicosDescontoDecimal(): string
    {
        $soma = '0.00';

        foreach ($this->nfseServicos as $linha) {
            $parcela = $this->nfseNormalizarDecimal($linha['desconto'] ?? '0', 2) ?? '0.00';
            $soma = $this->nfseSomarDecimal($soma, $parcela);
        }

        return $soma;
    }

    protected function nfseServicosValorBrutoDecimal(): string
    {
        $soma = '0.00';

        foreach ($this->nfseServicos as $linha) {
            $qtd = $this->nfseNormalizarDecimal($linha['quantidade'] ?? '0', 3) ?? '0.000';
            $valor = $this->nfseNormalizarDecimal($linha['valor'] ?? '0', 2) ?? '0.00';
            $acrescimo = $this->nfseNormalizarDecimal($linha['acrescimo'] ?? '0', 2) ?? '0.00';
            $bruto = $this->nfseSomarDecimal($this->nfseMultiplicarDecimal($qtd, $valor), $acrescimo);
            $soma = $this->nfseSomarDecimal($soma, $bruto);
        }

        return $soma;
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    protected function nfseAplicarTotalServico(
        array &$linha,
        string $quantidade,
        string $valor,
        ?string $desconto = null,
        ?string $acrescimo = null,
    ): void {
        $desconto ??= $this->nfseNormalizarDecimal($linha['desconto'] ?? '0', 2) ?? '0.00';
        $acrescimo ??= $this->nfseNormalizarDecimal($linha['acrescimo'] ?? '0', 2) ?? '0.00';
        $total = $this->nfseTotalLinhaServico($quantidade, $valor, $desconto, $acrescimo);
        $linha['desconto'] = $this->nfseFormatarDecimal($desconto, 2);
        $linha['acrescimo'] = $this->nfseFormatarDecimal($acrescimo, 2);
        $linha['total'] = $this->nfseFormatarDecimal($total, 2);
        $linha['total_decimal'] = $total;
    }

    protected function nfseTotalLinhaServico(string $quantidade, string $valor, string $desconto, string $acrescimo): string
    {
        $bruto = $this->nfseMultiplicarDecimal($quantidade, $valor);
        $liquido = $this->nfseSubtrairDecimal($bruto, $desconto);

        return $this->nfseSomarDecimal($liquido, $acrescimo);
    }

    protected function nfseRecusarAlteracaoServico(int $index, string $mensagem, string $campoId): void
    {
        if (isset($this->nfseServicos[$index])) {
            $linha = $this->nfseServicos[$index];
            $linha['rev'] = ((int) ($linha['rev'] ?? 0)) + 1;
            $this->nfseServicos[$index] = $linha;
        }

        Notification::make()
            ->title($mensagem)
            ->warning()
            ->send();

        $this->nfseFocarCampo($campoId);
    }

    protected function abrirNfseServicoSugestoes(string $term): void
    {
        $sugestoes = $this->buscarNfseServicos($term);
        $this->nfseServicoSugestoes = $sugestoes;
        $this->nfseServicoSugestoesOpen = true;
        $this->nfseServicoSugestaoIndex = 0;
    }

    /**
     * @param  array<string, mixed>  $sugestao
     */
    protected function aplicarNfseServicoSugestao(array $sugestao): void
    {
        $descricao = trim((string) ($sugestao['descricao'] ?? ''));

        if ($descricao === '') {
            $descricao = trim((string) ($sugestao['codigo'] ?? ''));
        }

        $this->nfseServicoId = (int) $sugestao['id'];
        $this->nfseServicoCodigo = (string) ($sugestao['codigo'] ?? '');
        $this->nfseServicoDescricao = $descricao;
        $this->nfseServicoSelecionadoLabel = $descricao;
        $this->nfseServicoUnidade = (string) ($sugestao['unidade'] ?? '');
        $this->nfseServicoCtribNac = (string) ($sugestao['c_trib_nac'] ?? '');
        $this->nfseServicoQuantidade = $this->nfseFormatarDecimal('1', 3);
        $this->nfseServicoValor = (string) ($sugestao['preco'] ?? '0,00');
        $this->nfseServicoDesconto = '0,00';
        $this->nfseServicoAcrescimo = '0,00';

        if ($descricao !== '') {
            $this->nfseServicoBusca = $descricao;
        }
        $this->fecharNfseServicoSugestoes();
        $this->nfseFocarCampo('nfse-servico-qtd');
    }

    protected function limparNfseServicoPendente(): void
    {
        $this->limparNfseServicoCampos();
        $this->nfseServicoBusca = '';
        $this->fecharNfseServicoSugestoes();
    }

    protected function limparNfseServicoCampos(): void
    {
        $this->nfseServicoId = null;
        $this->nfseServicoCodigo = '';
        $this->nfseServicoDescricao = '';
        $this->nfseServicoSelecionadoLabel = '';
        $this->nfseServicoUnidade = '';
        $this->nfseServicoQuantidade = '';
        $this->nfseServicoValor = '';
        $this->nfseServicoDesconto = '';
        $this->nfseServicoAcrescimo = '';
        $this->nfseServicoCtribNac = '';
    }

    protected function nfseServicoSelecionadoCorresponde(string $term): bool
    {
        if ($this->nfseServicoId === null || $this->nfseServicoSelecionadoLabel === '') {
            return false;
        }

        return mb_strtoupper(trim($term), 'UTF-8') === mb_strtoupper($this->nfseServicoSelecionadoLabel, 'UTF-8');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buscarNfseServicos(string $term): array
    {
        $term = trim($term);
        $numeric = preg_match('/^\d+$/', $term) === 1;

        if ($term === '' || (! $numeric && mb_strlen($term) < 2)) {
            return [];
        }

        $termUpper = mb_strtoupper($term, 'UTF-8');
        $like = '%'.$termUpper.'%';

        $products = Product::query()
            ->where('ativo', true)
            ->where('is_servico', true)
            ->where(function ($query) use ($like, $term, $numeric): void {
                $query->whereRaw('UPPER(codigo) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(descricao) LIKE ?', [$like])
                    ->orWhereRaw('UPPER(referencia) LIKE ?', [$like]);

                if ($numeric) {
                    $query->orWhere('codigo', $term);
                }
            })
            ->orderBy('descricao')
            ->limit(12)
            ->get(['id', 'codigo', 'descricao', 'preco_venda', 'unidade', 'c_trib_nac']);

        return $products
            ->map(fn (Product $product): array => $this->mapearNfseServico($product))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapearNfseServico(Product $product): array
    {
        $preco = $this->nfseNormalizarDecimal($product->getAttributes()['preco_venda'] ?? '0', 2) ?? '0.00';

        return [
            'id' => (int) $product->id,
            'codigo' => (string) ($product->codigo ?? ''),
            'descricao' => (string) ($product->descricao ?? ''),
            'unidade' => (string) ($product->unidade ?? ''),
            'preco' => $this->nfseFormatarDecimal($preco, 2),
            'c_trib_nac' => $product->c_trib_nac,
        ];
    }

    protected function nfseFocarCampo(string $id): void
    {
        $idJs = Js::from($id);

        $this->js(<<<JS
            setTimeout(() => {
                const el = document.getElementById({$idJs});
                if (! el) return;
                el.removeAttribute('readonly');
                el.focus();
                if (typeof el.select === 'function') el.select();
            }, 40);
        JS);
    }

    protected function nfseNormalizarDecimal(mixed $value, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $negative = false;

        if (str_starts_with($raw, '-')) {
            $negative = true;
            $raw = substr($raw, 1);
        } elseif (str_starts_with($raw, '+')) {
            $raw = substr($raw, 1);
        }

        if ($raw === '' || str_contains($raw, ' ')) {
            return null;
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        if (preg_match('/^\d+(\.\d+)?$/', $raw) !== 1) {
            return null;
        }

        if ($negative) {
            $raw = '-'.$raw;
        }

        return $this->nfseArredondarDecimal($raw, $scale);
    }

    protected function nfseArredondarDecimal(string $value, int $scale): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;
        $increment = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($absolute, $increment, $scale);

        if ($negative && bccomp($rounded, '0', $scale) !== 0) {
            return '-'.$rounded;
        }

        return $rounded;
    }

    protected function nfseMultiplicarDecimal(string $quantidade, string $valor): string
    {
        return $this->nfseArredondarDecimal(bcmul($quantidade, $valor, 8), 2);
    }

    protected function nfseSomarDecimal(string $atual, string $parcela): string
    {
        return $this->nfseArredondarDecimal(bcadd($atual, $parcela, 8), 2);
    }

    protected function nfseSubtrairDecimal(string $atual, string $parcela): string
    {
        return $this->nfseArredondarDecimal(bcsub($atual, $parcela, 8), 2);
    }

    protected function nfseCompararDecimal(string $left, string $right, int $scale): int
    {
        return bccomp($left, $right, $scale);
    }

    protected function nfseFormatarDecimal(string $value, int $scale): string
    {
        $normalized = $this->nfseArredondarDecimal($value, $scale);
        $negative = str_starts_with($normalized, '-');
        $absolute = $negative ? substr($normalized, 1) : $normalized;
        [$inteiro, $fracao] = array_pad(explode('.', $absolute, 2), 2, '');
        $fracao = str_pad(substr($fracao, 0, $scale), $scale, '0');
        $inteiro = $inteiro === '' ? '0' : $inteiro;
        $inteiro = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $inteiro) ?? $inteiro;
        $formatted = $scale > 0 ? $inteiro.','.$fracao : $inteiro;

        return $negative ? '-'.$formatted : $formatted;
    }

    public function editNfse(): void
    {
        if ($this->nfseModalOpen) {
            return;
        }

        if ($this->highlightedRecordId === null) {
            Notification::make()
                ->title('Selecione uma NFS-e.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = ErpContext::currentEmpresaId();
        $nfse = Nfse::query()
            ->with('itens')
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->find($this->highlightedRecordId);

        if ($nfse === null) {
            Notification::make()
                ->title('NFS-e não encontrada.')
                ->warning()
                ->send();

            return;
        }

        if ($nfse->status !== Nfse::STATUS_ABERTA) {
            Notification::make()
                ->title('Só é possível alterar NFS-e aberta.')
                ->warning()
                ->send();

            return;
        }

        $this->resetNfseModalForm();
        $this->aplicarNfseGravada($nfse);
        $this->nfseModalOpen = true;
    }

    public function cancelarNfse(): void
    {
        $this->modulePending('Cancelar NFS-e');
    }

    public function imprimirNfse(): void
    {
        $this->nfseFiscalSucessoId = $this->highlightedRecordId;
        $this->imprimirNfseAutorizada();
    }

    public function enviarNfse(): void
    {
        $this->nfseFiscalSucessoId = $this->highlightedRecordId;
        $this->abrirNfseEnviar();
    }

    public function printRelatorioNfse(): void
    {
        $this->modulePending('Relatório de NFS-e');
    }

    public function gerarPdfNfse(): void
    {
        $this->openNfseContadorEmailModal();
    }

    public function refreshTable(): void
    {
        unset($this->nfseRegistros, $this->nfseListagemTotalFormatado);

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');

        $this->redirect(filament()->getUrl());
    }

    public function modulePending(string $module): void
    {
        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();

        $this->skipRender();
    }

    /**
     * @param  list<mixed>  $alertas
     */
    protected function mostrarNfseFiscalSucesso(Nfse $nfse, array $alertas = []): void
    {
        $this->closeNfseFiscalErro();
        $numeroNfse = trim((string) ($nfse->numero_nfse ?? ''));
        $verificacao = trim((string) ($nfse->chave ?? ''));
        $chave = trim((string) ($nfse->chave_acesso ?? ''));

        if ($numeroNfse !== '' && $chave === '') {
            $detalhe = "NFS-e {$numeroNfse} autorizada.";

            if ($verificacao !== '') {
                $detalhe .= "\nCódigo de verificação: {$verificacao}";
            }

            if ($alertas !== []) {
                $detalhe .= "\nO provedor IPM retornou alertas.";
            }
        } else {
            $numero = (string) $nfse->numero_dps;
            $detalhe = $chave !== '' ? "DPS {$numero} — Chave: {$chave}" : "DPS {$numero} autorizada.";

            if ($alertas !== []) {
                $detalhe .= "\nA SEFIN retornou alertas.";
            }
        }

        $osId = (int) ($nfse->ordem_servico_id ?? $this->nfseOsOrigemId ?? 0);
        $ordem = $osId > 0
            ? OrdemServico::query()->with(['itens'])->find($osId)
            : null;

        $this->nfseFiscalSucessoId = (int) $nfse->id;
        $this->nfseFiscalSucessoOsId = $ordem !== null ? (int) $ordem->id : null;
        $this->nfseFiscalSucessoPodeGerarNfe = $ordem !== null
            && ErpAccess::currentCan('nfe.access')
            && \App\Support\Erp\Nfe\NfeOrdemServicoService::osTemPecasParaNfe($ordem);
        $this->nfseFiscalSucessoDetalhe = $detalhe;
        $this->js(
            'window.__erpNfseShowSucesso && window.__erpNfseShowSucesso('
            .Js::from([
                'detalhe' => $detalhe,
                'podeGerarNfe' => $this->nfseFiscalSucessoPodeGerarNfe,
            ])
            .')'
        );
    }

    protected function mostrarNfseFiscalErro(string $titulo, string $mensagem, ?string $codigo = null, bool $sefin = false, ?string $origem = null, ?string $rotuloCodigo = null): void
    {
        $this->closeNfseFiscalSucesso();
        $this->nfseFiscalErroTitulo = $titulo;
        $this->nfseFiscalErroMensagem = $mensagem;
        $this->nfseFiscalErroCodigo = $codigo;
        $this->nfseFiscalErroSefin = $sefin;
        $this->nfseFiscalErroOrigem = $origem ?? '';
        $this->nfseFiscalErroRotulo = $rotuloCodigo ?? '';
        $this->js(
            'window.__erpNfseShowErro && window.__erpNfseShowErro('
            .Js::from([
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'codigo' => $codigo,
                'sefin' => $sefin,
                'origem' => $origem,
                'rotuloCodigo' => $rotuloCodigo,
            ])
            .')'
        );
    }

    protected function mostrarNfseJaAutorizadaOuErro(): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        $nfse = $this->nfseId
            ? Nfse::query()->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))->find($this->nfseId)
            : null;

        if ($nfse !== null && $nfse->status === Nfse::STATUS_AUTORIZADA && filled($nfse->xml_nfse)) {
            $this->mostrarNfseFiscalSucesso($nfse);

            return;
        }

        $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL TRANSMITIR A NFS-E', 'Esta DPS já foi autorizada e não pode ser transmitida novamente.');
    }

    protected function nfseAutorizadaParaCompartilhar(): ?Nfse
    {
        $id = $this->nfseDanfseModalId ?: $this->nfseFiscalSucessoId ?: $this->highlightedRecordId;
        $empresaId = ErpContext::currentEmpresaId();
        $nfse = Nfse::query()
            ->with(['empresa', 'itens'])
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->find($id);

        if ($nfse === null) {
            $this->mostrarNfseFiscalErro('NFS-E NÃO ENCONTRADA', 'Selecione uma NFS-e autorizada.');

            return null;
        }

        if ($nfse->status !== Nfse::STATUS_AUTORIZADA || blank($nfse->xml_nfse)) {
            $this->mostrarNfseFiscalErro('NFS-E AINDA NÃO AUTORIZADA', 'Imprimir e enviar ficam disponíveis depois que a nota for autorizada.');

            return null;
        }

        $xmlNacional = str_contains((string) $nfse->xml_nfse, 'http://www.sped.fazenda.gov.br/nfse');

        if (! $xmlNacional && ! NfseIpmImpressaoViewData::aplica($nfse)) {
            $this->mostrarNfseFiscalErro('IMPRESSÃO INDISPONÍVEL', 'A impressão desta NFS-e ainda não está disponível.');

            return null;
        }

        return $nfse;
    }

    /**
     * @return list<array{path: string, name: string}>
     */
    protected function anexosNfse(Nfse $nfse): array
    {
        $numero = preg_replace('/\D/', '', (string) $nfse->numero_dps) ?: 'nfse';
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nfse-envio-'.$nfse->id;
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return [];
        }

        $anexos = [];
        $xml = (string) $nfse->xml_nfse;

        if (trim($xml) !== '') {
            $xmlPath = $dir.DIRECTORY_SEPARATOR.'NFSe-DPS-'.$numero.'.xml';
            file_put_contents($xmlPath, $xml);
            $anexos[] = ['path' => $xmlPath, 'name' => 'NFSe-DPS-'.$numero.'.xml'];
        }

        $pdfPath = $dir.DIRECTORY_SEPARATOR.'NFSe-DPS-'.$numero.'.pdf';
        $dadosPdf = $this->dadosImpressaoNfse($nfse);
        \Barryvdh\DomPDF\Facade\Pdf::loadView(
            (string) ($dadosPdf['impressao_view'] ?? NfseImpressao::VIEW_NACIONAL),
            $dadosPdf,
        )
            ->setPaper('a4', 'portrait')
            ->save($pdfPath);

        if (is_file($pdfPath)) {
            $anexos[] = ['path' => $pdfPath, 'name' => 'NFSe-DPS-'.$numero.'.pdf'];
        }

        return $anexos;
    }

    /**
     * @param  list<array{path: string, name: string}>  $anexos
     */
    protected function limparAnexosNfse(array $anexos): void
    {
        foreach ($anexos as $anexo) {
            if (is_file($anexo['path'])) {
                @unlink($anexo['path']);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function dadosImpressaoNfse(Nfse $nfse): array
    {
        return NfseImpressao::dados($nfse, autoPrint: false, embedded: true);
    }

    protected function persistirNfseAberta(): Nfse
    {
        if ($this->nfseSomenteLeitura()) {
            throw new NfseNaoGravada('NFS-e autorizada não pode ser alterada.');
        }

        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId === null) {
            throw new NfseNaoGravada('Nenhuma empresa selecionada.');
        }

        $payload = $this->montarPayloadNfse();

        return app(NfseGravarService::class)->gravar(
            $empresaId,
            $this->nfseId,
            $payload['cabecalho'],
            $payload['itens'],
        );
    }

    protected function motivoBloqueioTransmissao(): ?string
    {
        if ($this->nfseSomenteLeitura()) {
            return 'Esta DPS já foi autorizada e não pode ser transmitida novamente.';
        }

        $empresa = ErpContext::currentEmpresa();

        if ($empresa === null) {
            return 'Nenhuma empresa selecionada.';
        }

        if (! $this->nfseAmbienteAtual() instanceof NfseSefinAmbiente) {
            return 'Selecione o ambiente da NFS-e.';
        }

        if ($this->nfseProvedorIpm($empresa)) {
            return $this->motivoBloqueioTransmissaoIpm($empresa);
        }

        if ($this->nfseTomadorId === null) {
            return 'Selecione o tomador.';
        }

        $documento = preg_replace('/\D/', '', $this->nfseTomadorCpfCnpj) ?? '';

        if (strlen($documento) !== 11 && strlen($documento) !== 14) {
            return 'Informe o CPF ou CNPJ do tomador.';
        }

        if ($this->nfseServicos === []) {
            return 'Informe ao menos um serviço.';
        }

        foreach ($this->nfseServicos as $linha) {
            $nacional = preg_replace('/\D/', '', (string) ($linha['c_trib_nac'] ?? '')) ?? '';
            $nbs = preg_replace('/\D/', '', (string) ($linha['c_nbs'] ?? '')) ?? '';
            $quantidade = $this->nfseNormalizarDecimal($linha['quantidade'] ?? null, 3);

            if (trim((string) ($linha['descricao'] ?? '')) === '' || strlen($nacional) !== 6 || strlen($nbs) !== 9 || $quantidade === null || bccomp($quantidade, '0', 3) !== 1) {
                return 'Faltam dados fiscais do serviço.';
            }

            $obra = NfseObra::pendencia($linha['c_trib_nac'] ?? null, $linha, (string) ($linha['descricao'] ?? ''));

            if ($obra !== null) {
                return $obra;
            }
        }

        if (! CepLookupService::isValidIbgeCode($this->nfseMunicipioPrestacaoCodigo())) {
            return 'Falta o código IBGE do município da prestação.';
        }

        if (! array_key_exists($this->nfseTribIssqn, Nfse::tributacoesIssqn()) || ! array_key_exists($this->nfseTpRetIssqn, Nfse::retencoesIssqn())) {
            return 'Informe a tributação e a retenção do ISSQN.';
        }

        if (! $this->certificadoNfseValido($empresa)) {
            return 'Certificado da empresa inválido ou vencido.';
        }

        return null;
    }

    protected function nfseProvedorIpm(?Empresa $empresa): bool
    {
        return strtolower(trim((string) ($empresa?->nfse_provedor ?? ''))) === 'ipm';
    }

    protected function motivoBloqueioTransmissaoIpm(Empresa $empresa): ?string
    {
        $serie = trim((string) ($empresa->nfse_serie_rps ?? ''));

        if (preg_match('/^[A-Za-z0-9]{1,5}$/', $serie) !== 1) {
            return 'Informe a série RPS da NFS-e.';
        }

        if ((string) ($empresa->nfse_ws_senha ?? '') === '') {
            return 'Informe a senha do WebService IPM.';
        }

        try {
            app(NfseIpmCliente::class)->usuario($empresa);
            app(NfseIpmCliente::class)->url($empresa);
        } catch (NfseNaoTransmitida $exception) {
            return $exception->getMessage();
        }

        if (trim((string) $empresa->im) === '') {
            return 'Informe a inscrição municipal da empresa.';
        }

        if ($this->nfseTomadorId === null) {
            return 'Selecione o tomador.';
        }

        $documento = preg_replace('/\D/', '', $this->nfseTomadorCpfCnpj) ?? '';

        if (strlen($documento) !== 11 && strlen($documento) !== 14) {
            return 'Informe o CPF ou CNPJ do tomador.';
        }

        if ($this->nfseServicos === []) {
            return 'Informe ao menos um serviço.';
        }

        foreach ($this->nfseServicos as $linha) {
            $nacional = preg_replace('/\D/', '', (string) ($linha['c_trib_nac'] ?? '')) ?? '';
            $quantidade = $this->nfseNormalizarDecimal($linha['quantidade'] ?? null, 3);

            if (trim((string) ($linha['descricao'] ?? '')) === '' || strlen($nacional) !== 6 || $quantidade === null || bccomp($quantidade, '0', 3) !== 1) {
                return 'Faltam dados fiscais do serviço.';
            }

            if (strlen(preg_replace('/\D/', '', (string) ($linha['c_nbs'] ?? '')) ?? '') !== 9) {
                return 'Informe o código NBS do serviço com 9 dígitos.';
            }

            $obra = NfseObra::pendencia($linha['c_trib_nac'] ?? null, $linha, (string) ($linha['descricao'] ?? ''));

            if ($obra !== null) {
                return $obra;
            }
        }

        if (! CepLookupService::isValidIbgeCode($this->nfseMunicipioPrestacaoCodigo())) {
            return 'Falta o código IBGE do município da prestação.';
        }

        if (! array_key_exists($this->nfseTribIssqn, Nfse::tributacoesIssqn()) || ! array_key_exists($this->nfseTpRetIssqn, Nfse::retencoesIssqn())) {
            return 'Informe a tributação e a retenção do ISSQN.';
        }

        return null;
    }

    protected function nfseAmbienteAtual(): ?NfseSefinAmbiente
    {
        $empresa = ErpContext::currentEmpresa();

        if ($empresa === null) {
            return null;
        }

        return NfseSefinAmbiente::tryFrom(strtolower(trim((string) $empresa->nfse_ambiente)));
    }

    protected function certificadoNfseValido(Empresa $empresa): bool
    {
        if ($this->nfseCertificadoOk === null) {
            $this->nfseCertificadoOk = app(NfseTransmitirService::class)->certificadoValido($empresa);
        }

        return $this->nfseCertificadoOk;
    }

    protected function nfseMunicipioPrestacaoCodigo(): string
    {
        return preg_replace('/\D/', '', (string) ($this->nfseMunicipioCodigo ?? '')) ?? '';
    }

    /**
     * @return array{cabecalho: array<string, mixed>, itens: list<array<string, mixed>>}
     */
    protected function montarPayloadNfse(): array
    {
        if ($this->nfseTomadorId === null) {
            throw new NfseNaoGravada('Selecione o tomador.');
        }

        $person = Person::query()->find($this->nfseTomadorId, ['id', 'nome_razao']);
        $nome = trim((string) ($person->nome_razao ?? ''));

        if ($person === null || $nome === '') {
            throw new NfseNaoGravada('Selecione o tomador.');
        }

        $competencia = $this->competenciaNfseParaData($this->nfseCompetencia);
        $emissao = $this->nfseNormalizarData($this->nfseDataEmissao);

        if ($competencia === null) {
            throw new NfseNaoGravada('Informe a competência.');
        }

        if ($emissao === null) {
            throw new NfseNaoGravada('Informe a data de emissão.');
        }

        $itens = [];
        $somaTotais = '0.00';
        $somaBrutos = '0.00';
        $somaDescontos = '0.00';

        foreach ($this->nfseServicos as $linha) {
            $quantidade = $this->nfseNormalizarDecimal($linha['quantidade'] ?? null, 3);
            $valor = $this->nfseNormalizarDecimal($linha['valor'] ?? null, 2);
            $desconto = $this->nfseNormalizarDecimal($linha['desconto'] ?? '0', 2) ?? '0.00';
            $acrescimo = $this->nfseNormalizarDecimal($linha['acrescimo'] ?? '0', 2) ?? '0.00';
            $total = $this->nfseNormalizarDecimal($linha['total_decimal'] ?? null, 2);
            $codigo = trim((string) ($linha['codigo'] ?? ''));
            $descricao = trim((string) ($linha['descricao'] ?? ''));

            if ($quantidade === null || $valor === null || $total === null || $codigo === '' || $descricao === '') {
                throw new NfseNaoGravada('Informe ao menos um serviço.');
            }

            if ($this->nfseCompararDecimal($desconto, '0', 2) < 0 || $this->nfseCompararDecimal($acrescimo, '0', 2) < 0) {
                throw new NfseNaoGravada('Desconto e acréscimo não podem ser negativos.');
            }

            $esperado = $this->nfseTotalLinhaServico($quantidade, $valor, $desconto, $acrescimo);

            if ($this->nfseCompararDecimal($esperado, $total, 2) !== 0) {
                throw new NfseNaoGravada('O total do serviço não confere com quantidade, valor, desconto e acréscimo.');
            }

            $unidade = trim((string) ($linha['unidade'] ?? ''));
            $productId = (int) ($linha['product_id'] ?? 0);
            $brutoComAcre = $this->nfseSomarDecimal($this->nfseMultiplicarDecimal($quantidade, $valor), $acrescimo);

            $itens[] = [
                'product_id' => $productId > 0 ? $productId : null,
                'codigo' => $codigo,
                'descricao' => $descricao,
                'unidade' => $unidade !== '' ? $unidade : null,
                'quantidade' => $quantidade,
                'valor' => $valor,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'total' => $total,
                'c_trib_nac' => $this->textoFiscalNfse($linha['c_trib_nac'] ?? null),
                'c_nbs' => $this->textoFiscalNfse($linha['c_nbs'] ?? null),
                'c_trib_mun' => $this->textoFiscalNfse($linha['c_trib_mun'] ?? null),
                'c_ind_op' => $this->textoFiscalNfse($linha['c_ind_op'] ?? null),
                'obra_tipo' => $linha['obra_tipo'] ?? null,
                'obra_insc_imob_fisc' => $linha['obra_insc_imob_fisc'] ?? null,
                'obra_c_obra' => $linha['obra_c_obra'] ?? null,
                'obra_c_cib' => $linha['obra_c_cib'] ?? null,
                'obra_cep' => $linha['obra_cep'] ?? null,
                'obra_logradouro' => $linha['obra_logradouro'] ?? null,
                'obra_numero' => $linha['obra_numero'] ?? null,
                'obra_complemento' => $linha['obra_complemento'] ?? null,
                'obra_bairro' => $linha['obra_bairro'] ?? null,
            ];
            $somaTotais = $this->nfseSomarDecimal($somaTotais, $total);
            $somaBrutos = $this->nfseSomarDecimal($somaBrutos, $brutoComAcre);
            $somaDescontos = $this->nfseSomarDecimal($somaDescontos, $desconto);
        }

        if ($itens === []) {
            throw new NfseNaoGravada('Informe ao menos um serviço.');
        }

        $municipio = $this->municipioPrestacaoParaGravar();
        $uf = mb_strtoupper(trim($this->nfseTomadorUf), 'UTF-8');

        return [
            'cabecalho' => [
                'tomador_id' => (int) $this->nfseTomadorId,
                'tomador_nome' => $nome,
                'tomador_cpf_cnpj' => $this->textoNfseOuNulo($this->nfseTomadorCpfCnpj),
                'tomador_telefone' => $this->textoNfseOuNulo($this->nfseTomadorTelefone),
                'tomador_endereco' => $this->textoNfseOuNulo($this->nfseTomadorEndereco),
                'tomador_numero' => $this->textoNfseOuNulo($this->nfseTomadorNumero, 30),
                'tomador_bairro' => $this->textoNfseOuNulo($this->nfseTomadorBairro),
                'tomador_cep' => $this->textoNfseOuNulo($this->nfseTomadorCep, 20),
                'tomador_cidade' => $this->textoNfseOuNulo($this->nfseTomadorCidade),
                'tomador_uf' => $uf !== '' ? $this->textoNfseOuNulo($uf, 2) : null,
                'tomador_cidade_codigo' => $this->textoNfseOuNulo($this->nfseTomadorCidadeCodigo),
                'tomador_email' => $this->textoNfseOuNulo($this->nfseTomadorEmail),
                'competencia' => $competencia,
                'data_emissao' => $emissao,
                'municipio_incidencia' => $municipio['nome'],
                'municipio_prestacao_codigo' => $municipio['codigo'],
                'municipio_prestacao_nome' => $municipio['nome'],
                'municipio_prestacao_uf' => $municipio['uf'],
                'trib_issqn' => $this->codigoIssqnNfse($this->nfseTribIssqn, Nfse::tributacoesIssqn(), 'Informe a tributação do ISSQN.'),
                'tp_ret_issqn' => $this->codigoIssqnNfse($this->nfseTpRetIssqn, Nfse::retencoesIssqn(), 'Informe a retenção do ISSQN.'),
                'valor_servicos' => $somaBrutos,
                'desconto' => $somaDescontos,
                'iss' => '0.00',
                'total' => $somaTotais,
                'ordem_servico_id' => $this->resolverOrdemServicoOrigemId(),
            ],
            'itens' => $itens,
        ];
    }

    protected function resolverOrdemServicoOrigemId(): ?int
    {
        if ($this->nfseOsOrigemId !== null && (int) $this->nfseOsOrigemId > 0) {
            return (int) $this->nfseOsOrigemId;
        }

        foreach ($this->nfseServicos as $linha) {
            $osId = (int) ($linha['os_id'] ?? 0);
            if ($osId > 0) {
                return $osId;
            }
        }

        return null;
    }

    protected function vincularNfseAOrdemServico(Nfse $nfse): void
    {
        $osId = $this->resolverOrdemServicoOrigemId();

        if ($osId === null) {
            return;
        }

        $this->nfseOsOrigemId = $osId;

        if ((int) ($nfse->ordem_servico_id ?? 0) === $osId) {
            return;
        }

        Nfse::query()->whereKey($nfse->id)->update(['ordem_servico_id' => $osId]);
        $nfse->ordem_servico_id = $osId;
    }

    protected function aplicarNfseGravada(Nfse $nfse): void
    {
        $nfse->loadMissing('itens');

        $this->nfseId = (int) $nfse->id;
        if ($nfse->ordem_servico_id !== null && (int) $nfse->ordem_servico_id > 0) {
            $this->nfseOsOrigemId = (int) $nfse->ordem_servico_id;
        }
        $this->nfseStatus = (string) ($nfse->status ?: Nfse::STATUS_ABERTA);
        $this->nfseSerieDps = (string) $nfse->serie_dps;
        $this->nfseNumeroDps = (string) $nfse->numero_dps;
        $this->nfseNumeroNfse = trim((string) ($nfse->numero_nfse ?? ''));
        $this->highlightedRecordId = $this->nfseId;
        $this->nfseTomadorId = $nfse->tomador_id !== null ? (int) $nfse->tomador_id : null;
        $this->nfseTomador = (string) $nfse->tomador_nome;
        $this->nfseTomadorSelecionadoLabel = (string) $nfse->tomador_nome;
        $this->nfseTomadorCpfCnpj = (string) ($nfse->tomador_cpf_cnpj ?? '');
        $this->nfseTomadorTelefone = (string) ($nfse->tomador_telefone ?? '');
        $this->nfseTomadorEndereco = (string) ($nfse->tomador_endereco ?? '');
        $this->nfseTomadorNumero = (string) ($nfse->tomador_numero ?? '');
        $this->nfseTomadorBairro = (string) ($nfse->tomador_bairro ?? '');
        $this->nfseTomadorCep = (string) ($nfse->tomador_cep ?? '');
        $this->nfseTomadorCidade = (string) ($nfse->tomador_cidade ?? '');
        $this->nfseTomadorUf = (string) ($nfse->tomador_uf ?? '');
        $this->nfseTomadorCidadeCodigo = (string) ($nfse->tomador_cidade_codigo ?? '');
        $this->nfseTomadorEmail = (string) ($nfse->tomador_email ?? '');
        $this->nfseCompetencia = $nfse->competencia?->format('Y-m') ?? '';
        $this->nfseDataEmissao = $nfse->data_emissao?->format('Y-m-d') ?? '';
        $this->aplicarNfseMunicipioGravado($nfse);
        $this->nfseTribIssqn = $this->codigoIssqnGravado($nfse->trib_issqn, Nfse::tributacoesIssqn(), Nfse::TRIB_ISSQN_TRIBUTAVEL);
        $this->nfseTpRetIssqn = $this->codigoIssqnGravado($nfse->tp_ret_issqn, Nfse::retencoesIssqn(), Nfse::TP_RET_ISSQN_NAO_RETIDO);
        $this->nfseServicos = [];
        $seq = 0;

        foreach ($nfse->itens as $item) {
            $seq++;
            $quantidade = $this->nfseNormalizarDecimal($item->quantidade, 3) ?? '0.000';
            $valor = $this->nfseNormalizarDecimal($item->valor, 2) ?? '0.00';
            $total = $this->nfseNormalizarDecimal($item->total, 2) ?? '0.00';
            $bruto = $this->nfseMultiplicarDecimal($quantidade, $valor);
            $diff = $this->nfseSubtrairDecimal($bruto, $total);
            $desconto = '0.00';
            $acrescimo = '0.00';

            if ($this->nfseCompararDecimal($diff, '0', 2) > 0) {
                $desconto = $diff;
            } elseif ($this->nfseCompararDecimal($diff, '0', 2) < 0) {
                $acrescimo = $this->nfseSubtrairDecimal('0.00', $diff);
            }

            $this->nfseServicos[] = [
                'key' => 'nfse-item-'.$item->id,
                'rev' => 0,
                'product_id' => $item->product_id !== null ? (int) $item->product_id : null,
                'codigo' => (string) $item->codigo,
                'descricao' => (string) $item->descricao,
                'unidade' => (string) ($item->unidade ?? ''),
                'quantidade' => $this->nfseFormatarDecimal($quantidade, 3),
                'valor' => $this->nfseFormatarDecimal($valor, 2),
                'desconto' => $this->nfseFormatarDecimal($desconto, 2),
                'acrescimo' => $this->nfseFormatarDecimal($acrescimo, 2),
                'total' => $this->nfseFormatarDecimal($total, 2),
                'total_decimal' => $total,
                'c_trib_nac' => $item->c_trib_nac,
                'c_nbs' => $item->c_nbs,
                'c_trib_mun' => $item->c_trib_mun,
                'c_ind_op' => $item->c_ind_op,
                'obra_tipo' => $item->obra_tipo,
                'obra_insc_imob_fisc' => $item->obra_insc_imob_fisc,
                'obra_c_obra' => $item->obra_c_obra,
                'obra_c_cib' => $item->obra_c_cib,
                'obra_cep' => $item->obra_cep,
                'obra_logradouro' => $item->obra_logradouro,
                'obra_numero' => $item->obra_numero,
                'obra_complemento' => $item->obra_complemento,
                'obra_bairro' => $item->obra_bairro,
            ];
        }

        $this->nfseServicoSeq = $seq;
        $this->nfseServicoLinhaIndex = $this->nfseServicos === [] ? null : 0;
        $this->nfseServicoExcluirIndex = null;
        $this->limparNfseServicoPendente();
    }

    /**
     * @param  array<string, string>  $opcoes
     */
    protected function codigoIssqnNfse(string $valor, array $opcoes, string $mensagem): string
    {
        $codigo = trim($valor);

        if (! array_key_exists($codigo, $opcoes)) {
            throw new NfseNaoGravada($mensagem);
        }

        return $codigo;
    }

    /**
     * @param  array<string, string>  $opcoes
     */
    protected function codigoIssqnGravado(mixed $valor, array $opcoes, string $padrao): string
    {
        $codigo = trim((string) $valor);

        return array_key_exists($codigo, $opcoes) ? $codigo : $padrao;
    }

    protected function competenciaNfseParaData(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $this->nfseNormalizarData($value.'-01');
    }

    protected function nfseNormalizarData(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $data !== false && $data->format('Y-m-d') === $value ? $value : null;
    }

    protected function textoFiscalNfse(mixed $value): ?string
    {
        $texto = trim((string) $value);

        return $texto === '' ? null : $texto;
    }

    protected function textoNfseOuNulo(?string $value, int $max = 255): ?string
    {
        $texto = trim((string) $value);

        if ($texto === '') {
            return null;
        }

        if (mb_strlen($texto) > $max) {
            throw new NfseNaoGravada('Há um campo maior do que o permitido.');
        }

        return $texto;
    }

    protected function aplicarFiltroDataNfse(\Illuminate\Database\Eloquent\Builder $query, string $column): void
    {
        $de = $this->nfseNormalizarData($this->localSearchDe);
        $ate = $this->nfseNormalizarData($this->localSearchAte);
        $campo = $column === 'competencia' ? 'competencia' : 'data_emissao';

        if ($de !== null) {
            $query->whereDate($campo, '>=', $column === 'competencia' ? substr($de, 0, 7).'-01' : $de);
        }

        if ($ate === null) {
            return;
        }

        if ($column === 'competencia') {
            $fim = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($ate, 0, 7).'-01');
            $query->whereDate($campo, '<=', $fim !== false ? $fim->format('Y-m-t') : $ate);

            return;
        }

        $query->whereDate($campo, '<=', $ate);
    }

    protected function aplicarFiltroTextoNfse(\Illuminate\Database\Eloquent\Builder $query, string $column, string $term): void
    {
        $like = '%'.mb_strtoupper($term, 'UTF-8').'%';

        match ($column) {
            'numero' => $query->where('numero_dps', (int) preg_replace('/\D/', '', $term)),
            'tomador' => $query->whereRaw('UPPER(tomador_nome) LIKE ?', [$like]),
            'chave' => $query->where('chave', 'like', $like),
            'protocolo' => $query->where('protocolo', 'like', $like),
            'municipio' => $query->whereRaw('UPPER(municipio_incidencia) LIKE ?', [$like]),
            'total' => $this->aplicarFiltroTotalNfse($query, $term),
            default => null,
        };
    }

    protected function aplicarFiltroTotalNfse(\Illuminate\Database\Eloquent\Builder $query, string $term): void
    {
        $total = $this->nfseNormalizarDecimal($term, 2);

        if ($total === null) {
            return;
        }

        $query->where('total', $total);
    }

    /**
     * @return list<string>
     */
    protected function localSearchColumns(): array
    {
        return ['numero', 'data_emissao', 'competencia', 'tomador', 'chave', 'protocolo', 'municipio', 'total'];
    }

    protected function isDateSearchColumn(string $column): bool
    {
        return in_array($column, ['data_emissao', 'competencia'], true);
    }

    protected function activeDateSearchColumn(): ?string
    {
        foreach ($this->normalizedSearchFieldsActive() as $column) {
            if ($this->isDateSearchColumn($column)) {
                return $column;
            }
        }

        return null;
    }

    protected function applyCurrentMonthDateFilter(): void
    {
        $hoje = ErpTimezone::toLocal();
        $this->localSearchDe = $hoje->copy()->startOfMonth()->toDateString();
        $this->localSearchAte = $hoje->copy()->endOfMonth()->toDateString();
    }

    protected function normalizeStatusFilter(string $filter): string
    {
        return in_array($filter, self::statusTabs(), true) ? $filter : 'todas';
    }

    /**
     * @param  list<string>  $active
     * @return list<string>
     */
    protected function ensureTwoSearchFields(array $active): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_unique(array_filter(
            $active,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        )));

        $defaults = ['tomador', 'data_emissao'];

        foreach ($defaults as $default) {
            if (count($active) >= 2) {
                break;
            }

            if (! in_array($default, $active, true)) {
                $active[] = $default;
            }
        }

        return array_values(array_slice($active, 0, 2));
    }

    /**
     * @return list<string>
     */
    protected function normalizedSearchFieldsActive(): array
    {
        $allowed = $this->localSearchColumns();
        $active = array_values(array_filter(
            $this->searchFieldsActive,
            fn (mixed $column): bool => is_string($column) && in_array($column, $allowed, true),
        ));

        if ($active === []) {
            return ['tomador'];
        }

        return array_values(array_unique($active));
    }

    protected function pruneLocalSearchByField(): void
    {
        $active = $this->normalizedSearchFieldsActive();

        foreach (array_keys($this->localSearchByField) as $column) {
            if (! in_array($column, $active, true) || $this->isDateSearchColumn($column)) {
                unset($this->localSearchByField[$column]);
            }
        }
    }
}
