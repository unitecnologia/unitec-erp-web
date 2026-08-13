@php

    use App\Models\Empresa;



    $empresas = Empresa::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id');

    $perfis = $this->userProfileOptions();

    $usuariosCopia = $this->userCopyPermissionUsers();
    $usuarioCopiaSelecionado = (int) ($this->userForm['copiar_permissoes_de'] ?? 0);

@endphp



@if ($this->userModalOpen)

    <div

        class="erp-lookup-modal erp-usuario-form-modal"

        wire:keydown.escape.window="closeUserModal"

        wire:keydown.f10.window.prevent="saveUser"

    >

        <div class="erp-lookup-modal__backdrop" wire:click="closeUserModal"></div>



        <div

            class="erp-lookup-modal__window erp-usuario-form-modal__window"

            role="dialog"

            aria-modal="true"

            aria-labelledby="erp-usuario-form-title"

        >

            <div class="erp-lookup-modal__titlebar">

                <span id="erp-usuario-form-title">
                    {{ \App\Support\Erp\ErpOnboarding::step() === \App\Support\Erp\ErpOnboarding::STEP_USUARIO
                        ? 'Primeiro acesso — Cadastro de Usuário'
                        : 'Sistema ERP — Usuários' }}
                </span>

                <button

                    type="button"

                    class="erp-lookup-modal__close"

                    wire:click="closeUserModal"

                    title="Fechar"

                >✕</button>

            </div>



            <div class="erp-lookup-modal__body erp-usuario-form-modal__body">

                <div class="erp-usuario-form-modal__columns">

                    <div class="erp-pcad-form erp-usuario-form-modal__form erp-usuario-form-modal__col erp-usuario-form-modal__col--left">

                        <div class="erp-pcad-form__row">

                            <label class="erp-pcad-form__label" for="usuario-nome">Usuário</label>

                            <input

                                id="usuario-nome"

                                type="text"

                                wire:model="userForm.name"

                                class="erp-pcad-form__input erp-pcad-form__input--grow"

                                data-erp-uppercase

                                autocomplete="off"

                                autofocus

                            >

                            @error('userForm.name')

                                <span class="erp-usuario-form-modal__error">{{ $message }}</span>

                            @enderror

                        </div>



                        <div class="erp-pcad-form__row">

                            <label class="erp-pcad-form__label" for="usuario-senha">Senha</label>

                            <div class="erp-pcad-form__password-wrap erp-pcad-form__input--grow">

                                <input

                                    id="usuario-senha"

                                    type="text"

                                    wire:model="userForm.password"

                                    data-erp-password-mask="plain"

                                    class="erp-pcad-form__input erp-pcad-form__input--password is-masked"

                                    placeholder="{{ $this->userModalRecordId ? 'Deixe em branco para manter' : '' }}"

                                    autocomplete="off"

                                >

                                <button

                                    type="button"

                                    class="erp-pcad-form__password-toggle"

                                    data-erp-password-toggle="usuario-senha"

                                    title="Exibir senha"

                                    aria-label="Exibir senha"

                                >

                                    <svg class="erp-pcad-form__password-icon erp-pcad-form__password-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>

                                        <circle cx="12" cy="12" r="3"/>

                                    </svg>

                                    <svg class="erp-pcad-form__password-icon erp-pcad-form__password-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                                        <path d="M3 3l18 18"/>

                                        <path d="M10.58 10.58a2 2 0 0 0 2.83 2.83"/>

                                        <path d="M9.88 5.09A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.12 4.25"/>

                                        <path d="M6.11 6.11A17.2 17.2 0 0 0 2 12s3.5 7 10 7a10.9 10.9 0 0 0 4.11-.79"/>

                                    </svg>

                                </button>

                            </div>

                            @error('userForm.password')

                                <span class="erp-usuario-form-modal__error">{{ $message }}</span>

                            @enderror

                        </div>



                        <div class="erp-pcad-form__row">

                            <label class="erp-pcad-form__label" for="usuario-senha-conf">Confirmar Senha</label>

                            <div class="erp-pcad-form__password-wrap erp-pcad-form__input--grow">

                                <input

                                    id="usuario-senha-conf"

                                    type="text"

                                    wire:model="userForm.password_confirmation"

                                    data-erp-password-mask="plain"

                                    class="erp-pcad-form__input erp-pcad-form__input--password is-masked"

                                    autocomplete="off"

                                >

                                <button

                                    type="button"

                                    class="erp-pcad-form__password-toggle"

                                    data-erp-password-toggle="usuario-senha-conf"

                                    title="Exibir senha"

                                    aria-label="Exibir senha"

                                >

                                    <svg class="erp-pcad-form__password-icon erp-pcad-form__password-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>

                                        <circle cx="12" cy="12" r="3"/>

                                    </svg>

                                    <svg class="erp-pcad-form__password-icon erp-pcad-form__password-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                                        <path d="M3 3l18 18"/>

                                        <path d="M10.58 10.58a2 2 0 0 0 2.83 2.83"/>

                                        <path d="M9.88 5.09A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.12 4.25"/>

                                        <path d="M6.11 6.11A17.2 17.2 0 0 0 2 12s3.5 7 10 7a10.9 10.9 0 0 0 4.11-.79"/>

                                    </svg>

                                </button>

                            </div>

                            @error('userForm.password_confirmation')

                                <span class="erp-usuario-form-modal__error">{{ $message }}</span>

                            @enderror

                        </div>



                        <div class="erp-pcad-form__row">

                            <label class="erp-pcad-form__label" for="usuario-senha-app">Senha App Força de Vendas</label>

                            <div class="erp-pcad-form__password-wrap erp-pcad-form__input--grow">

                                <input

                                    id="usuario-senha-app"

                                    type="text"

                                    wire:model="userForm.senha_app_forca_vendas"

                                    data-erp-password-mask="plain"

                                    class="erp-pcad-form__input erp-pcad-form__input--password is-masked"

                                    autocomplete="off"

                                >

                                <button

                                    type="button"

                                    class="erp-pcad-form__password-toggle"

                                    data-erp-password-toggle="usuario-senha-app"

                                    title="Exibir senha"

                                    aria-label="Exibir senha"

                                >

                                    <svg class="erp-pcad-form__password-icon erp-pcad-form__password-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>

                                        <circle cx="12" cy="12" r="3"/>

                                    </svg>

                                    <svg class="erp-pcad-form__password-icon erp-pcad-form__password-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                                        <path d="M3 3l18 18"/>

                                        <path d="M10.58 10.58a2 2 0 0 0 2.83 2.83"/>

                                        <path d="M9.88 5.09A10.94 10.94 0 0 1 12 5c6.5 0 10 7 10 7a17.2 17.2 0 0 1-3.12 4.25"/>

                                        <path d="M6.11 6.11A17.2 17.2 0 0 0 2 12s3.5 7 10 7a10.9 10.9 0 0 0 4.11-.79"/>

                                    </svg>

                                </button>

                            </div>

                            @error('userForm.senha_app_forca_vendas')

                                <span class="erp-usuario-form-modal__error">{{ $message }}</span>

                            @enderror

                        </div>



                        <div class="erp-pcad-form__row erp-usuario-form-modal__vinculo-row">
                            <label class="erp-pcad-form__label">Operador (RH)</label>
                            <div class="erp-vform__field-stack">
                                <div class="erp-usuario-form-modal__vinculo {{ $this->userOperadorVinculado() ? 'is-linked' : 'is-empty' }}">
                                    {{ $this->userOperadorVinculoInfo() }}
                                </div>
                                <p class="erp-usuario-form-modal__hint">
                                    Somente leitura — vínculo em <strong>RH → Funcionários → aba Operador</strong>.
                                </p>
                            </div>
                        </div>



                        <div class="erp-pcad-form__row">
                            <label class="erp-pcad-form__label" for="usuario-empresa">Empresa padrão</label>
                            <select id="usuario-empresa" wire:model="userForm.empresa_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
                                @foreach ($empresas as $id => $nome)
                                    <option value="{{ $id }}">{{ $nome }}</option>
                                @endforeach
                            </select>
                            @error('userForm.empresa_id')
                                <span class="erp-usuario-form-modal__error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="erp-pcad-form__row">
                            <label class="erp-pcad-form__label">Empresas liberadas</label>
                            @php
                                $empresaCodigos = $this->userEmpresaCodigos();
                                $empresaNomes = $this->userEmpresaOptions();
                            @endphp
                            <div
                                class="erp-vform__multi"
                                x-data="{
                                    open: false,
                                    q: '',
                                    codigos: @js($empresaCodigos),
                                    nomes: @js($empresaNomes),
                                    label(id) {
                                        const key = String(id);
                                        const codigo = this.codigos[key] ?? this.codigos[id] ?? '';
                                        const nome = this.nomes[key] ?? this.nomes[id] ?? '';
                                        return codigo && nome ? (codigo + ' — ' + nome) : (nome || codigo || key);
                                    }
                                }"
                                @click.outside="open = false"
                                @keydown.escape.stop="open = false"
                            >
                                <button type="button" class="erp-vform__multi-toggle" @click="open = !open">
                                    <span
                                        class="erp-vform__multi-summary"
                                        :class="{ 'erp-vform__multi-summary--empty': !($wire.userForm.empresas || []).length }"
                                        x-text="($wire.userForm.empresas || []).length
                                            ? $wire.userForm.empresas.map(id => label(id)).filter(Boolean).join(', ')
                                            : 'Selecione as empresas...'"
                                    ></span>
                                    <span class="erp-vform__multi-count" x-show="($wire.userForm.empresas || []).length"
                                        x-text="($wire.userForm.empresas || []).length"></span>
                                    <svg class="erp-vform__multi-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="open ? 'transform:rotate(180deg)' : ''" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                </button>

                                <div class="erp-vform__multi-panel" x-show="open" x-cloak x-transition.opacity>
                                    @if (count($empresaCodigos))
                                        <input type="text" class="erp-vform__multi-search" placeholder="Pesquisar empresa..." x-model="q" @click.stop>
                                        <div class="erp-vform__multi-list">
                                            @foreach ($empresaNomes as $id => $nome)
                                                <label
                                                    class="erp-vform__check"
                                                    x-show="q === '' || @js(mb_strtolower(($empresaCodigos[$id] ?? '').' '.$nome, 'UTF-8')).includes(q.toLowerCase())"
                                                >
                                                    <input type="checkbox" value="{{ $id }}" wire:model="userForm.empresas">
                                                    <strong>{{ $empresaCodigos[$id] ?? '' }}</strong> {{ $nome }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="erp-vform__empresas-empty">Nenhuma empresa cadastrada.</span>
                                    @endif
                                </div>
                            </div>
                            @error('userForm.empresas')
                                <span class="erp-usuario-form-modal__error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="erp-pcad-form__row">
                            <label class="erp-pcad-form__label" for="usuario-perfil">Perfil</label>

                            <select id="usuario-perfil" wire:model="userForm.erp_profile_id" class="erp-pcad-form__select erp-pcad-form__input--grow">

                                <option value="">— Nenhum —</option>

                                @foreach ($perfis as $id => $nome)

                                    <option value="{{ $id }}">{{ $nome }}</option>

                                @endforeach

                            </select>

                        </div>



                        <div class="erp-pcad-form__row">

                            <label class="erp-pcad-form__label" for="usuario-admin">Administrador</label>

                            <select id="usuario-admin" wire:model="userForm.is_admin" class="erp-pcad-form__select erp-pcad-form__select--sn">

                                <option value="N">NAO</option>

                                <option value="S">SIM</option>

                            </select>

                        </div>

                    </div>



                    <div class="erp-usuario-form-modal__col erp-usuario-form-modal__col--right">
                        <label class="erp-pcad__check erp-usuario-form-modal__copy-check">
                            <input
                                type="checkbox"
                                @checked($this->copiarPermissoesAtivo)
                                wire:change="setCopiarPermissoes($event.target.checked)"
                            >
                            <span>Copiar Permissões do Usuário</span>
                        </label>

                        @if ($this->copiarPermissoesAtivo)
                            <fieldset class="erp-pcad__group erp-usuario-form-modal__copy-panel">
                                <legend class="erp-pcad__group-title">Selecione um dos usuários.</legend>

                                <div class="erp-lookup-modal__grid-wrap erp-usuario-form-modal__copy-grid-wrap">
                                    <table class="erp-lookup-modal__grid erp-usuario-form-modal__copy-grid">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="erp-lookup-modal__grid-head erp-lookup-modal__grid-head--active">
                                                    &gt;&gt;Nome
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($usuariosCopia as $usuario)
                                                <tr
                                                    wire:key="usuario-copia-{{ $usuario['id'] }}"
                                                    wire:click="selectCopyPermissionsUser({{ $usuario['id'] }})"
                                                    @class([
                                                        'erp-lookup-modal__row',
                                                        'erp-lookup-modal__row--selected' => $usuarioCopiaSelecionado === (int) $usuario['id'],
                                                    ])
                                                >
                                                    <td>{{ $usuario['name'] }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="erp-lookup-modal__empty">Nenhum usuário cadastrado.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </fieldset>

                            @error('userForm.copiar_permissoes_de')
                                <span class="erp-usuario-form-modal__error">{{ $message }}</span>
                            @enderror
                        @endif

                        <div class="erp-usuario-form-modal__status-checks">
                            <label class="erp-pcad__check erp-usuario-form-modal__status-check">

                                <input

                                    type="checkbox"

                                    wire:model="userForm.ativo"

                                    true-value="S"

                                    false-value="N"

                                >

                                <span>ATIVO</span>

                            </label>

                        </div>

                    </div>

                </div>

            </div>



            <div class="erp-lookup-modal__actions erp-pcad-actions erp-usuario-form-modal__actions">

                <button type="button" wire:click="saveUser" class="erp-pcad-actions__btn" data-erp-key="F10">

                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>

                    <span class="erp-pcad-actions__label"><kbd>F10</kbd> | Salvar</span>

                </button>

                <button type="button" wire:click="closeUserModal" class="erp-pcad-actions__btn" data-erp-key="Escape">

                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>

                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>

                </button>

            </div>

        </div>

    </div>



    @include('filament.components.erp.form-scripts')

@endif

