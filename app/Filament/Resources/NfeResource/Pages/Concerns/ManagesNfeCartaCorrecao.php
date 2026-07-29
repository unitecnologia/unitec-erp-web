<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeCartaCorrecao;
use App\Support\Erp\Nfe\NfeCartaCorrecaoMotivo;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Fiscal\NfeCartaCorrecaoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

trait ManagesNfeCartaCorrecao
{
    public bool $nfeCceModalOpen = false;

    public ?int $nfeCceNfeId = null;

    public string $nfeCceCorrecao = '';

    public ?string $nfeCceSucessoDetalhe = null;

    public ?int $nfeCceSucessoCartaId = null;

    public function cartaCorrecaoNfe(): void
    {
        if ($this->nfeModalOpen && filled($this->nfeFiscalSucessoDetalhe)) {
            $this->showNfeFiscalOverlayInfo('Carta de Correção', 'Feche a tela de sucesso antes de emitir CC-e.');

            return;
        }

        $this->closeNfeCceSucessoOverlay();

        $nfeId = $this->resolveNfeCceTargetId();

        if (! $nfeId) {
            return;
        }

        $nfe = Nfe::query()->withCount('cartasCorrecao')->find($nfeId);

        if (! $nfe) {
            $this->notifyNfeCceWarning('NF-e não encontrada.');

            return;
        }

        if ($nfe->status === Nfe::STATUS_CANCELADA) {
            $this->notifyNfeCceWarning('NF-e cancelada não pode receber Carta de Correção.');

            return;
        }

        if ($nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            $this->notifyNfeCceWarning('Somente NF-e transmitida pode receber Carta de Correção.');

            return;
        }

        if ((int) $nfe->cartas_correcao_count >= NfeCartaCorrecaoMotivo::MAX_SEQUENCIA) {
            $this->notifyNfeCceWarning('Limite de 20 Cartas de Correção atingido para esta NF-e.');

            return;
        }

        $this->nfeCceNfeId = $nfe->id;
        $this->nfeCceCorrecao = NfeCartaCorrecaoMotivo::TEXTO_PADRAO;
        $this->nfeCceModalOpen = true;

        $this->dispatch('erp-nfe-focus-cce-modal');
    }

    public function confirmCartaCorrecaoNfe(): void
    {
        $nfe = $this->nfeCceNfeId
            ? Nfe::query()->find($this->nfeCceNfeId)
            : null;

        $empresa = $nfe?->empresa_id
            ? Empresa::query()->find($nfe->empresa_id)
            : $this->resolveNfeCceEmpresa();

        if (! $nfe || ! $empresa) {
            $this->closeNfeCceModal();
            $this->notifyNfeCceWarning('Não foi possível localizar os dados para Carta de Correção.');

            return;
        }

        try {
            $carta = (new NfeCartaCorrecaoService())->emitir(
                $nfe,
                $empresa,
                $this->nfeCceCorrecao,
            );
        } catch (FiscalEngineException $exception) {
            $this->notifyNfeCceFiscalError($exception);

            return;
        }

        $this->closeNfeCceModal();

        if ($this->nfeModalOpen && $this->nfeModalRecordId === $nfe->id) {
            $this->loadNfeIntoModal($nfe->fresh() ?? $nfe);
        }

        $this->resetTable();

        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;
        $detalhe = "Nota {$numero} | Série {$nfe->serie} | CC-e seq. {$carta->sequencia}";

        if (filled($carta->protocolo)) {
            $detalhe .= ' | Protocolo ' . $carta->protocolo;
        }

        $this->nfeCceSucessoDetalhe = $detalhe;
        $this->nfeCceSucessoCartaId = $carta->id;
        $this->dispatch('erp-nfe-focus-cce-sucesso');
    }

    public function closeNfeCceModal(): void
    {
        $this->nfeCceModalOpen = false;
        $this->nfeCceNfeId = null;
        $this->nfeCceCorrecao = '';
    }

    public function closeNfeCceSucessoOverlay(): void
    {
        $this->closeNfeCceDispatchModals();
        $this->nfeCceSucessoDetalhe = null;
        $this->nfeCceSucessoCartaId = null;
    }

    public function printNfeCartaCorrecao(): void
    {
        if (! $this->nfeCceSucessoCartaId) {
            return;
        }

        $url = route('erp.reports.nfe-carta-correcao', [
            'carta' => $this->nfeCceSucessoCartaId,
        ]);

        $this->js('window.ErpNfePrint?.openDanfe(' . Js::from($url) . ')');
    }

    #[Computed]
    public function nfeCceChaveFormatada(): string
    {
        if (! $this->nfeCceNfeId) {
            return '';
        }

        $nfe = Nfe::query()->find($this->nfeCceNfeId);
        $chave = preg_replace('/\D/', '', (string) ($nfe?->chave ?? '')) ?? '';

        if (strlen($chave) !== 44) {
            return (string) ($nfe?->chave ?? '');
        }

        return trim(chunk_split($chave, 4, ' '));
    }

    #[Computed]
    public function nfeCceNumeroDetalhe(): string
    {
        if (! $this->nfeCceNfeId) {
            return '';
        }

        $nfe = Nfe::query()->withCount('cartasCorrecao')->find($this->nfeCceNfeId);

        if (! $nfe) {
            return '';
        }

        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;
        $proxima = ((int) $nfe->cartas_correcao_count) + 1;

        return "Nota {$numero} | Série {$nfe->serie} | Próxima CC-e: {$proxima}";
    }

    protected function resolveNfeCceTargetId(): ?int
    {
        if ($this->nfeModalOpen && $this->nfeModalRecordId) {
            return $this->nfeModalRecordId;
        }

        return $this->highlightedRecordIdOrNotify('cce');
    }

    protected function resolveNfeCceEmpresa(): ?Empresa
    {
        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        return $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;
    }

    protected function notifyNfeCceWarning(string $message): void
    {
        if ($this->nfeModalOpen) {
            $this->showNfeFiscalOverlayInfo('Carta de Correção', $message);

            return;
        }

        Notification::make()
            ->title($message)
            ->warning()
            ->send();
    }

    protected function notifyNfeCceFiscalError(FiscalEngineException $exception): void
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

        $notification = Notification::make()
            ->title($resolvido['titulo'])
            ->danger();

        if ($resolvido['corpo'] !== null) {
            $notification->body($resolvido['corpo']);
        }

        $notification->send();
    }
}
