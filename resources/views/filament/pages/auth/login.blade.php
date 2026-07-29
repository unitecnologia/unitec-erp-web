<div class="unitec-login-root">
    <div class="unitec-login" aria-label="Tela de acesso">
        <div class="unitec-login__modal">
            <div class="unitec-login__left">
                <p class="unitec-login__eyebrow">Acesso ao ERP</p>
                <h1 class="unitec-login__welcome">Bem-vindo ao Sistema</h1>
                <p class="unitec-login__tagline">Gestão completa para o seu negócio</p>

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
                    <p class="unitec-login__version" aria-label="Versão do sistema">
                        Versão {{ config('unitec.versao') }}
                    </p>
                    <p class="unitec-login__copyright">
                        © Unitecnologia Sistemas LTDA
                    </p>
                </div>
            </div>

            <div class="unitec-login__right">
                <div class="unitec-login__right-header">
                    <div>
                        <p class="unitec-login__instruction">Entrar</p>
                        <p class="unitec-login__instruction-hint">Informe empresa, usuário e senha</p>
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
</div>
