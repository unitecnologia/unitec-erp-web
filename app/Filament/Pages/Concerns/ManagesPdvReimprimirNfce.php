<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVenda;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Pdv\PdvNfceReimpressao;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesPdvReimprimirNfce
{
    public string $reimprimirNfceSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $reimprimirNfceResults = [];

    public ?int $selectedReimprimirNfceIndex = null;

    public function openReimprimirNfceModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if (! $this->caixaSessaoId) {
            $this->notifyPdvError('Não há NFC-e disponível para reimpressão.');

            return;
        }

        $this->reimprimirNfceSearch = '';
        $this->refreshReimprimirNfceResults();

        if ($this->reimprimirNfceResults === []) {
            Notification::make()
                ->title('Não há NFC-e disponível para reimpressão.')
                ->body('Só é possível reimprimir NFC-e autorizada ou em contingência válida deste caixa.')
                ->warning()
                ->send();

            return;
        }

        $this->openPdvModal('reimprimir_nfce');
        $this->dispatch('erp-pdv-focus-reimprimir-nfce');
    }

    public function updatedReimprimirNfceSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->reimprimirNfceSearch !== $upper) {
            $this->reimprimirNfceSearch = $upper;
        }

        $this->refreshReimprimirNfceResults();
    }

    public function refreshReimprimirNfceResults(): void
    {
        $sessaoId = (int) ($this->caixaSessaoId ?? 0);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);

        if ($sessaoId <= 0 || $empresaId <= 0) {
            $this->reimprimirNfceResults = [];
            $this->selectedReimprimirNfceIndex = null;

            return;
        }

        $this->reimprimirNfceResults = PdvNfceReimpressao::listarParaModal(
            $sessaoId,
            $empresaId,
            trim($this->reimprimirNfceSearch),
        );
        $this->selectedReimprimirNfceIndex = $this->reimprimirNfceResults === [] ? null : 0;
    }

    public function selectReimprimirNfceRow(int $index): void
    {
        if (! isset($this->reimprimirNfceResults[$index])) {
            return;
        }

        $this->selectedReimprimirNfceIndex = $index;
    }

    public function moveReimprimirNfceSelection(int $delta): void
    {
        if ($this->reimprimirNfceResults === []) {
            return;
        }

        $count = count($this->reimprimirNfceResults);
        $index = ($this->selectedReimprimirNfceIndex ?? 0) + $delta;
        $this->selectedReimprimirNfceIndex = max(0, min($count - 1, $index));
    }

    public function confirmReimprimirNfce(): void
    {
        $index = $this->selectedReimprimirNfceIndex;

        if ($index === null || ! isset($this->reimprimirNfceResults[$index])) {
            $this->notifyPdvError('Selecione uma NFC-e.');

            return;
        }

        $vendaId = (int) ($this->reimprimirNfceResults[$index]['venda_id'] ?? 0);
        $this->reimprimirNfceDaVenda($vendaId);
    }

    public function cancelReimprimirNfce(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function reimprimirNfceDaVenda(int $vendaId): void
    {
        if ($vendaId <= 0) {
            $this->notifyPdvError('Não há NFC-e disponível para reimpressão.');

            return;
        }

        $sessaoId = (int) ($this->caixaSessaoId ?? 0);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);

        $venda = PdvVenda::query()
            ->with('nfce')
            ->whereKey($vendaId)
            ->where('pdv_caixa_sessao_id', $sessaoId)
            ->where('situacao', '!=', 'C')
            ->first();

        $nfce = $venda?->nfce;

        if ($venda === null || $nfce === null || (int) $nfce->empresa_id !== $empresaId) {
            $this->notifyPdvError('Não há NFC-e disponível para reimpressão.');

            return;
        }

        if (! PdvNfceReimpressao::podeReimprimir($nfce)) {
            $this->notifyPdvError(
                'Não há NFC-e disponível para reimpressão.',
                PdvNfceReimpressao::motivoIndisponivel($nfce),
            );

            return;
        }

        // Somente reabre o DANFE já gravado — sem emitir/retransmitir/alterar venda.
        $this->imprimirNfceCupomPosVenda($vendaId, $this->pdvConfig()->viasImpressao());
        $this->closePdvModal();

        Notification::make()
            ->title('Reimpressão da NFC-e enviada.')
            ->success()
            ->send();

        $this->dispatch('erp-pdv-focus-search');
    }
}
