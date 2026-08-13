<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Filament\Resources\ProductResource;
use Filament\Notifications\Notification;

trait ManagesProductCardex
{
    public function openProductCardex(): void
    {
        if (method_exists($this, 'erpAuthorizeOrNotify') && ! $this->erpAuthorizeOrNotify('produtos.cardex')) {
            return;
        }

        if (method_exists($this, 'isSeriaisView') && $this->isSeriaisView()) {
            Notification::make()
                ->title('Histórico está disponível na aba Produtos.')
                ->warning()
                ->send();

            return;
        }

        $recordId = $this->resolveProductCardexRecordId();

        if (! $recordId) {
            if (method_exists($this, 'isEditingProduct') && ! $this->isEditingProduct()) {
                Notification::make()
                    ->title('Grave o produto antes de consultar o histórico.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $params = ['record' => $recordId];
        $query = [];

        if (method_exists($this, 'isEditingProduct') && $this->isEditingProduct()) {
            $query['return'] = 'edit';
        }

        $url = ProductResource::getUrl('cardex', $params);

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $this->redirect($url);
    }

    protected function resolveProductCardexRecordId(): ?int
    {
        if (method_exists($this, 'isEditingProduct') && $this->isEditingProduct()) {
            return $this->record?->getKey();
        }

        if (method_exists($this, 'highlightedRecordIdOrNotify')) {
            return $this->highlightedRecordIdOrNotify('history');
        }

        return null;
    }
}
