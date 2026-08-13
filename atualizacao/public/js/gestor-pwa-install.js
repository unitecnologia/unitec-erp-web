(function () {
    const DISMISS_KEY = 'unitec_gestor_pwa_install_dismissed_until';
    const INSTALLED_KEY = 'unitec_gestor_pwa_installed';
    const ICON_SRC = '/pwa-gestor/icons/icon-192.png';

    // Pode ter sido capturado cedo no <head> (antes deste arquivo carregar).
    let deferredPrompt = window.__unitecGestorBip || null;
    let banner = null;
    let waitingForPrompt = false;

    if (deferredPrompt) {
        try {
            delete window.__unitecGestorBip;
        } catch (e) {
            window.__unitecGestorBip = null;
        }
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: minimal-ui)').matches
            || window.navigator.standalone === true;
    }

    function isSecureEnough() {
        if (window.isSecureContext) {
            return true;
        }

        const host = (window.location.hostname || '').toLowerCase();
        return host === 'localhost' || host === '127.0.0.1' || host === '::1';
    }

    function isIos() {
        const ua = window.navigator.userAgent || '';
        return /iPad|iPhone|iPod/.test(ua)
            || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    }

    function isAndroid() {
        return /Android/i.test(window.navigator.userAgent || '');
    }

    function isDesktop() {
        return ! isIos() && ! isAndroid();
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

    function currentOriginLabel() {
        return window.location.origin || '';
    }

    function helpText() {
        const url = currentOriginLabel() + '/gestor/';

        if (! isSecureEnough()) {
            return (
                'Neste endereço o Chrome NÃO instala PWA.\n\n' +
                'Você está em: ' + currentOriginLabel() + '\n' +
                '(HTTP pelo IP da rede = bloqueado)\n\n' +
                'Como instalar de verdade:\n' +
                '• No PC do servidor: abra http://127.0.0.1:8000/gestor e instale\n' +
                '• Ou configure HTTPS no servidor e abra pelo celular\n\n' +
                'Atalho temporário no Android:\n' +
                'Chrome → Menu ⋮ → “Adicionar à tela inicial”'
            );
        }

        if (isIos()) {
            return (
                'No iPhone/iPad use o Safari:\n\n' +
                '1. Abra ' + url + '\n' +
                '2. Toque em Compartilhar\n' +
                '3. “Adicionar à Tela de Início”\n' +
                '4. Confirme'
            );
        }

        if (deferredPrompt) {
            return 'Toque em Instalar para abrir o prompt do Chrome/Edge.';
        }

        if (isDesktop()) {
            return (
                'No Chrome/Edge deste computador:\n\n' +
                '1. Abra ' + url + '\n' +
                '2. Na barra de endereço, clique no ícone de computador / ⊕\n' +
                '   (fica perto da estrela de favoritos)\n' +
                '3. Confirme “Instalar”\n\n' +
                'Se aparecer “Abrir no app”, o Executivo já está instalado.'
            );
        }

        return (
            'No Chrome Android:\n\n' +
            '1. Abra ' + url + '\n' +
            '2. Menu ⋮ → “Instalar app” ou “Adicionar à tela inicial”\n' +
            '3. Confirme'
        );
    }

    function ensureBanner() {
        if (banner && document.body.contains(banner)) {
            return banner;
        }

        banner = document.createElement('div');
        banner.id = 'gestor-pwa-install';
        banner.className = 'gestor-pwa-install';
        banner.setAttribute('role', 'dialog');
        banner.setAttribute('aria-label', 'Instalar Unitec Executivo');
        banner.innerHTML =
            '<div class="gestor-pwa-install__icon" style="background-image:url(\'' + ICON_SRC + '\')"></div>' +
            '<div class="gestor-pwa-install__body">' +
            '  <p class="gestor-pwa-install__title"></p>' +
            '  <p class="gestor-pwa-install__text"></p>' +
            '  <div class="gestor-pwa-install__actions">' +
            '    <button type="button" class="gestor-pwa-install__btn gestor-pwa-install__btn--primary" data-gestor-pwa-install>Instalar</button>' +
            '    <button type="button" class="gestor-pwa-install__btn gestor-pwa-install__btn--ghost" data-gestor-pwa-help>Ajuda</button>' +
            '    <button type="button" class="gestor-pwa-install__btn gestor-pwa-install__btn--ghost" data-gestor-pwa-dismiss>Agora não</button>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(banner);

        banner.querySelector('[data-gestor-pwa-install]').addEventListener('click', function () {
            void promptInstall();
        });
        banner.querySelector('[data-gestor-pwa-help]').addEventListener('click', function () {
            alert(helpText());
        });
        banner.querySelector('[data-gestor-pwa-dismiss]').addEventListener('click', function () {
            dismissForDays(7);
            hideBanner();
        });

        return banner;
    }

    function refreshBannerCopy() {
        const el = ensureBanner();
        const title = el.querySelector('.gestor-pwa-install__title');
        const text = el.querySelector('.gestor-pwa-install__text');
        const installBtn = el.querySelector('[data-gestor-pwa-install]');
        const helpBtn = el.querySelector('[data-gestor-pwa-help]');

        if (! isSecureEnough()) {
            title.textContent = 'Não dá para instalar neste endereço';
            text.textContent = 'Você está em ' + currentOriginLabel() + '. PWA só instala em HTTPS ou 127.0.0.1.';
            installBtn.textContent = 'Entendi o motivo';
            helpBtn.hidden = false;
            return;
        }

        if (isIos()) {
            title.textContent = 'Adicionar à tela inicial';
            text.textContent = 'Safari → Compartilhar → “Adicionar à Tela de Início”.';
            installBtn.textContent = 'Ver passos';
            helpBtn.hidden = true;
            return;
        }

        if (deferredPrompt) {
            title.textContent = 'Instalar Unitec Executivo';
            text.textContent = 'Abre como app, sem barra do navegador.';
            installBtn.textContent = 'Instalar agora';
            helpBtn.hidden = false;
            return;
        }

        if (waitingForPrompt) {
            title.textContent = 'Preparando instalação…';
            text.textContent = 'Aguardando o Chrome liberar o botão nativo. Aguarde alguns segundos.';
            installBtn.textContent = 'Aguardando…';
            installBtn.disabled = true;
            helpBtn.hidden = false;
            return;
        }

        installBtn.disabled = false;

        if (looksInstalled()) {
            title.textContent = 'App já instalado';
            text.textContent = 'Abra pelo ícone na tela inicial ou pelo menu Iniciar.';
            installBtn.textContent = 'Entendi';
            helpBtn.hidden = true;
            return;
        }

        if (isDesktop()) {
            title.textContent = 'Instalar pelo Chrome/Edge';
            text.textContent = 'Clique no ícone de computador / ⊕ na barra de endereço (ao lado da estrela) e confirme Instalar.';
            installBtn.textContent = 'Ver passo a passo';
            helpBtn.hidden = false;
            return;
        }

        title.textContent = 'Instalar no celular';
        text.textContent = 'Chrome → Menu ⋮ → “Instalar app” (ou “Adicionar à tela inicial”).';
        installBtn.textContent = 'Ver passo a passo';
        helpBtn.hidden = false;
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

    function capturePrompt(event) {
        try {
            event.preventDefault();
        } catch (e) {}

        deferredPrompt = event;
        waitingForPrompt = false;

        try {
            localStorage.removeItem(INSTALLED_KEY);
        } catch (e) {}

        refreshBannerCopy();

        if (shouldOffer()) {
            showBanner(false);
        }
    }

    async function ensureServiceWorkerReady(timeoutMs) {
        if (! ('serviceWorker' in navigator) || ! isSecureEnough()) {
            return false;
        }

        try {
            await Promise.race([
                navigator.serviceWorker.ready,
                new Promise(function (_, reject) {
                    window.setTimeout(function () {
                        reject(new Error('timeout'));
                    }, timeoutMs || 4000);
                }),
            ]);
            return true;
        } catch (e) {
            return false;
        }
    }

    async function waitForNativePrompt(timeoutMs) {
        if (deferredPrompt) {
            return true;
        }

        waitingForPrompt = true;
        refreshBannerCopy();

        await ensureServiceWorkerReady(3000);

        const deadline = Date.now() + (timeoutMs || 4500);

        while (! deferredPrompt && Date.now() < deadline) {
            await new Promise(function (resolve) {
                window.setTimeout(resolve, 250);
            });
        }

        waitingForPrompt = false;
        refreshBannerCopy();

        return !! deferredPrompt;
    }

    async function promptInstall() {
        if (! deferredPrompt) {
            showBanner(true);
            const got = await waitForNativePrompt(5000);
            if (! got) {
                alert(helpText());
                return false;
            }
        }

        const promptEvent = deferredPrompt;
        deferredPrompt = null;

        try {
            await promptEvent.prompt();
            const choice = await promptEvent.userChoice;

            if (choice && choice.outcome === 'accepted') {
                markInstalled();
                dismissForDays(365);
                hideBanner();
                return true;
            }

            dismissForDays(1);
            refreshBannerCopy();
            return false;
        } catch (e) {
            deferredPrompt = null;
            refreshBannerCopy();
            alert(helpText());
            return false;
        }
    }

    window.addEventListener('beforeinstallprompt', capturePrompt);

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        waitingForPrompt = false;
        markInstalled();
        dismissForDays(365);
        hideBanner();
    });

    function bootOffer() {
        if (! shouldOffer()) {
            return;
        }

        void (async function () {
            if (! isSecureEnough() || isIos()) {
                window.setTimeout(function () {
                    showBanner(false);
                }, 900);
                return;
            }

            await ensureServiceWorkerReady(4000);
            await waitForNativePrompt(3500);

            if (deferredPrompt || isDesktop()) {
                showBanner(false);
            }
        })();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootOffer);
    } else {
        bootOffer();
    }

    window.UnitecGestorInstall = function () {
        clearDismiss();
        showBanner(true);
        return promptInstall();
    };

    window.UnitecGestorPwaInstall = {
        canInstall: function () {
            return ! isStandalone() && !! deferredPrompt;
        },
        isInstalled: function () {
            return looksInstalled() || isStandalone();
        },
        isSecure: isSecureEnough,
        install: function () {
            return window.UnitecGestorInstall();
        },
        show: function () {
            clearDismiss();
            showBanner(true);
        },
        help: helpText,
    };
})();
