<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\VendasParametro;
use App\Support\Erp\Nfe\NfeInutilizacaoMotivo;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Fiscal\NfeInutilizacaoService;
use Filament\Notifications\Notification;
use Unitec\FiscalEngine\Dto\InutilizarNfeResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

trait ManagesNfeInutilizacao
{
    public bool $nfeInutilizarModalOpen = false;

    public string $nfeInutilizarSerie = '1';

    public string $nfeInutilizarNumeroIni = '';

    public string $nfeInutilizarNumeroFim = '';

    public string $nfeInutilizarJustificativa = '';

    public ?string $nfeInutilizarSucessoDetalhe = null;

    public function inutilizarNfe(): void
    {
        if ($this->nfeModalOpen && filled($this->nfeFiscalSucessoDetalhe)) {
            $this->showNfeFiscalOverlayInfo('Inutilizar', 'Feche a tela de sucesso antes de inutilizar numeração.');

            return;
        }

        $this->closeNfeInutilizarSucessoOverlay();

        $empresa = $this->resolveNfeInutilizarEmpresa();
        $parametros = $empresa
            ? VendasParametro::forEmpresa((int) $empresa->id)
            : null;
        $nfeSelecionada = $this->resolveNfeInutilizarSelecionada();

        $this->nfeInutilizarSerie = (string) (
            $nfeSelecionada?->serie
            ?? $parametros?->serie_nfe
            ?? 1
        );
        $this->nfeInutilizarNumeroIni = $nfeSelecionada
            ? $this->formatNfeNumeroParaInutilizacao($nfeSelecionada->numero)
            : '';
        $this->nfeInutilizarNumeroFim = '';
        $this->nfeInutilizarJustificativa = NfeInutilizacaoMotivo::TEXTO_PADRAO;
        $this->nfeInutilizarModalOpen = true;

        $this->dispatch('erp-nfe-focus-inutilizar-modal');
    }

    public function confirmInutilizarNfe(): void
    {
        $empresa = $this->resolveNfeInutilizarEmpresa();

        if (! $empresa) {
            $this->closeNfeInutilizarModal();
            $this->notifyNfeInutilizarWarning('Empresa não configurada para inutilização fiscal.');

            return;
        }

        $serie = (int) ltrim($this->nfeInutilizarSerie, '0') ?: 1;
        $numeroIni = (int) $this->nfeInutilizarNumeroIni;
        $numeroFim = (int) ($this->nfeInutilizarNumeroFim !== '' ? $this->nfeInutilizarNumeroFim : $this->nfeInutilizarNumeroIni);

        try {
            $service = new NfeInutilizacaoService();
            $response = $service->inutilizar(
                $empresa,
                $serie,
                $numeroIni,
                $numeroFim,
                $this->nfeInutilizarJustificativa,
            );
            $service->marcarNotasLocaisInutilizadas($empresa, $serie, $numeroIni, $numeroFim);
        } catch (FiscalEngineException $exception) {
            $this->notifyNfeInutilizarFiscalError($exception);

            return;
        }

        $this->closeNfeInutilizarModal();
        $this->clearListSelection();
        $this->setStatusFilter(Nfe::STATUS_INUTILIZADA);
        $this->showNfeInutilizarSucessoOverlay($response);
    }

    public function closeNfeInutilizarSucessoOverlay(): void
    {
        $this->nfeInutilizarSucessoDetalhe = null;
    }

    protected function showNfeInutilizarSucessoOverlay(InutilizarNfeResponse $response): void
    {
        $this->closeNfeFiscalOverlay();
        $this->closeNfeFiscalInfoOverlay();

        $this->nfeInutilizarSucessoDetalhe = NfeInutilizacaoMotivo::formatDetalheSucesso(
            $response->serie,
            $response->numeroInicial,
            $response->numeroFinal,
            $response->protocolo,
        );

        $this->dispatch('erp-nfe-focus-inutilizar-sucesso');
    }

    public function closeNfeInutilizarModal(): void
    {
        $this->nfeInutilizarModalOpen = false;
        $this->nfeInutilizarJustificativa = '';
    }

    protected function resolveNfeInutilizarEmpresa(): ?Empresa
    {
        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        return $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;
    }

    protected function resolveNfeInutilizarSelecionada(): ?Nfe
    {
        if (! $this->highlightedRecordId || $this->statusFilter !== Nfe::STATUS_ABERTA) {
            return null;
        }

        $nfe = Nfe::query()->find($this->highlightedRecordId);

        if (! $nfe || $nfe->status !== Nfe::STATUS_ABERTA) {
            return null;
        }

        return $nfe;
    }

    protected function formatNfeNumeroParaInutilizacao(mixed $numero): string
    {
        if ($numero === null || $numero === '') {
            return '';
        }

        $digits = preg_replace('/\D/', '', (string) $numero) ?? '';

        if ($digits === '') {
            return '';
        }

        $normalized = ltrim($digits, '0');

        return $normalized !== '' ? $normalized : '0';
    }

    protected function notifyNfeInutilizarWarning(string $message): void
    {
        if ($this->nfeModalOpen) {
            $this->showNfeFiscalOverlayInfo('Inutilizar', $message);

            return;
        }

        Notification::make()
            ->title($message)
            ->warning()
            ->send();
    }

    protected function notifyNfeInutilizarFiscalError(FiscalEngineException $exception): void
    {
        $resolvido = PdvNfceFiscalMensagens::resolver($exception);

        if ($this->nfeModalOpen) {
            $this->closeNfeFiscalOverlay();
            $this->closeNfeFiscalSucessoOverlay();
            $this->closeNfeFiscalInfoOverlay();

            $this->nfeFiscalOverlayTitulo = mb_strtoupper($resolvido['titulo'], 'UTF-8');
            $this->nfeFiscalOverlayMensagem = $resolvido['corpo'] ?? trim($exception->getMessage());
            $this->nfeFiscalOverlayCodigo = $exception->sefazCodigo;
            $this->dispatch('erp-nfe-focus-fiscal-overlay');

            return;
        }

        $this->closeNfeInutilizarSucessoOverlay();
        $this->closeNfeFiscalInfoOverlay();

        $this->nfeFiscalOverlayTitulo = mb_strtoupper($resolvido['titulo'], 'UTF-8');
        $this->nfeFiscalOverlayMensagem = $resolvido['corpo'] ?? trim($exception->getMessage());
        $this->nfeFiscalOverlayCodigo = $exception->sefazCodigo;
        $this->dispatch('erp-nfe-focus-fiscal-overlay');
    }
}
