<?php

namespace App\Filament\Pages\Concerns;

use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvItemValidator;
use App\Support\Erp\Pdv\PdvNfceCupomPrinter;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Erp\Pdv\PdvProductPriceService;
use App\Support\Erp\Pdv\PdvScaleBarcodeService;
use Filament\Notifications\Notification;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

trait ManagesPdvConfig
{
    protected ?PdvConfig $pdvConfigCache = null;

    public string $pdvFiscalOverlayTipo = '';

    public string $pdvFiscalOverlayTitulo = '';

    public string $pdvFiscalOverlayMensagem = '';

    public string $pdvFiscalOverlayDetalhe = '';

    public ?int $pdvFiscalOverlayVendaId = null;

    protected function pdvConfig(): PdvConfig
    {
        return $this->pdvConfigCache ??= PdvConfig::make();
    }

    protected function pdvPriceService(): PdvProductPriceService
    {
        return new PdvProductPriceService($this->pdvConfig());
    }

    protected function pdvScaleService(): PdvScaleBarcodeService
    {
        return new PdvScaleBarcodeService($this->pdvConfig(), $this->pdvPriceService());
    }

    protected function pdvItemValidator(): PdvItemValidator
    {
        return new PdvItemValidator($this->pdvConfig(), $this->pdvPriceService());
    }

    public function getPdvCaixaRapidoProperty(): bool
    {
        return $this->pdvConfig()->caixaRapido();
    }

    public function getPdvMarqueeTextoProperty(): string
    {
        return $this->pdvConfig()->marqueeTexto();
    }

    public function getPdvExibirResumoCaixaProperty(): bool
    {
        return $this->pdvConfig()->exibirResumoCaixa();
    }

    public function getPdvExibirF3VendedorProperty(): bool
    {
        return $this->pdvConfig()->exibirF3Vendedor();
    }

    public function getPdvPermitirDescontoItemProperty(): bool
    {
        return $this->pdvConfig()->permitirDescontoItem();
    }

    public function getPdvSomAtivoProperty(): bool
    {
        return $this->pdvConfig()->somAtivo();
    }

    public function getPdvExibeMesasProperty(): bool
    {
        return $this->pdvConfig()->exibeMesas();
    }

    public function getPdvLerPesoBalancaProperty(): bool
    {
        return $this->pdvConfig()->lerPesoBalanca();
    }

    public function getPdvBuscaBalancaBarrasProperty(): bool
    {
        return $this->pdvConfig()->buscaBalancaBarras();
    }

    public function getPdvUsaTefProperty(): bool
    {
        return $this->pdvConfig()->usaTef();
    }

    /**
     * @return list<array{key: string, atalho: string, label: string, fiscal: bool, primary: bool}>
     */
    public function getPdvFinalizarOperacaoBotoesProperty(): array
    {
        return $this->pdvConfig()->finalizarOperacaoBotoes();
    }

    public function getPdvFinalizarOperacaoUnicaProperty(): ?string
    {
        return $this->pdvConfig()->finalizarOperacaoUnica();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function pdvMovimentoPayload(string $tipo, array $payload): array
    {
        $plano = $this->pdvConfig()->planoContaCodigo($tipo);

        if ($plano) {
            $payload['plano_conta_codigo'] = $plano;
        }

        return $payload;
    }

    /**
     * @return array{term: string, quantidade: float|null}
     */
    protected function parsePdvSearchTerm(string $raw): array
    {
        $raw = trim($raw);
        $pos = strpos($raw, '*');

        if ($pos === false || $pos === 0) {
            return ['term' => $raw, 'quantidade' => null];
        }

        $qtyPart = substr($raw, 0, $pos);
        $term = trim(substr($raw, $pos + 1));

        if ($term === '') {
            return ['term' => $raw, 'quantidade' => null];
        }

        $quantidade = ErpMoney::parseBr($qtyPart, 3);

        return [
            'term' => $term,
            'quantidade' => $quantidade > 0 ? $quantidade : null,
        ];
    }

    protected function isNumericPdvTerm(string $term): bool
    {
        return $term !== '' && preg_match('/^\d+$/', $term) === 1;
    }

    protected function notifyPdvError(string $title, ?string $body = null): void
    {
        if (($this->activeModal ?? null) === 'finalizar') {
            $this->finalizarAlertaTitulo = mb_strtoupper(rtrim($title, '.'), 'UTF-8');
            $this->finalizarAlertaDetalhe = $body;
            $this->dispatch('erp-pdv-hide-fiscal-progress');
            $this->dispatch('erp-pdv-erro-beep');

            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->send();
    }

    protected function notifyPdvFiscalError(FiscalEngineException $exception): void
    {
        $resolvido = PdvNfceFiscalMensagens::resolver($exception);

        if ($resolvido['modal']) {
            $this->showPdvFiscalOverlayErro(
                $resolvido['titulo'],
                (string) ($resolvido['corpo'] ?? ''),
            );
            $this->dispatch('erp-pdv-erro-beep');

            return;
        }

        $this->notifyPdvError($resolvido['titulo'], $resolvido['corpo']);
    }

    protected function showPdvFiscalOverlayErro(string $titulo, string $mensagem): void
    {
        $this->clearPdvFiscalOverlay();
        $this->consultaVendaMotivoEstorno = '';
        $this->consultaVendaEstornoId = null;
        $this->consultaVendaEstornoNumero = null;
        $this->closePdvModal();

        $this->pdvFiscalOverlayTipo = 'erro';
        $this->pdvFiscalOverlayTitulo = mb_strtoupper($titulo, 'UTF-8');
        $this->pdvFiscalOverlayMensagem = $mensagem;
        $this->dispatch('erp-pdv-focus-fiscal-overlay');
    }

    protected function showPdvFiscalOverlaySucessoCancelamento(int $vendaId, string $numeroVenda, string $protocolo): void
    {
        $this->clearPdvFiscalOverlay();
        $this->consultaVendaMotivoEstorno = '';
        $this->consultaVendaEstornoId = null;
        $this->consultaVendaEstornoNumero = null;
        $this->closePdvModal();

        $this->pdvFiscalOverlayTipo = 'sucesso';
        $this->pdvFiscalOverlayTitulo = 'NFC-E CANCELADA COM SUCESSO';
        $this->pdvFiscalOverlayDetalhe = "Venda #{$numeroVenda} — Protocolo:\n{$protocolo}";
        $this->pdvFiscalOverlayVendaId = $vendaId;
        $this->dispatch('erp-pdv-focus-fiscal-overlay');
    }

    public function imprimirProtocoloCancelamentoNfce(): void
    {
        $vendaId = (int) ($this->pdvFiscalOverlayVendaId ?? 0);

        if ($vendaId <= 0) {
            return;
        }

        $this->js(PdvNfceCupomPrinter::livewireOpenProtocoloCancelamentoJs($vendaId));
    }

    public function sairPdvFiscalOverlay(): void
    {
        $this->clearPdvFiscalOverlay();
        $this->consultaVendaMotivoEstorno = '';
        $this->consultaVendaEstornoId = null;
        $this->consultaVendaEstornoNumero = null;
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function clearPdvFiscalOverlay(): void
    {
        $this->pdvFiscalOverlayTipo = '';
        $this->pdvFiscalOverlayTitulo = '';
        $this->pdvFiscalOverlayMensagem = '';
        $this->pdvFiscalOverlayDetalhe = '';
        $this->pdvFiscalOverlayVendaId = null;
    }
}
