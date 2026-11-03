@once('erp-form-scripts')
@php
    $version = \App\Support\Erp\ErpAssetVersion::bundle();
@endphp

@include('filament.components.erp.form-scripts-styles')

<script>
(function () {
    const selector = 'input[type="date"][data-erp-date-wire], input[type="date"][data-wire-field], input[type="date"][data-erp-date], input[type="date"][data-mask="date-br"]';

    function isoToBr(value) {
        const trimmed = String(value ?? '').trim();

        if (! /^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return trimmed;
        }

        const [year, month, day] = trimmed.split('-');

        return `${day}/${month}/${year}`;
    }

    function prepDateInputs(root) {
        if (! root?.querySelectorAll) {
            return;
        }

        root.querySelectorAll(selector).forEach((input) => {
            if (input.dataset.erpDatePrepped === '1') {
                // Re-render / value ISO no wire:ignore: garante dd/mm mesmo sem rebind.
                if (/^\d{4}-\d{2}-\d{2}$/.test(String(input.value ?? '').trim())) {
                    input.value = isoToBr(input.value);
                }

                return;
            }

            if (input.hasAttribute('data-erp-native-date') || input.dataset.erpDateSkip === '1') {
                return;
            }

            input.type = 'text';
            input.value = isoToBr(input.value);
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
