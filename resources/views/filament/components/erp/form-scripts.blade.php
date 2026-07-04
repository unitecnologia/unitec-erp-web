@once('erp-form-scripts')
@php
    $version = \App\Support\Erp\ErpAssetVersion::bundle();
@endphp

@include('filament.components.erp.form-scripts-styles')

<script>
(function () {
    const selector = 'input[type="date"][data-erp-date-wire], input[type="date"][data-wire-field], input[type="date"][data-erp-date], input[type="date"][data-mask="date-br"]';

    function prepDateInputs(root) {
        if (! root?.querySelectorAll) {
            return;
        }

        root.querySelectorAll(selector).forEach((input) => {
            if (input.dataset.erpDatePrepped === '1') {
                return;
            }

            input.type = 'text';
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('placeholder', 'dd/mm/aaaa');
            input.classList.add('erp-date-input');
            input.dataset.erpDatePrepped = '1';
        });
    }

    window.__erpPrepDateInputs = prepDateInputs;
    prepDateInputs(document);
    document.addEventListener('DOMContentLoaded', () => prepDateInputs(document));
    document.addEventListener('livewire:navigated', () => prepDateInputs(document));
})();
</script>

<script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}?v={{ $version }}"></script>
<script src="{{ asset('vendor/flatpickr/pt.js') }}?v={{ $version }}"></script>
<script src="{{ asset('js/erp-masks.js') }}?v={{ $version }}"></script>
<script src="{{ asset('js/erp-datepicker.js') }}?v={{ $version }}"></script>
<script src="{{ asset('js/erp-uppercase.js') }}?v={{ $version }}"></script>
@endonce
