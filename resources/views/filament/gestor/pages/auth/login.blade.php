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
            padding-right: 2.75rem !important;
        }

        .gestor-login__form .unitec-login__senha-wrap.is-revealed input {
            -webkit-text-security: none !important;
            text-security: none !important;
        }

        .gestor-login__form .unitec-login__senha-wrap .fi-input-wrp {
            position: relative !important;
        }

        .gestor-login__form .unitec-login__senha-toggle {
            position: absolute;
            top: 50%;
            right: 0.45rem;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 0.45rem;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            transform: translateY(-50%);
        }

        .gestor-login__form .unitec-login__senha-toggle:hover {
            color: #1e5a9e;
            background: rgba(30, 90, 158, 0.08);
        }

        .gestor-login__form .unitec-login__senha-toggle-icon {
            width: 1.15rem;
            height: 1.15rem;
            display: block;
        }

        .gestor-login__form .unitec-login__senha-toggle-icon--hide {
            display: none;
        }

        .gestor-login__form .unitec-login__senha-toggle.is-visible .unitec-login__senha-toggle-icon--show {
            display: none;
        }

        .gestor-login__form .unitec-login__senha-toggle.is-visible .unitec-login__senha-toggle-icon--hide {
            display: block;
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

    <script>
        (function bindUnitecLoginSenhaToggle() {
            if (window.__unitecLoginSenhaToggleBound) {
                return;
            }
            window.__unitecLoginSenhaToggleBound = true;

            var eyeShow = '<svg class="unitec-login__senha-toggle-icon unitec-login__senha-toggle-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>';
            var eyeHide = '<svg class="unitec-login__senha-toggle-icon unitec-login__senha-toggle-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>';

            function ensure() {
                document.querySelectorAll('.unitec-login__senha-wrap').forEach(function (wrap) {
                    if (wrap.querySelector('[data-unitec-login-senha-toggle]')) {
                        return;
                    }

                    var input = wrap.querySelector('input');
                    if (!input) {
                        return;
                    }

                    if (!input.id) {
                        input.id = 'unitec-login-senha';
                    }

                    var inputWrp = wrap.querySelector('.fi-input-wrp') || wrap;
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'unitec-login__senha-toggle';
                    btn.setAttribute('data-unitec-login-senha-toggle', '1');
                    btn.setAttribute('aria-label', 'Exibir senha');
                    btn.setAttribute('title', 'Exibir senha');
                    btn.innerHTML = eyeShow + eyeHide;
                    btn.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        var revealed = wrap.classList.toggle('is-revealed');
                        btn.classList.toggle('is-visible', revealed);
                        btn.setAttribute('aria-label', revealed ? 'Ocultar senha' : 'Exibir senha');
                        btn.setAttribute('title', revealed ? 'Ocultar senha' : 'Exibir senha');
                        input.focus();
                    });
                    inputWrp.appendChild(btn);
                });
            }

            document.addEventListener('DOMContentLoaded', ensure);
            document.addEventListener('livewire:navigated', ensure);
            document.addEventListener('livewire:init', function () {
                window.Livewire?.hook?.('morph.updated', function () {
                    ensure();
                });
            });
            if (document.readyState !== 'loading') {
                ensure();
            }
        })();
    </script>
</div>
