<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Empresa;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ProductFormValidator;
use Filament\Notifications\Notification;

trait ManagesProductImpostoPadrao
{
    /**
     * Aplica o Imposto Padrão da empresa no formulário do produto.
     * Usado no cadastro novo (automático) e no botão "Restaurar padrão".
     */
    public function applyEmpresaImpostoPadraoToProductForm(bool $notify = true): void
    {
        $empresa = $this->resolveProductFormEmpresa(fresh: true);

        if (! $empresa instanceof Empresa) {
            if ($notify) {
                Notification::make()
                    ->title('Empresa não encontrada.')
                    ->body('Selecione a empresa ativa antes de carregar o imposto padrão.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $fiscal = ProductFormValidator::fiscalDefaultsFromEmpresa($empresa);

        foreach ($fiscal as $field => $value) {
            $this->data[$field] = $value;
        }

        $formatted = $this->formatProductFormDataForDisplay($this->data);
        $this->data = $formatted;

        if (isset($this->form) && method_exists($this->form, 'fill')) {
            $this->form->fill($formatted);
            $this->data = array_merge($this->data ?? [], $formatted);
        }

        $this->dispatch('erp-masks-refresh');

        if ($notify) {
            Notification::make()
                ->title('Imposto padrão aplicado.')
                ->body(sprintf(
                    'Valores da empresa %s · IVA IBS UF %s · CBS %s.',
                    $empresa->fantasia ?: $empresa->razao_social ?: '#'.$empresa->id,
                    $this->data['aliq_ibs_uf'] ?? '0',
                    $this->data['aliq_cbs'] ?? '0',
                ))
                ->success()
                ->send();
        }
    }

    /**
     * Cadastro novo: se algum imposto fiscal ainda estiver vazio/zerado
     * enquanto a empresa tem padrão, completa (não sobrescreve o que o usuário já editou).
     */
    public function refreshEmpresaImpostoPadraoOnImpostosTab(): void
    {
        if ($this->isEditingProduct()) {
            return;
        }

        $empresa = $this->resolveProductFormEmpresa(fresh: true);

        if (! $empresa instanceof Empresa) {
            return;
        }

        if ($this->productFiscalLooksUninitialized()) {
            $this->applyEmpresaImpostoPadraoToProductForm(notify: false);
        }
    }

    protected function resolveProductFormEmpresa(bool $fresh = false): ?Empresa
    {
        $empresaId = $this->currentProductEmpresaId();

        if ($empresaId <= 0) {
            return null;
        }

        $query = Empresa::query();

        if ($fresh) {
            $query->getQuery()->useWritePdo();
        }

        return $query->find($empresaId);
    }

    /**
     * Considera "ainda não inicializado" quando ICMS/PIS/IVA principais
     * estão no estado padrão vazio (0) e a empresa tem valores a copiar.
     */
    protected function productFiscalLooksUninitialized(): bool
    {
        $ivaEmpty = true;

        foreach (['aliq_ibs_uf', 'aliq_cbs', 'aliq_ibs_mun', 'aliq_adrem_ibs', 'aliq_adrem_cbs', 'reducao_cbs', 'reducao_ibs'] as $field) {
            if (BrDecimal::parse($this->data[$field] ?? 0, 4) > 0) {
                $ivaEmpty = false;
                break;
            }
        }

        $hasCodes = filled($this->data['cfop_interno'] ?? null)
            || filled($this->data['cst_icms'] ?? null)
            || filled($this->data['iva_cst'] ?? null)
            || filled($this->data['cclass_trib'] ?? null);

        // Já tem códigos/alíquotas preenchidos → usuário ou mount já aplicou.
        if (! $ivaEmpty && $hasCodes) {
            return false;
        }

        // Só reaplicar se IVA estiver zerado (caso típico de wipe pela máscara).
        return $ivaEmpty;
    }
}
