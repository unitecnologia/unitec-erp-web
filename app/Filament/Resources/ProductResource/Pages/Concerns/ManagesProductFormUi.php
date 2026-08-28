<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use Filament\Notifications\Notification;

trait ManagesProductFormUi
{
    public string $activeFormTab = 'estoques';

    public string $activeEstoqueSubTab = 'estoques';

    /**
     * @return list<array{key: string, label: string, visible: bool}>
     */
    public function getVisibleProductFormTabsProperty(): array
    {
        $data = $this->data ?? [];

        $tabs = [
            ['key' => 'estoques', 'label' => 'Estoque', 'visible' => true],
            ['key' => 'impostos', 'label' => 'Impostos', 'visible' => true],
            ['key' => 'adicionais', 'label' => 'Adicionais', 'visible' => true],
            ['key' => 'lotes', 'label' => 'Lote / Validade', 'visible' => (bool) ($data['controla_lote_validade'] ?? false)],
            ['key' => 'combustivel', 'label' => 'Combustível', 'visible' => (bool) ($data['is_combustivel'] ?? false)],
            ['key' => 'info_nutricional', 'label' => 'Info. Nutricional', 'visible' => (bool) ($data['tem_info_nutricional'] ?? false)],
            ['key' => 'composicao', 'label' => 'Composição', 'visible' => (bool) ($data['is_composicao'] ?? false)],
            ['key' => 'grade', 'label' => 'Grade', 'visible' => (bool) ($data['is_grade'] ?? false)],
            ['key' => 'imei', 'label' => 'IMEI', 'visible' => (bool) ($data['usa_imei'] ?? false)],
            ['key' => 'tabela_preco', 'label' => 'Tab. Preço', 'visible' => (bool) ($data['usa_tab_preco'] ?? false)],
            ['key' => 'ultimos_precos', 'label' => 'Últimos Preços', 'visible' => $this->isEditingProduct()],
        ];

        return array_values(array_filter($tabs, fn (array $tab): bool => $tab['visible']));
    }

    public function setActiveEstoqueSubTab(string $tab): void
    {
        if (! in_array($tab, ['estoques', 'localizacoes', 'trocas', 'dados_anp'], true)) {
            return;
        }

        // Ao sair de Localizações, grava partes já presentes no estado Livewire
        // (o DOM é lido no save; aqui só garante a subaba).
        $this->activeEstoqueSubTab = $tab;
    }

    public function setActiveFormTab(string $tab): void
    {
        if ($this->embedsInPdv) {
            if (in_array($tab, ['adicionais'], true)) {
                $this->modulePending(ucfirst(str_replace('_', ' ', $tab)));

                return;
            }

            if (in_array($tab, ['dados', 'impostos', 'foto'], true)) {
                $this->activeFormTab = $tab;

                if ($tab === 'impostos' && method_exists($this, 'refreshEmpresaImpostoPadraoOnImpostosTab')) {
                    $this->refreshEmpresaImpostoPadraoOnImpostosTab();
                }

                $this->dispatch('erp-masks-refresh');

                return;
            }
        }

        $allowed = collect($this->visibleProductFormTabs)->pluck('key')->all();

        if (! in_array($tab, $allowed, true)) {
            return;
        }

        $this->activeFormTab = $tab;

        if ($tab === 'impostos' && method_exists($this, 'refreshEmpresaImpostoPadraoOnImpostosTab')) {
            $this->refreshEmpresaImpostoPadraoOnImpostosTab();
        }

        if ($tab === 'lotes' && method_exists($this, 'loadProductLotes')) {
            $this->loadProductLotes($this->record ?? null);
        }

        $this->dispatch('erp-masks-refresh');

        if ($tab === 'estoques' && blank($this->activeEstoqueSubTab)) {
            $this->activeEstoqueSubTab = 'estoques';
        }
    }

    public function updatedData(mixed $value, string $key): void
    {
        if (in_array($key, ['is_combustivel', 'tem_info_nutricional', 'is_composicao', 'is_grade', 'usa_tab_preco', 'usa_imei', 'controla_lote_validade'], true)) {
            $this->syncActiveTabAfterParameterChange($key, (bool) $value);
        }

        if ($key === 'controla_lote_validade' && (bool) $value && method_exists($this, 'loadProductLotes')) {
            $this->loadProductLotes($this->record ?? null);
        }

        if ($key === 'is_restaurante' && ! (bool) $value) {
            $this->data['menu_id'] = null;
            $this->data['tipo_alimento'] = null;
            $this->data['qtd_sabores'] = 0;
            $this->data['valor_pequena'] = $this->formatBrDecimal(0, 4);
            $this->data['valor_media'] = $this->formatBrDecimal(0, 4);
            $this->data['valor_grande'] = $this->formatBrDecimal(0, 4);
        }

        if ($key === 'is_remedio' && ! (bool) $value) {
            $this->data['aplicacao'] = null;
            $this->data['principio_ativo_id'] = null;
        }

        if ($key === 'produto_pesado' && ! (bool) $value) {
            $this->data['prefixo_balanca'] = null;
        }

        if ($key === 'produto_pesado' && (bool) $value) {
            $this->avisoBalancaAoMarcar();
        }
    }

    protected function avisoBalancaAoMarcar(): void
    {
        $criticas = \App\Support\Erp\Balanca\BalancaProductRules::criticas(is_array($this->data) ? $this->data : []);

        if ($criticas === []) {
            return;
        }

        Notification::make()
            ->title('Produto de Balança — confira o cadastro')
            ->body(implode("\n", array_values($criticas)))
            ->warning()
            ->send();
    }

    protected function syncActiveTabAfterParameterChange(string $key, bool $enabled): void
    {
        $tabMap = [
            'is_combustivel' => 'combustivel',
            'tem_info_nutricional' => 'info_nutricional',
            'is_composicao' => 'composicao',
            'is_grade' => 'grade',
            'usa_tab_preco' => 'tabela_preco',
            'usa_imei' => 'imei',
            'controla_lote_validade' => 'lotes',
        ];

        $tab = $tabMap[$key] ?? null;

        if ($enabled && $tab) {
            $this->activeFormTab = $tab;

            return;
        }

        $visibleKeys = collect($this->visibleProductFormTabs)->pluck('key')->all();

        if (! in_array($this->activeFormTab, $visibleKeys, true)) {
            $this->activeFormTab = $visibleKeys[0] ?? 'estoques';
        }
    }

    public function modulePending(string $module): void
    {
        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();
    }
}
