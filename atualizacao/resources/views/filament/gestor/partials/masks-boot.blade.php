@php
    $version = \App\Support\Erp\ErpAssetVersion::bundle();
@endphp
<script src="{{ asset('js/erp-masks.js') }}?v={{ $version }}"></script>
<script>
(function () {
    function boot() {
        if (!window.ErpMasks) return;
        window.ErpMasks.init(document);
    }

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('livewire:navigated', boot);
    document.addEventListener('livewire:init', function () {
        if (!window.Livewire) return;
        window.Livewire.hook('morph.updated', function () {
            boot();
        });
    });
    boot();
})();
</script>
