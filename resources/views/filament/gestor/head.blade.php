<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" content="#0d2f57">
<meta name="apple-mobile-web-app-title" content="Unitec Executivo">
<link rel="manifest" href="{{ asset('manifest-gestor.webmanifest') }}?v=6">
<link rel="apple-touch-icon" href="{{ asset('pwa-gestor/icons/icon-192.png') }}">
<title>Unitec Executivo</title>

<script>
    (function () {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        window.addEventListener('load', function () {
            navigator.serviceWorker
                .register('/sw-gestor.js', { scope: '/gestor/', updateViaCache: 'none' })
                .then(function (reg) {
                    try { reg.update(); } catch (e) {}
                })
                .catch(function () {
                    // Falha silenciosa (ex.: contexto não seguro fora de localhost).
                });
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
