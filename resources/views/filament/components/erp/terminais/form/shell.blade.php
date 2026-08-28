<div class="erp-pcad erp-terminais-pcad">
    <nav class="erp-pcad__tabs erp-terminais-pcad__tabs">
        @foreach ([
            'configuracoes' => 'Configurações',
            'balanca' => 'Balanças',
            'tef' => 'TEF/POS',
            'aparelhos' => 'Aparelhos',
        ] as $tab => $label)
            <button
                type="button"
                wire:click="selectTerminalTab('{{ $tab }}')"
                @class([
                    'erp-pcad__tab',
                    'erp-pcad__tab--active' => $this->activeTerminalTab === $tab,
                ])
            >{{ $label }}</button>
        @endforeach
    </nav>

    <div class="erp-pcad__workspace erp-terminais-pcad__workspace">
        <div class="erp-pcad__content">
            @php
                $terminalTab = in_array($this->activeTerminalTab, $this->terminalTabKeys(), true)
                    ? $this->activeTerminalTab
                    : 'configuracoes';
            @endphp
            @include('filament.components.erp.terminais.form.tabs.' . $terminalTab)
        </div>
    </div>
</div>
