/**
 * DESATIVADO — NÃO capturar Enter.
 * Enter na precificação = wire:keydown.enter → precificacaoEnter() (PHP),
 * igual ao orçamento. Listeners em window/document com stopImmediatePropagation
 * impediam o Livewire de receber a tecla.
 */
(function () {
    window.__erpPrecifEnterVersion = 'off';
    // Remove binds antigos se existirem: não há API remove fácil sem referência.
    // O importante é NÃO registrar novos captures aqui.
})();
