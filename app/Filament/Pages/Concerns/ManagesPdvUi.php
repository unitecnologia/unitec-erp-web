<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\PersonResource;
use App\Filament\Resources\ProductResource;
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
    use ManagesPdvReceber;
    use ManagesPdvReimprimir;
    use ManagesPdvRemoverItens;
    use ManagesPdvSerial;
    use ManagesPdvTabelaPreco;
    use ManagesPdvVenda;
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

    public bool $overlayProductOpen = false;

    public bool $overlayPersonOpen = false;

    /** @var array<string, string> */
    public array $sangriaForm = [
        'historico' => '',
        'valor' => '0,00',
        'tipo_conta' => '',
        'destino' => 'SANGRIA P/ CAIXA GERAL',
    ];

    /** @var array<string, string> */
    public array $suprimentoForm = [
        'historico' => 'SUPRIMENTO DE CAIXA',
        'valor' => '0,00',
        'tipo_conta' => '',
    ];

    public string $vendedor = 'LOJA';

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
     * @return list<string>
     */
    public function getSangriaDestinoOptionsProperty(): array
    {
        return [
            'SANGRIA P/ CAIXA GERAL',
            'SANGRIA P/ BANCO',
            'SANGRIA P/ TESOURARIA',
        ];
    }

    public function getCaixaTituloProperty(): string
    {
        return $this->caixaAberto ? 'CAIXA ABERTO' : 'CAIXA FECHADO';
    }

    public function getProductOverlayUrlProperty(): string
    {
        return ProductResource::getUrl('create') . '?pdv=1';
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
        if (in_array($modal, ['resumo', 'sangria', 'suprimento', 'finalizar'], true)) {
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

        $this->activeModal = $modal;
        $this->dispatch('erp-pdv-modal-opened', modal: $modal);
    }

    public function closePdvModal(): void
    {
        if ($this->activeModal === 'finalizar') {
            $this->finalizarConfirmSair = false;
            $this->cancelFinalizarImprimir();
            $this->finalizarAba = 'totais';
            $this->limparFinalizarAlerta();
        }

        $this->activeModal = null;
    }

    public function handlePdvEscape(): void
    {
        if ($this->overlayProductOpen) {
            $this->closeProductOverlay();

            return;
        }

        if ($this->overlayPersonOpen) {
            $this->closePersonOverlay();

            return;
        }

        if ($this->activeModal !== null) {
            if ($this->activeModal === 'bloqueio' || $this->pdvBloqueado) {
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

                $this->requestCloseFinalizar();

                return;
            }

            match ($this->activeModal) {
                'grade' => $this->cancelPdvGrade(),
                'serial' => $this->cancelPdvSerial(),
                'busca_avancada' => $this->cancelBuscaAvancada(),
                'busca_preco' => $this->cancelBuscaPreco(),
                'importar' => $this->cancelImportar(),
                'receber' => $this->cancelReceber(),
                'reimprimir' => $this->cancelReimprimir(),
                'consulta_venda' => $this->cancelConsultaVenda(),
                'tabela_preco' => $this->cancelTabelaPreco(),
                'remover_itens' => $this->cancelRemoverItens(),
                'autorizacao' => $this->cancelPdvAutorizacao(),
                'bloqueio' => $this->cancelUnlockPdv(),
                default => $this->closePdvModal(),
            };

            return;
        }

        if ($this->pdvEmConsulta) {
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
            $this->openPdvModal('fechar_caixa');

            return;
        }

        $this->aberturaForm['valor'] = '0,00';
        $this->openPdvModal('abrir_caixa');
    }

    public function openProductOverlay(): void
    {
        $this->overlayProductOpen = true;
        $this->dispatch('erp-pdv-overlay-opened', type: 'product');
    }

    public function closeProductOverlay(): void
    {
        if (! $this->overlayProductOpen) {
            return;
        }

        $this->overlayProductOpen = false;
        $this->dispatch('erp-pdv-overlay-closed');
    }

    public function openPersonOverlay(): void
    {
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
            ->body('Emissão e reimpressão de NFC-e em implementação no web.')
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
        return [
            'conta' => 'CAIXA',
            'usuario' => Auth::user()?->name ?? 'USUARIO',
            'vendedor' => $this->vendedor,
            'tabela_preco' => $this->pdvTabelaPrecoLabel,
            'data_hora' => now()->format('d/m/Y H:i:s'),
        ];
    }
}
