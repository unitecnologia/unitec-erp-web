<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Fiscal\NfeCancelamentoService;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

trait ManagesNfeCancelamento
{
    public bool $nfeCancelModalOpen = false;

    public ?int $nfeCancelNfeId = null;

    public string $nfeCancelJustificativa = '';

    public function cancelarNfe(): void
    {
        if ($this->nfeModalOpen && filled($this->nfeFiscalSucessoDetalhe)) {
            $this->showNfeFiscalOverlayInfo('Cancelar NF-e', 'Feche a tela de sucesso antes de cancelar.');

            return;
        }

        $nfeId = $this->resolveNfeCancelTargetId();

        if (! $nfeId) {
            return;
        }

        $nfe = Nfe::query()->find($nfeId);

        if (! $nfe) {
            $this->notifyNfeCancelWarning('NF-e não encontrada.');

            return;
        }

        if ($nfe->status === Nfe::STATUS_CANCELADA) {
            $this->notifyNfeCancelWarning('Esta NF-e já está cancelada.');

            return;
        }

        if ($nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            $this->notifyNfeCancelWarning('Somente NF-e transmitida pode ser cancelada.');

            return;
        }

        $this->nfeCancelNfeId = $nfe->id;
        $this->nfeCancelJustificativa = PdvEstornoMotivo::MOTIVO_AUTOMATICO;
        $this->nfeCancelModalOpen = true;

        $this->dispatch('erp-nfe-focus-cancel-modal');
    }

    public function confirmCancelarNfe(): void
    {
        $nfe = $this->nfeCancelNfeId
            ? Nfe::query()->find($this->nfeCancelNfeId)
            : null;

        $empresa = $nfe?->empresa_id
            ? Empresa::query()->find($nfe->empresa_id)
            : $this->resolveNfeCancelEmpresa();

        if (! $nfe || ! $empresa) {
            $this->closeNfeCancelModal();
            $this->notifyNfeCancelWarning('Não foi possível localizar os dados para cancelamento.');

            return;
        }

        try {
            $nfe = (new NfeCancelamentoService())->cancelar(
                $nfe,
                $empresa,
                $this->nfeCancelJustificativa,
            );
        } catch (FiscalEngineException $exception) {
            $this->notifyNfeCancelFiscalError($exception);

            return;
        }

        $this->closeNfeCancelModal();

        if ($this->nfeModalOpen && $this->nfeModalRecordId === $nfe->id) {
            $this->loadNfeIntoModal($nfe->fresh() ?? $nfe);
        }

        $this->resetTable();

        $body = filled($nfe->protocolo_cancelamento)
            ? 'Protocolo: ' . $nfe->protocolo_cancelamento
            : 'Cancelamento registrado.';

        Notification::make()
            ->title('NF-e cancelada com sucesso.')
            ->body($body)
            ->success()
            ->send();
    }

    public function closeNfeCancelModal(): void
    {
        $this->nfeCancelModalOpen = false;
        $this->nfeCancelNfeId = null;
        $this->nfeCancelJustificativa = '';
    }

    #[Computed]
    public function nfeCancelChaveFormatada(): string
    {
        if (! $this->nfeCancelNfeId) {
            return '';
        }

        $nfe = Nfe::query()->find($this->nfeCancelNfeId);
        $chave = preg_replace('/\D/', '', (string) ($nfe?->chave ?? '')) ?? '';

        if (strlen($chave) !== 44) {
            return (string) ($nfe?->chave ?? '');
        }

        return trim(chunk_split($chave, 4, ' '));
    }

    #[Computed]
    public function nfeCancelNumeroDetalhe(): string
    {
        if (! $this->nfeCancelNfeId) {
            return '';
        }

        $nfe = Nfe::query()->find($this->nfeCancelNfeId);

        if (! $nfe) {
            return '';
        }

        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;

        return "Nota {$numero} | Série {$nfe->serie}";
    }

    protected function resolveNfeCancelTargetId(): ?int
    {
        if ($this->nfeModalOpen && $this->nfeModalRecordId) {
            return $this->nfeModalRecordId;
        }

        return $this->highlightedRecordIdOrNotify('cancelar');
    }

    protected function resolveNfeCancelEmpresa(): ?Empresa
    {
        $empresaId = \App\Support\Erp\ErpContext::currentEmpresaId();

        return $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;
    }

    protected function notifyNfeCancelWarning(string $message): void
    {
        if ($this->nfeModalOpen) {
            $this->showNfeFiscalOverlayInfo('Cancelar NF-e', $message);

            return;
        }

        Notification::make()
            ->title($message)
            ->warning()
            ->send();
    }

    protected function notifyNfeCancelFiscalError(FiscalEngineException $exception): void
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
