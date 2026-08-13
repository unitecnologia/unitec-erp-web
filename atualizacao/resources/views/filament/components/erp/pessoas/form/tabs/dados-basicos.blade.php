@php
    use App\Models\Person;
@endphp

<div
    class="erp-pcad-form erp-pessoas-panel"
    x-data="{
        selectField(el) {
            if (! (el instanceof HTMLInputElement) || el.disabled) {
                return;
            }
            if (['checkbox', 'radio', 'file', 'button', 'submit', 'hidden'].includes(el.type)) {
                return;
            }
            el.removeAttribute('readonly');
            const run = () => {
                try {
                    el.removeAttribute('readonly');
                    el.focus({ preventScroll: true });
                    el.select();
                    if (typeof el.setSelectionRange === 'function' && el.value !== '') {
                        el.setSelectionRange(0, el.value.length);
                    }
                } catch (e) {}
            };
            run();
            requestAnimationFrame(run);
            setTimeout(run, 0);
            setTimeout(run, 50);
            setTimeout(run, 120);
        }
    }"
    x-on:erp-pessoa-focus-email.window="
        $nextTick(() => {
            const el = document.getElementById('pcad-email');
            if (! el) return;
            selectField(el);
            setTimeout(() => selectField(el), 60);
            setTimeout(() => selectField(el), 160);
        })
    "
    @keydown.enter="
        const el = $event.target;
        if (
            (! (el instanceof HTMLInputElement) && ! (el instanceof HTMLSelectElement))
            || el.disabled
        ) {
            return;
        }
        if (! el.hasAttribute('data-erp-pessoa-enter')) {
            return;
        }

        // Cidade: Enter é tratado no JS (confirma IBGE/UF e vai para Email).
        if (el.id === 'pcad-cidade-nome') {
            return;
        }

        $event.preventDefault();
        $event.stopPropagation();

        const fields = Array.from($el.querySelectorAll(
            'input[data-erp-pessoa-enter]:not([disabled]), select[data-erp-pessoa-enter]:not([disabled])'
        )).filter((field) => field.offsetParent !== null);

        const idx = fields.indexOf(el);
        const next = idx >= 0 ? (fields[idx + 1] ?? null) : null;

        if (! next) {
            return;
        }

        // Não dá blur() — no Livewire isso sincroniza o model e apaga o select() do próximo campo.
        next.removeAttribute?.('readonly');
        next.focus({ preventScroll: true });
        if (next instanceof HTMLInputElement) {
            selectField(next);
        }
    "
    @focusin="
        const el = $event.target;
        if (el instanceof HTMLInputElement && el.hasAttribute('data-erp-pessoa-enter')) {
            selectField(el);
        }
    "
    @click="
        const el = $event.target;
        if (el instanceof HTMLInputElement && el.hasAttribute('data-erp-pessoa-enter')) {
            selectField(el);
        }
    "
>
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-codigo">Código</label>
        <input id="pcad-codigo" type="text" wire:model="data.codigo" class="erp-pcad-form__input erp-pcad-form__input--xs">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-pessoa">Pessoa</label>
        <select id="pcad-pessoa" wire:model.live="data.pessoa_tipo" class="erp-pcad-form__select erp-pcad-form__select--sm">
            @foreach (Person::pessoaTipos() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-cpf">CPF/CNPJ</label>
        <input id="pcad-cpf" type="text" wire:model="data.cpf_cnpj" data-mask="cpf-cnpj" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--doc">
        @error('data.cpf_cnpj')
            <span class="erp-pcad-form__error">{{ $message }}</span>
        @enderror
        <button
            type="button"
            data-erp-search-pj
            wire:loading.attr="disabled"
            wire:target="searchPessoaJuridica"
            class="erp-pcad-form__btn"
        >
            <span class="erp-pcad-form__btn-icon">🔍</span>
            <span wire:loading.remove wire:target="searchPessoaJuridica">Pesquisar Pessoa Jurídica</span>
            <span wire:loading wire:target="searchPessoaJuridica">Consultando...</span>
        </button>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-rg">RG/IE</label>
        <input id="pcad-rg" type="text" wire:model="data.rg_ie" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--sm">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-nome">Nome</label>
        <input id="pcad-nome" type="text" wire:model="data.nome_razao" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-apelido">Apelido</label>
        <input id="pcad-apelido" type="text" wire:model="data.apelido_fantasia" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-cep">CEP</label>
        <input id="pcad-cep" type="text" wire:model="data.cep" data-mask="cep" data-erp-pessoa-enter x-on:blur="$wire.buscarCepPessoa()" class="erp-pcad-form__input erp-pcad-form__input--cep">
        <button type="button" wire:click="buscarCepPessoa" wire:loading.attr="disabled" wire:target="buscarCepPessoa" class="erp-pcad-form__btn">
            <span class="erp-pcad-form__btn-icon">🔍</span> Pesquisar CEP
        </button>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-endereco">Endereço</label>
        <input id="pcad-endereco" type="text" wire:model="data.endereco" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--grow">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-numero">Número</label>
        <input id="pcad-numero" type="text" wire:model="data.numero" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--xs">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-complemento">Complemento</label>
        <input id="pcad-complemento" type="text" wire:model="data.complemento" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-bairro">Bairro</label>
        <input id="pcad-bairro" type="text" wire:model="data.bairro" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-cidade-cod">Cidade</label>
        <input id="pcad-cidade-cod" type="text" wire:model="data.cidade_codigo" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--city-code" title="Código IBGE" maxlength="7">
        <div class="erp-pcad-form__city-wrap" @if ($this->pessoaCidadeSugestoesOpen && $this->pessoaCidadeSugestoes !== []) data-lookup-open="1" @endif>
            <input
                id="pcad-cidade-nome"
                type="text"
                wire:model.live.debounce.250ms="data.cidade_nome"
                wire:keydown.escape.prevent="fecharPessoaCidadeSugestoes"
                wire:keydown.arrow-up.prevent="moverPessoaCidadeSugestao(-1)"
                wire:keydown.arrow-down.prevent="moverPessoaCidadeSugestao(1)"
                class="erp-pcad-form__input erp-pcad-form__input--city"
                data-erp-pessoa-enter
                autocomplete="off"
                placeholder="Digite a cidade"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded="{{ $this->pessoaCidadeSugestoesOpen && $this->pessoaCidadeSugestoes !== [] ? 'true' : 'false' }}"
                aria-controls="pcad-cidade-sugestoes"
            >
            @if ($this->pessoaCidadeSugestoesOpen && $this->pessoaCidadeSugestoes !== [])
                <ul id="pcad-cidade-sugestoes" class="erp-pcad-form__city-suggest" role="listbox" aria-label="Cidades encontradas">
                    @foreach ($this->pessoaCidadeSugestoes as $index => $sug)
                        <li wire:key="pcad-cid-sug-{{ $sug['codigo'] }}" role="presentation">
                            <button
                                type="button"
                                role="option"
                                aria-selected="{{ (int) $this->pessoaCidadeSugestaoIndex === (int) $index ? 'true' : 'false' }}"
                                wire:click="selecionarPessoaCidade('{{ $sug['codigo'] }}', @js($sug['nome']), '{{ $sug['uf'] }}')"
                                @class(['is-selected' => (int) $this->pessoaCidadeSugestaoIndex === (int) $index])
                            >
                                <span class="erp-pcad-form__city-suggest-code">{{ $sug['codigo'] }}</span>
                                <span class="erp-pcad-form__city-suggest-nome">{{ $sug['nome'] }}</span>
                                <span class="erp-pcad-form__city-suggest-uf">{{ $sug['uf'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-uf">UF</label>
        <select id="pcad-uf" wire:model.live="data.uf" data-erp-pessoa-enter class="erp-pcad-form__select erp-pcad-form__select--uf">
            <option value="">—</option>
            @foreach (Person::ufs() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-email">Email</label>
        <input id="pcad-email" type="email" wire:model="data.email" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-email2">Email 2</label>
        <input id="pcad-email2" type="email" wire:model="data.email2" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-fone1">Fone 1</label>
        <input id="pcad-fone1" type="text" wire:model="data.fone1" data-mask="phone" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--phone">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-cel1">Celular 1</label>
        <input id="pcad-cel1" type="text" wire:model="data.celular1" data-mask="mobile-phone" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--phone">
        @error('data.celular1')
            <span class="erp-pcad-form__error">{{ $message }}</span>
        @enderror
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-whats">WhatsApp</label>
        <input id="pcad-whats" type="text" wire:model="data.whatsapp" data-mask="mobile-phone" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--phone">
        @error('data.whatsapp')
            <span class="erp-pcad-form__error">{{ $message }}</span>
        @enderror
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-fone2">Fone 2</label>
        <input id="pcad-fone2" type="text" wire:model="data.fone2" data-mask="phone" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--phone">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-cel2">Celular 2</label>
        <input id="pcad-cel2" type="text" wire:model="data.celular2" data-mask="mobile-phone" data-erp-pessoa-enter class="erp-pcad-form__input erp-pcad-form__input--phone">
        @error('data.celular2')
            <span class="erp-pcad-form__error">{{ $message }}</span>
        @enderror
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-regime">Regime Trib.</label>
        <select id="pcad-regime" wire:model="data.regime_tributario" data-erp-pessoa-enter class="erp-pcad-form__select erp-pcad-form__select--md">
            @foreach (Person::regimesTributarios() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-receb">Tipo de Recebimento</label>
        <select id="pcad-receb" wire:model="data.tipo_recebimento" data-erp-pessoa-enter class="erp-pcad-form__select erp-pcad-form__select--md">
            @foreach (Person::tiposRecebimento() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-cont">Tipo de Cont.</label>
        <select id="pcad-cont" wire:model="data.tipo_contribuinte" data-erp-pessoa-enter class="erp-pcad-form__select erp-pcad-form__select--contrib">
            @foreach (Person::tiposContribuinte() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
