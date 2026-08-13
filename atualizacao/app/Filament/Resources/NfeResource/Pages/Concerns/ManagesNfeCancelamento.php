<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Support\Erp\Nfe\NfeInutilizacaoMotivo;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Fiscal\NfeCancelamentoService;
use App\Support\Fiscal\NfeInutilizacaoService;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

trait ManagesNfeCancelamento
{
    public bool $nfeCancelModalOpen = false;

    public ?int $nfeCancelNfeId = null;

    public string $nfeCancelJustificativa = '';

    public bool $nfeCancelAbertaModalOpen = false;

    public ?int $nfeCancelAbertaNfeId = null;

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

        if ($nfe->status === Nfe::STATUS_INUTILIZADA) {
            $this->notifyNfeCancelWarning('Esta NF-e já está inutilizada.');

            return;
        }

        if ($nfe->status === Nfe::STATUS_ABERTA) {
            $this->abrirCancelamentoNotaAberta($nfe);

            return;
        }

        if ($nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            $this->notifyNfeCancelWarning('Somente NF-e aberta ou transmitida pode ser cancelada.');

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
            ? 'Protocolo: '.$nfe->protocolo_cancelamento
            : 'Cancelamento registrado.';

        Notification::make()
            ->title('NF-e cancelada com sucesso.')
            ->body($body)
            ->success()
            ->send();
    }

    public function confirmCancelarNfeAberta(): void
    {
        $nfe = $this->nfeCancelAbertaNfeId
            ? Nfe::query()->find($this->nfeCancelAbertaNfeId)
            : null;

        $empresa = $nfe?->empresa_id
            ? Empresa::query()->find($nfe->empresa_id)
            : $this->resolveNfeCancelEmpresa();

        if (! $nfe || ! $empresa) {
            $this->closeNfeCancelAbertaModal();
            $this->notifyNfeCancelWarning('Não foi possível localizar a NF-e aberta.');

            return;
        }

        if ($nfe->status !== Nfe::STATUS_ABERTA) {
            $this->closeNfeCancelAbertaModal();
            $this->notifyNfeCancelWarning('A nota não está mais aberta.');

            return;
        }

        $serie = (int) (ltrim((string) ($nfe->serie ?? '1'), '0') ?: 1);
        $numero = (int) (ltrim(preg_replace('/\D/', '', (string) $nfe->numero) ?? '', '0') ?: '0');

        if ($numero < 1) {
            $this->closeNfeCancelAbertaModal();
            $this->notifyNfeCancelWarning('NF-e sem número válido para inutilizar.');

            return;
        }

        $service = new NfeInutilizacaoService();

        try {
            $response = $service->inutilizar(
                $empresa,
                $serie,
                $numero,
                $numero,
                NfeInutilizacaoMotivo::TEXTO_PADRAO,
            );
        } catch (FiscalEngineException $exception) {
            $this->closeNfeCancelAbertaModal();
            $this->notifyNfeCancelFiscalError($exception);

            return;
        }

        $this->zerarItensNfeAberta($nfe);
        $service->marcarNotasLocaisInutilizadas($empresa, $serie, $numero, $numero);

        $this->closeNfeCancelAbertaModal();

        if ($this->nfeModalOpen && $this->nfeModalRecordId === $nfe->id) {
            $this->closeNfeModal();
        }

        $this->clearListSelection();
        $this->setStatusFilter(Nfe::STATUS_INUTILIZADA);
        $this->resetTable();

        if (method_exists($this, 'showNfeInutilizarSucessoOverlay')) {
            $this->showNfeInutilizarSucessoOverlay($response);
        } else {
            Notification::make()
                ->title('Nota aberta inutilizada.')
                ->body('Itens zerados e numeração inutilizada na SEFAZ.')
                ->success()
                ->send();
        }
    }

    public function closeNfeCancelModal(): void
    {
        $this->nfeCancelModalOpen = false;
        $this->nfeCancelNfeId = null;
        $this->nfeCancelJustificativa = '';
    }

    public function closeNfeCancelAbertaModal(): void
    {
        $this->nfeCancelAbertaModalOpen = false;
        $this->nfeCancelAbertaNfeId = null;
    }

    public function handleNfeCancelAbertaEscape(): void
    {
        $this->closeNfeCancelAbertaModal();
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

    #[Computed]
    public function nfeCancelAbertaNumeroDetalhe(): string
    {
        if (! $this->nfeCancelAbertaNfeId) {
            return '';
        }

        $nfe = Nfe::query()->find($this->nfeCancelAbertaNfeId);

        if (! $nfe) {
            return '';
        }

        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;

        return "Nota {$numero} | Série {$nfe->serie}";
    }

    protected function abrirCancelamentoNotaAberta(Nfe $nfe): void
    {
        $numero = (int) (ltrim(preg_replace('/\D/', '', (string) $nfe->numero) ?? '', '0') ?: '0');

        if ($numero < 1) {
            $this->notifyNfeCancelWarning('NF-e aberta sem número válido para inutilizar.');

            return;
        }

        $this->nfeCancelAbertaNfeId = $nfe->id;
        $this->nfeCancelAbertaModalOpen = true;
        $this->dispatch('erp-nfe-focus-cancel-aberta');
    }

    protected function zerarItensNfeAberta(Nfe $nfe): void
    {
        $nfe->itens()->delete();

        if (method_exists($nfe, 'faturas')) {
            $nfe->faturas()->delete();
        }

        if (method_exists($nfe, 'referencias')) {
            $nfe->referencias()->delete();
        }

        $nfe->update([
            'subtotal' => 0,
            'desconto' => 0,
            'frete' => 0,
            'seguro' => 0,
            'despesas' => 0,
            'outros' => 0,
            'troco' => 0,
            'total' => 0,
            'base_icms' => 0,
            'total_icms' => 0,
            'base_icms_st' => 0,
            'valor_icms_st' => 0,
            'base_ipi' => 0,
            'total_ipi' => 0,
            'base_icms_pis' => 0,
            'total_icms_pis' => 0,
            'base_icms_cofins' => 0,
            'total_icms_cofins' => 0,
            'total_desoneracao' => 0,
            'cliente_id' => null,
            'obs_fisco' => null,
            'obs_contribuinte' => null,
        ]);
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
