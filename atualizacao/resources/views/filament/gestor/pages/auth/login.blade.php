<div class="gestor-login-root">
    <div class="gestor-login" aria-label="Acesso do gestor">
        <header class="gestor-login__brand">
            <p class="gestor-login__eyebrow">Unitec ERP</p>
            <h1 class="gestor-login__title">Executivo</h1>
            <p class="gestor-login__hint">Acompanhe a empresa em 30 segundos</p>
        </header>

        <div class="gestor-login__card">
            <div class="gestor-login__form" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
                {{ $this->content }}
            </div>
        </div>

        <p class="gestor-login__foot">Acesse pelo navegador do celular · online</p>
    </div>

    <x-filament-actions::modals />

    <style>
        .gestor-login__form .unitec-login__senha-wrap input {
            -webkit-text-security: disc !important;
            text-security: disc !important;
        }

        .gestor-login__form .fi-input-wrp,
        .gestor-login__form .fi-select-input {
            border-radius: 12px !important;
        }

        .gestor-login__form .fi-fo-field-wrp-label {
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.04em !important;
            text-transform: uppercase !important;
            color: #64748b !important;
        }

        .gestor-login__form .fi-ac {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 0.65rem !important;
            margin-top: 0.75rem !important;
        }

        .gestor-login__form .fi-ac .fi-ac-btn-action[type="submit"],
        .gestor-login__form .fi-ac .fi-color-primary.fi-ac-btn-action {
            background: linear-gradient(180deg, #2f6fbf 0%, #1e5a9e 100%) !important;
            border-color: #164a82 !important;
            color: #fff !important;
            border-radius: 12px !important;
            min-height: 48px !important;
            font-weight: 700 !important;
        }

        .gestor-login__form .fi-ac .fi-ac-btn-action[type="button"],
        .gestor-login__form .gestor-login__btn-secondary {
            background: #e2e8f0 !important;
            border: 1px solid #94a3b8 !important;
            color: #0f172a !important;
            border-radius: 12px !important;
            min-height: 48px !important;
        }
    </style>

    <div class="gestor-login-boot" id="gestor-login-boot" hidden aria-live="polite">
        <div class="gestor-login-boot__card">
            <div class="gestor-login-boot__spinner" aria-hidden="true"></div>
            <p class="gestor-login-boot__title">Entrando…</p>
            <p class="gestor-login-boot__hint">Abrindo o Executivo</p>
        </div>
    </div>

    <style>
        .gestor-login-boot {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: grid;
            place-items: center;
            background: rgba(15, 23, 42, 0.42);
            backdrop-filter: blur(2px);
        }

        .gestor-login-boot[hidden] {
            display: none !important;
        }

        .gestor-login-boot__card {
            width: min(18rem, calc(100vw - 2rem));
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.22);
            padding: 1.25rem 1.1rem;
            text-align: center;
        }

        .gestor-login-boot__spinner {
            width: 2rem;
            height: 2rem;
            margin: 0 auto 0.75rem;
            border-radius: 999px;
            border: 3px solid #dbeafe;
            border-top-color: #1e5a9e;
            animation: gestor-login-spin 0.7s linear infinite;
        }

        .gestor-login-boot__title {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: #0f2847;
        }

        .gestor-login-boot__hint {
            margin: 0.25rem 0 0;
            font-size: 0.78rem;
            color: #64748b;
        }

        @keyframes gestor-login-spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <script>
        (function () {
            var root = document.querySelector('.gestor-login-root');
            if (!root) return;

            function revealActions() {
                var actions = root.querySelector('.fi-ac');
                if (!actions) return;
                try {
                    actions.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
                } catch (e) {
                    actions.scrollIntoView(false);
                }
            }

            root.addEventListener('focusin', function (e) {
                var t = e.target;
                if (!t || (t.tagName !== 'INPUT' && t.tagName !== 'SELECT' && t.tagName !== 'TEXTAREA')) return;
                setTimeout(revealActions, 280);
                setTimeout(revealActions, 650);
            });

            // Login do ERP chama UnitecLoginBoot.succeed(url) após autenticar.
            // Sem isso a sessão fica autenticada e a tela não redireciona.
            var boot = document.getElementById('gestor-login-boot');

            function showBoot() {
                if (boot) boot.hidden = false;
            }

            function hideBoot() {
                if (boot) boot.hidden = true;
            }

            function succeed(url) {
                showBoot();
                var target = (typeof url === 'string' && url.trim() !== '')
                    ? url
                    : @json(url('/gestor'));
                window.setTimeout(function () {
                    window.location.replace(target);
                }, 120);
            }

            window.UnitecLoginBoot = {
                __ready: true,
                show: showBoot,
                hide: hideBoot,
                succeed: succeed,
            };
        })();
    </script>
</div>
