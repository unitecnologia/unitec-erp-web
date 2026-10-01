@php
    $visivel = $this->empresaNfeEmitentesConfigVisivel();
    $empresaSalva = property_exists($this, 'record') && $this->record?->getKey();
    $catalogo = $visivel ? $this->empresaNfeEmitentesCatalogo() : [];
    $emitentesHint = 'Selecione quais empresas podem emitir NF-e a partir desta empresa. Isso não altera o acesso do usuário nem troca a empresa da sessão.';
@endphp

<div
    class="erp-empresas-nfe-emitentes"
    wire:key="emp-nfe-emitentes-{{ $visivel ? 'on' : 'off' }}"
>
    <p class="erp-empresas-nfe-emitentes__title">
        Empresas emitentes autorizadas
        <span
            class="erp-produtos-impostos__label--hint"
            title="{{ $emitentesHint }}"
            role="img"
            aria-label="{{ $emitentesHint }}"
        ></span>
    </p>

    @if ($visivel)
        @unless ($empresaSalva)
            <p class="erp-empresas-parametros__hint">Salve a empresa primeiro para gravar as emitentes.</p>
        @elseif ($catalogo === [])
            <p class="erp-empresas-parametros__hint">Não há outras empresas ativas para autorizar como emitente.</p>
        @else
            <div class="erp-empresas-nfe-emitentes__list" role="group" aria-label="Empresas emitentes autorizadas">
                @foreach ($catalogo as $row)
                    <label class="erp-pcad__check erp-empresas-nfe-emitentes__item">
                        <input
                            type="checkbox"
                            value="{{ $row['id'] }}"
                            wire:model="empresaNfeEmitenteIds"
                        >
                        <span>
                            @if ($row['codigo'] !== '')
                                <strong>{{ $row['codigo'] }}</strong> —
                            @endif
                            {{ $row['nome'] }}
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
    @endif
</div>
