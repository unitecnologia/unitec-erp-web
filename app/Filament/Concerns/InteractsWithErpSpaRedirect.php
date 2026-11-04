<?php

namespace App\Filament\Concerns;

/**
 * Redirects internos do painel admin.
 *
 * Livewire 3: redirect($url) usa navigate=false por padrão (full reload).
 * Cadastros comuns devem usar SPA (navigate=true). Embeds PDV/orçamento/nota
 * e fluxos especiais continuam com reload completo.
 */
trait InteractsWithErpSpaRedirect
{
    /**
     * @param  bool  $forceFullPage  Exceções explícitas (Monitor, overlays, fiscal, etc.)
     */
    protected function redirectErpAdmin(string $url, bool $forceFullPage = false): void
    {
        $useSpa = ! $forceFullPage && ! $this->erpAdminRedirectRequiresFullPage($url);

        $this->redirect($url, navigate: $useSpa);
    }

    protected function erpAdminRedirectRequiresFullPage(?string $url = null): bool
    {
        if (property_exists($this, 'embedsInPdv') && $this->embedsInPdv) {
            return true;
        }

        if (property_exists($this, 'embedsInOrcamento') && $this->embedsInOrcamento) {
            return true;
        }

        if (property_exists($this, 'embedsInNotaFornecedor') && $this->embedsInNotaFornecedor) {
            return true;
        }

        if (! is_string($url) || $url === '') {
            return false;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $params);

        foreach (['pdv', 'orcamento', 'nota_fornecedor'] as $flag) {
            if (! empty($params[$flag])) {
                return true;
            }
        }

        return false;
    }
}
