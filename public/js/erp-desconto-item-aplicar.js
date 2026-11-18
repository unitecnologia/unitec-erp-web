/**
 * Fecha o modal Ctrl+D no DOM na hora e chama o Livewire.
 * Evita remorph atrasado deixar o diálogo aberto após Aplicar.
 *
 * Preferir api[method](valor). Em Livewire 3, $wire.call pode falhar
 * (MethodNotFoundException $wire) em alguns contextos.
 */
(function () {
  'use strict';

  function findLivewireComponent() {
    try {
      const root = document.querySelector('.erp-fv-tv-root')?.closest('[wire\\:id]')
        || document.querySelector('.erp-pdv-modal')?.closest('[wire\\:id]')
        || document.querySelector('[wire\\:id].fi-page, [wire\\:id]');

      if (root && window.Livewire) {
        return window.Livewire.find(root.getAttribute('wire:id'));
      }
    } catch (e) {
      /* ignore */
    }

    return null;
  }

  function invoke(api, methodName, valor) {
    if (!api || !methodName) {
      return false;
    }

    if (typeof api[methodName] === 'function') {
      api[methodName](valor);
      return true;
    }

    if (typeof api.call === 'function') {
      api.call(methodName, valor);
      return true;
    }

    return false;
  }

  window.erpDescontoItemAplicar = function (wire, methodName, inputOrId, valorOverride) {
    if (!methodName) {
      return;
    }

    const input = typeof inputOrId === 'string'
      ? document.getElementById(inputOrId)
      : inputOrId;
    const valor = valorOverride != null
      ? String(valorOverride)
      : (input ? String(input.value ?? '') : '');
    const modal = (input && typeof input.closest === 'function')
      ? input.closest('.erp-pdv-modal')
      : document.querySelector('.erp-pdv-modal.erp-fv-tv-desconto, .erp-pdv-modal.erp-nfe-desconto, .erp-pdv-modal:has(.erp-pdv-desconto)');

    if (modal && modal.parentNode) {
      modal.parentNode.removeChild(modal);
    }

    try {
      if (invoke(wire, methodName, valor)) {
        return;
      }
      invoke(findLivewireComponent(), methodName, valor);
    } catch (e) {
      console.error('erpDescontoItemAplicar', e);
    }
  };
})();
