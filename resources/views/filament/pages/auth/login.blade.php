<div class="unitec-login-root">
    <div class="unitec-login">
        <div class="unitec-login__modal">
            <div class="unitec-login__left">
                <h1 class="unitec-login__welcome">Bem-vindo ao Sistema</h1>

                <div
                    class="unitec-login__logo"
                    @if ($this->getEmpresaLogoUrl()) aria-label="Logomarca da empresa" @else aria-hidden="true" @endif
                >
                    @if ($url = $this->getEmpresaLogoUrl())
                        <img
                            src="{{ $url }}"
                            alt="Logomarca da empresa"
                            class="unitec-login__logo-img"
                            wire:key="login-logo-{{ md5($url) }}"
                        >
                    @endif
                </div>

                <div class="unitec-login__meta">
                    <p class="unitec-login__version" aria-label="Versão do sistema">
                        Versão {{ config('unitec.versao') }}
                    </p>
                    <p class="unitec-login__copyright">
                        © Unitecnologia Sistemas LTDA · Licenciado até {{ config('unitec.licenca') }}
                    </p>
                </div>
            </div>

            <div class="unitec-login__right">
                <div class="unitec-login__right-header">
                    <p class="unitec-login__instruction">Digite seu Usuário e sua Senha:</p>
                    <button
                        type="button"
                        class="unitec-login__close"
                        wire:click="cancel"
                        aria-label="Fechar"
                    >
                        &times;
                    </button>
                </div>

                <div class="unitec-login__form">
                    {{ $this->content }}
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />

    {{-- Override final — garante contraste após CSS do Filament --}}
    <style>
        .unitec-login__form .fi-select-input-value-label,
        .unitec-login__form .fi-input-wrp input {
            color: #000 !important;
            -webkit-text-fill-color: #000 !important;
            font-weight: 700 !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="button"],
        .unitec-login__form .fi-ac .unitec-login__btn-cancel {
            background: #cbd5e1 !important;
            border: 2px solid #334155 !important;
            color: #000 !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="button"] *,
        .unitec-login__form .fi-ac .unitec-login__btn-cancel * {
            color: #000 !important;
            opacity: 1 !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="submit"],
        .unitec-login__form .fi-ac .fi-color-primary.fi-ac-btn-action {
            background: #2563eb !important;
            border-color: #1d4ed8 !important;
            color: #000 !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="submit"] *,
        .unitec-login__form .fi-ac .fi-color-primary.fi-ac-btn-action * {
            color: #000 !important;
        }
    </style>
</div>