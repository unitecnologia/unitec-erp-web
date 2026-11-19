<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Support\Erp\Nfce\NfceHistoricoService;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ManagesNfceHistoricoModal
{
    public bool $nfceHistoricoModalOpen = false;

    public ?int $nfceHistoricoNfceId = null;

    /** @var array<string, mixed> */
    public array $nfceHistoricoData = [];

    #[On('erp-nfce-open-historico')]
    public function openNfceHistorico(int $id): void
    {
        $record = $this->findNfceNoEscopo($id);
        if ($record === null) {
            Notification::make()
                ->title('NFC-e não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $dados = (new NfceHistoricoService())->montar((int) $record->getKey());
        if ($dados === null) {
            Notification::make()
                ->title('Não foi possível carregar a NFC-e.')
                ->danger()
                ->send();

            return;
        }

        $this->highlightedRecordId = (int) $record->getKey();
        $this->nfceHistoricoNfceId = (int) $record->getKey();
        $this->nfceHistoricoData = $dados;
        $this->nfceHistoricoModalOpen = true;
    }

    public function closeNfceHistorico(): void
    {
        $this->nfceHistoricoModalOpen = false;
        $this->nfceHistoricoNfceId = null;
        $this->nfceHistoricoData = [];
    }

    public function baixarXmlNfceHistorico(): ?StreamedResponse
    {
        if (! $this->nfcePodeExecutar()) {
            return null;
        }

        $nfceId = (int) ($this->nfceHistoricoNfceId ?? 0);
        $nfce = $nfceId > 0 ? $this->findNfceNoEscopo($nfceId) : null;

        if ($nfce === null) {
            Notification::make()
                ->title('NFC-e não encontrada.')
                ->danger()
                ->send();

            return null;
        }

        $xml = trim((string) ($nfce->xml ?? ''));
        $chave = preg_replace('/\D/', '', (string) ($nfce->chave ?? '')) ?? '';

        if ($xml === '' || $chave === '') {
            Notification::make()
                ->title('XML da NFC-e não disponível.')
                ->body('Esta nota ainda não possui XML autorizado armazenado para download.')
                ->warning()
                ->send();

            return null;
        }

        $nome = $chave.'.xml';

        return response()->streamDownload(function () use ($xml): void {
            echo $xml;
        }, $nome, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
