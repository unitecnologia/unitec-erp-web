<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\PersonResource;
use App\Models\CaixaConta;
use App\Support\Erp\CloudflaredStatus;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesPdvUi
{
    use ManagesPdvAutorizacao;
    use ManagesPdvBloqueio;
    use ManagesPdvBuscaAvancada;
    use ManagesPdvBuscaPreco;
    use ManagesPdvCaixa;
    use ManagesPdvClienteLimite;
    use ManagesPdvConfig;
    use ManagesPdvConsultaVenda;
    use ManagesPdvDesconto;
    use ManagesPdvGaveta;
    use ManagesPdvGrade;
    use ManagesPdvImportar;
    use ManagesPdvAcessoRapido;
    use ManagesPdvReceber;
    use ManagesPdvReimprimir;
    use ManagesPdvRemoverItens;
    use ManagesPdvSerial;
    use ManagesPdvTabelaPreco;
    use ManagesPdvVenda;
    use ManagesPdvVendaEspera;
    use ManagesPdvVendedor;

    public bool $caixaAberto = false;

    public string $pdvSearch = '';

    public function updatedPdvSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->pdvSearch !== $upper) {
            $this->pdvSearch = $upper;
        }

        $this->refreshPdvSearchResults();
    }

    public ?string $activeModal = null;

    public bool $overlayPersonOpen = false;

    /** @var array<string, string> */
    public array $sangriaForm = [
        'historico' => '',
        'valor' => '0,00',
        'tipo_conta' => '',
        'destino' => '',
    ];

    /** @var array<string, string> */
    public array $suprimentoForm = [
        'historico' => 'SUPRIMENTO DE CAIXA',
        'valor' => '0,00',
        'tipo_conta' => '',
    ];

    public string $vendedor = '';

    public string $pdvAcessoNegadoTitle = '';

    /** @var list<string> */
    public array $pdvAcessoNegadoLines = [];

    public ?string $pdvAcessoNegadoHint = null;

    public bool $pdvAcessoNegadoVoltarDashboard = true;

    /**
     * @param  list<string>  $lines
     */
    public function openPdvAcessoNegado(
        string $title,
        array $lines,
        bool $voltarDashboard = true,
        ?string $hint = null,
    ): void {
        $this->pdvAcessoNegadoTitle = $title;
        $this->pdvAcessoNegadoLines = array_values($lines);
        $this->pdvAcessoNegadoHint = $hint;
        $this->pdvAcessoNegadoVoltarDashboard = $voltarDashboard;
        // Modal nativo do PDV (visível); substitui Options / qualquer outro modal.
        $this->activeModal = 'acesso_negado';
    }

    public function dismissPdvAcessoNegado(): void
    {
        $voltar = $this->pdvAcessoNegadoVoltarDashboard;

        $this->activeModal = null;
        $this->pdvAcessoNegadoTitle = '';
        $this->pdvAcessoNegadoLines = [];
        $this->pdvAcessoNegadoHint = null;
        $this->pdvAcessoNegadoVoltarDashboard = true;

        if ($voltar) {
            ErpScreen::set('Principal');
            $this->redirect(Dashboard::getUrl(), navigate: false);
        }
    }

    /**
     * @return list<string>
     */
    public function getTipoContaOptionsProperty(): array
    {
        return [
            'DINHEIRO',
            'CARTÃO CRÉDITO',
            'CARTÃO DÉBITO',
            'PIX',
            'CHEQUE',
        ];
    }

    /**
     * Formas permitidas na sangria com saldo disponível na sessão.
     *
     * @return array<string, string> value => label com saldo
     */
    public function getSangriaTipoContaOptionsProperty(): array
    {
        $sessao = $this->caixaSessaoAtual();

        if (! $sessao) {
            return [];
        }

        $options = [];

        foreach (['DINHEIRO', 'CHEQUE'] as $forma) {
            $saldo = $sessao->saldoPorForma($forma);

            if ($saldo <= 0) {
                continue;
            }

            $options[$forma] = $forma.' — R$ '.ErpMoney::formatBr($saldo);
        }

        return $options;
    }

    /**
     * Destinos da sangria: apenas subcaixas ativas.
     *
     * @return array<string, string> id => "codigo — NOME"
     */
    public function getSangriaDestinoOptionsProperty(): array
    {
        return CaixaConta::query()
            ->where('ativo', true)
            ->where('tipo', CaixaConta::TIPO_SUBCAIXA)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome'])
            ->mapWithKeys(fn (CaixaConta $conta): array => [
                (string) $conta->id => $this->sangriaDestinoLabel($conta),
            ])
            ->all();
    }

    protected function sangriaDestinoLabel(CaixaConta $conta): string
    {
        return trim((string) $conta->codigo).' — '.mb_strtoupper((string) $conta->nome, 'UTF-8');
    }

    /**
     * @param  array<string, string>  $destinos
     */
    protected function defaultSangriaDestinoId(array $destinos): string
    {
        if ($destinos === []) {
            return '';
        }

        $geralId = (string) CaixaConta::ensureCaixaGeral()->id;

        if (isset($destinos[$geralId])) {
            return $geralId;
        }

        return (string) array_key_first($destinos);
    }

    protected function prepareSangriaFormOnOpen(): void
    {
        $formas = $this->sangriaTipoContaOptions;

        if ($formas === []) {
            Notification::make()
                ->title('Sem saldo para sangria.')
                ->body('Não há saldo em DINHEIRO ou CHEQUE nesta sessão.')
                ->warning()
                ->send();
            $this->sangriaForm['tipo_conta'] = '';
        } else {
            $atual = (string) ($this->sangriaForm['tipo_conta'] ?? '');

            if ($atual === '' || ! isset($formas[$atual])) {
                $this->sangriaForm['tipo_conta'] = isset($formas['DINHEIRO'])
                    ? 'DINHEIRO'
                    : (string) array_key_first($formas);
            }
        }

        $destinos = $this->sangriaDestinoOptions;
        $destinoAtual = (string) ($this->sangriaForm['destino'] ?? '');

        if ($destinoAtual === '' || ! isset($destinos[$destinoAtual])) {
            $this->sangriaForm['destino'] = $this->defaultSangriaDestinoId($destinos);
        }

        if ($this->sangriaForm['valor'] === '' || $this->sangriaForm['valor'] === null) {
            $this->sangriaForm['valor'] = '0,00';
        }
    }

    public function getCaixaTituloProperty(): string
    {
        return $this->caixaAberto ? 'CAIXA ABERTO' : 'CAIXA FECHADO';
    }

    public function getPersonOverlayUrlProperty(): string
    {
        return PersonResource::getUrl('create') . '?tipo=clientes&pdv=1';
    }

    protected function loadPdvSessionState(): void
    {
        $this->loadVendedorFromSession();
        $this->loadPdvPriceTableFromSession();
        $this->loadCaixaFromDatabase();
    }

    public function openPdvModal(string $modal): void
    {
        if (in_array($modal, ['resumo', 'sangria', 'suprimento', 'finalizar', 'acesso_rapido'], true)) {
            if (! $this->caixaAberto) {
                Notification::make()
                    ->title('Caixa fechado.')
                    ->body('Abra o caixa com F2 antes de continuar.')
                    ->warning()
                    ->send();

                return;
            }
        }

        if ($modal === 'finalizar' && ! $this->cupomTemItens()) {
            Notification::make()
                ->title('Informe os produtos da venda.')
                ->warning()
                ->send();

            return;
        }

        if ($modal === 'sangria') {
            $this->prepareSangriaFormOnOpen();
        }

        if ($modal === 'acesso_rapido') {
            $this->prepareAcessoRapidoOnOpen();
        }

        $this->activeModal = $modal;
        $this->dispatch('erp-pdv-modal-opened', modal: $modal);
    }

    public function closePdvModal(): void
    {
        if ($this->activeModal === 'acesso_rapido' && ($this->acessoRapidoEditando ?? false)) {
            $this->saveAcessoRapidoToDb();
            $this->acessoRapidoEditando = false;
            $this->acessoRapidoBusca = '';
            $this->acessoRapidoBuscaResults = [];
            $this->acessoRapidoSlotAlvo = null;
        }

        $wasSair = $this->activeModal === 'sair';
        $wasFinalizar = $this->activeModal === 'finalizar';

        if ($this->fechamentoMoedasModalOpen ?? false) {
            $this->fechamentoMoedasModalOpen = false;
        }

        if ($this->activeModal === 'finalizar') {
            $this->finalizarConfirmSair = false;
            $this->cancelFinalizarImprimir();
            $this->finalizarAba = 'totais';
            $this->limparFinalizarAlerta();
        }

        $this->activeModal = null;

        if ($wasSair || $wasFinalizar) {
            $this->dispatch('erp-pdv-focus-search');
        }
    }

    public function handlePdvEscape(): void
    {
        if ($this->fechamentoMoedasModalOpen ?? false) {
            $this->fecharContarMoedas();

            return;
        }

        if ($this->activeModal === 'acesso_negado') {
            $this->dismissPdvAcessoNegado();

            return;
        }

        if ($this->overlayPersonOpen) {
            $this->closePersonOverlay();

            return;
        }

        if ($this->pdvConfirmCancelarVenda) {
            $this->cancelCancelarCupom();

            return;
        }

        if ($this->pdvConfirmImprimirMovimentoCaixa ?? false) {
            $this->confirmImprimirMovimentoCaixa(false);

            return;
        }

        if ($this->pdvConfirmImprimirResumoCaixa ?? false) {
            $this->confirmImprimirResumoCaixa(false);

            return;
        }

        if ($this->activeModal !== null) {
            if ($this->activeModal === 'bloqueio' || $this->pdvBloqueado) {
                return;
            }

            if ($this->activeModal === 'acesso_negado') {
                $this->dismissPdvAcessoNegado();

                return;
            }

            if ($this->activeModal === 'excluir_item') {
                $this->cancelExcluirItemCupom();

                return;
            }

            if ($this->activeModal === 'finalizar') {
                if ($this->finalizarConfirmImprimir) {
                    $this->cancelFinalizarImprimir();

                    return;
                }

                if ($this->finalizarConfirmSair) {
                    $this->cancelCloseFinalizar();

                    return;
                }

                if ($this->finalizarClienteConsulta) {
                    $this->cancelFinalizarClienteConsulta();

                    return;
                }

                if ($this->finalizarCarneImpressaoAberta) {
                    $this->fecharCarneImpressao();

                    return;
                }

                if ($this->finalizarTabelaPrazoConsulta) {
                    $this->cancelFinalizarTabelaPrazoConsulta();

                    return;
                }

                if ($this->finalizarCartaoCanhotoAberta) {
                    $this->cancelFinalizarCartaoCanhoto();

                    return;
                }

                $this->requestCloseFinalizar();

                return;
            }

            match ($this->activeModal) {
                'grade' => $this->cancelPdvGrade(),
                'serial' => $this->cancelPdvSerial(),
                'busca_avancada' => $this->cancelBuscaAvancada(),
                'busca_preco' => $this->cancelBuscaPreco(),
                'importar_menu' => $this->cancelImportarMenu(),
                'importar_pedido' => $this->cancelImportar(),
                'importar' => $this->cancelImportar(),
                'receber' => $this->cancelReceber(),
                'reimprimir' => $this->cancelReimprimir(),
                'consulta_venda' => $this->cancelConsultaVenda(),
                'estorno_venda' => $this->cancelEstornoVenda(),
                'vendas_espera' => $this->cancelVendaEmEspera(),
                'fiscal_aviso' => $this->sairPdvFiscalOverlay(),
                'tabela_preco' => $this->cancelTabelaPreco(),
                'remover_itens' => $this->cancelRemoverItens(),
                'autorizacao' => $this->cancelPdvAutorizacao(),
                'bloqueio' => $this->cancelUnlockPdv(),
                'acesso_rapido' => $this->fecharAcessoRapido(),
                default => $this->closePdvModal(),
            };

            return;
        }

        // Fluxo normal (sem Caixa Rápido): Esc volta preço → qtd → código,
        // mesmo se o termo de busca já estiver vazio (não depende de pdvEmConsulta).
        if ($this->pdvLaunchStep === 'preco') {
            $this->pdvLaunchStep = 'qtd';
            $this->dispatch('erp-pdv-focus-launch', field: 'qtd');

            return;
        }

        if ($this->pdvLaunchStep === 'qtd') {
            $this->pdvLaunchStep = 'search';
            $this->dispatch('erp-pdv-focus-search');

            return;
        }

        if ($this->pdvEmConsulta) {
            $this->clearPdvSearch();

            return;
        }

        $this->openPdvModal('sair');
    }

    public function confirmSairPdv(): void
    {
        ErpScreen::set('Principal');

        $this->redirect(Dashboard::getUrl(), navigate: false);
    }

    public function toggleCaixa(): void
    {
        if ($this->caixaAberto) {
            $this->resetFechamentoForm();
            $this->openPdvModal('fechar_caixa');

            return;
        }

        if (! $this->garantirOperadorDoUsuarioLogado()) {
            return;
        }

        $this->aberturaForm['valor'] = '0,00';
        $this->openPdvModal('abrir_caixa');
    }

    public function openPersonOverlay(): void
    {
        if (! $this->caixaAberto) {
            Notification::make()
                ->title('Caixa fechado.')
                ->body('Abra o caixa com F2 antes de continuar.')
                ->warning()
                ->send();

            return;
        }

        $this->overlayPersonOpen = true;
        $this->dispatch('erp-pdv-overlay-opened', type: 'person');
    }

    public function closePersonOverlay(): void
    {
        if (! $this->overlayPersonOpen) {
            return;
        }

        $this->overlayPersonOpen = false;
        $this->dispatch('erp-pdv-overlay-closed');
    }

    public function modulePending(string $module): void
    {
        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();
    }

    public function moduleStubTef(): void
    {
        Notification::make()
            ->title('TEF')
            ->body('Integração TEF disponível apenas no PDV desktop. Em implementação no web.')
            ->info()
            ->send();
    }

    public function moduleStubNfce(): void
    {
        Notification::make()
            ->title('NFC-e')
            ->body('Use o fechamento fiscal (F4/F6) no PDV para emitir NFC-e.')
            ->info()
            ->send();
    }

    public function moduleStubMesa(string $acao): void
    {
        Notification::make()
            ->title($acao)
            ->body('Módulo restaurante/mesas disponível no PDV desktop. Em implementação no web.')
            ->info()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getPdvStatusBarProperty(): array
    {
        $user = Auth::user();
        $conta = trim((string) ($user?->defaultCaixaContaNome(ErpContext::currentEmpresaId()) ?? ''));
        $empresaNome = trim((string) (ErpContext::currentEmpresa()?->nome ?? ''));

        return [
            'empresa' => $empresaNome !== ''
                ? mb_strtoupper($empresaNome, 'UTF-8')
                : '—',
            'conta' => $conta !== '' ? $conta : 'CAIXA',
            'usuario' => $user?->name ?? 'USUARIO',
            'vendedor' => $this->vendedor,
            'tabela_preco' => $this->pdvTabelaPrecoLabel,
            'data_hora' => now()->format('d/m/Y H:i:s'),
            'tunel' => \App\Support\Erp\ErpSystemConfig::acessoRemotoHabilitado()
                ? CloudflaredStatus::forUi()
                : null,
        ];
    }
}
