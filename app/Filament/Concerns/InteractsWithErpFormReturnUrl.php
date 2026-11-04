<?php

namespace App\Filament\Concerns;

use App\Support\Erp\ErpFormReturnUrl;
use App\Support\Erp\ErpScreen;

trait InteractsWithErpFormReturnUrl
{
    use InteractsWithErpSpaRedirect;

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

        // Monitor: estado vivo / polling — full reload seguro.
        if (ErpFormReturnUrl::isMonitorUrl($returnUrl)) {
            ErpFormReturnUrl::forget();
            ErpScreen::set('Monitor de Vendas');
            $this->redirectErpAdmin(
                (string) ErpFormReturnUrl::toRedirectUrl($returnUrl),
                forceFullPage: true,
            );

            return;
        }

        // Retorno ao lançamento de orçamento (contexto de overlay/form pesado).
        if (ErpFormReturnUrl::isOrcamentoFormUrl($returnUrl)) {
            ErpFormReturnUrl::forget();
            ErpScreen::set('Lançamento de Orçamento');
            $this->redirectErpAdmin(
                (string) ErpFormReturnUrl::toRedirectUrl($returnUrl),
                forceFullPage: true,
            );

            return;
        }

        ErpScreen::set($defaultScreen);
        $this->redirectErpAdmin($fallback);
    }

    protected function redirectToOrcamentoReturnIfPresent(): bool
    {
        $returnUrl = $this->resolveErpFormReturnUrl();

        if (! ErpFormReturnUrl::isOrcamentoUrl($returnUrl)) {
            return false;
        }

        ErpFormReturnUrl::forget();
        ErpScreen::set('Lançamento de Orçamento');
        $this->redirectErpAdmin(
            (string) ErpFormReturnUrl::toRedirectUrl($returnUrl),
            forceFullPage: true,
        );

        return true;
    }
}
