<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Nfe;
use App\Support\Erp\Nfe\NfeDuplicacaoService;
use DomainException;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use RuntimeException;
use Throwable;

trait ManagesNfeDuplicacao
{
    public bool $nfeDuplicarModalOpen = false;

    public ?int $nfeDuplicarOrigemId = null;

    public function duplicarNfe(): void
    {
        if (method_exists($this, 'erpAuthorizeOrNotify') && ! $this->erpAuthorizeOrNotify('nfe.emit')) {
            return;
        }

        if ($this->nfeModalOpen) {
            Notification::make()
                ->title('Feche o lançamento da NF-e antes de duplicar.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('duplicar')) {
            return;
        }

        $origem = Nfe::query()->find($this->highlightedRecordId);

        if (! $origem) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = method_exists($this, 'resolveEmpresaId')
            ? (int) ($this->resolveEmpresaId() ?: 0)
            : 0;

        if ($empresaId <= 0 || (int) $origem->empresa_id !== $empresaId) {
            Notification::make()
                ->title('Só é permitido duplicar NF-e da empresa logada.')
                ->warning()
                ->send();

            return;
        }

        $permitidas = [
            Nfe::STATUS_ABERTA,
            Nfe::STATUS_TRANSMITIDA,
            Nfe::STATUS_CANCELADA,
        ];

        if (! in_array((string) $origem->status, $permitidas, true)) {
            $label = Nfe::statusLabels()[$origem->status] ?? $origem->status;
            Notification::make()
                ->title('Não é permitido duplicar NF-e com situação '.$label.'.')
                ->warning()
                ->send();

            return;
        }

        $this->nfeDuplicarOrigemId = (int) $origem->id;
        $this->nfeDuplicarModalOpen = true;
    }

    public function confirmDuplicarNfe(): void
    {
        if (method_exists($this, 'erpAuthorizeOrNotify') && ! $this->erpAuthorizeOrNotify('nfe.emit')) {
            $this->closeNfeDuplicarModal();

            return;
        }

        $origemId = (int) ($this->nfeDuplicarOrigemId ?: 0);
        $this->closeNfeDuplicarModal();

        if ($origemId <= 0) {
            Notification::make()
                ->title('Selecione uma NF-e para duplicar.')
                ->warning()
                ->send();

            return;
        }

        $origem = Nfe::query()
            ->with(['itens', 'faturas', 'cliente', 'transportadora', 'empresa'])
            ->find($origemId);

        if (! $origem) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = method_exists($this, 'resolveEmpresaId')
            ? (int) ($this->resolveEmpresaId() ?: 0)
            : 0;

        try {
            $nova = app(NfeDuplicacaoService::class)->duplicar($origem, $empresaId);
        } catch (DomainException|RuntimeException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            return;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Falha ao duplicar a NF-e.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('NF-e duplicada (nº '.$nova->numero.').')
            ->success()
            ->send();

        if (method_exists($this, 'clearListSelection')) {
            $this->clearListSelection();
        }
        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
        if (method_exists($this, 'highlightRecord')) {
            $this->highlightRecord((int) $nova->id);
        }

        $nova->loadMissing(['cliente', 'transportadora', 'itens.product', 'faturas', 'referencias', 'empresa', 'venda']);
        $this->loadNfeIntoModal($nova);
    }

    public function closeNfeDuplicarModal(): void
    {
        $this->nfeDuplicarModalOpen = false;
        $this->nfeDuplicarOrigemId = null;
    }

    #[Computed]
    public function nfeDuplicarNumeroDetalhe(): string
    {
        if (! $this->nfeDuplicarOrigemId) {
            return '';
        }

        $nfe = Nfe::query()->find($this->nfeDuplicarOrigemId);
        if (! $nfe) {
            return '';
        }

        $status = Nfe::statusLabels()[$nfe->status] ?? (string) $nfe->status;

        return 'NF-e '.$nfe->numero.' / série '.(string) $nfe->serie.' · '.$status;
    }
}
