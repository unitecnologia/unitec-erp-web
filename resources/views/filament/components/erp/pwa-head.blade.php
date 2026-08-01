{{-- PWA do ERP: manifesto + SW + instalar no Windows --}}
@php
    use App\Support\Erp\ErpAssetVersion;
    $pwaVersion = ErpAssetVersion::bundle().'-pwa2';
@endphp

<link rel="manifest" href="{{ asset('manifest-erp.webmanifest') }}?v={{ $pwaVersion }}">
<meta name="theme-color" content="#0f3460">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Unitec ERP">
<link rel="apple-touch-icon" href="{{ asset('images/pwa/icon-192.png') }}?v={{ $pwaVersion }}">

<link rel="stylesheet" href="{{ asset('css/erp-pwa-install.css') }}?v={{ $pwaVersion }}">
<script src="{{ asset('js/erp-pwa-install.js') }}?v={{ $pwaVersion }}" defer></script>

<script>
    (function () {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        var swUrl = @json(asset('sw-erp.js'));
        swUrl += (swUrl.indexOf('?') >= 0 ? '&' : '?') + 'v={{ $pwaVersion }}';

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
            }).then(function (reg) {
                if (reg && reg.update) {
                    reg.update().catch(function () {});
                }
            }).catch(function () {});
        });
    })();
</script>
