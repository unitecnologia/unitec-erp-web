(function () {
    const DISMISS_KEY = 'unitec_erp_pwa_install_dismissed_until';
    const INSTALLED_KEY = 'unitec_erp_pwa_installed';
    const ICON_SRC = '/images/pwa/icon-192.png';

    let deferredPrompt = null;
    let banner = null;

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

    function markInstalled() {
        try {
            localStorage.setItem(INSTALLED_KEY, '1');
        } catch (e) {}
    }

    function wasInstalledFlag() {
        try {
            return localStorage.getItem(INSTALLED_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function looksInstalled() {
        return isStandalone() || wasInstalledFlag();
    }

    async function detectRelatedInstalled() {
        if (! navigator.getInstalledRelatedApps) {
            return false;
        }

        try {
            const apps = await navigator.getInstalledRelatedApps();
            if (apps && apps.length > 0) {
                markInstalled();
                return true;
            }
        } catch (e) {}

        return false;
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
        if (isStandalone()) {
            return false;
        }

        return dismissedUntil() <= Date.now();
    }

    function installHelpText() {
        const url = window.location.origin + '/admin';

        if (looksInstalled() && ! deferredPrompt) {
            return (
                'O Unitec ERP já está instalado neste computador.\n\n' +
                'Para abrir como aplicativo:\n' +
                '• Clique em “Abrir no app” na barra de endereço do Chrome/Edge\n' +
                '  (fica perto da estrela de favoritos)\n' +
                '• Ou abra pelo Menu Iniciar → Unitec ERP'
            );
        }

        return (
            'Para instalar o Unitec ERP no Windows:\n\n' +
            '1. Use Google Chrome ou Microsoft Edge\n' +
            '2. Abra: ' + url + '\n' +
            '3. Na barra de endereço, clique no ícone de computador / ⊕\n' +
            '   ou Menu (⋯) → “Instalar Unitec ERP” / “Aplicativos”\n\n' +
            (isSecureEnough()
                ? 'Dica: em 127.0.0.1 a instalação funciona sem HTTPS.'
                : 'Atenção: use http://127.0.0.1:8765 (não o IP da rede).')
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
            '<h2 class="erp-pwa-install__title" data-erp-pwa-title>Instalar no computador</h2>' +
            '<p class="erp-pwa-install__text" data-erp-pwa-text></p>' +
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
        const root = ensureBanner();
        const title = root.querySelector('[data-erp-pwa-title]');
        const text = root.querySelector('[data-erp-pwa-text]');
        const installBtn = root.querySelector('[data-erp-pwa-install]');
        const helpBtn = root.querySelector('[data-erp-pwa-help]');

        if (! text || ! title || ! installBtn) {
            return;
        }

        if (deferredPrompt) {
            title.textContent = 'Instalar no computador';
            text.textContent = 'Clique em “Instalar aplicativo” para criar o atalho no Windows (Menu Iniciar).';
            installBtn.textContent = 'Instalar aplicativo';
            installBtn.hidden = false;
            if (helpBtn) {
                helpBtn.hidden = false;
            }
            return;
        }

        if (looksInstalled()) {
            title.textContent = 'App já instalado';
            text.innerHTML =
                'O Unitec ERP já está no Windows.<br>' +
                '<strong>Clique em “Abrir no app”</strong> na barra de endereço ' +
                '(ao lado da estrela) — ou abra pelo Menu Iniciar.';
            installBtn.textContent = 'Entendi';
            installBtn.hidden = false;
            if (helpBtn) {
                helpBtn.hidden = true;
            }
            return;
        }

        title.textContent = 'Instalar no computador';
        if (! isSecureEnough()) {
            text.textContent = 'Abra em http://127.0.0.1:8765/admin no Chrome/Edge para liberar a instalação.';
            installBtn.textContent = 'Ver como instalar';
        } else {
            // Sem beforeinstallprompt: em geral já está instalado (aparece “Abrir no app”)
            // ou o Chrome só libera pelo ícone da barra.
            text.innerHTML =
                'Olhe a barra de endereço do Chrome/Edge:<br>' +
                '• <strong>“Abrir no app”</strong> → já está instalado — clique ali<br>' +
                '• Ícone de <strong>computador / ⊕</strong> → ainda não instalou — clique nele';
            installBtn.textContent = 'Entendi';
        }
        installBtn.hidden = false;
        if (helpBtn) {
            helpBtn.hidden = false;
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
        // Prompt nativo do Chrome/Edge (só existe com gesto do usuário).
        if (deferredPrompt) {
            const promptEvent = deferredPrompt;
            try {
                await promptEvent.prompt();
                const choice = await promptEvent.userChoice;
                deferredPrompt = null;
                refreshBannerCopy();

                if (choice && choice.outcome === 'accepted') {
                    markInstalled();
                    dismissForDays(365);
                    hideBanner();
                    return true;
                }

                dismissForDays(1);
                return false;
            } catch (e) {
                deferredPrompt = null;
                refreshBannerCopy();
            }
        }

        // Sem prompt nativo: não dispara alert em loop — atualiza o banner e fecha.
        await detectRelatedInstalled();
        refreshBannerCopy();

        if (looksInstalled() || isSecureEnough()) {
            hideBanner();
            dismissForDays(looksInstalled() ? 14 : 3);
            return looksInstalled();
        }

        // Ambiente inseguro (IP da rede): aí o guia em alert ainda ajuda.
        alert(installHelpText());
        return false;
    }

    // Captura o mais cedo possível (antes do load).
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        try {
            localStorage.removeItem(INSTALLED_KEY);
        } catch (e) {}
        refreshBannerCopy();

        if (shouldOffer()) {
            window.setTimeout(function () {
                showBanner(false);
            }, 600);
        }
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        markInstalled();
        dismissForDays(365);
        hideBanner();
    });

    function bootOffer() {
        if (! shouldOffer()) {
            return;
        }

        void detectRelatedInstalled().then(function () {
            // Se já instalou (ou Chrome mostra “Abrir no app”), avisa sem alert.
            window.setTimeout(function () {
                if (deferredPrompt || looksInstalled()) {
                    showBanner(false);
                }
            }, looksInstalled() ? 900 : 2200);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootOffer);
    } else {
        bootOffer();
    }

    window.UnitecErpPwa = {
        canInstall: function () {
            return ! isStandalone() && !! deferredPrompt;
        },
        isInstalled: function () {
            return looksInstalled() || isStandalone();
        },
        install: function () {
            clearDismiss();
            showBanner(true);
            // Só dispara o prompt nativo se existir; senão o banner já explica.
            if (deferredPrompt) {
                return promptInstall();
            }
            refreshBannerCopy();
            return Promise.resolve(false);
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
