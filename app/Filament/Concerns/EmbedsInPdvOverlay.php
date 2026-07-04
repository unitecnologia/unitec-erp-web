<?php

namespace App\Filament\Concerns;

trait EmbedsInPdvOverlay
{
    public bool $embedsInPdv = false;

    public bool $embedsInOrcamento = false;

    public function mountEmbedsInPdvOverlay(): void
    {
        if (request()->boolean('pdv')) {
            $this->embedsInPdv = true;
        }

        if (request()->boolean('orcamento')) {
            $this->embedsInOrcamento = true;
        }
    }

    protected function isEmbedsInPdvOverlay(): bool
    {
        return $this->embedsInPdv;
    }

    protected function embedsInParentOverlay(): bool
    {
        return $this->embedsInPdv || $this->embedsInOrcamento;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function closeEmbedOverlay(?array $payload = null): void
    {
        if ($this->embedsInPdv) {
            $this->js('window.parent.postMessage({ type: "erp-pdv-overlay-close" }, "*")');

            return;
        }

        if ($this->embedsInOrcamento) {
            $message = array_merge(['type' => 'erp-orcamento-overlay-close'], $payload ?? []);
            $encoded = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->js('window.parent.postMessage(' . $encoded . ', "*")');
        }
    }

    protected function closePdvEmbedOverlay(): void
    {
        $this->closeEmbedOverlay();
    }

    /**
     * @return array<mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        $attributes = parent::getExtraBodyAttributes();

        if ($this->embedsInPdv) {
            $attributes['class'] = trim(($attributes['class'] ?? '') . ' erp-pdv-embed-body');
        }

        if ($this->embedsInOrcamento) {
            $attributes['class'] = trim(($attributes['class'] ?? '') . ' erp-orcamento-embed-body');
        }

        return $attributes;
    }
}
