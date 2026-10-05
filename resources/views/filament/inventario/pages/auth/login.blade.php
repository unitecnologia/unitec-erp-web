<div class="inv-login-root">
    <div class="inv-login" aria-label="Acesso do inventário">
        <header class="inv-login__brand">
            <p class="inv-login__eyebrow">Unitec ERP</p>
            <h1 class="inv-login__title">Inventário</h1>
            <p class="inv-login__hint">Contagem física de estoque</p>
        </header>

        <div class="inv-login__card">
            @if (filled($deviceLimitError))
                <div class="inv-login__alert" role="alert">
                    <strong>Acesso bloqueado pela licença</strong>
                    <p>{{ $deviceLimitError }}</p>
                </div>
            @endif
            <div class="inv-login__form">
                {{ $this->content }}
            </div>
        </div>

        <p class="inv-login__foot">Use o celular, online. Este app não substitui o Executivo.</p>
    </div>

    <x-filament-actions::modals />

    <script>
        (function () {
            var boot = null;

            function succeed(url) {
                var target = '/inventario';
                if (typeof url === 'string' && url.trim() !== '') {
                    try {
                        var parsed = new URL(url.trim(), window.location.origin);
                        var host = (parsed.hostname || '').toLowerCase();
                        target = (host === '127.0.0.1' || host === 'localhost' || host === '[::1]')
                            ? parsed.pathname + parsed.search + parsed.hash
                            : parsed.href;
                    } catch (e) {
                        target = url.charAt(0) === '/' ? url : '/inventario';
                    }
                }
                window.setTimeout(function () {
                    window.location.replace(target);
                }, 80);
            }

            window.UnitecLoginBoot = {
                __ready: true,
                show: function () {},
                hide: function () {},
                succeed: succeed,
            };
        })();
    </script>
</div>
