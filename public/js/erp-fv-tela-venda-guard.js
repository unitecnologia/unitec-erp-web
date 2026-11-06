/**
 * Tela de Venda — proteção de navegação SPA (sem beforeunload / sem confirm nativo).
 *
 * - Sem "Sair do site?" do Chrome (beforeunload removido de propósito).
 * - Menu SPA com itens: abre o modal laranja UNI (grava pendente ou continua).
 * - Saída autorizada (Sair/F3/F4 ok): allowLeave libera o livewire:navigate do redirect.
 */
(function () {
    'use strict';

    let allowLeave = false;
    let listenersBound = false;

    function telaVendaAtiva() {
        return !! document.querySelector('.erp-fv-tv-root');
    }

    function isDirty() {
        if (allowLeave || ! telaVendaAtiva()) {
            return false;
        }

        const root = document.querySelector('.erp-fv-tv-root');
        if (root && root.getAttribute('data-fv-dirty') === '1') {
            return true;
        }

        return !! document.querySelector('.erp-fv-tv__grid tbody tr[wire\\:key^="fv-item"]');
    }

    function markAllowLeave() {
        allowLeave = true;
    }

    function resetAllowLeave() {
        allowLeave = false;
    }

    function beginAuthorizedLeave() {
        allowLeave = true;
    }

    function keepGuard() {
        allowLeave = false;
    }

    function removeHintIfAny() {
        document.getElementById('erp-fv-tv-leave-hint')?.remove();
    }

    function findTelaVendaWire() {
        if (! window.Livewire) {
            return null;
        }

        const root = document.querySelector('.erp-fv-tv-root');
        const host = root?.closest?.('[wire\\:id]')
            || document.querySelector('.erp-fv-tela-venda-page[wire\\:id], [wire\\:id].erp-fv-tela-venda-page');

        if (! host) {
            return null;
        }

        try {
            return window.Livewire.find(host.getAttribute('wire:id'));
        } catch (e) {
            return null;
        }
    }

    function resolveNavigateHref(detail) {
        const url = detail?.url;

        if (! url) {
            return null;
        }

        if (typeof url === 'string') {
            return url;
        }

        if (typeof url.href === 'string') {
            return url.href;
        }

        const path = String(url.pathname || '');
        const search = String(url.search || '');

        return path ? (path + search) : null;
    }

    function onLivewireNavigate(event) {
        if (! isDirty()) {
            return;
        }

        event.preventDefault();

        const href = resolveNavigateHref(event.detail);
        const component = findTelaVendaWire();

        if (component && typeof component.call === 'function') {
            component.call('abrirSairConfirmNavegacao', href || '');

            return;
        }

        // Fallback: se o componente não estiver disponível, só bloqueia.
    }

    function bindListeners() {
        if (listenersBound) {
            return;
        }

        listenersBound = true;
        // Sem beforeunload — sem aviso nativo do Chrome ao fechar/recarregar a aba.
        document.addEventListener('livewire:navigate', onLivewireNavigate);
    }

    function boot() {
        removeHintIfAny();

        if (! telaVendaAtiva()) {
            return;
        }

        if (! allowLeave) {
            resetAllowLeave();
        }

        bindListeners();
    }

    document.addEventListener('fv-tela-venda-allow-leave', markAllowLeave);
    document.addEventListener('fv-tela-venda-keep-guard', keepGuard);

    document.addEventListener('livewire:init', () => {
        Livewire.on('fv-tela-venda-allow-leave', () => markAllowLeave());
        Livewire.on('fv-tela-venda-keep-guard', () => keepGuard());
    });

    document.addEventListener('DOMContentLoaded', () => {
        resetAllowLeave();
        boot();
    });

    document.addEventListener('livewire:navigated', () => {
        if (! telaVendaAtiva()) {
            resetAllowLeave();
        }
        boot();
    });

    window.erpFvTvIsDirty = isDirty;
    window.erpFvTvAllowLeave = markAllowLeave;
    window.erpFvTvBeginAuthorizedLeave = beginAuthorizedLeave;
    window.erpFvTvKeepGuard = keepGuard;
})();
