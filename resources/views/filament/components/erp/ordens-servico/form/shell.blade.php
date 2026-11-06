@php
    $readOnly = $this->osReadOnly();
    $mainTabs = [
        'dados' => 'Dados da OS',
        'equipamento' => 'Equipamento',
        'defeito' => 'Defeito/Problema',
        'observacoes' => 'Observações',
    ];
@endphp

{{-- Abas principais: troca local (Alpine). Campos ficam no DOM → não perde digitação; sem round-trip Livewire. --}}
<div
    class="erp-os-shell"
    x-data="{ tab: @entangle('activeFormTab') }"
>
    <div class="erp-os-shell__tabs" role="tablist">
        @foreach ($mainTabs as $tab => $label)
            <button
                type="button"
                role="tab"
                @click="tab = '{{ $tab }}'"
                :class="{ 'erp-os-shell__tab--active': tab === '{{ $tab }}' }"
                :aria-selected="tab === '{{ $tab }}' ? 'true' : 'false'"
                @class([
                    'erp-os-shell__tab',
                    'erp-os-shell__tab--active' => $this->activeFormTab === $tab,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="erp-os-shell__workspace">
        <div class="erp-os-shell__content">
            {{-- erp-os-shell__panel restaura a cadeia flex (grade Serviços/Peças) sob x-show --}}
            <div class="erp-os-shell__panel" x-show="tab === 'dados'" x-cloak role="tabpanel">
                @include('filament.components.erp.ordens-servico.form.tabs.dados', ['readOnly' => $readOnly])
            </div>
            <div class="erp-os-shell__panel" x-show="tab === 'equipamento'" x-cloak role="tabpanel">
                @include('filament.components.erp.ordens-servico.form.tabs.equipamento', ['readOnly' => $readOnly])
            </div>
            <div class="erp-os-shell__panel" x-show="tab === 'defeito'" x-cloak role="tabpanel">
                @include('filament.components.erp.ordens-servico.form.tabs.defeito', ['readOnly' => $readOnly])
            </div>
            <div class="erp-os-shell__panel" x-show="tab === 'observacoes'" x-cloak role="tabpanel">
                @include('filament.components.erp.ordens-servico.form.tabs.observacoes', ['readOnly' => $readOnly])
            </div>
        </div>
    </div>
</div>

@include('filament.components.erp.form-scripts')
@php
    $jsPath = public_path('js/erp-os-form.js');
    $jsVersion = file_exists($jsPath) ? filemtime($jsPath) : time();
    $jsFatPath = public_path('js/erp-os-faturamento.js');
    $jsFatVersion = file_exists($jsFatPath) ? filemtime($jsFatPath) : time();
@endphp
<script src="{{ asset('js/erp-os-form.js') }}?v={{ $jsVersion }}" defer></script>
<script src="{{ asset('js/erp-os-faturamento.js') }}?v={{ $jsFatVersion }}" defer></script>
<script data-navigate-track>
    if (! window.__erpOsPreviewCloseBound) {
        window.__erpOsPreviewCloseBound = true;
        window.addEventListener('message', (event) => {
            if (event.data?.type !== 'erp-os-preview-close') {
                return;
            }
            if (window.Livewire?.dispatch) {
                window.Livewire.dispatch('close-os-preview');
                return;
            }
            const overlay = document.querySelector('.erp-os-preview-overlay');
            const componentId = overlay?.dataset.livewireId;
            if (componentId && window.Livewire?.find) {
                window.Livewire.find(componentId)?.call('closePreviewOverlay');
            }
        });
    }
</script>
