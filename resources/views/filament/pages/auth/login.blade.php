<div class="unitec-login-root">
    <div class="unitec-login" aria-label="Tela de acesso">
        <div class="unitec-login__modal">
            <div class="unitec-login__left">
                <p class="unitec-login__eyebrow">Acesso ao ERP</p>
                <h1 class="unitec-login__welcome">Bem-vindo ao Sistema</h1>
                <p class="unitec-login__tagline">GestÃ£o completa para o seu negÃ³cio</p>

                <div class="unitec-login__brand" aria-label="Unitecnologia Sistemas">
                    <img
                        src="{{ asset('img/erp/brand/unitecnologia-logo.png') }}"
                        alt="Unitecnologia Sistemas"
                        class="unitec-login__brand-img"
                        width="480"
                        height="160"
                        decoding="async"
                    >
                </div>

                <div class="unitec-login__meta">
                    <p class="unitec-login__version" aria-label="VersÃ£o do sistema">
                        VersÃ£o {{ config('unitec.versao') }}
                    </p>
                    <p class="unitec-login__copyright">
                        Â© Unitecnologia Sistemas LTDA
                    </p>
                </div>
            </div>

            <div class="unitec-login__right">
                <div class="unitec-login__right-header">
                    <div>
                        <p class="unitec-login__instruction">Entrar</p>
                        <p class="unitec-login__instruction-hint">Informe empresa, usuÃ¡rio e senha</p>
                    </div>
                    <button
                        type="button"
                        class="unitec-login__close"
                        wire:click="cancel"
                        aria-label="Fechar"
                    >
                        &times;
                    </button>
                </div>

                <div class="unitec-login__form" autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-bwignore="true">
                    {{ $this->content }}
                </div>

                <div class="unitec-login__pwa">
                    <button type="button" class="unitec-login__pwa-btn" data-unitec-login-install-app>
                        Instalar no Windows
                    </button>
                    <p class="unitec-login__pwa-hint">Abre como aplicativo no computador (Chrome / Edge).</p>
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />

    {{-- Override final — garante contraste após CSS do Filament --}}
    <style>
        .unitec-login__form .unitec-login__senha-wrap input {
            -webkit-text-security: disc !important;
            text-security: disc !important;
        }

        .unitec-login__form .unitec-login__senha-wrap.is-revealed input {
            -webkit-text-security: none !important;
            text-security: none !important;
        }

        .unitec-login__form .fi-select-input-value-label,
        .unitec-login__form .fi-input-wrp input {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
            font-weight: 650 !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="button"],
        .unitec-login__form .fi-ac .unitec-login__btn-cancel {
            background: #e2e8f0 !important;
            border: 1px solid #94a3b8 !important;
            color: #0f172a !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="button"] *,
        .unitec-login__form .fi-ac .unitec-login__btn-cancel * {
            color: #0f172a !important;
            opacity: 1 !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="submit"],
        .unitec-login__form .fi-ac .fi-color-primary.fi-ac-btn-action {
            background: linear-gradient(180deg, #2f6fbf 0%, #1e5a9e 100%) !important;
            border-color: #164a82 !important;
            color: #ffffff !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="submit"] *,
        .unitec-login__form .fi-ac .fi-color-primary.fi-ac-btn-action * {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
        }
    </style>

    <script>
        (function () {
            if (window.UnitecLoginBoot && window.UnitecLoginBoot.__ready) {
                return;
            }

            const logoUrl = @json(asset('img/erp/brand/unitecnologia-logo.png'));
            const messages = [
                'Validando acessoâ€¦',
                'Preparando ambienteâ€¦',
                'Carregando mÃ³dulosâ€¦',
                'Abrindo o sistemaâ€¦',
            ];

            let progress = 0;
            let raf = 0;
            let messageTimer = 0;
            let stuckTimer = 0;
            let messageIndex = 0;
            let finishing = false;
            let active = false;
            let overlay = null;

            function ensureOverlay() {
                if (overlay && document.body.contains(overlay)) {
                    return overlay;
                }

                overlay = document.createElement('div');
                overlay.id = 'unitec-login-boot';
                overlay.className = 'unitec-login-boot';
                overlay.setAttribute('role', 'status');
                overlay.setAttribute('aria-live', 'polite');
                overlay.setAttribute('aria-busy', 'false');
                overlay.setAttribute('aria-hidden', 'true');
                overlay.innerHTML =
                    '<div class="unitec-login-boot__glow" aria-hidden="true"></div>' +
                    '<div class="unitec-login-boot__card">' +
                    '<img src="' + logoUrl + '" alt="" class="unitec-login-boot__logo" width="280" height="94" decoding="async">' +
                    '<p class="unitec-login-boot__eyebrow">Unitec ERP</p>' +
                    '<h2 class="unitec-login-boot__title">Abrindo o sistema</h2>' +
                    '<p class="unitec-login-boot__status" data-unitec-boot-status>Validando acessoâ€¦</p>' +
                    '<div class="unitec-login-boot__track" aria-hidden="true">' +
                    '<div class="unitec-login-boot__bar" data-unitec-boot-bar></div>' +
                    '</div>' +
                    '<p class="unitec-login-boot__percent"><span data-unitec-boot-pct>0</span>%</p>' +
                    '</div>';

                document.body.appendChild(overlay);
                return overlay;
            }

            function qs(sel) {
                return ensureOverlay().querySelector(sel);
            }

            function paint() {
                const b = qs('[data-unitec-boot-bar]');
                const p = qs('[data-unitec-boot-pct]');
                if (b) b.style.width = Math.max(0, Math.min(100, progress)) + '%';
                if (p) p.textContent = String(Math.round(Math.max(0, Math.min(100, progress))));
            }

            function setMessage(text) {
                const s = qs('[data-unitec-boot-status]');
                if (s) s.textContent = text;
            }

            function tick() {
                if (!active || finishing) return;

                const ceiling = 88;
                if (progress < ceiling) {
                    const remaining = ceiling - progress;
                    const step = Math.max(0.08, remaining * 0.028);
                    progress = Math.min(ceiling, progress + step);
                    paint();
                }

                raf = window.requestAnimationFrame(tick);
            }

            function startMessages() {
                messageIndex = 0;
                setMessage(messages[0]);
                window.clearInterval(messageTimer);
                messageTimer = window.setInterval(function () {
                    if (!active || finishing) return;
                    messageIndex = Math.min(messages.length - 1, messageIndex + 1);
                    setMessage(messages[messageIndex]);
                }, 1500);
            }

            function armStuckWatchdog() {
                window.clearTimeout(stuckTimer);
                stuckTimer = window.setTimeout(function () {
                    if (active && !finishing) {
                        hide(true);
                    }
                }, 20000);
            }

            function show() {
                const el = ensureOverlay();

                if (!active) {
                    active = true;
                    finishing = false;
                    progress = 6;
                    startMessages();
                    window.cancelAnimationFrame(raf);
                    raf = window.requestAnimationFrame(tick);
                    armStuckWatchdog();
                }

                paint();
                el.hidden = false;
                el.setAttribute('aria-hidden', 'false');
                el.setAttribute('aria-busy', 'true');
                el.classList.add('is-visible');
                document.documentElement.classList.add('unitec-login-booting');
            }

            function hide(force) {
                if (finishing && !force) {
                    return;
                }

                active = false;
                finishing = false;
                window.cancelAnimationFrame(raf);
                window.clearInterval(messageTimer);
                window.clearTimeout(stuckTimer);

                const el = ensureOverlay();
                el.classList.remove('is-visible');
                el.hidden = true;
                el.setAttribute('aria-hidden', 'true');
                el.setAttribute('aria-busy', 'false');
                document.documentElement.classList.remove('unitec-login-booting');
                progress = 0;
                paint();
            }

            function hasLoginErrors() {
                return !!document.querySelector(
                    '.unitec-login__form .fi-fo-field-wrp-error-message, ' +
                    '.unitec-login__form .fi-fo-field-wrp-error, ' +
                    '.unitec-login__form .fi-fo-field-wrp-error-list, ' +
                    '.unitec-login__form [data-validation-error], ' +
                    '.unitec-login__form .fi-fo-field-wrp-label .text-danger-600, ' +
                    '.unitec-login__form .fi-fo-field-wrp [role="alert"]'
                );
            }

            function formReadyForLogin() {
                const root = document.querySelector('.unitec-login__form');
                if (!root) {
                    return true;
                }

                const fields = root.querySelectorAll('select[required], input[required], textarea[required]');
                for (let i = 0; i < fields.length; i++) {
                    const field = fields[i];
                    if (!field.checkValidity()) {
                        try {
                            field.reportValidity();
                        } catch (e) {}
                        return false;
                    }
                }

                const form = root.closest('form') || root.querySelector('form');
                if (form && typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    try {
                        form.reportValidity();
                    } catch (e) {}
                    return false;
                }

                return true;
            }

            function tryShowBoot() {
                if (!formReadyForLogin()) {
                    hide(true);
                    return;
                }

                show();
            }

            function succeed(url) {
                show();
                finishing = true;
                window.clearInterval(messageTimer);
                window.clearTimeout(stuckTimer);
                setMessage('Quase lÃ¡â€¦');

                const target = typeof url === 'string' && url !== '' ? url : '/admin';
                const start = performance.now();
                const from = Math.max(progress, 40);
                progress = from;
                paint();
                const duration = 900;

                function finishFrame(now) {
                    const t = Math.min(1, (now - start) / duration);
                    const eased = 1 - Math.pow(1 - t, 3);
                    progress = from + (100 - from) * eased;
                    paint();

                    if (t < 1) {
                        raf = window.requestAnimationFrame(finishFrame);
                        return;
                    }

                    setMessage('Sistema pronto');
                    window.setTimeout(function () {
                        window.location.replace(target);
                    }, 220);
                }

                window.cancelAnimationFrame(raf);
                raf = window.requestAnimationFrame(finishFrame);
            }

            function isAuthenticateTrigger(target) {
                if (!(target instanceof Element)) return false;

                return !!target.closest(
                    '.unitec-login__form button[type="submit"], ' +
                    '.unitec-login__form .fi-ac-btn-action[type="submit"], ' +
                    '.unitec-login__form .fi-color-primary.fi-ac-btn-action'
                );
            }

            document.addEventListener('click', function (event) {
                if (isAuthenticateTrigger(event.target)) {
                    tryShowBoot();
                }
            }, true);

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') return;
                if (!event.target || !(event.target instanceof Element)) return;
                if (!event.target.closest('.unitec-login__form')) return;
                if (event.target.closest('.unitec-login__btn-cancel, .unitec-login__close')) return;
                tryShowBoot();
            }, true);

            document.addEventListener('invalid', function (event) {
                if (!event.target || !(event.target instanceof Element)) return;
                if (!event.target.closest('.unitec-login__form')) return;
                hide(true);
            }, true);

            function bindLivewire() {
                if (!window.Livewire || typeof Livewire.hook !== 'function') {
                    return;
                }

                Livewire.hook('request', function ({ fail }) {
                    fail(function () {
                        if (active && !finishing) {
                            hide(true);
                        }
                    });
                });

                Livewire.hook('commit', function ({ succeed, fail }) {
                    succeed(function () {
                        window.setTimeout(function () {
                            if (active && !finishing && hasLoginErrors()) {
                                hide(true);
                            }
                        }, 80);
                    });

                    fail(function () {
                        if (active && !finishing) {
                            hide(true);
                        }
                    });
                });

                Livewire.hook('morph.updated', function () {
                    if (!active || finishing) {
                        return;
                    }

                    window.setTimeout(function () {
                        if (active && !finishing && hasLoginErrors()) {
                            hide(true);
                        }
                    }, 40);
                });
            }

            if (window.Livewire) {
                bindLivewire();
            } else {
                document.addEventListener('livewire:init', bindLivewire);
            }

            window.UnitecLoginBoot = {
                __ready: true,
                show: show,
                hide: hide,
                succeed: succeed,
            };

            document.addEventListener('click', function (event) {
                const btn = event.target && event.target.closest
                    ? event.target.closest('[data-unitec-login-install-app]')
                    : null;
                if (! btn) {
                    return;
                }

                event.preventDefault();
                if (window.UnitecErpPwa) {
                    if (typeof window.UnitecErpPwa.show === 'function') {
                        window.UnitecErpPwa.show();
                    }
                    if (typeof window.UnitecErpPwa.install === 'function') {
                        void window.UnitecErpPwa.install();
                    }
                } else {
                    alert('Abra em Chrome/Edge (http://127.0.0.1:8000/admin) e use Instalar na barra de endereço.');
                }
            });
        })();
    </script>
</div>
