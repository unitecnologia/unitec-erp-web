{{--
    Menu “Mais opções” (Tela de Venda / Monitor / Orçamento).
    Alpine puro no abrir — sem query.
    @var bool $enabled  default true (Monitor desabilita com 0 ou >1 selecionados)
    @var string|null $onMargem  wire method ao clicar “Margem da venda” (null = stub)
    @var string|null $onToggleCusto  wire method para mostrar/ocultar custo na grade
    @var bool $exibirCusto  estado atual da coluna de custo (rótulo do toggle)
--}}
@php
    $enabled = $enabled ?? true;
    $onMargem = $onMargem ?? null;
    $onToggleCusto = $onToggleCusto ?? null;
    $exibirCusto = (bool) ($exibirCusto ?? false);
@endphp
<div
    class="erp-field-dd erp-fv-mais-opcoes"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        class="erp-field-dd__btn erp-fv-mais-opcoes__btn"
        @disabled(! $enabled)
        @click="if (! $el.disabled) open = ! open"
        :aria-expanded="open.toString()"
        title="Mais opções"
    >
        <span>Mais opções</span>
        <span class="erp-field-dd__caret" aria-hidden="true">▾</span>
    </button>
    <ul
        class="erp-field-dd__menu erp-fv-mais-opcoes__menu"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.75ms
        role="menu"
        aria-label="Mais opções"
    >
        @if (filled($onToggleCusto))
            <li role="none">
                <button
                    type="button"
                    class="erp-field-dd__item"
                    role="menuitem"
                    wire:click="{{ $onToggleCusto }}"
                    @click="open = false"
                >
                    <x-filament::icon icon="heroicon-o-currency-dollar" class="erp-fv-mais-opcoes__icon" />
                    <span class="erp-field-dd__label">
                        {{ $exibirCusto ? 'Ocultar custo dos produtos' : 'Exibir custo dos produtos' }}
                    </span>
                </button>
            </li>
        @endif
        <li role="none">
            @if (filled($onMargem))
                <button
                    type="button"
                    class="erp-field-dd__item"
                    role="menuitem"
                    wire:click="{{ $onMargem }}"
                    @click="open = false"
                >
                    <x-filament::icon icon="heroicon-o-chart-bar" class="erp-fv-mais-opcoes__icon" />
                    <span class="erp-field-dd__label">Margem da venda</span>
                </button>
            @else
                <button
                    type="button"
                    class="erp-field-dd__item"
                    role="menuitem"
                    @click="open = false"
                    title="Em breve"
                >
                    <x-filament::icon icon="heroicon-o-chart-bar" class="erp-fv-mais-opcoes__icon" />
                    <span class="erp-field-dd__label">Margem da venda</span>
                </button>
            @endif
        </li>
        <li role="none">
            <button type="button" class="erp-field-dd__item" role="menuitem" disabled title="Em breve">
                <x-filament::icon icon="heroicon-o-document-text" class="erp-fv-mais-opcoes__icon" />
                <span class="erp-field-dd__label">Exibir impostos</span>
                <span class="erp-fv-mais-opcoes__soon">Em breve</span>
            </button>
        </li>
        <li role="none">
            <button type="button" class="erp-field-dd__item" role="menuitem" disabled title="Em breve">
                <x-filament::icon icon="heroicon-o-truck" class="erp-fv-mais-opcoes__icon" />
                <span class="erp-field-dd__label">Expedição</span>
                <span class="erp-fv-mais-opcoes__soon">Em breve</span>
            </button>
        </li>
    </ul>
</div>
