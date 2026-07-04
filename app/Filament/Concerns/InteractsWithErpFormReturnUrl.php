<?php

namespace App\Filament\Concerns;

use App\Support\Erp\ErpFormReturnUrl;
use App\Support\Erp\ErpScreen;

trait InteractsWithErpFormReturnUrl
{
    public ?string $erpFormReturnUrl = null;

    public function mountInteractsWithErpFormReturnUrl(): void
    {
        $this->captureErpFormReturnUrl();
    }

    public function bootInteractsWithErpFormReturnUrl(): void
    {
        $this->captureErpFormReturnUrl();
    }

    protected function captureErpFormReturnUrl(): void
    {
        $resolved = ErpFormReturnUrl::fromRequest();

        if ($resolved !== null) {
            $this->erpFormReturnUrl = $resolved;

            return;
        }

        if ($this->erpFormReturnUrl === null) {
            $this->erpFormReturnUrl = ErpFormReturnUrl::peek();
        }
    }

    protected function resolveErpFormReturnUrl(): ?string
    {
        return $this->erpFormReturnUrl ?? ErpFormReturnUrl::peek();
    }

    protected function erpFormReturnRedirectUrl(string $fallback): string
    {
        $returnUrl = $this->resolveErpFormReturnUrl();

        if (ErpFormReturnUrl::isOrcamentoUrl($returnUrl)) {
            return ErpFormReturnUrl::toRedirectUrl($returnUrl) ?? $fallback;
        }

        return $fallback;
    }

    protected function redirectToErpFormReturnOr(string $fallback, string $defaultScreen): void
    {
        $returnUrl = $this->resolveErpFormReturnUrl();

        if (ErpFormReturnUrl::isMonitorUrl($returnUrl)) {
            ErpFormReturnUrl::forget();
            ErpScreen::set('Monitor de Vendas');
            $this->redirect(ErpFormReturnUrl::toRedirectUrl($returnUrl), navigate: false);

            return;
        }

        if (ErpFormReturnUrl::isOrcamentoFormUrl($returnUrl)) {
            ErpFormReturnUrl::forget();
            ErpScreen::set('Lançamento de Orçamento');
            $this->redirect(ErpFormReturnUrl::toRedirectUrl($returnUrl), navigate: false);

            return;
        }

        ErpScreen::set($defaultScreen);
        $this->redirect($fallback, navigate: false);
    }

    protected function redirectToOrcamentoReturnIfPresent(): bool
    {
        $returnUrl = $this->resolveErpFormReturnUrl();

        if (! ErpFormReturnUrl::isOrcamentoUrl($returnUrl)) {
            return false;
        }

        ErpFormReturnUrl::forget();
        ErpScreen::set('Lançamento de Orçamento');
        $this->redirect(ErpFormReturnUrl::toRedirectUrl($returnUrl), navigate: false);

        return true;
    }
}
