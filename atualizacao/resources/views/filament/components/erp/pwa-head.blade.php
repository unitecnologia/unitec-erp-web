{{-- PWA do ERP: manifesto + SW (sem UI de instalação) --}}
@php
    use App\Support\Erp\ErpAssetVersion;
    $pwaVersion = ErpAssetVersion::bundle().'-pwa6';
@endphp

<link rel="manifest" href="{{ asset('manifest-erp.webmanifest') }}?v={{ $pwaVersion }}">
<meta name="theme-color" content="#0f3460">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Unitec ERP">
<link rel="apple-touch-icon" href="{{ asset('images/pwa/icon-192.png') }}?v={{ $pwaVersion }}">

<script>
    (function () {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        var swUrl = @json(asset('sw-erp.js'));
        swUrl += (swUrl.indexOf('?') >= 0 ? '&' : '?') + 'v={{ $pwaVersion }}';

        // Não atrasa a abertura da tela: registra depois do load.
        window.addEventListener('load', function () {
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                return Promise.all(regs.map(function (reg) {
                    var url = (reg.active && reg.active.scriptURL)
                        || (reg.installing && reg.installing.scriptURL)
                        || (reg.waiting && reg.waiting.scriptURL)
                        || '';

                    if (url && url.indexOf('/sw-erp.js') === -1) {
                        return reg.unregister();
                    }

                    return Promise.resolve();
                }));
            }).then(function () {
                return navigator.serviceWorker.register(swUrl, { scope: '/admin/' });
            }).catch(function () {});
        });
    })();
</script>
