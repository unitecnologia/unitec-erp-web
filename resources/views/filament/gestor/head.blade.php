@php
    $gestorPwaVersion = '8';
@endphp
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" content="#0d2f57">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Unitec Executivo">
<link rel="manifest" href="{{ asset('manifest-gestor.webmanifest') }}?v={{ $gestorPwaVersion }}">
<link rel="apple-touch-icon" href="{{ asset('pwa-gestor/icons/icon-192.png') }}?v={{ $gestorPwaVersion }}">
<title>Unitec Executivo</title>

{{-- Captura beforeinstallprompt o mais cedo possível (antes do JS defer). --}}
<script>
    window.__unitecGestorBip = window.__unitecGestorBip || null;
    window.addEventListener('beforeinstallprompt', function (event) {
        try { event.preventDefault(); } catch (e) {}
        window.__unitecGestorBip = event;
    });
</script>

<link rel="stylesheet" href="{{ asset('css/gestor-pwa-install.css') }}?v={{ $gestorPwaVersion }}">
<script src="{{ asset('js/gestor-pwa-install.js') }}?v={{ $gestorPwaVersion }}" defer></script>

<script>
    (function () {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        var swUrl = @json(asset('sw-gestor.js'));
        swUrl += (swUrl.indexOf('?') >= 0 ? '&' : '?') + 'v={{ $gestorPwaVersion }}';

        window.addEventListener('load', function () {
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                return Promise.all(regs.map(function (reg) {
                    var url = (reg.active && reg.active.scriptURL)
                        || (reg.installing && reg.installing.scriptURL)
                        || (reg.waiting && reg.waiting.scriptURL)
                        || '';

                    // Remove SW antigo/conflitante só no escopo do gestor.
                    if (url && url.indexOf('/sw-gestor.js') === -1) {
                        var scope = String(reg.scope || '');
                        if (scope.indexOf('/gestor') !== -1) {
                            return reg.unregister();
                        }
                    }

                    return Promise.resolve();
                }));
            }).then(function () {
                if (! window.isSecureContext
                    && ! /^(localhost|127\.0\.0\.1|::1)$/i.test(location.hostname || '')) {
                    // HTTP em IP da rede: navegador bloqueia SW — PWA não instala.
                    return null;
                }

                return navigator.serviceWorker.register(swUrl, {
                    scope: '/gestor/',
                    updateViaCache: 'none',
                });
            }).then(function (reg) {
                if (reg) {
                    try { reg.update(); } catch (e) {}
                }
            }).catch(function () {});
        });
    })();
</script>

{{-- Caixa alta em todo input de texto do painel (exceto preço/quantidade/senha). --}}
<script>
    (function () {
        function shouldUpper(el) {
            if (!el || el.disabled || el.readOnly) return false;
            if (el.tagName !== 'INPUT' && el.tagName !== 'TEXTAREA') return false;
            var type = (el.getAttribute('type') || 'text').toLowerCase();
            if (['password', 'email', 'number', 'hidden', 'checkbox', 'radio', 'file', 'date', 'time'].indexOf(type) >= 0) {
                return false;
            }
            if (el.getAttribute('inputmode') === 'decimal' || el.getAttribute('inputmode') === 'numeric') {
                return false;
            }
            if (el.hasAttribute('data-mask')) return false;
            if (el.classList.contains('gestor-field__input--price')) return false;
            return el.classList.contains('gestor-field__input') || el.classList.contains('gestor-uppercase');
        }

        function toUpper(el) {
            if (!shouldUpper(el)) return;
            var start = el.selectionStart;
            var end = el.selectionEnd;
            var upper = (el.value || '').toLocaleUpperCase('pt-BR');
            if (el.value === upper) return;
            el.value = upper;
            try { el.setSelectionRange(start, end); } catch (e) {}
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }

        document.addEventListener('input', function (e) {
            toUpper(e.target);
        }, true);

        document.addEventListener('blur', function (e) {
            toUpper(e.target);
        }, true);
    })();
</script>
