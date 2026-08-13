<div>
    @if ($alterarSenhaOpen)
        <div
            class="erp-account-modal"
            wire:keydown.escape.window="closeAlterarSenha"
            role="presentation"
        >
            <div class="erp-account-modal__backdrop" wire:click="closeAlterarSenha"></div>
            <div class="erp-account-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-alterar-senha-title">
                <header class="erp-account-modal__header">
                    <div>
                        <h2 id="erp-alterar-senha-title" class="erp-account-modal__title">Alterar senha</h2>
                        <p class="erp-account-modal__subtitle">Atualize a senha do usuário logado</p>
                    </div>
                    <button type="button" class="erp-account-modal__close" wire:click="closeAlterarSenha" aria-label="Fechar">✕</button>
                </header>

                <form class="erp-account-modal__body" wire:submit="salvarNovaSenha">
                    <div class="erp-account-modal__field">
                        <label for="erp-senha-atual">Senha atual</label>
                        <div class="erp-account-modal__password-wrap">
                            <input
                                id="erp-senha-atual"
                                type="password"
                                class="erp-account-modal__input erp-account-modal__input--password"
                                wire:model="senhaAtual"
                                autocomplete="current-password"
                                autofocus
                            >
                            <button
                                type="button"
                                class="erp-account-modal__password-toggle"
                                data-erp-password-toggle="erp-senha-atual"
                                aria-label="Exibir senha"
                                title="Exibir senha"
                            >
                                <svg class="erp-account-modal__password-icon erp-account-modal__password-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg class="erp-account-modal__password-icon erp-account-modal__password-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('senhaAtual') <span class="erp-account-modal__error">{{ $message }}</span> @enderror
                    </div>

                    <div class="erp-account-modal__field">
                        <label for="erp-senha-nova">Nova senha</label>
                        <div class="erp-account-modal__password-wrap">
                            <input
                                id="erp-senha-nova"
                                type="password"
                                class="erp-account-modal__input erp-account-modal__input--password"
                                wire:model="senhaNova"
                                autocomplete="new-password"
                            >
                            <button
                                type="button"
                                class="erp-account-modal__password-toggle"
                                data-erp-password-toggle="erp-senha-nova"
                                aria-label="Exibir senha"
                                title="Exibir senha"
                            >
                                <svg class="erp-account-modal__password-icon erp-account-modal__password-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg class="erp-account-modal__password-icon erp-account-modal__password-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('senhaNova') <span class="erp-account-modal__error">{{ $message }}</span> @enderror
                    </div>

                    <div class="erp-account-modal__field">
                        <label for="erp-senha-confirmacao">Confirmar nova senha</label>
                        <div class="erp-account-modal__password-wrap">
                            <input
                                id="erp-senha-confirmacao"
                                type="password"
                                class="erp-account-modal__input erp-account-modal__input--password"
                                wire:model="senhaConfirmacao"
                                autocomplete="new-password"
                            >
                            <button
                                type="button"
                                class="erp-account-modal__password-toggle"
                                data-erp-password-toggle="erp-senha-confirmacao"
                                aria-label="Exibir senha"
                                title="Exibir senha"
                            >
                                <svg class="erp-account-modal__password-icon erp-account-modal__password-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg class="erp-account-modal__password-icon erp-account-modal__password-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('senhaConfirmacao') <span class="erp-account-modal__error">{{ $message }}</span> @enderror
                    </div>

                    <footer class="erp-account-modal__footer">
                        <button type="button" class="erp-account-modal__btn erp-account-modal__btn--ghost" wire:click="closeAlterarSenha">
                            Cancelar
                        </button>
                        <button type="submit" class="erp-account-modal__btn erp-account-modal__btn--primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="salvarNovaSenha">Salvar senha</span>
                            <span wire:loading wire:target="salvarNovaSenha">Salvando…</span>
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    @endif

    @if ($trocarUsuarioOpen)
        <div
            class="erp-account-modal"
            wire:keydown.escape.window="closeTrocarUsuario"
            role="presentation"
        >
            <div class="erp-account-modal__backdrop" wire:click="closeTrocarUsuario"></div>
            <div class="erp-account-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-trocar-usuario-title">
                <header class="erp-account-modal__header">
                    <div>
                        <h2 id="erp-trocar-usuario-title" class="erp-account-modal__title">Trocar de usuário</h2>
                        <p class="erp-account-modal__subtitle">Entre com outro usuário na mesma empresa</p>
                    </div>
                    <button type="button" class="erp-account-modal__close" wire:click="closeTrocarUsuario" aria-label="Fechar">✕</button>
                </header>

                <form class="erp-account-modal__body" wire:submit="confirmarTrocaUsuario">
                    <div class="erp-account-modal__field">
                        <label for="erp-trocar-user">Usuário</label>
                        <select id="erp-trocar-user" class="erp-account-modal__input" wire:model.live="trocarUserId">
                            <option value="">Selecione…</option>
                            @foreach ($usuariosOptions as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                        @error('trocarUserId') <span class="erp-account-modal__error">{{ $message }}</span> @enderror
                    </div>

                    <div class="erp-account-modal__field">
                        <label for="erp-trocar-senha">Senha</label>
                        <input
                            id="erp-trocar-senha"
                            type="password"
                            class="erp-account-modal__input"
                            wire:model="trocarSenha"
                            autocomplete="current-password"
                        >
                        @error('trocarSenha') <span class="erp-account-modal__error">{{ $message }}</span> @enderror
                    </div>

                    <footer class="erp-account-modal__footer erp-account-modal__footer--split">
                        <button type="button" class="erp-account-modal__btn erp-account-modal__btn--ghost" wire:click="irParaLogin">
                            Ir para login
                        </button>
                        <div class="erp-account-modal__footer-right">
                            <button type="button" class="erp-account-modal__btn erp-account-modal__btn--ghost" wire:click="closeTrocarUsuario">
                                Cancelar
                            </button>
                            <button type="submit" class="erp-account-modal__btn erp-account-modal__btn--primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="confirmarTrocaUsuario">Entrar</span>
                                <span wire:loading wire:target="confirmarTrocaUsuario">Trocando…</span>
                            </button>
                        </div>
                    </footer>
                </form>
            </div>
        </div>
    @endif
</div>
