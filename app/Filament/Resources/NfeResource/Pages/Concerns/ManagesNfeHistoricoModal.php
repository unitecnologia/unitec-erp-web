<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Nfe;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfe\NfeHistoricoService;
use Filament\Notifications\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ManagesNfeHistoricoModal
{
    public bool $nfeHistoricoModalOpen = false;

    public ?int $nfeHistoricoNfeId = null;

    /** @var array<string, mixed> */
    public array $nfeHistoricoData = [];

    public function openNfeHistorico(int $nfeId): void
    {
        if (! $this->nfeHistoricoExisteNoEscopo($nfeId)) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $dados = (new NfeHistoricoService())->montar($nfeId);

        if ($dados === null) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->danger()
                ->send();

            return;
        }

        $this->nfeHistoricoNfeId = $nfeId;
        $this->nfeHistoricoData = $dados;
        $this->nfeHistoricoModalOpen = true;
        $this->dispatch('erp-nfe-focus-historico-modal');
    }

    public function closeNfeHistorico(): void
    {
        $this->nfeHistoricoModalOpen = false;
        $this->nfeHistoricoNfeId = null;
        $this->nfeHistoricoData = [];
    }

    public function baixarXmlNfeHistorico(): ?StreamedResponse
    {
        if (method_exists($this, 'erpAuthorizeOrNotify') && ! $this->erpAuthorizeOrNotify('nfe.access')) {
            return null;
        }

        $nfeId = (int) ($this->nfeHistoricoNfeId ?? 0);
        if ($nfeId <= 0) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->danger()
                ->send();

            return null;
        }

        $empresaId = ErpContext::currentEmpresaId();
        $nfe = Nfe::query()
            ->select(['id', 'empresa_id', 'chave', 'xml'])
            ->whereKey($nfeId)
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->first();

        if ($nfe === null) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->danger()
                ->send();

            return null;
        }

        $xml = trim((string) ($nfe->xml ?? ''));
        $chave = preg_replace('/\D/', '', (string) ($nfe->chave ?? '')) ?? '';

        if ($xml === '' || $chave === '') {
            Notification::make()
                ->title('XML da NF-e não disponível.')
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

    private function nfeHistoricoExisteNoEscopo(int $nfeId): bool
    {
        $empresaId = ErpContext::currentEmpresaId();

        return Nfe::query()
            ->whereKey($nfeId)
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->exists();
    }
}
