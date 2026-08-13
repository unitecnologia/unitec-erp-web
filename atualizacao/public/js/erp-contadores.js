(function () {
    function refreshContadorModalMasks() {
        const modal = document.querySelector('.erp-contador-form-modal');

        if (modal && window.ErpMasks) {
            window.ErpMasks.refresh(modal);
        }
    }

    document.addEventListener('livewire:navigated', refreshContadorModalMasks);

    document.addEventListener('livewire:initialized', () => {
        if (! window.Livewire) {
            return;
        }

        window.Livewire.hook('morph.updated', ({ el }) => {
            if (el?.closest?.('.erp-contador-form-modal') || document.querySelector('.erp-contador-form-modal')) {
                refreshContadorModalMasks();
            }
        });
    });
})();
