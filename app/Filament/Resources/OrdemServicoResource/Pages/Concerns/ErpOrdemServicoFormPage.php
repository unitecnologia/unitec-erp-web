<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Filament\Concerns\InteractsWithErpSpaRedirect;
use App\Filament\Pages\Concerns\ManagesBoletoPosDocumentoPrompt;
use App\Filament\Pages\NfsePage;
use App\Filament\Resources\OrdemServicoResource;
use App\Filament\Resources\PersonResource;
use App\Filament\Resources\ProductResource;
use App\Models\FormaPagamento;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\OsVeiculo;
use App\Models\Person;
use App\Models\Product;
use App\Models\Vendedor;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\FormaPagamentoAtalho;
use App\Support\Erp\Nfse\NfseFromOrdemServico;
use App\Support\Erp\Os\OsFaturamentoService;
use App\Support\Erp\Os\OsProdutoLookupService;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Erp\Pdv\PdvNotaClienteService;
use App\Support\Erp\DocumentoBrasileiroValidator;
use App\Support\Erp\ProductPhotoUrl;
use App\Support\Erp\ProductTabelaPrecoService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ErpOrdemServicoFormPage
{
    use InteractsWithErpSpaRedirect;
    use ManagesBoletoPosDocumentoPrompt;
    use ManagesOrdemServicoFotos;
    use ManagesOrdemServicoFaturamentoParcelas;
    use ManagesEquipamentoVeiculo;
    use ManagesOrdemServicoImportarOrcamento;
    use ManagesOrdemServicoTransportadora;
    public string $activeFormTab = 'dados';

    public bool $osFaturamentoOpen = false;

    public bool $osPersistindo = false;

    public bool $previewOverlayOpen = false;

    public ?string $previewOverlayUrl = null;

    public bool $printModalOpen = false;

    /** @var list<array<string, mixed>> */
    public array $osMeiosPagamento = [];

    public int $osPagamentoIndex = 0;

    public string $osAcrescimoPct = '0,00';

    public string $osAcrescimoValor = '0,00';

    public string $osDescontoPct = '0,00';

    public string $osDescontoValor = '0,00';

    /** Divisão informativa da conta no faturamento — não altera pagamentos. */
    public string $osDividirPor = '1';

    public string $activeItemTab = 'servicos';

    public string $clienteSearch = '';

    public bool $clienteLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $clienteResults = [];

    public ?int $selectedClienteIndex = null;

    public ?int $clienteId = null;

    public string $documento = '';

    public string $fone1 = '';

    public string $nome = '';

    public string $endereco = '';

    public string $bairro = '';

    public string $cidade = '';

    public string $uf = 'SC';

    public ?int $atendenteId = null;

    public string $dataInicio = '';

    public string $horaInicio = '';

    public string $previsaoEntrega = '';

    public string $dataTermino = '';

    public string $horaTermino = '';


    public string $problema = '';

    public string $observacoes = '';

    /** CPF na Nota no F4 — sessão da tela; NFC-e da OS continua pelo fluxo fiscal próprio. */
    public string $osCpfNota = '';

    public string $laudo = '';

    /** @var array<int, array<string, mixed>> */
    public array $itens = [];

    public ?int $selectedItemIndex = null;

    public ?int $editingItemIndex = null;

    public string $itemCodigoInput = '';

    public string $itemProdutoSearch = '';

    public bool $produtoLookupOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $produtoResults = [];

    public ?int $selectedProdutoIndex = null;

    public ?int $itemPendingProductId = null;

    public bool $osPendingUsaTabPreco = false;

    public string $itemQtdInput = '1,000';

    public string $itemPrecoInput = '';

    public string $itemPendingDesconto = '0,00';

    public string $itemPendingAcrescimo = '0,00';

    public string $itemTotalEntryDisplay = '0,00';

    /**
     * Preço unitário sugerido pelo motor no staging (servidor).
     * Na confirmação: se o informado diverge → alteração manual; se igual → revalidar no servidor.
     */
    public ?float $osPrecoSugeridoInclusao = null;

    /** Modal Tab. Preço ao lançar peça/serviço com usa_tab_preco. */
    public bool $tabPrecoItemModalOpen = false;

    public bool $tabPrecoItemConfirmada = false;

    public ?int $tabPrecoItemProductId = null;

    /** @var list<array{nivel: string, price_table_id: int, codigo: string, descricao: string, label: string, preco: float}> */
    public array $tabPrecoItemOpcoes = [];

    public ?int $tabPrecoItemSelectedIndex = null;

    public bool $postSavePromptOpen = false;

    public bool $descontoModalOpen = false;

    public bool $servicoPrestadoModalOpen = false;

    public ?int $servicoPrestadoIndex = null;

    public string $servicoPrestadoTexto = '';

    public ?string $itemAjusteAlvo = null;

    public string $itemAjusteTipo = 'desconto';

    public string $itemAjusteModo = 'percentual';

    public string $itemAjusteValor = '0,00';

    public string $barcodeInput = '';

    public string $subtotalPecas = '0,00';

    public string $subtotalServicos = '0,00';

    public string $subtotalGeral = '0,00';

    /** Desconto extra no cabeçalho (vl_desc_*), além dos descontos por item. */
    public string $descPecasGlobal = '0,00';

    public string $descServicosGlobal = '0,00';

    /** Exibição: desconto dos itens + global. */
    public string $descPecas = '0,00';

    public string $descServicos = '0,00';

    public string $totalPecas = '0,00';

    public string $totalServicos = '0,00';

    public string $totalGeral = '0,00';

    public bool $overlayProductOpen = false;

    public bool $overlayPersonOpen = false;

    public ?int $itemDeleteConfirmIndex = null;

    public bool $isConfirmingPendingItem = false;

    public ?string $produtoAtualFoto = null;

    public string $produtoAtualNome = '';

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
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

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
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
            'erp-form-page',
            'erp-os-form-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.ordens-servico.form.window'),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                    ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
            ]);
    }


    public function setActiveFormTab(string $tab): void
    {
        if (in_array($tab, ['dados', 'equipamento', 'defeito', 'observacoes'], true)) {
            $this->activeFormTab = $tab;
        }
    }

    public function setActiveItemTab(string $tab): void
    {
        if (! in_array($tab, ['servicos', 'pecas'], true)) {
            return;
        }

        if ($this->activeItemTab === $tab) {
            $this->skipRender();

            return;
        }

        // Grade Serviços/Peças troca no Alpine. A barra (texto, dropdown, seleção)
        // é limpa no estado Livewire; o DOM do lookup é fechado no JS na hora.
        $needsBarRemorph = $this->itemPendingProductId !== null
            || $this->editingItemIndex !== null
            || $this->tabPrecoItemModalOpen
            || $this->produtoAtualFoto !== null;

        $this->activeItemTab = $tab;
        $this->selectedItemIndex = null;
        $this->clearItemEntryRow();

        if (! $needsBarRemorph) {
            $this->skipRender();
        }
    }

    public function isEditingOs(): bool
    {
        return $this instanceof EditRecord;
    }

    public function osReadOnly(): bool
    {
        return $this->isEditingOs() && ! ($this->record?->isEditable() ?? true);
    }

    public function osPodeEmitirNfse(): bool
    {
        $ordem = $this->record;

        return $ordem instanceof OrdemServico && NfseFromOrdemServico::podeFaturar($ordem);
    }

    public function emitirNfseDaOs(): void
    {
        $ordem = $this->record;

        if (! $ordem instanceof OrdemServico) {
            Notification::make()->title('Salve a OS antes de emitir NFS-e.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'nfse.access')) {
            return;
        }

        $motivo = NfseFromOrdemServico::motivoBloqueio($ordem);

        if ($motivo !== null) {
            Notification::make()->title($motivo)->warning()->send();

            return;
        }

        $this->redirect(NfsePage::getUrl().'?os='.$ordem->id, navigate: false);
    }

    public function openPrintModal(): void
    {
        $ordem = $this->record;

        if (! $ordem instanceof OrdemServico || ! $ordem->exists) {
            Notification::make()->title('Salve a OS antes de imprimir.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.print')) {
            return;
        }

        $this->printModalOpen = true;
    }

    public function closePrintModal(): void
    {
        $this->printModalOpen = false;
    }

    public function imprimirOs(): void
    {
        $this->openPrintModal();
    }

    public function imprimirOsCompleta(): void
    {
        $this->abrirPreviewOs(tecnica: false);
    }

    public function imprimirOsTecnica(): void
    {
        $this->abrirPreviewOs(tecnica: true);
    }

    protected function abrirPreviewOs(bool $tecnica): void
    {
        $ordem = $this->record;

        if (! $ordem instanceof OrdemServico || ! $ordem->exists) {
            Notification::make()->title('Salve a OS antes de imprimir.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'ordens_servico.print')) {
            return;
        }

        $params = [
            'ordem' => $ordem->id,
            'embed' => 1,
        ];

        if ($tecnica) {
            $params['tecnica'] = 1;
        }

        $this->closePrintModal();
        $this->previewOverlayUrl = route('erp.reports.ordem-servico', $params);
        $this->previewOverlayOpen = true;
    }

    #[On('close-os-preview')]
    public function closePreviewOverlay(): void
    {
        $this->previewOverlayOpen = false;
        $this->previewOverlayUrl = null;
    }

    public function osNumeroDisplay(): string
    {
        $fromData = trim((string) ($this->data['numero'] ?? ''));

        if ($fromData !== '') {
            return $fromData;
        }

        if ($this->isEditingOs()) {
            return (string) ($this->record?->numero ?? '—');
        }

        return OrdemServico::nextNumero();
    }

    /**
     * @return array<int, array{id: int, nome: string}>
     */
    public function updatedAtendenteId(): void
    {
        unset($this->atendenteOptions);
        $this->syncTecnicoEmTodosItens();
    }

    protected function syncTecnicoEmTodosItens(): void
    {
        if ($this->itens === []) {
            return;
        }

        $itens = $this->itens;

        foreach (array_keys($itens) as $index) {
            $itens[$index]['funcionario_id'] = $this->atendenteId;
        }

        $this->itens = $itens;
    }

    /**
     * Técnicos para NOVAS seleções: ativos, setor_servicos, com RH e empresa atual.
     * Value = vendedores.id (atendente_id / funcionario_id). Label = código/nome do RH.
     * Se a OS já tiver atendente legado (órfão/inativo/fora do setor), inclui só esse id
     * para não sumir o histórico — sem liberá-lo como opção genérica em OS nova.
     *
     * Memoizado por request (#[Computed]): evita 2× a mesma query pesada no painel Dados + grade.
     *
     * @return list<array{id: int, nome: string}>
     */
    #[Computed]
    public function atendenteOptions(): array
    {
        $empresaId = ErpContext::currentEmpresaId();
        $atualId = (int) ($this->atendenteId ?? 0);

        $query = Vendedor::query()
            ->where('ativo', true)
            ->where('setor_servicos', true)
            ->whereHas('rhFuncionario')
            ->with('rhFuncionario');

        if ($empresaId) {
            $query->whereHas(
                'empresas',
                fn ($q) => $q->where('empresas.id', (int) $empresaId)
            );
        }

        $opcoes = $query
            ->get(['id', 'codigo', 'nome'])
            ->sortBy(fn (Vendedor $v): int => (int) preg_replace('/\D/', '', (string) ($v->rhFuncionario?->codigo ?? '0')))
            ->values()
            ->map(fn (Vendedor $v): array => $this->mapAtendenteOption($v))
            ->all();

        if ($atualId > 0 && ! collect($opcoes)->contains(fn (array $op): bool => (int) $op['id'] === $atualId)) {
            $atual = Vendedor::query()->with('rhFuncionario')->find($atualId);

            if ($atual) {
                array_unshift($opcoes, $this->mapAtendenteOption($atual));
            }
        }

        return $opcoes;
    }

    /**
     * @return array{id: int, nome: string}
     */
    private function mapAtendenteOption(Vendedor $vendedor): array
    {
        $rh = $vendedor->rhFuncionario;
        $codigo = trim((string) ($rh?->codigo ?? $vendedor->codigo ?? ''));
        $nome = trim((string) ($rh?->nome ?? $vendedor->nome ?? ''));
        $label = trim(($codigo !== '' ? $codigo.' - ' : '').$nome);

        return [
            'id' => (int) $vendedor->id,
            'nome' => mb_strtoupper($label !== '' ? $label : (string) ($vendedor->nome ?? ''), 'UTF-8'),
        ];
    }

    public function getProductOverlayUrlProperty(): string
    {
        return ProductResource::getUrl('create').'?orcamento=1&os_embed=1';
    }

    public function getPersonOverlayUrlProperty(): string
    {
        return PersonResource::getUrl('create').'?tipo=clientes&orcamento=1&os_embed=1';
    }

    /**
     * Hook Livewire do trait: reduz o snapshot na resposta sem apagar o que a tela
     * ainda está usando (lookups/modais abertos, itens digitados, totais).
     */
    public function dehydrateErpOrdemServicoFormPage(): void
    {
        $this->pruneOsEphemeralLivewireState();
    }

    /**
     * Remove do snapshot arrays efêmeros e URLs de foto nas linhas da grade.
     * Não toca em persistOs / Gravar / Finalizar.
     */
    protected function pruneOsEphemeralLivewireState(): void
    {
        if (! $this->clienteLookupOpen && $this->clienteResults !== []) {
            $this->clienteResults = [];
            $this->selectedClienteIndex = null;
        }

        if (! $this->produtoLookupOpen && $this->produtoResults !== []) {
            $this->produtoResults = [];
            $this->selectedProdutoIndex = null;
        }

        if (! $this->equipamentoLookupOpen && $this->equipamentoResults !== []) {
            $this->equipamentoResults = [];
            $this->selectedEquipamentoIndex = null;
        }

        if (! $this->osImportOrcamentoOpen && $this->osImportOrcamentoResults !== []) {
            $this->osImportOrcamentoResults = [];
            $this->osImportOrcamentoSelectedIndex = null;
        }

        if (! $this->tabPrecoItemModalOpen) {
            $this->tabPrecoItemOpcoes = [];
            $this->tabPrecoItemProductId = null;
            $this->tabPrecoItemSelectedIndex = null;
        }

        if (! $this->osFaturamentoOpen) {
            if ($this->osMeiosPagamento !== []) {
                $this->osMeiosPagamento = [];
            }

            $this->resetOsParcelasFaturamento();
            $this->resetAjusteFaturamentoOs();
        } elseif (! $this->osTabelasPrazoListaAberta && $this->osTabelasPrazoPredefinidas !== []) {
            $this->osTabelasPrazoPredefinidas = [];
            $this->osSelectedTabelaPredefinidaIndex = null;
        }

        if (! $this->previewOverlayOpen) {
            $this->previewOverlayUrl = null;
        }

        if (! $this->boletoEnviarModalOpen) {
            $this->boletoEnviarAttachments = [];
        }

        if ($this->itens === []) {
            return;
        }

        $changed = false;
        $itens = $this->itens;

        foreach ($itens as $index => $row) {
            if (! is_array($row) || ! array_key_exists('foto', $row)) {
                continue;
            }

            unset($itens[$index]['foto']);
            $changed = true;
        }

        if ($changed) {
            $this->itens = $itens;
        }
    }

    /**
     * Atualiza o índice no servidor e o destaque no DOM, sem remorph da OS.
     */
    protected function skipOsRenderWithLookupHighlight(string $kind, ?int $index): void
    {
        $this->skipRender();

        if ($index !== null) {
            $this->dispatch('erp-os-lookup-highlight', kind: $kind, index: $index);
        }
    }

    /**
     * @return list<string>
     */
    protected function osFormRecordRelations(): array
    {
        return [
            'cliente:id,nome_razao',
            'itens.product:id,codigo,descricao',
            'imagens',
        ];
    }

    protected function initializeOsFormDefaults(): void
    {
        $momento = ErpTimezone::toLocal();

        $this->atendenteId = Auth::user()?->vendedor_id;
        $this->dataInicio = $momento->format('Y-m-d');
        $this->horaInicio = $momento->format('H:i');
        $this->previsaoEntrega = '';
        $this->dataTermino = '';
        $this->horaTermino = '';
        $this->activeFormTab = 'dados';
        $this->activeItemTab = 'servicos';
        $this->orcamentoOrigemId = null;
        $this->itens = [];
        $this->syncTotaisDisplay(0, 0, 0, 0);
        $this->resetOsFotosState();

        $this->data = [
            'numero' => OrdemServico::nextNumero(),
            'situacao' => OrdemServico::SITUACAO_ABERTA,
        ];
        $this->form->fill($this->data);
    }

    protected function loadOsFormFromRecord(OrdemServico $ordem): void
    {
        $ordem->load($this->osFormRecordRelations());

        $this->data = [
            'numero' => $ordem->numero,
            'situacao' => $ordem->situacao,
        ];
        $this->form->fill($this->data);

        $this->clienteId = $ordem->cliente_id;
        $this->orcamentoOrigemId = $ordem->orcamento_id ? (int) $ordem->orcamento_id : null;
        $this->nome = mb_strtoupper((string) ($ordem->nome ?? ''), 'UTF-8');
        $this->clienteSearch = $this->nome !== ''
            ? $this->nome
            : mb_strtoupper((string) ($ordem->cliente?->nome_razao ?? ''), 'UTF-8');
        $this->documento = (string) ($ordem->documento ?? '');
        $this->fone1 = (string) ($ordem->fone1 ?? '');
        $this->endereco = mb_strtoupper((string) ($ordem->endereco ?? ''), 'UTF-8');
        $this->bairro = mb_strtoupper((string) ($ordem->bairro ?? ''), 'UTF-8');
        $this->cidade = mb_strtoupper((string) ($ordem->cidade ?? ''), 'UTF-8');
        $this->uf = mb_strtoupper((string) ($ordem->uf ?: 'SC'), 'UTF-8');
        $this->atendenteId = $ordem->atendente_id;

        $this->dataInicio = $ordem->data_inicio?->format('Y-m-d') ?? '';
        $this->horaInicio = $ordem->horaInicioExibicao() ?? '';
        $this->previsaoEntrega = $ordem->previsao_entrega?->format('Y-m-d\TH:i') ?? '';
        $this->dataTermino = $ordem->data_termino?->format('Y-m-d') ?? '';
        $this->horaTermino = $ordem->hora_termino
            ? substr((string) $ordem->hora_termino, 0, 5)
            : '';

        $this->numeroSerie = (string) ($ordem->numero_serie ?? '');
        $this->descricao = mb_strtoupper((string) ($ordem->descricao ?: $ordem->marca ?: $ordem->marca_veiculo ?: ''), 'UTF-8');
        $this->descricao2 = mb_strtoupper((string) ($ordem->descricao2 ?? ''), 'UTF-8');
        $this->modelo = mb_strtoupper((string) ($ordem->modelo ?: $ordem->modelo_veiculo ?: ''), 'UTF-8');
        $this->ano = (string) ($ordem->ano ?: $ordem->ano_veiculo ?: '');
        $this->placa = mb_strtoupper((string) ($ordem->placa ?: $ordem->placa_veiculo ?: ''), 'UTF-8');
        $this->km = (string) ($ordem->km ?? '');

        $this->corVeiculo = mb_strtoupper((string) ($ordem->cor_veiculo ?? ''), 'UTF-8');
        $this->chassiVeiculo = mb_strtoupper((string) ($ordem->chassi_veiculo ?? ''), 'UTF-8');
        $placaLocal = OsVeiculo::normalizarPlaca($this->placa);
        $this->placaLocalResolvida = OsVeiculo::placaValida($placaLocal) ? $placaLocal : '';
        $this->carregarExtrasVeiculoLocal();

        $this->problema = (string) ($ordem->problema ?? '');
        $this->observacoes = (string) ($ordem->observacoes ?? '');
        $this->laudo = (string) ($ordem->laudo ?? '');

        $this->itens = $ordem->itens
            ->values()
            ->map(fn (OrdemServicoItem $item): array => $this->mapItemToRow($item))
            ->all();

        $this->syncTecnicoEmTodosItens();

        $this->descPecasGlobal = ErpMoney::formatBr((float) $ordem->vl_desc_pecas);
        $this->descServicosGlobal = ErpMoney::formatBr((float) $ordem->vl_desc_servicos);
        $this->recalcTotais();
        $this->refreshOsFotosFromOrdem($ordem);
        $this->aplicarOsTransporteFromPayload(is_array($ordem->transporte ?? null) ? $ordem->transporte : null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapItemToRow(OrdemServicoItem $item): array
    {
        $concluido = '';

        if ($item->data_termino) {
            $hora = $item->hora_termino
                ? substr((string) $item->hora_termino, 0, 5)
                : '00:00';
            $concluido = $item->data_termino->format('Y-m-d') . 'T' . $hora;
        }

        return [
            'id' => $item->id,
            'key' => 'item-' . $item->id,
            'tipo' => in_array($item->tipo, ['S', 'P'], true) ? $item->tipo : 'P',
            'product_id' => $item->product_id,
            'product_codigo' => $item->product?->codigo ?? '',
            'discriminacao' => mb_strtoupper((string) ($item->discriminacao ?? $item->product?->descricao ?? ''), 'UTF-8'),
            'servico_prestado' => (string) ($item->servico_prestado ?? ''),
            'qtd' => ErpMoney::formatBr((float) $item->qtd, 3),
            'preco' => ErpMoney::formatBr((float) $item->preco),
            'desconto' => ErpMoney::formatBr((float) ($item->desconto ?? 0)),
            'acrescimo' => ErpMoney::formatBr((float) ($item->acrescimo ?? 0)),
            'total' => ErpMoney::formatBr((float) $item->total),
            'funcionario_id' => $item->funcionario_id,
            'concluido_em' => $concluido,
        ];
    }

    public function updatedClienteSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->clienteSearch !== $upper) {
            $this->clienteSearch = $upper;
        }

        $this->clienteLookupOpen = true;
        $this->refreshClienteResults();
    }

    public function openClienteLookup(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->clienteLookupOpen = true;

        if (filled(trim($this->clienteSearch))) {
            $this->refreshClienteResults();

            return;
        }

        // Sem termo a lookup não aparece no Blade — evita remorph só por focus.
        $this->skipRender();
    }

    public function refreshClienteResults(): void
    {
        $term = trim($this->clienteSearch);

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true);

        if ($term !== '') {
            $like = '%' . $term . '%';
            $digits = preg_replace('/\D/', '', $term) ?? '';

            $query->where(function ($sub) use ($like, $digits, $term): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $digitsLike = '%' . $digits . '%';
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        [$digitsLike]
                    );
                }

                if (ctype_digit($term)) {
                    $sub->orWhere('codigo', 'like', $like);
                }
            });
        }

        $this->clienteResults = $query
            ->orderBy('nome_razao')
            ->limit(50)
            ->get(['id', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj'])
            ->map(fn (Person $person): array => [
                'id' => $person->id,
                'nome' => mb_strtoupper($person->nome_razao, 'UTF-8'),
                'fantasia' => mb_strtoupper((string) ($person->apelido_fantasia ?? ''), 'UTF-8'),
                'cpf_cnpj' => $person->cpf_cnpj ?? '',
            ])
            ->all();

        $this->selectedClienteIndex = $this->clienteResults === [] ? null : 0;
    }

    public function moveClienteSelection(int $delta): void
    {
        if ($this->clienteResults === []) {
            $this->skipRender();

            return;
        }

        $index = ($this->selectedClienteIndex ?? 0) + $delta;
        $count = count($this->clienteResults);
        $this->selectedClienteIndex = max(0, min($count - 1, $index));
        $this->skipOsRenderWithLookupHighlight('cliente', $this->selectedClienteIndex);
    }

    public function selectClienteResult(int $index): void
    {
        if (! isset($this->clienteResults[$index])) {
            return;
        }

        $this->selectedClienteIndex = $index;
        $this->confirmClienteSelection();
    }

    public function confirmClienteSelection(): void
    {
        $index = $this->selectedClienteIndex;

        if ($index === null || ! isset($this->clienteResults[$index])) {
            $this->clienteLookupOpen = false;

            return;
        }

        $person = Person::query()->find($this->clienteResults[$index]['id']);

        if (! $person) {
            return;
        }

        $this->clienteId = $person->id;
        $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
        $this->applyClienteFields($person);
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;

        $this->focusOsItemEntryAfterCliente();
    }

    public function handleClienteEnter(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if (
            $this->clienteLookupOpen
            && $this->clienteResults !== []
        ) {
            if ($this->selectedClienteIndex === null) {
                $this->selectedClienteIndex = 0;
            }

            if (isset($this->clienteResults[$this->selectedClienteIndex])) {
                $this->confirmClienteSelection();
            }
        }
    }

    protected function focusOsItemEntryAfterCliente(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->activeFormTab = 'dados';
        $this->activeItemTab = 'servicos';
        $this->dispatch('erp-os-focus-item-descricao');
    }

    protected function applyClienteFields(?Person $person): void
    {
        if (! $person) {
            $this->documento = '';
            $this->fone1 = '';
            $this->nome = '';
            $this->endereco = '';
            $this->bairro = '';
            $this->cidade = '';
            $this->uf = 'SC';

            return;
        }

        $this->nome = mb_strtoupper((string) $person->nome_razao, 'UTF-8');
        $this->documento = (string) ($person->cpf_cnpj ?? '');
        // CONSUMIDOR FINAL é cadastro genérico — não trazer telefone/contato do suporte.
        $this->fone1 = $person->isCadastroConsumidorFinal()
            ? ''
            : (string) ($person->fone1 ?? '');
        $this->endereco = mb_strtoupper((string) ($person->endereco ?? ''), 'UTF-8');
        $this->bairro = mb_strtoupper((string) ($person->bairro ?? ''), 'UTF-8');
        $this->cidade = mb_strtoupper((string) ($person->cidade_nome ?? ''), 'UTF-8');
        $this->uf = mb_strtoupper((string) ($person->uf ?: 'SC'), 'UTF-8');

        if ($person->vendedor_loja_id) {
            $this->atendenteId = $person->vendedor_loja_id;
        }
    }

    public function closeClienteLookup(): void
    {
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;
    }

    public function confirmClienteSelectionOnBlur(): void
    {
        if (! $this->clienteLookupOpen) {
            return;
        }

        if ($this->selectedClienteIndex !== null && isset($this->clienteResults[$this->selectedClienteIndex])) {
            $this->confirmClienteSelection();

            return;
        }

        $this->closeClienteLookup();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itensByActiveTab(): array
    {
        $tipo = $this->activeItemTab === 'servicos' ? 'S' : 'P';
        $filtered = [];

        foreach ($this->itens as $index => $row) {
            if (($row['tipo'] ?? 'P') === $tipo) {
                $filtered[$index] = $row;
            }
        }

        return $filtered;
    }

    public function selectItemRowByKey(string $key): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index !== null) {
            $this->selectItemRow($index);
        }
    }

    public function requestDeleteItemByKey(string $key): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index !== null) {
            $this->requestDeleteItem($index);
        }
    }

    public function startEditItemByKey(string $key): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index !== null) {
            $this->startEditItem($index);
        }
    }

    public function selectItemRow(int $index): void
    {
        $this->selectedItemIndex = $index;

        if ($this->activeItemTab === 'pecas' && isset($this->itens[$index])) {
            $this->aplicarFotoDoItemSelecionado($index);
        }

        $this->skipRender();
    }

    public function resolveItemDisplayNumberInTab(int $positionInTab, ?int $tabTotal = null): int
    {
        $tabTotal ??= count($this->itensByActiveTab());

        return max(1, $tabTotal - $positionInTab);
    }

    /**
     * Duplo clique na linha: carrega o item na barra para editar (mesmo índice ao confirmar).
     * Preços vêm da linha gravada (promo/manual); não recalcula promoção.
     * skipRender + payload JS: a descrição da barra usa wire:ignore (pesquisa GET).
     */
    public function startEditItem(int $index): void
    {
        if ($this->osReadOnly() || ! isset($this->itens[$index])) {
            return;
        }

        $row = $this->itens[$index];
        $productId = (int) ($row['product_id'] ?? 0);

        if ($productId <= 0) {
            return;
        }

        $this->editingItemIndex = $index;
        $this->selectedItemIndex = $index;
        $this->itemPendingProductId = $productId;
        $this->osPendingUsaTabPreco = false;
        $this->osPrecoSugeridoInclusao = null;
        $this->tabPrecoItemConfirmada = false;
        $this->itemCodigoInput = (string) ($row['product_codigo'] ?? '');
        $this->itemProdutoSearch = (string) ($row['discriminacao'] ?? '');
        $this->itemQtdInput = (string) ($row['qtd'] ?? '1,000');
        $this->itemPrecoInput = (string) ($row['preco'] ?? '0,00');
        $this->itemPendingAcrescimo = (string) ($row['acrescimo'] ?? '0,00');
        $this->itemPendingDesconto = (string) ($row['desconto'] ?? '0,00');
        $this->itemTotalEntryDisplay = (string) ($row['total'] ?? '0,00');
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;

        $this->produtoAtualNome = mb_strtoupper((string) ($row['discriminacao'] ?? ''), 'UTF-8');

        if (($row['tipo'] ?? 'P') === 'P') {
            $this->aplicarFotoDoItemSelecionado($index);
        } else {
            $this->produtoAtualFoto = null;
        }

        $this->skipRender();
        $this->dispatch('erp-os-item-edit-loaded', [
            'key' => (string) ($row['key'] ?? ''),
            'nome' => $this->produtoAtualNome,
            'codigo' => $this->itemCodigoInput,
            'qtd' => $this->itemQtdInput,
            'preco' => $this->itemPrecoInput,
            'total' => $this->itemTotalEntryDisplay,
            'foto' => $this->produtoAtualFoto,
        ]);
        $this->dispatch('erp-os-focus-item-qtd');
    }

    public function updateItemField(int $index, string $field, string $value): void
    {
        if ($this->osReadOnly() || ! isset($this->itens[$index])) {
            return;
        }

        if (! in_array($field, ['qtd', 'preco', 'desconto', 'acrescimo', 'discriminacao', 'concluido_em'], true)) {
            return;
        }

        $itens = $this->itens;

        if ($field === 'discriminacao') {
            $itens[$index][$field] = mb_strtoupper(trim($value), 'UTF-8');
        } elseif ($field === 'concluido_em') {
            $itens[$index][$field] = trim($value);
        } else {
            $itens[$index][$field] = $value;
            $itens[$index] = $this->recalcItemRowData($itens[$index]);
        }

        $this->itens = $itens;

        if (in_array($field, ['qtd', 'preco', 'desconto', 'acrescimo'], true)) {
            $this->recalcTotais();
        }
    }

    public function blurItemFieldByKey(string $key, string $field, string $value): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index === null) {
            return;
        }

        $this->updateItemField($index, $field, $value);
    }

    protected function findItemIndexByKey(string $key): ?int
    {
        foreach ($this->itens as $index => $row) {
            if (($row['key'] ?? '') === $key) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function recalcItemRowData(array $row): array
    {
        $qtd = max(0, ErpMoney::parseBr($row['qtd'] ?? 0, 3));
        $preco = max(0, ErpMoney::parseBr($row['preco'] ?? 0));
        $acrescimo = max(0, ErpMoney::parseBr($row['acrescimo'] ?? 0));
        $desconto = max(0, ErpMoney::parseBr($row['desconto'] ?? 0));
        $bruto = round($qtd * $preco, 2);
        $total = round(max(0, $bruto + $acrescimo - $desconto), 2);

        $row['qtd'] = ErpMoney::formatBr($qtd, 3);
        $row['preco'] = ErpMoney::formatBr($preco);
        $row['acrescimo'] = ErpMoney::formatBr($acrescimo);
        $row['desconto'] = ErpMoney::formatBr($desconto);
        $row['total'] = ErpMoney::formatBr($total);

        return $row;
    }

    public function applyDescontoPecas(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $desired = max(0, ErpMoney::parseBr($this->descPecas));
        $fromItens = $this->sumDescontoItensPorTipo('P');
        $this->descPecasGlobal = ErpMoney::formatBr(max(0, round($desired - $fromItens, 2)));
        $this->recalcTotais();
    }

    public function applyDescontoServicos(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $desired = max(0, ErpMoney::parseBr($this->descServicos));
        $fromItens = $this->sumDescontoItensPorTipo('S');
        $this->descServicosGlobal = ErpMoney::formatBr(max(0, round($desired - $fromItens, 2)));
        $this->recalcTotais();
    }

    protected function sumDescontoItensPorTipo(string $tipo): float
    {
        $sum = 0.0;

        foreach ($this->itens as $row) {
            if (($row['tipo'] ?? 'P') !== $tipo) {
                continue;
            }

            $sum += max(0, ErpMoney::parseBr($row['desconto'] ?? 0));
        }

        return round($sum, 2);
    }

    protected function recalcTotais(): void
    {
        $subBrutoPecas = 0.0;
        $subBrutoServicos = 0.0;
        $descItensPecas = 0.0;
        $descItensServicos = 0.0;

        foreach ($this->itens as $row) {
            $qtd = max(0, ErpMoney::parseBr($row['qtd'] ?? 0, 3));
            $preco = max(0, ErpMoney::parseBr($row['preco'] ?? 0));
            $acrescimo = max(0, ErpMoney::parseBr($row['acrescimo'] ?? 0));
            $desconto = max(0, ErpMoney::parseBr($row['desconto'] ?? 0));
            $bruto = round($qtd * $preco + $acrescimo, 2);

            if (($row['tipo'] ?? 'P') === 'S') {
                $subBrutoServicos += $bruto;
                $descItensServicos += $desconto;
            } else {
                $subBrutoPecas += $bruto;
                $descItensPecas += $desconto;
            }
        }

        $descGlobalPecas = max(0, ErpMoney::parseBr($this->descPecasGlobal));
        $descGlobalServicos = max(0, ErpMoney::parseBr($this->descServicosGlobal));
        $descPecasTotal = round($descItensPecas + $descGlobalPecas, 2);
        $descServicosTotal = round($descItensServicos + $descGlobalServicos, 2);

        $totalPecas = round(max(0, $subBrutoPecas - $descPecasTotal), 2);
        $totalServicos = round(max(0, $subBrutoServicos - $descServicosTotal), 2);

        $this->descPecas = ErpMoney::formatBr($descPecasTotal);
        $this->descServicos = ErpMoney::formatBr($descServicosTotal);

        $this->syncTotaisDisplay($subBrutoPecas, $subBrutoServicos, $totalPecas, $totalServicos);
    }

    protected function syncTotaisDisplay(
        float $subPecas,
        float $subServicos,
        float $totalPecas,
        float $totalServicos,
    ): void {
        $this->subtotalPecas = ErpMoney::formatBr($subPecas);
        $this->subtotalServicos = ErpMoney::formatBr($subServicos);
        $this->subtotalGeral = ErpMoney::formatBr(round($subPecas + $subServicos, 2));
        $this->totalPecas = ErpMoney::formatBr($totalPecas);
        $this->totalServicos = ErpMoney::formatBr($totalServicos);
        $this->totalGeral = ErpMoney::formatBr(round($totalPecas + $totalServicos, 2));
    }

    public function requestDeleteItem(int $index): void
    {
        if ($this->osReadOnly() || ! isset($this->itens[$index])) {
            return;
        }

        $this->selectedItemIndex = $index;
        $this->itemDeleteConfirmIndex = $index;
    }

    public function confirmDeleteItem(): void
    {
        if ($this->itemDeleteConfirmIndex === null || ! isset($this->itens[$this->itemDeleteConfirmIndex])) {
            $this->itemDeleteConfirmIndex = null;

            return;
        }

        $index = $this->itemDeleteConfirmIndex;
        $this->itemDeleteConfirmIndex = null;

        $wasEditing = $this->editingItemIndex === $index;

        if ($this->editingItemIndex !== null && $this->editingItemIndex > $index) {
            $this->editingItemIndex--;
        }

        if ($wasEditing) {
            $this->editingItemIndex = null;
            $this->clearItemEntryRow();
        }

        $itens = $this->itens;
        array_splice($itens, $index, 1);
        $this->itens = array_values($itens);
        $this->selectedItemIndex = null;
        $this->recalcTotais();
    }

    public function cancelDeleteItem(): void
    {
        $this->itemDeleteConfirmIndex = null;
    }

    public function deleteSelectedItem(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if ($this->selectedItemIndex === null || ! isset($this->itens[$this->selectedItemIndex])) {
            Notification::make()
                ->title('Selecione um item para excluir.')
                ->warning()
                ->send();

            return;
        }

        $this->requestDeleteItem($this->selectedItemIndex);
    }

    public function handleItemCodigoEnter(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if (blank(trim($this->itemCodigoInput))) {
            return;
        }

        $this->submitItemByCodigo();
    }

    public function submitItemByCodigo(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $codigo = mb_strtoupper(trim($this->itemCodigoInput), 'UTF-8');

        if ($codigo === '') {
            return;
        }

        $product = $this->findProductByCodigo($codigo);

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->body('Verifique o código informado.')
                ->warning()
                ->send();

            return;
        }

        $this->stageProductForEntry($product);
    }

    public function submitBarcodeItem(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $raw = trim($this->barcodeInput);

        if ($raw === '') {
            return;
        }

        $qtd = 1.0;
        $term = $raw;

        if (str_contains($raw, '*')) {
            [$qtdPart, $codePart] = explode('*', $raw, 2);
            $qtd = max(0.001, ErpMoney::parseBr($qtdPart, 3));
            $term = trim($codePart);
        }

        $product = $this->findProductByTerm(mb_strtoupper($term, 'UTF-8'));

        if (! $product) {
            Notification::make()
                ->title('Produto não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->barcodeInput = '';

        // Mesmo fluxo da barra: Tab. Preço abre o modal e só então inclui.
        $this->stageProductForEntry($product);

        if ($this->tabPrecoItemModalOpen) {
            if (abs($qtd - 1.0) > 0.0005) {
                $this->itemQtdInput = ErpMoney::formatBr($qtd, 3);
            }

            return;
        }

        if ($this->itemPendingProductId !== null) {
            $this->itemQtdInput = ErpMoney::formatBr($qtd, 3);
            $this->confirmPendingItemEntry();
        }
    }

    public function searchItemProduto(string $value): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->itemProdutoSearch = mb_strtoupper($value, 'UTF-8');
        $this->produtoLookupOpen = true;
        $this->refreshProdutoResults();
    }

    public function updatedItemProdutoSearch(): void
    {
        $this->applyOsProdutoSearchTerm($this->itemProdutoSearch);
    }

    protected function applyOsProdutoSearchTerm(string $term): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $upper = mb_strtoupper($term, 'UTF-8');
        $this->itemProdutoSearch = $upper;
        $trimmed = trim($upper);

        if ($trimmed === '') {
            $this->produtoLookupOpen = false;
            $this->produtoResults = [];
            $this->selectedProdutoIndex = null;

            if ($this->editingItemIndex === null && $this->itemPendingProductId !== null) {
                $this->releasePendingProductForSearch();
            }

            return;
        }

        if ($this->itemPendingProductId !== null) {
            $stagedLabel = trim($this->produtoAtualNome);

            if ($stagedLabel !== '' && $trimmed === $stagedLabel) {
                $this->produtoLookupOpen = false;
                $this->produtoResults = [];
                $this->selectedProdutoIndex = null;
                $this->skipRender();

                return;
            }

            if ($this->editingItemIndex === null) {
                $this->releasePendingProductForSearch();
            }
        }

        $this->produtoLookupOpen = true;
        $this->refreshProdutoResults();
    }

    protected function releasePendingProductForSearch(): void
    {
        $this->itemPendingProductId = null;
        $this->osPendingUsaTabPreco = false;
        $this->itemCodigoInput = '';
        $this->itemQtdInput = '1,000';
        $this->itemPrecoInput = '0,00';
        $this->itemTotalEntryDisplay = '0,00';
        $this->itemPendingDesconto = '0,00';
        $this->itemPendingAcrescimo = '0,00';
        $this->clearProdutoAtual();
    }

    public function openProdutoLookup(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $this->produtoLookupOpen = true;

        if (filled(trim($this->itemProdutoSearch))) {
            $this->refreshProdutoResults();

            return;
        }

        $this->skipRender();
    }

    public function refreshProdutoResults(): void
    {
        $term = trim($this->itemProdutoSearch);

        if ($term === '') {
            $this->produtoResults = [];
            $this->selectedProdutoIndex = null;

            return;
        }

        $this->produtoResults = app(OsProdutoLookupService::class)->buscar(
            $term,
            $this->activeItemTab,
            (int) (ErpContext::currentEmpresaId() ?? 0),
        );
        $this->selectedProdutoIndex = $this->produtoResults === [] ? null : 0;
    }

    public function selectProdutoLookupRow(int $id): void
    {
        if ($id <= 0 || $this->osReadOnly()) {
            return;
        }

        $product = $this->findOsProductForEntry($id);

        if ($product) {
            $this->stageProductForEntry($product);
        }
    }

    public function moveProdutoSelection(int $delta): void
    {
        if ($this->produtoResults === []) {
            $this->skipRender();

            return;
        }

        $index = ($this->selectedProdutoIndex ?? 0) + $delta;
        $count = count($this->produtoResults);
        $this->selectedProdutoIndex = max(0, min($count - 1, $index));
        $this->skipOsRenderWithLookupHighlight('produto', $this->selectedProdutoIndex);
    }

    public function selectProdutoResult(int $index): void
    {
        if (! isset($this->produtoResults[$index])) {
            return;
        }

        if (! $this->osProdutoResultMatchesActiveTab($this->produtoResults[$index])) {
            $this->closeProdutoLookup();
            $this->skipRender();

            return;
        }

        $this->selectedProdutoIndex = $index;
        $this->confirmProdutoSelection();
    }

    public function confirmProdutoSelection(): void
    {
        $index = $this->selectedProdutoIndex;

        if ($index === null || ! isset($this->produtoResults[$index])) {
            $this->produtoLookupOpen = false;

            return;
        }

        if (! $this->osProdutoResultMatchesActiveTab($this->produtoResults[$index])) {
            $this->closeProdutoLookup();
            $this->skipRender();

            return;
        }

        $this->stageProductFromLookupRow($this->produtoResults[$index]);
    }

    public function confirmarItemProdutoBar(?string $typed = null): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if (is_string($typed)) {
            $this->itemProdutoSearch = mb_strtoupper(trim($typed), 'UTF-8');
        }

        $term = mb_strtoupper(trim($this->itemProdutoSearch), 'UTF-8');

        if ($term === '') {
            return;
        }

        if ($this->editingItemIndex !== null && $this->itemPendingProductId !== null) {
            $staged = trim($this->produtoAtualNome);

            if ($staged !== '' && $term === $staged) {
                $this->dispatch('erp-os-focus-item-qtd');

                return;
            }
        }

        if ($this->produtoLookupOpen && $this->produtoResults !== []) {
            $this->selectProdutoResult((int) ($this->selectedProdutoIndex ?? 0));

            return;
        }

        $product = $this->findProductByCodigo($term);

        if ($product) {
            $this->stageProductForEntry($product);

            return;
        }

        $product = $this->findProductByTerm($term);

        if ($product) {
            $this->stageProductForEntry($product);

            return;
        }

        $this->submitItemProdutoSearch($term);
    }

    public function submitItemProdutoSearch(?string $term = null): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        if ($term !== null) {
            $this->itemProdutoSearch = mb_strtoupper($term, 'UTF-8');
        }

        if (trim($this->itemProdutoSearch) === '') {
            return;
        }

        $this->refreshProdutoResults();

        if ($this->produtoResults === []) {
            Notification::make()
                ->title($this->activeItemTab === 'servicos' ? 'Serviço não encontrado.' : 'Peça não encontrada.')
                ->warning()
                ->send();

            return;
        }

        if (count($this->produtoResults) === 1) {
            $this->selectProdutoResult(0);

            return;
        }

        if ($this->selectedProdutoIndex !== null && isset($this->produtoResults[$this->selectedProdutoIndex])) {
            $this->confirmProdutoSelection();

            return;
        }

        $this->produtoLookupOpen = true;
        $this->selectedProdutoIndex = 0;
    }

    public function closeProdutoLookup(): void
    {
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function osProdutoResultMatchesActiveTab(array $row): bool
    {
        $esperado = $this->activeItemTab === 'servicos' ? 'S' : 'P';
        $tipo = (string) ($row['tipo'] ?? '');

        return $tipo === '' || $tipo === $esperado;
    }

    public function normalizeItemQtdInput(): void
    {
        if ($this->osReadOnly() || $this->itemPendingProductId === null) {
            return;
        }

        $qtd = trim($this->itemQtdInput) === ''
            ? 0.0
            : ErpMoney::parseBr($this->itemQtdInput, 3);

        if ($qtd <= 0) {
            $qtd = 1;
        }

        $this->itemQtdInput = ErpMoney::formatBr($qtd, 3);
        $this->recalcOsEntryRowFromPending();
    }

    public function focoPrecoAposQtd(): void
    {
        if ($this->osReadOnly() || $this->itemPendingProductId === null) {
            return;
        }

        $this->normalizeItemQtdInput();

        // Após confirmar Tab. Preço, Enter na qtde já inclui o item.
        if (
            $this->tabPrecoItemConfirmada
            && ErpMoney::parseBr($this->itemPrecoInput) > 0
        ) {
            $this->confirmPendingItemEntry();

            return;
        }

        $this->skipRender();
        $this->dispatch('erp-os-focus-item-preco');
    }

    protected function recalcOsEntryRowFromPending(): void
    {
        if ($this->itemPendingProductId === null) {
            return;
        }

        if (trim($this->itemQtdInput) === '') {
            return;
        }

        $qtd = ErpMoney::parseBr($this->itemQtdInput, 3);
        $preco = ErpMoney::parseBr($this->itemPrecoInput);
        $acr = ErpMoney::parseBr($this->itemPendingAcrescimo);
        $desc = ErpMoney::parseBr($this->itemPendingDesconto);
        $total = round(max(0, (max(0.0, $qtd) * $preco) + $acr - $desc), 2);
        $this->itemTotalEntryDisplay = ErpMoney::formatBr($total);
        $this->dispatch('erp-os-sync-bar-total', total: $this->itemTotalEntryDisplay);
    }

    public function cancelItemEdit(): void
    {
        if ($this->editingItemIndex === null && $this->itemPendingProductId === null) {
            return;
        }

        $this->editingItemIndex = null;
        $this->clearItemEntryRow();
        $this->skipRender();
        $this->dispatch('erp-os-item-bar-cleared');
        $this->dispatch('erp-os-focus-item-descricao');
    }

    public function confirmPendingItemEntry(?string $qtdRaw = null, ?string $precoRaw = null): void
    {
        if ($this->osReadOnly() || $this->isConfirmingPendingItem || $this->itemPendingProductId === null) {
            return;
        }

        if (is_string($qtdRaw)) {
            $this->itemQtdInput = $qtdRaw;
        }

        if (is_string($precoRaw)) {
            $this->itemPrecoInput = $precoRaw;
        }

        $this->normalizeItemQtdInput();

        $qtd = ErpMoney::parseBr($this->itemQtdInput, 3);

        if ($qtd <= 0) {
            Notification::make()->title('Informe a quantidade do item.')->warning()->send();
            $this->dispatch('erp-os-focus-item-qtd');

            return;
        }

        if (
            $this->editingItemIndex === null
            && $this->osPendingUsaTabPreco
            && ! $this->tabPrecoItemConfirmada
        ) {
            $product = $this->findOsProductForEntry((int) $this->itemPendingProductId);

            if (! $product) {
                $this->clearItemEntryRow();

                return;
            }

            $this->abrirTabPrecoItemModal($product, $qtd ?: 1);
            Notification::make()
                ->title('Confirme a tabela de preço do produto.')
                ->warning()
                ->send();

            return;
        }

        $precoInformado = max(0.0, ErpMoney::parseBr($this->itemPrecoInput));
        $preco = $this->editingItemIndex !== null
            ? $precoInformado
            : $this->resolverPrecoConfirmacaoInclusao($precoInformado, $qtd);

        if ($preco <= 0) {
            Notification::make()->title('Informe o valor unitário do item.')->warning()->send();
            $this->dispatch('erp-os-focus-item-preco');

            return;
        }

        $acrescimo = max(0, ErpMoney::parseBr($this->itemPendingAcrescimo));
        $desconto = max(0, ErpMoney::parseBr($this->itemPendingDesconto));

        $this->isConfirmingPendingItem = true;

        try {
            if ($this->editingItemIndex !== null) {
                $this->applyEditedItemFromBar($qtd, $preco, $acrescimo, $desconto);
                $this->clearItemEntryRow();
                $this->dispatch('erp-os-focus-item-descricao');

                return;
            }

            $hadRows = $this->itens !== [];
            $this->appendStagedOsItem($qtd, $preco, $acrescimo, $desconto);
            $included = $this->osItemIncludedPayload();
            $this->clearItemEntryRow();

            if (is_array($included) && ($included['item']['tipo'] ?? '') === 'P') {
                $this->produtoAtualFoto = $included['foto'] ?? null;
                $this->produtoAtualNome = (string) ($included['nome'] ?? '');
            }

            if ($hadRows && $included !== null) {
                $this->skipRender();
                $this->dispatch(
                    'erp-os-item-included',
                    item: $included['item'],
                    totais: $included['totais'],
                    foto: $included['foto'] ?? null,
                    nome: $included['nome'] ?? '',
                );
            }

            $this->dispatch('erp-os-focus-item-descricao');
        } finally {
            $this->isConfirmingPendingItem = false;
        }
    }

    protected function applyEditedItemFromBar(float $qtd, float $preco, float $acrescimo, float $desconto): void
    {
        $index = $this->editingItemIndex;

        if ($index === null || ! isset($this->itens[$index])) {
            $this->editingItemIndex = null;

            return;
        }

        $itens = $this->itens;
        $row = $itens[$index];
        $row['product_id'] = $this->itemPendingProductId;
        $row['product_codigo'] = $this->itemCodigoInput !== ''
            ? $this->itemCodigoInput
            : (string) ($row['product_codigo'] ?? '');
        $row['discriminacao'] = $this->produtoAtualNome !== ''
            ? $this->produtoAtualNome
            : mb_strtoupper(trim($this->itemProdutoSearch), 'UTF-8');
        $row['qtd'] = ErpMoney::formatBr($qtd, 3);
        $row['preco'] = ErpMoney::formatBr($preco);
        $row['acrescimo'] = ErpMoney::formatBr($acrescimo);
        $row['desconto'] = ErpMoney::formatBr($desconto);
        $itens[$index] = $this->recalcItemRowData($row);
        $this->itens = $itens;
        $this->selectedItemIndex = $index;
        $this->editingItemIndex = null;
        $this->recalcTotais();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function stageProductFromLookupRow(array $row): void
    {
        $id = (int) ($row['id'] ?? 0);

        if ($id <= 0) {
            return;
        }

        $product = $this->findOsProductForEntry($id);

        if (! $product) {
            return;
        }

        $this->stageProductForEntry($product);
    }

    protected function findOsProductForEntry(int $id): ?Product
    {
        return Product::query()
            ->whereKey($id)
            ->where('ativo', true)
            ->where('is_servico', $this->activeItemTab === 'servicos')
            ->first([
                'id',
                'codigo',
                'descricao',
                'is_servico',
                'usa_tab_preco',
                'preco_venda',
                'preco_atacado',
                'preco_especial',
                'promo_preco_venda',
                'promo_data_inicio',
                'promo_data_fim',
                'foto_path',
            ]);
    }

    /**
     * Preço efetivo de inclusão na OS (varejo + campanha/legado da empresa ativa).
     */
    protected function resolveOsPrecoInclusao(Product $product, float $quantidade = 1.0): float
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        return app(ProductTabelaPrecoService::class)->resolvePrecoNivel(
            $product,
            ProductTabelaPrecoService::NIVEL_VAREJO,
            max(0.001, $quantidade),
            $empresaId > 0 ? $empresaId : null,
        );
    }

    /**
     * Inclusão nova: se o preço informado ainda é o sugerido, revalida no servidor.
     * Se o usuário alterou o unitário, preserva a alteração manual.
     * Edição de item já gravado na grade não passa por aqui.
     */
    protected function resolverPrecoConfirmacaoInclusao(float $precoInformado, float $quantidade): float
    {
        $sugerido = $this->osPrecoSugeridoInclusao;

        if ($sugerido !== null && abs($precoInformado - (float) $sugerido) >= 0.005) {
            return $precoInformado;
        }

        // Tab. Preço: o valor da opção já veio do motor (com promo no varejo). Não forçar Varejo.
        if ($this->tabPrecoItemConfirmada && $sugerido !== null) {
            return round((float) $sugerido, 2);
        }

        $product = $this->findOsProductForEntry((int) ($this->itemPendingProductId ?? 0));

        if (! $product) {
            return $precoInformado;
        }

        return $this->resolveOsPrecoInclusao($product, $quantidade);
    }

    protected function stageProductForEntry(Product $product): void
    {
        $esperadoServico = $this->activeItemTab === 'servicos';

        if ((bool) $product->is_servico !== $esperadoServico) {
            return;
        }

        $tipo = $product->is_servico ? 'S' : 'P';
        $exigeTabPreco = ($product->usa_tab_preco ?? false) && $this->editingItemIndex === null;

        $this->itemPendingProductId = $product->id;
        $this->osPendingUsaTabPreco = (bool) ($product->usa_tab_preco ?? false);
        $this->itemCodigoInput = (string) $product->codigo;
        $this->itemProdutoSearch = mb_strtoupper($product->descricao, 'UTF-8');
        $this->itemQtdInput = ErpMoney::formatBr(1, 3);
        $this->itemPendingDesconto = '0,00';
        $this->itemPendingAcrescimo = '0,00';
        $this->tabPrecoItemConfirmada = false;
        $this->osPrecoSugeridoInclusao = null;
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;

        $this->produtoAtualNome = mb_strtoupper($product->descricao, 'UTF-8');
        $this->produtoAtualFoto = $tipo === 'P'
            ? ProductPhotoUrl::forPath($product->foto_path ?? null, false)
            : null;

        if ($exigeTabPreco) {
            $this->itemPrecoInput = '0,00';
            $this->recalcOsEntryRowFromPending();
            $this->abrirTabPrecoItemModal($product, 1);

            return;
        }

        $preco = $this->resolveOsPrecoInclusao($product, 1.0);
        $this->osPrecoSugeridoInclusao = $preco;
        $this->itemPrecoInput = ErpMoney::formatBr($preco);
        $this->recalcOsEntryRowFromPending();
        $this->skipRender();
        $this->dispatchOsItemStaged();
    }

    protected function dispatchOsItemStaged(): void
    {
        $this->dispatch('erp-os-item-staged', [
            'nome' => $this->produtoAtualNome,
            'codigo' => $this->itemCodigoInput,
            'qtd' => $this->itemQtdInput,
            'preco' => $this->itemPrecoInput,
            'total' => $this->itemTotalEntryDisplay,
            'foto' => $this->produtoAtualFoto,
        ]);
        $this->dispatch('erp-os-focus-item-qtd');
    }

    protected function abrirTabPrecoItemModal(Product $product, float $quantidade = 1): void
    {
        $tabSvc = app(ProductTabelaPrecoService::class);
        $tabSvc->carregarItens($product);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $opcoes = $tabSvc->opcoesParaProduto(
            $product,
            max(0.001, $quantidade),
            $empresaId > 0 ? $empresaId : null,
        );

        if ($opcoes === []) {
            Notification::make()
                ->title('Produto com Tab. Preço sem tabelas habilitadas. Revise o cadastro.')
                ->danger()
                ->send();

            return;
        }

        $this->tabPrecoItemProductId = (int) $product->id;
        $this->tabPrecoItemOpcoes = array_map(static fn (array $op): array => [
            'nivel' => $op['nivel'],
            'price_table_id' => $op['price_table_id'],
            'codigo' => $op['codigo'],
            'descricao' => $op['descricao'],
            'label' => $op['label'].' — R$ '.number_format($op['preco'], 2, ',', '.'),
            'preco' => $op['preco'],
        ], $opcoes);
        $this->tabPrecoItemSelectedIndex = 0;
        $this->tabPrecoItemModalOpen = true;
        $this->tabPrecoItemConfirmada = false;
        $this->dispatch('erp-os-focus-tab-preco');
    }

    public function selectTabPrecoItemRow(int $index): void
    {
        if (isset($this->tabPrecoItemOpcoes[$index])) {
            $this->tabPrecoItemSelectedIndex = $index;
            $this->skipRender();
        }
    }

    public function confirmTabPrecoItemAt(int $index): void
    {
        $this->tabPrecoItemSelectedIndex = $index;
        $this->confirmTabPrecoItem();
    }

    public function confirmTabPrecoItem(): void
    {
        $index = $this->tabPrecoItemSelectedIndex;

        if ($index === null || ! isset($this->tabPrecoItemOpcoes[$index])) {
            Notification::make()->title('Selecione uma tabela de preço.')->warning()->send();

            return;
        }

        $op = $this->tabPrecoItemOpcoes[$index];
        $productId = (int) ($this->tabPrecoItemProductId ?? 0);
        $preco = round((float) $op['preco'], 2);

        $this->tabPrecoItemConfirmada = true;
        $this->tabPrecoItemModalOpen = false;
        $this->osPrecoSugeridoInclusao = $preco;
        $this->itemPrecoInput = ErpMoney::formatBr($preco);
        $this->recalcOsEntryRowFromPending();

        $this->tabPrecoItemProductId = null;
        $this->tabPrecoItemOpcoes = [];
        $this->tabPrecoItemSelectedIndex = null;

        if ($productId > 0) {
            $this->itemPendingProductId = $productId;
            $this->confirmPendingItemEntry();
        }
    }

    public function cancelTabPrecoItem(): void
    {
        $this->tabPrecoItemModalOpen = false;
        $this->tabPrecoItemProductId = null;
        $this->tabPrecoItemOpcoes = [];
        $this->tabPrecoItemSelectedIndex = null;
        $this->tabPrecoItemConfirmada = false;
        $this->clearItemEntryRow();
        $this->dispatch('erp-os-focus-item-descricao');
    }

    protected function clearItemEntryRow(): void
    {
        $this->editingItemIndex = null;
        $this->itemPendingProductId = null;
        $this->osPendingUsaTabPreco = false;
        $this->itemCodigoInput = '';
        $this->itemProdutoSearch = '';
        $this->itemQtdInput = '1,000';
        $this->itemPrecoInput = '';
        $this->itemPendingDesconto = '0,00';
        $this->itemPendingAcrescimo = '0,00';
        $this->itemTotalEntryDisplay = '0,00';
        $this->osPrecoSugeridoInclusao = null;
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];
        $this->selectedProdutoIndex = null;
        $this->tabPrecoItemModalOpen = false;
        $this->tabPrecoItemConfirmada = false;
        $this->tabPrecoItemProductId = null;
        $this->tabPrecoItemOpcoes = [];
        $this->tabPrecoItemSelectedIndex = null;
        $this->clearProdutoAtual();
    }

    protected function clearProdutoAtual(): void
    {
        $this->produtoAtualFoto = null;
        $this->produtoAtualNome = '';
    }

    /**
     * Carrega foto apenas do item selecionado. Cache em memória na linha (não grava no banco).
     */
    protected function aplicarFotoDoItemSelecionado(int $index): void
    {
        $row = $this->itens[$index];
        $this->produtoAtualNome = (string) ($row['discriminacao'] ?? '');

        $productId = (int) ($row['product_id'] ?? 0);

        if ($productId <= 0) {
            $this->produtoAtualFoto = null;

            return;
        }

        $path = Product::query()->whereKey($productId)->value('foto_path');
        $this->produtoAtualFoto = ProductPhotoUrl::forPath($path ? (string) $path : null, false);
    }

    /**
     * Preço unitário efetivo (desconto/acréscimo embutidos) para gravar sem colunas extras.
     */
    protected function precoEfetivoFromPendingEntry(float $qtd): float
    {
        $preco = max(0.0, ErpMoney::parseBr($this->itemPrecoInput));
        $acr = ErpMoney::parseBr($this->itemPendingAcrescimo);
        $desc = ErpMoney::parseBr($this->itemPendingDesconto);
        $total = round(max(0, ($qtd * $preco) + $acr - $desc), 2);

        if ($qtd <= 0) {
            return $preco;
        }

        return round($total / $qtd, 2);
    }

    public function abrirModalDescontoItem(): void
    {
        if ($this->osReadOnly() || $this->descontoModalOpen) {
            return;
        }

        if ($this->itemPendingProductId !== null && ErpMoney::parseBr($this->itemPrecoInput) > 0) {
            $this->itemAjusteAlvo = 'form';
        } elseif ($this->selectedItemIndex !== null && isset($this->itens[$this->selectedItemIndex])) {
            $this->itemAjusteAlvo = 'grid';
        } else {
            Notification::make()
                ->title('Informe o produto (ou selecione um item) para desconto/acréscimo.')
                ->warning()
                ->send();

            return;
        }

        $this->itemAjusteTipo = 'desconto';
        $this->itemAjusteModo = 'percentual';
        $this->itemAjusteValor = '0,00';
        $this->descontoModalOpen = true;
    }

    public function fecharModalDescontoItem(): void
    {
        $this->descontoModalOpen = false;
        $this->itemAjusteAlvo = null;
    }

    public function abrirModalServicoPrestadoByKey(string $key): void
    {
        $index = $this->findItemIndexByKey($key);

        if ($index !== null) {
            $this->abrirModalServicoPrestado($index);
        }
    }

    public function abrirModalServicoPrestado(int $index): void
    {
        if ($this->osReadOnly() || ($this->itens[$index]['tipo'] ?? null) !== 'S') {
            return;
        }

        $this->servicoPrestadoIndex = $index;
        $this->servicoPrestadoTexto = (string) ($this->itens[$index]['servico_prestado'] ?? '');
        $this->servicoPrestadoModalOpen = true;
        $this->dispatch('erp-os-focus-servico-prestado');
    }

    public function fecharModalServicoPrestado(): void
    {
        $this->servicoPrestadoModalOpen = false;
        $this->servicoPrestadoIndex = null;
        $this->servicoPrestadoTexto = '';
    }

    public function cancelarModalServicoPrestado(): void
    {
        $this->fecharModalServicoPrestado();
    }

    public function confirmarModalServicoPrestado(): void
    {
        $index = $this->servicoPrestadoIndex;

        if ($index !== null && isset($this->itens[$index])) {
            $this->itens[$index]['servico_prestado'] = mb_strtoupper(trim($this->servicoPrestadoTexto), 'UTF-8');
        }

        $this->fecharModalServicoPrestado();
    }

    public function getServicoPrestadoItemDescricaoProperty(): string
    {
        $index = $this->servicoPrestadoIndex;

        return $index !== null ? (string) ($this->itens[$index]['discriminacao'] ?? '') : '';
    }

    public function setItemAjusteTipo(string $tipo): void
    {
        $this->itemAjusteTipo = $tipo === 'acrescimo' ? 'acrescimo' : 'desconto';
    }

    public function setItemAjusteModo(string $modo): void
    {
        $this->itemAjusteModo = $modo === 'valor' ? 'valor' : 'percentual';
    }

    /**
     * @return array{descricao: string, base: string, novoPreco: string, total: string, tipo: string, temAjuste: bool}
     */
    public function getItemAjustePreviewProperty(): array
    {
        $ctx = $this->contextoItemAjuste();

        if ($ctx === null) {
            return [
                'descricao' => '',
                'base' => ErpMoney::formatBr(0),
                'novoPreco' => ErpMoney::formatBr(0),
                'total' => ErpMoney::formatBr(0),
                'tipo' => $this->itemAjusteTipo,
                'temAjuste' => false,
            ];
        }

        $calc = $this->calcularItemAjuste($ctx['preco'], $ctx['quantidade']);

        return [
            'descricao' => $ctx['descricao'],
            'base' => ErpMoney::formatBr($calc['base']),
            'novoPreco' => ErpMoney::formatBr($calc['novoPreco']),
            'total' => ErpMoney::formatBr($calc['total']),
            'tipo' => $this->itemAjusteTipo,
            'temAjuste' => abs($calc['deltaUnit']) > 0.0001,
        ];
    }

    public function confirmarItemAjuste(): void
    {
        $ctx = $this->contextoItemAjuste();

        if ($ctx === null) {
            $this->fecharModalDescontoItem();

            return;
        }

        $calc = $this->calcularItemAjuste($ctx['preco'], $ctx['quantidade']);
        $ajusteLinha = round(abs($calc['deltaUnit']) * $ctx['quantidade'], 2);

        if ($this->itemAjusteTipo === 'desconto' && $calc['novoPreco'] < 0) {
            Notification::make()->title('Desconto inválido.')->warning()->send();

            return;
        }

        if ($this->itemAjusteAlvo === 'form') {
            if ($this->itemAjusteTipo === 'desconto') {
                $this->itemPendingDesconto = ErpMoney::formatBr($ajusteLinha);
                $this->itemPendingAcrescimo = '0,00';
            } else {
                $this->itemPendingAcrescimo = ErpMoney::formatBr($ajusteLinha);
                $this->itemPendingDesconto = '0,00';
            }

            $this->recalcOsEntryRowFromPending();

            $tipo = $this->itemAjusteTipo;
            $pendingAntes = $this->itemPendingProductId;
            $this->fecharModalDescontoItem();
            $this->confirmPendingItemEntry();

            if ($pendingAntes !== null && $this->itemPendingProductId === null) {
                Notification::make()
                    ->title($tipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
                    ->success()
                    ->send();
            }

            return;
        } else {
            $index = (int) $this->selectedItemIndex;
            $itens = $this->itens;
            $item = $itens[$index];

            if ($this->itemAjusteTipo === 'desconto') {
                $item['desconto'] = ErpMoney::formatBr($ajusteLinha);
                $item['acrescimo'] = ErpMoney::formatBr(0);
            } else {
                $item['acrescimo'] = ErpMoney::formatBr($ajusteLinha);
                $item['desconto'] = ErpMoney::formatBr(0);
            }

            $itens[$index] = $this->recalcItemRowData($item);
            $this->itens = $itens;
            $this->recalcTotais();
        }

        $tipo = $this->itemAjusteTipo;
        $this->fecharModalDescontoItem();
        Notification::make()
            ->title($tipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
            ->success()
            ->send();
    }

    /**
     * @return array{descricao: string, preco: float, quantidade: float}|null
     */
    protected function contextoItemAjuste(): ?array
    {
        if ($this->itemAjusteAlvo === 'form' && $this->itemPendingProductId !== null) {
            return [
                'descricao' => trim($this->itemProdutoSearch),
                'preco' => ErpMoney::parseBr($this->itemPrecoInput),
                'quantidade' => max(0.0, ErpMoney::parseBr($this->itemQtdInput, 3)),
            ];
        }

        if ($this->itemAjusteAlvo === 'grid' && $this->selectedItemIndex !== null && isset($this->itens[$this->selectedItemIndex])) {
            $item = $this->itens[$this->selectedItemIndex];

            return [
                'descricao' => (string) ($item['discriminacao'] ?? ''),
                'preco' => ErpMoney::parseBr($item['preco'] ?? 0),
                'quantidade' => ErpMoney::parseBr($item['qtd'] ?? 0, 3),
            ];
        }

        return null;
    }

    /**
     * @return array{base: float, deltaUnit: float, novoPreco: float, total: float}
     */
    protected function calcularItemAjuste(float $base, float $quantidade): array
    {
        $valor = ErpMoney::parseBr($this->itemAjusteValor);

        if ($this->itemAjusteModo === 'percentual') {
            $deltaUnit = round($base * ($valor / 100), 2);
        } else {
            $deltaUnit = round($valor, 2);
        }

        $novoPreco = $this->itemAjusteTipo === 'acrescimo'
            ? round($base + $deltaUnit, 2)
            : round($base - $deltaUnit, 2);

        if ($novoPreco < 0) {
            $novoPreco = 0.0;
        }

        $total = round(max(0, $novoPreco * $quantidade), 2);

        return [
            'base' => $base,
            'deltaUnit' => abs($deltaUnit),
            'novoPreco' => $novoPreco,
            'total' => $total,
        ];
    }

    protected function appendProductItem(
        Product $product,
        float $qtd = 1.0,
        ?float $preco = null,
        float $acrescimo = 0.0,
        float $desconto = 0.0,
    ): void {
        $esperadoServico = $this->activeItemTab === 'servicos';

        if ((bool) $product->is_servico !== $esperadoServico) {
            return;
        }

        $preco ??= $this->resolveOsPrecoInclusao($product, $qtd);
        $tipo = $product->is_servico ? 'S' : 'P';
        $foto = $tipo === 'P'
            ? ProductPhotoUrl::forPath($product->foto_path ?? null, false)
            : null;

        $this->pushOsGridItem(
            $tipo,
            (int) $product->id,
            (string) $product->codigo,
            mb_strtoupper((string) $product->descricao, 'UTF-8'),
            $qtd,
            $preco,
            $acrescimo,
            $desconto,
            $foto,
        );
    }

    protected function appendStagedOsItem(float $qtd, float $preco, float $acrescimo, float $desconto): void
    {
        $tipo = $this->activeItemTab === 'servicos' ? 'S' : 'P';

        $this->pushOsGridItem(
            $tipo,
            (int) $this->itemPendingProductId,
            $this->itemCodigoInput,
            $this->produtoAtualNome !== ''
                ? $this->produtoAtualNome
                : mb_strtoupper(trim($this->itemProdutoSearch), 'UTF-8'),
            $qtd,
            $preco,
            $acrescimo,
            $desconto,
            $tipo === 'P' ? $this->produtoAtualFoto : null,
        );
    }

    protected function pushOsGridItem(
        string $tipo,
        int $productId,
        string $codigo,
        string $descricao,
        float $qtd,
        float $preco,
        float $acrescimo,
        float $desconto,
        ?string $foto,
    ): void {
        $itens = $this->itens;
        $row = $this->recalcItemRowData([
            'id' => null,
            'key' => 'new-'.Str::uuid()->toString(),
            'tipo' => $tipo,
            'product_id' => $productId,
            'product_codigo' => $codigo,
            'discriminacao' => $descricao,
            'servico_prestado' => '',
            'qtd' => ErpMoney::formatBr($qtd, 3),
            'preco' => ErpMoney::formatBr($preco),
            'acrescimo' => ErpMoney::formatBr(max(0, $acrescimo)),
            'desconto' => ErpMoney::formatBr(max(0, $desconto)),
            'total' => ErpMoney::formatBr(0),
            'funcionario_id' => $this->atendenteId,
            'concluido_em' => '',
        ]);

        array_unshift($itens, $row);

        $this->itens = array_values($itens);
        $this->selectedItemIndex = 0;
        $this->produtoAtualNome = (string) ($row['discriminacao'] ?? '');
        $this->produtoAtualFoto = $foto;
        $this->recalcTotais();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function osItemIncludedPayload(): ?array
    {
        $row = $this->itens[0] ?? null;

        if (! is_array($row)) {
            return null;
        }

        $qtdVal = ErpMoney::parseBr($row['qtd'] ?? 0, 3);
        $precoVal = ErpMoney::parseBr($row['preco'] ?? 0);
        $bruto = round($qtdVal * $precoVal, 2);

        return [
            'item' => [
                'key' => (string) ($row['key'] ?? ''),
                'tipo' => (string) ($row['tipo'] ?? 'P'),
                'codigo' => (string) ($row['product_codigo'] ?? ''),
                'descricao' => (string) ($row['discriminacao'] ?? ''),
                'qtd' => (string) ($row['qtd'] ?? ''),
                'preco' => (string) ($row['preco'] ?? '0,00'),
                'bruto' => ErpMoney::formatBr($bruto),
                'acrescimo' => ErpMoney::formatBr(ErpMoney::parseBr($row['acrescimo'] ?? 0)),
                'desconto' => ErpMoney::formatBr(ErpMoney::parseBr($row['desconto'] ?? 0)),
                'total' => (string) ($row['total'] ?? '0,00'),
                'tecnico' => $this->osVendedorLabel(),
            ],
            'totais' => [
                'subtotalPecas' => $this->subtotalPecas,
                'subtotalServicos' => $this->subtotalServicos,
                'subtotalGeral' => $this->subtotalGeral,
                'descPecas' => $this->descPecas,
                'descServicos' => $this->descServicos,
                'totalPecas' => $this->totalPecas,
                'totalServicos' => $this->totalServicos,
                'totalGeral' => $this->totalGeral,
            ],
            'foto' => $this->produtoAtualFoto,
            'nome' => $this->produtoAtualNome,
        ];
    }

    protected function findProductByCodigo(string $codigo): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where('is_servico', $this->activeItemTab === 'servicos')
            ->where(function ($query) use ($codigo): void {
                $query->where('codigo', $codigo)
                    ->orWhere('referencia', $codigo)
                    ->orWhere('codigo_barras', $codigo)
                    ->orWhere('codigo_barras_caixa', $codigo);
            })
            ->first();
    }

    protected function findProductByTerm(string $term): ?Product
    {
        return Product::query()
            ->where('ativo', true)
            ->where('is_servico', $this->activeItemTab === 'servicos')
            ->where(function ($query) use ($term): void {
                $query->where('codigo', $term)
                    ->orWhere('codigo_barras', $term)
                    ->orWhere('codigo_barras_caixa', $term)
                    ->orWhere('referencia', $term)
                    ->orWhere('descricao', 'like', '%' . $term . '%');
            })
            ->first();
    }

    protected function validateBeforeSave(bool $finalizar): bool
    {
        if ($this->clienteId === null || blank($this->clienteSearch)) {
            Notification::make()->title('Informe o Cliente!')->warning()->send();

            return false;
        }

        if ($this->atendenteId === null) {
            Notification::make()->title('Informe o Técnico!')->warning()->send();

            return false;
        }

        if ($finalizar && $this->itens === []) {
            Notification::make()->title('Informe os Itens da OS!')->warning()->send();

            return false;
        }

        return true;
    }

    /**
     * Gravar/Finalizar/Faturar só na empresa ativa da sessão (troca de aba não opera o documento anterior).
     */
    protected function osRecusarOperacaoForaDaEmpresaAtiva(?OrdemServico $ordem = null): bool
    {
        if (! $this->isEditingOs()) {
            return false;
        }

        $ordem ??= $this->record instanceof OrdemServico ? $this->record : null;

        if (! $ordem instanceof OrdemServico) {
            return true;
        }

        if ($ordem->pertenceAEmpresaAtiva()) {
            return false;
        }

        Notification::make()
            ->title('Esta OS não pertence à empresa ativa.')
            ->body('Troque para a empresa do documento para gravar, finalizar ou faturar.')
            ->warning()
            ->send();

        return true;
    }

    public function gravarOs(): void
    {
        if ($this->osPersistindo) {
            return;
        }

        if ($this->osRecusarOperacaoForaDaEmpresaAtiva()) {
            return;
        }

        if (! $this->validateBeforeSave(finalizar: false)) {
            return;
        }

        if (! $this->persistOs(finalizar: false)) {
            return;
        }

        if ($this->isEditingOs()) {
            $this->notifyOsGravada();
            $this->openPostSavePrompt();
        }
    }

    public function finalizarOs(): void
    {
        if ($this->osPersistindo || $this->osReadOnly() || $this->osFaturamentoOpen) {
            return;
        }

        if ($this->osRecusarOperacaoForaDaEmpresaAtiva()) {
            return;
        }

        if (! $this->validateBeforeSave(finalizar: true)) {
            return;
        }

        if (! $this->isEditingOs()) {
            session()->flash('erp_os_faturamento', true);
        }

        if (! $this->persistOs(finalizar: false)) {
            return;
        }

        if (! $this->isEditingOs()) {
            return;
        }

        $reabrindo = $this->osMeiosPagamento !== [];
        // Sempre relê cadastro de atalhos (empresa/formas); preserva valores já digitados.
        $this->carregarMeiosPagamentoOs();

        if ($this->osMeiosPagamento === []) {
            Notification::make()
                ->title('Cadastre uma forma de pagamento para faturar a OS.')
                ->warning()
                ->send();

            return;
        }

        if (! $reabrindo) {
            $this->resetAjusteFaturamentoOs();
        }

        $this->abrirModalFaturamentoOs(resetPagamentos: ! $reabrindo);
    }

    /**
     * Mesmo parâmetro da Tela de Venda / PDV: atalho_concluir_venda (F7|F9|F10).
     */
    public function atalhoConcluirOs(): string
    {
        return EmpresaParametros::atalhoConcluirVenda(ErpContext::currentEmpresaId());
    }

    public function gravarOsSemFaturar(): void
    {
        $this->osFaturamentoOpen = false;

        Notification::make()
            ->title('OS gravada.')
            ->body('O faturamento ainda não foi feito.')
            ->success()
            ->send();
    }

    public function updatedOsAcrescimoPct(): void
    {
        $this->osAcrescimoPct = $this->formatOsMoney($this->moneyOs($this->osAcrescimoPct));
        $valor = bcdiv(bcmul($this->moneyOs($this->totalGeral), $this->moneyOs($this->osAcrescimoPct), 4), '100', 2);
        $this->osAcrescimoValor = $this->formatOsMoney($valor);
        $this->aplicarTotalNaPrimeiraFormaOs();
    }

    public function updatedOsAcrescimoValor(): void
    {
        $this->osAcrescimoValor = $this->formatOsMoney($this->moneyOs($this->osAcrescimoValor));
        $base = $this->moneyOs($this->totalGeral);
        $this->osAcrescimoPct = bccomp($base, '0.00', 2) === 1
            ? $this->formatOsMoney(bcdiv(bcmul($this->moneyOs($this->osAcrescimoValor), '100', 4), $base, 2))
            : '0,00';
        $this->aplicarTotalNaPrimeiraFormaOs();
    }

    public function updatedOsDescontoPct(): void
    {
        $this->osDescontoPct = $this->formatOsMoney($this->moneyOs($this->osDescontoPct));
        $valor = bcdiv(bcmul($this->moneyOs($this->totalGeral), $this->moneyOs($this->osDescontoPct), 4), '100', 4);
        $this->osDescontoValor = $this->formatOsMoney(bcadd($valor, '0', 2));
        $this->aplicarTotalNaPrimeiraFormaOs();
    }

    public function updatedOsDescontoValor(): void
    {
        $this->osDescontoValor = $this->formatOsMoney($this->moneyOs($this->osDescontoValor));
        $base = $this->moneyOs($this->totalGeral);
        $this->osDescontoPct = bccomp($base, '0.00', 2) === 1
            ? $this->formatOsMoney(bcdiv(bcmul($this->moneyOs($this->osDescontoValor), '100', 4), $base, 2))
            : '0,00';
        $this->aplicarTotalNaPrimeiraFormaOs();
    }

    public function selectOsPagamentoByAtalho(string $atalho): void
    {
        $atalho = strtoupper(trim($atalho));

        foreach ($this->osMeiosPagamento as $index => $meio) {
            if (strtoupper((string) ($meio['atalho'] ?? '')) !== $atalho) {
                continue;
            }

            // Igual TV: se a linha já tem valor, só foca — não recalcula restante.
            if (ErpMoney::parseBr($meio['valor'] ?? '0') > 0.009) {
                $this->osPagamentoIndex = $index;
                $this->dispatch('erp-os-focus-finalizar-pagamento', index: $index);

                return;
            }

            $this->aplicarRestanteOsPagamento($index);

            return;
        }
    }

    /**
     * Esc / Voltar: fecha o F4 preservando meios, ajustes, CPF e observação (paridade TV).
     */
    public function voltarDoFaturamentoOs(): void
    {
        if ($this->osPersistindo) {
            return;
        }

        if ($this->osTabelaPrazoConsulta) {
            $this->cancelarOsTabelaPrazoConsulta();

            return;
        }

        $this->osFaturamentoOpen = false;
        $this->dispatch('erp-os-focus-item-descricao');
    }

    /**
     * Esc/Voltar em 1 round-trip: grava valores/CPF/infos do DOM e fecha o modal.
     * Sem validar CPF (só preservar; validação ocorre no Concluir).
     *
     * @param  array<int|string, string>  $valores
     */
    public function voltarDoFaturamentoOsComFlush(
        array $valores,
        ?int $selectedIndex,
        string $cpfNota,
        string $observacoes,
    ): void {
        if ($this->osPersistindo) {
            return;
        }

        if ($this->osTabelaPrazoConsulta) {
            $this->cancelarOsTabelaPrazoConsulta();

            return;
        }

        if (! $this->osFaturamentoOpen) {
            return;
        }

        $this->flushOsValoresPagamento($valores, $selectedIndex, skipRender: false);
        $this->flushOsCpfNotaEObservacoes($cpfNota, $observacoes, validarCpf: false, skipRender: false);
        $this->osFaturamentoOpen = false;
        $this->dispatch('erp-os-focus-item-descricao');
    }

    /** @deprecated Use voltarDoFaturamentoOs — mantido para handlers antigos. */
    public function cancelarFaturamentoOs(): void
    {
        $this->voltarDoFaturamentoOs();
    }

    public function selectOsPagamento(int $index): void
    {
        if (! isset($this->osMeiosPagamento[$index])) {
            return;
        }

        $this->osPagamentoIndex = $index;
        $this->skipRender();
        // Foco/seleção já feitos no DOM (mousedown). Sem dispatch — evita corrida com setas.
    }

    /**
     * @param  array<int|string, string>  $valores
     */
    public function flushOsValoresPagamento(array $valores, ?int $selectedIndex = null, bool $skipRender = true): void
    {
        foreach ($valores as $index => $raw) {
            $i = (int) $index;

            if (! isset($this->osMeiosPagamento[$i])) {
                continue;
            }

            $this->osMeiosPagamento[$i]['valor'] = $this->formatOsMoney($this->moneyOs((string) $raw));
        }

        if ($selectedIndex !== null && isset($this->osMeiosPagamento[$selectedIndex])) {
            $this->osPagamentoIndex = $selectedIndex;
        }

        if ($skipRender) {
            $this->skipRender();
        }
    }

    public function flushOsCpfNotaEObservacoes(
        string $cpfNota,
        string $observacoes,
        bool $validarCpf = true,
        bool $skipRender = true,
    ): bool {
        $raw = trim($cpfNota);
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if ($digits === '') {
            $this->osCpfNota = '';
        } elseif (strlen($digits) === 11 && DocumentoBrasileiroValidator::isValidCpf($digits)) {
            $this->osCpfNota = PdvNotaClienteService::formatarCpfNota($digits);
        } else {
            $this->osCpfNota = $raw;
        }

        $this->observacoes = trim(mb_strtoupper($observacoes, 'UTF-8'));

        if ($validarCpf && ($msg = (new PdvNotaClienteService())->validaDocumentoCpfNota($this->osCpfNota))) {
            Notification::make()->title($msg)->warning()->send();
            if ($skipRender) {
                $this->skipRender();
            }

            return false;
        }

        if ($this->osCpfNota !== '') {
            $this->osCpfNota = PdvNotaClienteService::formatarCpfNota($this->osCpfNota) ?: $this->osCpfNota;
        }

        if ($skipRender) {
            $this->skipRender();
        }
        $this->dispatch('erp-os-fin-cpf-synced', value: $this->osCpfNota);

        return true;
    }

    public function sincronizarClienteFaturamentoOs(string $buscaVisivel, bool $skipRender = true): bool
    {
        if (! $this->osFaturamentoOpen) {
            return true;
        }

        $term = trim($buscaVisivel);
        // Placeholder visual do input quando não há busca (não é texto digitado).
        if ($term === '—' || $term === '-') {
            $term = '';
        }
        $committed = trim($this->clienteSearch);

        if ($this->clienteId && ($term === '' || mb_strtoupper($term, 'UTF-8') === mb_strtoupper($committed, 'UTF-8'))) {
            if ($term === '') {
                $this->dispatch('erp-os-fin-cliente-synced', value: $this->clienteSearch);
            }
            if ($skipRender) {
                $this->skipRender();
            }

            return true;
        }

        $this->dispatch('erp-os-fin-cliente-synced', value: $this->clienteSearch);
        Notification::make()
            ->title('Selecione o cliente na lista.')
            ->body('A troca só vale após escolher um cadastro válido.')
            ->warning()
            ->send();

        return false;
    }

    public function selecionarClienteFaturamentoOs(int $id): void
    {
        if (! $this->osFaturamentoOpen || $this->osReadOnly()) {
            return;
        }

        $person = Person::query()->find($id);

        if (! $person) {
            Notification::make()->title('Cliente não encontrado.')->warning()->send();

            return;
        }

        $this->clienteId = (int) $person->id;
        $this->clienteSearch = mb_strtoupper((string) $person->nome_razao, 'UTF-8');
        $this->applyClienteFields($person);
        $this->syncOsCpfNotaFromCliente($person);
        $this->clienteLookupOpen = false;
        $this->clienteResults = [];
        $this->selectedClienteIndex = null;

        $this->dispatch('erp-os-fin-cliente-synced', value: $this->clienteSearch);
        $this->dispatch('erp-os-fin-cpf-synced', value: $this->osCpfNota);
    }

    private function syncOsCpfNotaFromCliente(Person $person): void
    {
        $cpf = PdvNotaClienteService::extrairCpfParaNota(
            (string) ($person->cpf_cnpj ?? ''),
            (string) ($person->pessoa_tipo ?? Person::PESSOA_FISICA),
        );

        $this->osCpfNota = $cpf ?? '';
    }

    public function aplicarRestanteOsPagamento(int $index): void
    {
        if (! isset($this->osMeiosPagamento[$index])) {
            return;
        }

        $outros = 0.0;

        foreach ($this->osMeiosPagamento as $i => $meio) {
            if ($i === $index) {
                continue;
            }

            $outros += ErpMoney::parseBr($meio['valor'] ?? '0');
        }

        $total = ErpMoney::parseBr($this->formatOsMoney($this->osTotalLiquido()));
        $restante = max(0, round($total - $outros, 2));
        $valorFormatado = ErpMoney::formatBr($restante);

        $meios = $this->osMeiosPagamento;
        $meios[$index]['valor'] = $valorFormatado;

        $pdvPagamento = $this->osPagamentoComoPdv($meios[$index]);

        if (PdvFinalizarPagamentosHelper::isFormaAPrazoPagamento($pdvPagamento)) {
            $pdvLinhas = array_map(fn (array $m): array => $this->osPagamentoComoPdv($m), $meios);
            $pdvLinhas = PdvFinalizarPagamentosHelper::aplicarFormaPrazoExclusiva($pdvLinhas, $index, $total);

            foreach ($pdvLinhas as $i => $linha) {
                if (isset($meios[$i])) {
                    $meios[$i]['valor'] = $linha['valor'];
                }
            }

            $valorFormatado = (string) ($meios[$index]['valor'] ?? $valorFormatado);
        }

        $this->osMeiosPagamento = $meios;
        $this->osPagamentoIndex = $index;
        $this->osTabelaPrazoDias = null;
        $this->osParcelasRows = [];
        $this->dispatch('erp-os-focus-finalizar-pagamento', index: $index, valor: $valorFormatado);

        if (PdvFinalizarPagamentosHelper::precisaParcelasCarne($this->osPagamentoComoPdv($meios[$index]))) {
            $this->ensureOsTabelaPrazoCrediario();
        }
    }

    /**
     * Sync local (atalho/Enter) + abre carnê se necessário. UI já atualizou no DOM.
     *
     * @param  array<int|string, string>  $valores
     */
    public function aplicarOsPagamentoLocalEParcelas(array $valores, int $index): void
    {
        if (! $this->osFaturamentoOpen) {
            return;
        }

        // Sem skipRender: carnê precisa remorphar. Só skip se não abrir parcelas.
        $this->flushOsValoresPagamento($valores, $index, skipRender: false);

        if (! isset($this->osMeiosPagamento[$index])) {
            $this->skipRender();

            return;
        }

        $pdv = $this->osPagamentoComoPdv($this->osMeiosPagamento[$index]);
        $valorNum = ErpMoney::parseBr($this->osMeiosPagamento[$index]['valor'] ?? '0');

        if ($valorNum > 0.009 && PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdv)) {
            $this->osTabelaPrazoDias = null;
            $this->osParcelasRows = [];
            $this->ensureOsTabelaPrazoCrediario();

            return;
        }

        $this->skipRender();
    }

    /**
     * Concluir em 1 round-trip: flush cliente/CPF/valores + faturarOs (mesmo fluxo oficial).
     * skipRender=false nos flushes para o faturamento poder remorphar a tela.
     *
     * @param  array<int|string, string>  $valores
     */
    public function faturarOsComFlush(
        array $valores,
        ?int $selectedIndex,
        string $clienteBusca,
        string $cpfNota,
        string $observacoes,
    ): void {
        if (! $this->osFaturamentoOpen || $this->osPersistindo) {
            return;
        }

        if (\App\Support\Erp\Os\OsFinPerf::enabled()) {
            \App\Support\Erp\Os\OsFinPerf::reset();
            \App\Support\Erp\Os\OsFinPerf::listenQueries();
            \App\Support\Erp\Os\OsFinPerf::mark('php.com_flush.start');
        }

        // Valores primeiro: se CPF/cliente falhar, remorph não apaga o que o operador digitou.
        $this->flushOsValoresPagamento($valores, $selectedIndex, skipRender: false);
        \App\Support\Erp\Os\OsFinPerf::mark('php.flush_valores');

        if (! $this->sincronizarClienteFaturamentoOs($clienteBusca, skipRender: false)) {
            \App\Support\Erp\Os\OsFinPerf::flushToLog('os-fin-perf-abort-cliente');

            return;
        }
        \App\Support\Erp\Os\OsFinPerf::mark('php.sync_cliente');

        if (! $this->flushOsCpfNotaEObservacoes($cpfNota, $observacoes, true, skipRender: false)) {
            \App\Support\Erp\Os\OsFinPerf::flushToLog('os-fin-perf-abort-cpf');

            return;
        }
        \App\Support\Erp\Os\OsFinPerf::mark('php.flush_cpf_obs');

        $this->faturarOs();
    }

    public function faturarOs(): void
    {
        if (! $this->osFaturamentoOpen || $this->osPersistindo) {
            return;
        }

        if ($this->osTabelaPrazoConsulta) {
            return;
        }

        \App\Support\Erp\Os\OsFinPerf::mark('php.faturarOs.start');

        if (! $this->ensureOsTabelaPrazoCrediario(abrirSeNecessario: true)) {
            \App\Support\Erp\Os\OsFinPerf::flushToLog('os-fin-perf-abort-parcelas');

            return;
        }
        \App\Support\Erp\Os\OsFinPerf::mark('php.ensure_parcelas');

        $ordem = $this->record;

        if (! $ordem instanceof OrdemServico) {
            return;
        }

        if ($this->osRecusarOperacaoForaDaEmpresaAtiva($ordem)) {
            return;
        }

        $liquido = $this->osTotalLiquido();

        if (bccomp($liquido, '0.00', 2) !== 1) {
            Notification::make()
                ->title('A OS não tem valor para faturar.')
                ->warning()
                ->send();

            return;
        }

        $ordem->total_geral = $liquido;
        // Informações Adicionais do F4 = mesma $observacoes da OS.
        $ordem->observacoes = trim($this->observacoes) !== '' ? trim($this->observacoes) : null;
        $this->persistirOsTransporteNaOrdem();
        \App\Support\Erp\Os\OsFinPerf::mark('php.persist_transporte');
        $this->osPersistindo = true;
        $lock = Cache::lock($this->osFaturarLockKey((int) $ordem->getKey()), 30);

        if (! $lock->get()) {
            $this->osPersistindo = false;
            Notification::make()
                ->title('O faturamento desta OS já está em andamento.')
                ->warning()
                ->send();

            return;
        }
        \App\Support\Erp\Os\OsFinPerf::mark('php.lock_ok');

        try {
            $contas = app(OsFaturamentoService::class)->faturar(
                $ordem,
                $this->osMeiosPagamento,
                $this->osTabelaPrazoDiasList(),
                $this->osParcelasChequeNumerosList(),
            );
            \App\Support\Erp\Os\OsFinPerf::mark('php.svc_faturar_ok');
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Não foi possível faturar a OS.')
                ->body($exception->getMessage())
                ->warning()
                ->send();
            \App\Support\Erp\Os\OsFinPerf::flushToLog('os-fin-perf-error');

            return;
        } finally {
            $lock->release();
            $this->osPersistindo = false;
        }

        $this->osFaturamentoOpen = false;
        $this->resetOsParcelasFaturamento();
        \App\Support\Erp\Os\OsFinPerf::mark('php.modal_closed_state');

        Notification::make()
            ->title('OS faturada.')
            ->success()
            ->send();

        $indexUrl = OrdemServicoResource::getUrl('index');

        if ($this->offerEmitirBoletosPosDocumento($contas, $indexUrl, navigate: false)) {
            \App\Support\Erp\Os\OsFinPerf::mark('php.offer_boletos');
            \App\Support\Erp\Os\OsFinPerf::flushToLog('os-fin-perf-ok-boletos');

            return;
        }

        \App\Support\Erp\Os\OsFinPerf::mark('php.before_redirect_index');
        \App\Support\Erp\Os\OsFinPerf::flushToLog('os-fin-perf-ok');
        $this->redirect($indexUrl, navigate: false);
    }

    public function osTotalFaturamento(): string
    {
        return $this->osTotalLiquido();
    }

    public function osTotalLiquido(): string
    {
        $liquido = bcsub(
            bcadd($this->moneyOs($this->totalGeral), $this->moneyOs($this->osAcrescimoValor), 2),
            $this->moneyOs($this->osDescontoValor),
            2,
        );

        return bccomp($liquido, '0.00', 2) === 1 ? $liquido : '0.00';
    }

    /**
     * Mesmo parâmetro do PDV (`param_geral_rateio_pessoa_pdv`): só UI informativa no faturamento.
     */
    public function getOsRateioPessoaProperty(): bool
    {
        return (bool) (ErpContext::currentEmpresa()?->param_geral_rateio_pessoa_pdv ?? true);
    }

    public function getOsValorPorPessoaProperty(): string
    {
        $divisor = max(1, (int) ErpMoney::parseBr($this->osDividirPor ?? '1', 0));
        $total = (float) $this->osTotalLiquido();

        return $this->formatOsMoney(number_format(round($total / $divisor, 2), 2, '.', ''));
    }

    public function osVendedorLabel(): string
    {
        foreach ($this->atendenteOptions as $opt) {
            if ((int) $opt['id'] === (int) $this->atendenteId) {
                return (string) $opt['nome'];
            }
        }

        return '—';
    }

    public function confirmarValorOsPagamento(int $index, string $valor): void
    {
        if (! isset($this->osMeiosPagamento[$index])) {
            return;
        }

        $this->osMeiosPagamento[$index]['valor'] = $this->formatOsMoney($this->moneyOs($valor));
        $this->osPagamentoIndex = $index;

        $pdv = $this->osPagamentoComoPdv($this->osMeiosPagamento[$index]);
        $valorNum = ErpMoney::parseBr($this->osMeiosPagamento[$index]['valor'] ?? '0');

        if ($valorNum > 0 && PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdv)) {
            $this->osTabelaPrazoDias = null;
            $this->osParcelasRows = [];
            $this->ensureOsTabelaPrazoCrediario();

            return;
        }

        // Paridade TV: Enter grava e avança o foco — nunca preenche restante.
        // skipRender: evita remorph que devolve o foco ao campo após ir ao Concluir.
        if (bccomp($this->osValorRestante(), '0.00', 2) !== 1) {
            $this->skipRender();
            $this->dispatch('erp-os-focus-finalizar-ok');

            return;
        }

        $count = count($this->osMeiosPagamento);

        if ($index < $count - 1) {
            $next = $index + 1;
            $this->osPagamentoIndex = $next;
            $this->skipRender();
            $this->dispatch('erp-os-focus-finalizar-pagamento', index: $next);

            return;
        }

        $this->skipRender();
        $this->dispatch('erp-os-focus-finalizar-pagamento', index: $index);
    }

    public function updatedOsMeiosPagamento(mixed $value, ?string $key = null): void
    {
        if (! is_string($key) || preg_match('/^(\d+)\.valor$/', $key, $m) !== 1) {
            return;
        }

        $index = (int) $m[1];

        if (! isset($this->osMeiosPagamento[$index])) {
            return;
        }

        $this->osMeiosPagamento[$index]['valor'] = $this->formatOsMoney(
            $this->moneyOs((string) ($this->osMeiosPagamento[$index]['valor'] ?? '0')),
        );
    }

    public function osTotalPago(): string
    {
        $soma = '0.00';

        foreach ($this->osMeiosPagamento as $meio) {
            $soma = bcadd($soma, $this->moneyOs((string) ($meio['valor'] ?? '0')), 2);
        }

        return $soma;
    }

    public function osValorRestante(?int $excetoIndex = null): string
    {
        $pago = '0.00';

        foreach ($this->osMeiosPagamento as $index => $meio) {
            if ($excetoIndex !== null && $index === $excetoIndex) {
                continue;
            }

            $pago = bcadd($pago, $this->moneyOs((string) ($meio['valor'] ?? '0')), 2);
        }

        $restante = bcsub($this->osTotalFaturamento(), $pago, 2);

        return bccomp($restante, '0.00', 2) === 1 ? $restante : '0.00';
    }

    public function osTroco(): string
    {
        $excesso = bcsub($this->osTotalPago(), $this->osTotalFaturamento(), 2);

        return bccomp($excesso, '0.00', 2) === 1 ? $excesso : '0.00';
    }

    public function formatOsMoney(string $value): string
    {
        $neg = str_starts_with($value, '-');
        $abs = $neg ? substr($value, 1) : $value;
        [$int, $frac] = array_pad(explode('.', bcadd($abs, '0', 2), 2), 2, '00');
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int) ?? $int;

        return ($neg ? '-' : '').$int.','.$frac;
    }

    protected function persistOs(bool $finalizar, bool $redirectOnCreate = true): bool
    {
        if ($this->osPersistindo) {
            return false;
        }

        if ($this->osRecusarOperacaoForaDaEmpresaAtiva()) {
            return false;
        }

        $this->osPersistindo = true;
        $lock = Cache::lock($this->osPersistLockKey(), 20);

        if (! $lock->get()) {
            $this->osPersistindo = false;
            Notification::make()
                ->title('A OS já está sendo gravada.')
                ->warning()
                ->send();

            return false;
        }

        $this->recalcTotais();

        $subPecas = ErpMoney::parseBr($this->subtotalPecas);
        $subServicos = ErpMoney::parseBr($this->subtotalServicos);
        $descPecas = ErpMoney::parseBr($this->descPecasGlobal);
        $descServicos = ErpMoney::parseBr($this->descServicosGlobal);
        $totalPecas = ErpMoney::parseBr($this->totalPecas);
        $totalServicos = ErpMoney::parseBr($this->totalServicos);
        $totalGeral = ErpMoney::parseBr($this->totalGeral);
        $createdId = null;
        $empresaAtiva = ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id;
        $empresaId = ($this->isEditingOs() && $this->record instanceof OrdemServico && filled($this->record->empresa_id))
            ? (int) $this->record->empresa_id
            : $empresaAtiva;
        $usuarioId = Auth::id();

        try {
            DB::transaction(function () use (
                $subPecas,
                $subServicos,
                $descPecas,
                $descServicos,
                $totalPecas,
                $totalServicos,
                $totalGeral,
                $finalizar,
                $empresaId,
                $usuarioId,
                &$createdId,
            ): void {
                $momento = ErpTimezone::toLocal();

                $attributes = [
                    'cliente_id' => $this->clienteId,
                    'orcamento_id' => $this->orcamentoOrigemId,
                    'atendente_id' => $this->atendenteId,
                    'usuario_id' => $usuarioId,
                    'documento' => trim($this->documento) ?: null,
                    'nome' => mb_strtoupper(trim($this->nome ?: $this->clienteSearch), 'UTF-8') ?: null,
                    'fone1' => trim($this->fone1) ?: null,
                    'endereco' => mb_strtoupper(trim($this->endereco), 'UTF-8') ?: null,
                    'bairro' => mb_strtoupper(trim($this->bairro), 'UTF-8') ?: null,
                    'cidade' => mb_strtoupper(trim($this->cidade), 'UTF-8') ?: null,
                    'uf' => mb_strtoupper(trim($this->uf), 'UTF-8') ?: null,
                    'data_inicio' => $this->dataInicio ?: $momento->format('Y-m-d'),
                    'hora_inicio' => $this->normalizeHora($this->horaInicio) ?? $momento->format('H:i:s'),
                    'previsao_entrega' => filled($this->previsaoEntrega) ? str_replace('T', ' ', $this->previsaoEntrega) : null,
                    'numero_serie' => trim($this->numeroSerie) ?: null,
                    'descricao' => mb_strtoupper(trim($this->descricao), 'UTF-8') ?: null,
                    'descricao2' => mb_strtoupper(trim($this->descricao2), 'UTF-8') ?: null,
                    'modelo' => mb_strtoupper(trim($this->modelo), 'UTF-8') ?: null,
                    'ano' => trim($this->ano) ?: null,
                    'placa' => mb_strtoupper(trim($this->placa), 'UTF-8') ?: null,
                    'km' => trim($this->km) ?: null,
                    'cor_veiculo' => mb_strtoupper(trim($this->corVeiculo), 'UTF-8') ?: null,
                    'chassi_veiculo' => mb_strtoupper(trim($this->chassiVeiculo), 'UTF-8') ?: null,
                    'problema' => trim($this->problema) ?: null,
                    'observacoes' => trim($this->observacoes) ?: null,
                    'laudo' => mb_strtoupper(trim($this->laudo), 'UTF-8') ?: null,
                    'subtotal' => round($subPecas + $subServicos, 2),
                    'subtotal_pecas' => $subPecas,
                    'subtotal_servicos' => $subServicos,
                    'vl_desc_pecas' => $descPecas,
                    'vl_desc_servicos' => $descServicos,
                    'total_produtos' => $totalPecas,
                    'total_servicos' => $totalServicos,
                    'total_geral' => $totalGeral,
                    'situacao' => $finalizar
                        ? OrdemServico::SITUACAO_FINALIZADA
                        : OrdemServico::SITUACAO_ABERTA,
                ];

                if ($finalizar) {
                    $attributes['data_termino'] = $momento->format('Y-m-d');
                    $attributes['hora_termino'] = $momento->format('H:i:s');
                } else {
                    $attributes['data_termino'] = $this->dataTermino ?: null;
                    $attributes['hora_termino'] = $this->normalizeHora($this->horaTermino);
                }

                $vinculoOrc = app(\App\Support\Erp\Os\OsOrcamentoVinculo::class);
                $novoOrcamentoId = filled($this->orcamentoOrigemId) ? (int) $this->orcamentoOrigemId : null;
                $novoOrcamentoId = ($novoOrcamentoId !== null && $novoOrcamentoId > 0) ? $novoOrcamentoId : null;

                if ($this->isEditingOs()) {
                    /** @var OrdemServico $ordem */
                    $ordem = $this->record;
                    $travada = OrdemServico::query()->whereKey($ordem->getKey())->lockForUpdate()->first();

                    if (! $travada instanceof OrdemServico || ! $travada->pertenceAEmpresaAtiva()) {
                        throw new \RuntimeException('Esta OS não pertence à empresa ativa.');
                    }

                    if (filled($travada->empresa_id)) {
                        $attributes['empresa_id'] = (int) $travada->empresa_id;
                    }

                    $orcamentoAnteriorId = filled($travada->orcamento_id) ? (int) $travada->orcamento_id : null;
                    $osId = (int) $travada->getKey();

                    // Mesmo vínculo já gravado: só atualiza a OS (não reprocessa importação).
                    // Troca/remoção: libera o anterior se livre; novo vínculo exige elegibilidade.
                    if ($novoOrcamentoId !== null && $novoOrcamentoId !== $orcamentoAnteriorId) {
                        $vinculoOrc->vincularAoGravarOs($novoOrcamentoId, $osId);
                    }

                    $ordem->update($attributes);

                    if (
                        $orcamentoAnteriorId !== null
                        && $orcamentoAnteriorId !== $novoOrcamentoId
                    ) {
                        $vinculoOrc->devolverSeLivre($orcamentoAnteriorId, $osId);
                    }
                } else {
                    // OS nova: valida + IMPORTADO antes do create (rollback se a TX falhar).
                    if ($novoOrcamentoId !== null) {
                        $vinculoOrc->vincularAoGravarOs($novoOrcamentoId);
                    }

                    $attributes['empresa_id'] = $empresaId;
                    OrdemServico::query()->lockForUpdate()->orderByDesc('id')->limit(1)->first();
                    $ordem = OrdemServico::query()->create([
                        'numero' => OrdemServico::nextNumero(),
                        'data_emissao' => $momento->format('Y-m-d'),
                        ...$attributes,
                    ]);
                    $createdId = $ordem->getKey();
                }

                $this->sincronizarItensPersistidos($ordem, $empresaId, $usuarioId);
                $this->salvarEquipamentoDaOs((int) ($empresaId ?: ErpContext::currentEmpresaId()));
            });
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível salvar a ordem de serviço.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return false;
        } finally {
            $lock->release();
            $this->osPersistindo = false;
        }

        if ($createdId !== null && ! $finalizar && $redirectOnCreate) {
            session()->flash('erp_os_post_save_prompt', true);

            $this->redirect(
                OrdemServicoResource::getUrl('edit', ['record' => $createdId]),
                navigate: false,
            );

            return true;
        }

        if ($this->isEditingOs() && ! $finalizar) {
            $this->syncOsStateAfterPersist();
        }

        return true;
    }

    /**
     * Atualiza IDs/número/situação já gravados sem recarregar o formulário do banco.
     */
    protected function syncOsStateAfterPersist(): void
    {
        if (! $this->record instanceof OrdemServico) {
            return;
        }

        $this->data['numero'] = $this->record->numero;
        $this->data['situacao'] = $this->record->situacao;
    }

    protected function sincronizarItensPersistidos(OrdemServico $ordem, mixed $empresaId, mixed $usuarioId): void
    {
        $existentes = $ordem->itens()->get()->keyBy('id');
        $keptIds = [];

        foreach ($this->itens as $index => $row) {
            [$dataTermino, $horaTermino] = $this->parseConcluidoEm((string) ($row['concluido_em'] ?? ''));

            $itemData = [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'product_id' => filled($row['product_id'] ?? null) ? (int) $row['product_id'] : null,
                'funcionario_id' => filled($row['funcionario_id'] ?? null) ? (int) $row['funcionario_id'] : null,
                'tipo' => ($row['tipo'] ?? 'P') === 'S' ? 'S' : 'P',
                'discriminacao' => mb_strtoupper((string) ($row['discriminacao'] ?? ''), 'UTF-8') ?: null,
                'servico_prestado' => ($row['tipo'] ?? 'P') === 'S'
                    ? (mb_strtoupper(trim((string) ($row['servico_prestado'] ?? '')), 'UTF-8') ?: null)
                    : null,
                'qtd' => ErpMoney::parseBr($row['qtd'] ?? 0, 3),
                'preco' => ErpMoney::parseBr($row['preco'] ?? 0),
                'desconto' => ErpMoney::parseBr($row['desconto'] ?? 0),
                'acrescimo' => ErpMoney::parseBr($row['acrescimo'] ?? 0),
                'total' => ErpMoney::parseBr($row['total'] ?? 0),
                'data_termino' => $dataTermino,
                'hora_termino' => $horaTermino,
            ];

            $rowId = filled($row['id'] ?? null) ? (int) $row['id'] : 0;
            $item = $rowId > 0 ? $existentes->get($rowId) : null;

            if ($item instanceof OrdemServicoItem) {
                $item->update($itemData);
                $keptIds[] = $item->id;
                $this->itens[$index]['id'] = $item->id;

                continue;
            }

            $item = $ordem->itens()->create($itemData);
            $keptIds[] = $item->id;
            $this->itens[$index]['id'] = $item->id;
            $this->itens[$index]['key'] = 'item-'.$item->id;
        }

        $remover = $ordem->itens();

        if ($keptIds === []) {
            $remover->delete();
        } else {
            $remover->whereNotIn('id', $keptIds)->delete();
        }
    }

    protected function osPersistLockKey(): string
    {
        $alvo = $this->isEditingOs()
            ? (string) $this->record->getKey()
            : 'novo';

        return 'os-gravar:'.(Auth::id() ?? 0).':'.$alvo;
    }

    protected function osFaturarLockKey(int $ordemId): string
    {
        return 'os-faturar:'.$ordemId;
    }

    protected function normalizeHora(?string $hora): ?string
    {
        $hora = trim((string) $hora);

        if ($hora === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $hora) === 1) {
            return $hora . ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora) === 1) {
            return $hora;
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function parseConcluidoEm(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [null, null];
        }

        $value = str_replace('T', ' ', $value);

        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s+(\d{2}:\d{2})(?::\d{2})?)?$/', $value, $m) === 1) {
            $hora = isset($m[2]) ? $m[2] . (strlen($m[2]) === 5 ? ':00' : '') : null;

            return [$m[1], $hora];
        }

        return [null, null];
    }

    public function openProdutosCadastro(): void
    {
        ErpScreen::set('Cadastro de Produtos');
        $this->overlayPersonOpen = false;
        $this->overlayProductOpen = true;
        $this->skipRender();
    }

    public function openPessoasCadastro(): void
    {
        ErpScreen::set('Cadastro de Pessoas');
        $this->overlayProductOpen = false;
        $this->overlayPersonOpen = true;
        $this->skipRender();
    }

    public function closeProductOverlay(): void
    {
        if (! $this->overlayProductOpen) {
            return;
        }

        $this->overlayProductOpen = false;
        ErpScreen::set('Lançamento OS');
        $this->skipRender();
    }

    public function closePersonOverlay(): void
    {
        if (! $this->overlayPersonOpen) {
            return;
        }

        $this->overlayPersonOpen = false;
        ErpScreen::set('Lançamento OS');
        $this->skipRender();
    }

    public function applyOverlayProdutoSaved(string $codigo): void
    {
        $this->overlayProductOpen = false;
        ErpScreen::set('Lançamento OS');

        if (filled($codigo)) {
            $codigo = mb_strtoupper(trim($codigo), 'UTF-8');
            $this->itemCodigoInput = $codigo;
            $product = $this->findProductByCodigo($codigo);

            if ($product) {
                $this->stageProductForEntry($product);

                return;
            }
        }
    }

    public function applyOverlayPersonSaved(int $clienteId): void
    {
        $this->overlayPersonOpen = false;
        ErpScreen::set('Lançamento OS');

        $person = Person::query()->find($clienteId);

        if ($person) {
            $this->clienteId = $person->id;
            $this->clienteSearch = mb_strtoupper($person->nome_razao, 'UTF-8');
            $this->applyClienteFields($person);
        }
    }

    public function handleOsFormEscape(): void
    {
        if ($this->tabPrecoItemModalOpen) {
            $this->cancelTabPrecoItem();

            return;
        }

        if ($this->osImportOrcamentoOpen) {
            $this->fecharImportarOrcamentoOs();

            return;
        }

        if ($this->printModalOpen) {
            $this->closePrintModal();

            return;
        }

        if ($this->descontoModalOpen) {
            $this->fecharModalDescontoItem();

            return;
        }

        if ($this->servicoPrestadoModalOpen) {
            $this->cancelarModalServicoPrestado();

            return;
        }

        if ($this->overlayProductOpen) {
            $this->closeProductOverlay();

            return;
        }

        if ($this->overlayPersonOpen) {
            $this->closePersonOverlay();

            return;
        }

        if ($this->osFaturamentoOpen) {
            // Preferir erpOsFecharFaturamentoInstantaneo (JS) — aqui só fecha estado PHP
            // (action bar Esc sem acesso ao DOM dos valores).
            if ($this->osPersistindo) {
                return;
            }
            $this->voltarDoFaturamentoOs();

            return;
        }

        if ($this->itemDeleteConfirmIndex !== null) {
            $this->cancelDeleteItem();

            return;
        }

        if ($this->editingItemIndex !== null || $this->itemPendingProductId !== null) {
            $this->cancelItemEdit();

            return;
        }

        if ($this->postSavePromptOpen) {
            $this->sairAposGravarOs();

            return;
        }

        $this->sairOsForm();
    }

    public function sairOsForm(): void
    {
        if ($this->editingItemIndex !== null || $this->itemPendingProductId !== null) {
            $this->cancelItemEdit();
        }

        if (! $this->osReadOnly() && $this->canPersistOsOnExit()) {
            if (! $this->persistOs(finalizar: false, redirectOnCreate: false)) {
                return;
            }
        }

        $this->cancelForm();
    }

    /**
     * Grava ao sair só quando há dados mínimos (sem toast de validação — o usuário quer fechar).
     */
    protected function canPersistOsOnExit(): bool
    {
        return $this->clienteId !== null
            && ! blank($this->clienteSearch)
            && $this->atendenteId !== null;
    }

    public function handlePostSavePromptEscape(): void
    {
        $this->sairAposGravarOs();
    }

    protected function notifyOsGravada(): void
    {
        Notification::make()
            ->title('Ordem de serviço gravada com sucesso!')
            ->success()
            ->send();
    }

    public function openPostSavePromptFromSession(): void
    {
        $this->notifyOsGravada();
        $this->openPostSavePrompt();
    }

    protected function openPostSavePrompt(): void
    {
        $this->postSavePromptOpen = true;
        $this->dispatch('erp-os-post-save-prompt-opened');
    }

    public function continuarOsAposGravar(): void
    {
        $this->postSavePromptOpen = false;
        $this->dispatch('erp-os-focus-item-descricao');
    }

    public function sairAposGravarOs(): void
    {
        $this->postSavePromptOpen = false;
        ErpScreen::set('Ordem de Serviço');
        $this->redirectErpAdmin(OrdemServicoResource::getUrl('index'));
    }

    public function iniciarNovaOs(): void
    {
        $this->postSavePromptOpen = false;
        ErpScreen::set('Lançamento OS');
        $this->redirectErpAdmin(OrdemServicoResource::getUrl('create'));
    }

    public function abrirFaturamentoOsCarregado(): void
    {
        $reabrindo = $this->osMeiosPagamento !== [];
        $this->carregarMeiosPagamentoOs();

        if ($this->osMeiosPagamento === []) {
            Notification::make()
                ->title('Cadastre uma forma de pagamento para faturar a OS.')
                ->warning()
                ->send();

            return;
        }

        if (! $reabrindo) {
            $this->resetAjusteFaturamentoOs();
        }

        $this->abrirModalFaturamentoOs(resetPagamentos: ! $reabrindo);
    }

    /**
     * Abre o fechamento. Na 1ª abertura zera valores; ao Voltar e reabrir preserva digitado.
     */
    protected function abrirModalFaturamentoOs(bool $resetPagamentos = true): void
    {
        if ($resetPagamentos) {
            $meios = $this->osMeiosPagamento;

            foreach ($meios as $index => $meio) {
                $meios[$index]['valor'] = '0,00';
            }

            $this->osMeiosPagamento = $meios;
            $this->osPagamentoIndex = 0;
            $this->osDividirPor = '1';
            $this->resetOsParcelasFaturamento();
        }

        if ($this->clienteId && trim($this->osCpfNota) === '') {
            $person = Person::query()->find((int) $this->clienteId);
            if ($person) {
                $this->syncOsCpfNotaFromCliente($person);
            }
        }

        $this->osFaturamentoOpen = true;
        $this->dispatch('erp-os-focus-finalizar-pagamento', index: $this->osPagamentoIndex);
    }

    protected function resetAjusteFaturamentoOs(): void
    {
        $this->osAcrescimoPct = '0,00';
        $this->osAcrescimoValor = '0,00';
        $this->osDescontoPct = '0,00';
        $this->osDescontoValor = '0,00';
    }

    protected function aplicarTotalNaPrimeiraFormaOs(): void
    {
        if ($this->osMeiosPagamento === []) {
            return;
        }

        // Recalcula o restante na forma selecionada (não força Dinheiro).
        $index = $this->osPagamentoIndex;

        if (! isset($this->osMeiosPagamento[$index])) {
            $index = 0;
        }

        foreach ($this->osMeiosPagamento as $i => $meio) {
            $this->osMeiosPagamento[$i]['valor'] = '0,00';
        }

        $this->aplicarRestanteOsPagamento($index);
    }

    protected function carregarMeiosPagamentoOs(): void
    {
        $colunas = ['id', 'codigo', 'descricao', 'tipo', 'tipo_movimento', 'atalho', 'ativo', 'aparece_venda'];

        $formas = FormaPagamento::query()
            ->where('ativo', true)
            ->where('aparece_venda', true)
            ->orderBy('codigo')
            ->orderBy('id')
            ->get($colunas);

        if ($formas->isEmpty()) {
            $formas = FormaPagamento::query()
                ->where('ativo', true)
                ->orderBy('codigo')
                ->orderBy('id')
                ->get($colunas);
        }

        // Mesma ordem visual da Tela de Venda / PDV.
        $formas = $formas->sortBy(function (FormaPagamento $f): array {
            return [$this->ordemOsFormaPagamento($f), (int) ($f->codigo ?? 0), (int) $f->id];
        })->values();

        $existentes = collect($this->osMeiosPagamento)->keyBy('id');
        $usados = [];

        // Concluir (F7/F9/F10) não pode competir com meio no mesmo modal.
        $concluir = $this->atalhoConcluirOs();
        if ($concluir !== '') {
            $usados[$concluir] = true;
        }

        $this->osMeiosPagamento = $formas->map(function (FormaPagamento $forma) use ($existentes, &$usados): array {
            $id = (int) $forma->id;
            $descricao = (string) $forma->descricao;

            // Somente o atalho cadastrado — sem inventar letra / preferência A/P/D/C.
            $atalho = FormaPagamentoAtalho::cadastradoUtilizavel(
                (string) ($forma->atalho ?? ''),
                $usados,
            ) ?? '';

            if ($atalho !== '') {
                $usados[$atalho] = true;
            }

            $valorExistente = $existentes[$id]['valor'] ?? '0,00';
            $valor = is_numeric($valorExistente)
                ? ErpMoney::formatBr((float) $valorExistente)
                : ErpMoney::formatBr(ErpMoney::parseBr((string) $valorExistente));

            return [
                'id' => $id,
                'descricao' => $descricao,
                'forma' => $descricao,
                'tipo' => (string) ($forma->tipo ?? ''),
                'tipo_movimento' => (string) ($forma->tipo_movimento ?? ''),
                'atalho' => $atalho,
                'valor' => $valor,
            ];
        })->values()->all();
    }

    protected function ordemOsFormaPagamento(FormaPagamento $forma): int
    {
        $tipo = strtolower(trim((string) ($forma->tipo ?? '')));
        $desc = mb_strtoupper(trim((string) ($forma->descricao ?? '')), 'UTF-8');

        return match (true) {
            $tipo === 'dinheiro', str_contains($desc, 'DINHEIRO') => 10,
            $tipo === 'pix', str_contains($desc, 'PIX') => 20,
            $tipo === 'cartao_debito',
            str_contains($desc, 'DÉBITO'),
            str_contains($desc, 'DEBITO') => 30,
            $tipo === 'cartao_credito',
            str_contains($desc, 'CRÉDITO'),
            str_contains($desc, 'CREDITO') => 40,
            $tipo === 'crediario',
            str_contains($desc, 'CREDIÁRIO'),
            str_contains($desc, 'CREDIARIO') => 50,
            default => 100,
        };
    }

    protected function moneyOs(string $value): string
    {
        $raw = trim($value);

        if ($raw === '') {
            return '0.00';
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $raw) !== 1) {
            return '0.00';
        }

        return bcadd($raw, '0', 2);
    }

    public function cancelForm(): void
    {
        ErpScreen::set('Ordem de Serviço');
        $this->redirectErpAdmin(OrdemServicoResource::getUrl('index'));
    }
}
