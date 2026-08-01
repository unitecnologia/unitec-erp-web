(function () {
    const DISMISS_KEY = 'unitec_erp_pwa_install_dismissed_until';
    const ICON_SRC = '/images/pwa/icon-192.png';

    let deferredPrompt = null;
    let banner = null;
    let helpOpen = false;

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: window-controls-overlay)').matches
            || window.navigator.standalone === true;
    }

    function isSecureEnough() {
        if (window.isSecureContext) {
            return true;
        }

        const host = (window.location.hostname || '').toLowerCase();
        return host === 'localhost' || host === '127.0.0.1' || host === '::1';
    }

    function dismissedUntil() {
        try {
            return Number(localStorage.getItem(DISMISS_KEY) || 0);
        } catch (e) {
            return 0;
        }
    }

    function dismissForDays(days) {
        try {
            localStorage.setItem(DISMISS_KEY, String(Date.now() + days * 86400000));
        } catch (e) {}
    }

    function clearDismiss() {
        try {
            localStorage.removeItem(DISMISS_KEY);
        } catch (e) {}
    }

    function shouldOffer() {
        return ! isStandalone() && dismissedUntil() <= Date.now();
    }

    function installHelpText() {
        const url = window.location.origin + '/admin';
        return (
            'Para instalar o Unitec ERP no Windows:\n\n' +
            '1. Use Google Chrome ou Microsoft Edge\n' +
            '2. Abra: ' + url + '\n' +
            '3. Clique no ícone ⊕ / computador na barra de endereço\n' +
            '   ou Menu (⋯) → “Instalar Unitec ERP” / “Aplicativo”\n\n' +
            (isSecureEnough()
                ? 'Dica: em 127.0.0.1 a instalação funciona sem HTTPS.'
                : 'Atenção: use http://127.0.0.1:8765 (não o IP da rede) para o Windows liberar a instalação.')
        );
    }

    function ensureBanner() {
        if (banner && document.body.contains(banner)) {
            return banner;
        }

        banner = document.createElement('div');
        banner.id = 'erp-pwa-install';
        banner.className = 'erp-pwa-install';
        banner.setAttribute('role', 'dialog');
        banner.setAttribute('aria-label', 'Instalar Unitec ERP');
        banner.innerHTML =
            '<div class="erp-pwa-install__top">' +
            '<img class="erp-pwa-install__icon" src="' + ICON_SRC + '" alt="" width="44" height="44">' +
            '<div class="erp-pwa-install__copy">' +
            '<p class="erp-pwa-install__eyebrow">Windows</p>' +
            '<h2 class="erp-pwa-install__title">Instalar no computador</h2>' +
            '<p class="erp-pwa-install__text" data-erp-pwa-text>Use como aplicativo: atalho no Menu Iniciar, sem barra do navegador.</p>' +
            '</div>' +
            '<button type="button" class="erp-pwa-install__close" data-erp-pwa-dismiss aria-label="Fechar">&times;</button>' +
            '</div>' +
            '<div class="erp-pwa-install__actions">' +
            '<button type="button" class="erp-pwa-install__btn erp-pwa-install__btn--primary" data-erp-pwa-install>Instalar aplicativo</button>' +
            '<button type="button" class="erp-pwa-install__btn erp-pwa-install__btn--ghost" data-erp-pwa-help>Como instalar</button>' +
            '<button type="button" class="erp-pwa-install__btn erp-pwa-install__btn--ghost" data-erp-pwa-later>Agora não</button>' +
            '</div>';

        document.body.appendChild(banner);

        banner.querySelector('[data-erp-pwa-install]')?.addEventListener('click', function () {
            void promptInstall();
        });
        banner.querySelector('[data-erp-pwa-help]')?.addEventListener('click', function () {
            alert(installHelpText());
        });
        banner.querySelector('[data-erp-pwa-later]')?.addEventListener('click', function () {
            dismissForDays(7);
            hideBanner();
        });
        banner.querySelector('[data-erp-pwa-dismiss]')?.addEventListener('click', function () {
            dismissForDays(3);
            hideBanner();
        });

        return banner;
    }

    function refreshBannerCopy() {
        const el = ensureBanner().querySelector('[data-erp-pwa-text]');
        if (! el) {
            return;
        }

        if (deferredPrompt) {
            el.textContent = 'Pronto para instalar. Clique em “Instalar aplicativo”.';
        } else if (! isSecureEnough()) {
            el.textContent = 'Abra em http://127.0.0.1:8765/admin (Chrome/Edge) para liberar a instalação.';
        } else {
            el.textContent = 'Se o botão não abrir o instalador, use o ícone na barra de endereço do Chrome/Edge.';
        }
    }

    function showBanner(force) {
        if (isStandalone()) {
            hideBanner();
            return;
        }

        if (! force && ! shouldOffer()) {
            return;
        }

        if (force) {
            clearDismiss();
        }

        refreshBannerCopy();
        ensureBanner().classList.add('is-visible');
    }

    function hideBanner() {
        if (banner) {
            banner.classList.remove('is-visible');
        }
    }

    async function promptInstall() {
        if (deferredPrompt) {
            try {
                deferredPrompt.prompt();
                const choice = await deferredPrompt.userChoice;
                deferredPrompt = null;
                hideBanner();

                if (choice && choice.outcome === 'accepted') {
                    dismissForDays(365);
                    return true;
                }

                dismissForDays(1);
                return false;
            } catch (e) {
                deferredPrompt = null;
            }
        }

        // Sem prompt nativo: mostra o caminho manual (sempre disponível).
        alert(installHelpText());
        return false;
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        refreshBannerCopy();

        if (shouldOffer()) {
            window.setTimeout(function () {
                showBanner(false);
            }, 800);
        }
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        dismissForDays(365);
        hideBanner();
    });

    // Sempre oferece a opção (não depende só do evento do Chrome).
    function bootOffer() {
        if (! shouldOffer()) {
            return;
        }

        window.setTimeout(function () {
            showBanner(false);
        }, 2500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootOffer);
    } else {
        bootOffer();
    }

    window.UnitecErpPwa = {
        canInstall: function () {
            return ! isStandalone();
        },
        isInstalled: isStandalone,
        install: function () {
            clearDismiss();
            showBanner(true);
            return promptInstall();
        },
        show: function () {
            clearDismiss();
            showBanner(true);
        },
        hide: hideBanner,
        help: function () {
            alert(installHelpText());
        },
    };
})();
