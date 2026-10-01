<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseNaoGravada;
use Filament\Notifications\Notification;
use Illuminate\Support\Js;

trait ManagesNfseEspelhoModal
{
    public bool $nfseEspelhoModalOpen = false;

    public ?int $nfseEspelhoModalId = null;

    public function openNfseEspelhoFromModal(): void
    {
        if (! $this->nfseModalOpen) {
            return;
        }

        if ($this->nfseImportOsOpen || $this->nfseImportOsConfirmOpen || $this->nfseDanfseModalOpen) {
            return;
        }

        if ($this->nfseStatus !== Nfse::STATUS_ABERTA) {
            Notification::make()
                ->title('Espelho disponível apenas para NFS-e aberta.')
                ->warning()
                ->send();

            return;
        }

        if ($this->nfseServicos === []) {
            Notification::make()
                ->title('Inclua ao menos um serviço antes de visualizar o espelho.')
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
                ->title('Não foi possível gravar a NFS-e para o espelho.')
                ->danger()
                ->send();

            return;
        }

        $this->aplicarNfseGravada($nfse);
        unset($this->nfseRegistros, $this->nfseListagemTotalFormatado);

        $this->nfseEspelhoModalId = (int) $nfse->id;
        $this->nfseEspelhoModalOpen = true;
    }

    public function closeNfseEspelhoModal(): void
    {
        $this->nfseEspelhoModalOpen = false;
        $this->nfseEspelhoModalId = null;
    }

    public function downloadNfseEspelhoPdf(): void
    {
        if (! $this->nfseEspelhoModalId) {
            return;
        }

        $url = route('erp.reports.nfse-espelho', [
            'nfse' => $this->nfseEspelhoModalId,
            'pdf' => 1,
        ]);

        $this->js('window.open('.Js::from($url).', "_blank")');
    }

    public function printNfseEspelhoDocument(): void
    {
        if (! $this->nfseEspelhoModalId) {
            return;
        }

        $this->js(<<<'JS'
            (() => {
                const frame = document.querySelector('[data-erp-nfse-espelho-frame]');
                if (! frame) return;
                try {
                    frame.contentWindow?.focus();
                    frame.contentWindow?.print();
                } catch (e) {}
            })()
        JS);
    }
}
