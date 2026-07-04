<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use Filament\Notifications\Notification;

trait ManagesProductFormUi
{
    public string $activeFormTab = 'impostos';

    /**
     * @return list<array{key: string, label: string, visible: bool}>
     */
    public function getVisibleProductFormTabsProperty(): array
    {
        $data = $this->data ?? [];

        $tabs = [
            ['key' => 'impostos', 'label' => 'Impostos', 'visible' => true],
            ['key' => 'promocao', 'label' => 'Promoção', 'visible' => true],
            ['key' => 'adicionais', 'label' => 'Adicionais', 'visible' => true],
            ['key' => 'balanca', 'label' => 'Balança', 'visible' => true],
            ['key' => 'combustivel', 'label' => 'Combustível', 'visible' => (bool) ($data['is_combustivel'] ?? false)],
            ['key' => 'composicao', 'label' => 'Composição', 'visible' => (bool) ($data['is_composicao'] ?? false)],
            ['key' => 'grade', 'label' => 'Grade', 'visible' => (bool) ($data['is_grade'] ?? false)],
            ['key' => 'imei', 'label' => 'IMEI', 'visible' => (bool) ($data['usa_imei'] ?? false)],
            ['key' => 'tabela_preco', 'label' => 'Tab. Preço', 'visible' => (bool) ($data['usa_tab_preco'] ?? false)],
            ['key' => 'ultimos_precos', 'label' => 'Últimos Preços', 'visible' => $this->isEditingProduct()],
        ];

        return array_values(array_filter($tabs, fn (array $tab): bool => $tab['visible']));
    }

    public function setActiveFormTab(string $tab): void
    {
        if ($this->embedsInPdv) {
            if (in_array($tab, ['adicionais'], true)) {
                $this->modulePending(ucfirst(str_replace('_', ' ', $tab)));

                return;
            }

            if (in_array($tab, ['dados', 'impostos', 'promocao', 'foto'], true)) {
                $this->activeFormTab = $tab;

                return;
            }
        }

        $allowed = collect($this->visibleProductFormTabs)->pluck('key')->all();

        if (! in_array($tab, $allowed, true)) {
            return;
        }

        $this->activeFormTab = $tab;
    }

    public function updatedData(mixed $value, string $key): void
    {
        if (in_array($key, ['is_combustivel', 'is_composicao', 'is_grade', 'usa_tab_preco', 'usa_imei'], true)) {
            $this->syncActiveTabAfterParameterChange($key, (bool) $value);
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
    }

    protected function syncActiveTabAfterParameterChange(string $key, bool $enabled): void
    {
        $tabMap = [
            'is_combustivel' => 'combustivel',
            'is_composicao' => 'composicao',
            'is_grade' => 'grade',
            'usa_tab_preco' => 'tabela_preco',
            'usa_imei' => 'imei',
        ];

        $tab = $tabMap[$key] ?? null;

        if ($enabled && $tab) {
            $this->activeFormTab = $tab;

            return;
        }

        $visibleKeys = collect($this->visibleProductFormTabs)->pluck('key')->all();

        if (! in_array($this->activeFormTab, $visibleKeys, true)) {
            $this->activeFormTab = $visibleKeys[0] ?? 'impostos';
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
