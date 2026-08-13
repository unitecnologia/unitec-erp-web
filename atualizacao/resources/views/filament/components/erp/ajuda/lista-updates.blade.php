@php
    $releases = $this->releases;
    $versaoAtual = $this->versaoAtual;
    $openIndex = 0;
    foreach ($releases as $i => $release) {
        if ($versaoAtual !== '' && $versaoAtual === ($release['version'] ?? '')) {
            $openIndex = $i;
            break;
        }
    }
@endphp

<div
    class="erp-lista-updates"
    wire:keydown.escape.window="closeScreen"
    x-data="{ open: {{ (int) $openIndex }} }"
>
    <header class="erp-lista-updates__header">
        <div class="erp-lista-updates__header-text">
            <h1 class="erp-lista-updates__title">Lista de Updates</h1>
            @if ($versaoAtual !== '')
                <span class="erp-lista-updates__current">{{ $versaoAtual }}</span>
            @endif
            <p class="erp-lista-updates__subtitle">Melhorias e novidades</p>
        </div>
        <button
            type="button"
            class="erp-lista-updates__close"
            wire:click="closeScreen"
            title="Fechar (Esc)"
            aria-label="Fechar"
        >&times;</button>
    </header>

    <div class="erp-lista-updates__body">
        @forelse ($releases as $index => $release)
            @php
                $isCurrent = $versaoAtual !== '' && $versaoAtual === ($release['version'] ?? '');
                $items = [];
                foreach ($release['highlights'] ?? [] as $item) {
                    $items[] = ['tone' => 'melhoria', 'text' => $item];
                }
                foreach ($release['news'] ?? [] as $item) {
                    $items[] = ['tone' => 'novidade', 'text' => $item];
                }
                foreach ($release['fixes'] ?? [] as $item) {
                    $items[] = ['tone' => 'correcao', 'text' => $item];
                }
            @endphp
            <article
                @class([
                    'erp-lista-updates__card',
                    'is-current' => $isCurrent,
                    'is-open' => false,
                ])
                :class="{ 'is-open': open === {{ $index }} }"
            >
                <button
                    type="button"
                    class="erp-lista-updates__card-toggle"
                    @click="open = open === {{ $index }} ? -1 : {{ $index }}"
                    :aria-expanded="(open === {{ $index }}).toString()"
                >
                    <span class="erp-lista-updates__version-row">
                        <span class="erp-lista-updates__version">{{ $release['version'] }}</span>
                        @if ($isCurrent)
                            <span class="erp-lista-updates__badge">Atual</span>
                        @endif
                        <span class="erp-lista-updates__card-title">{{ $release['title'] }}</span>
                    </span>
                    <span class="erp-lista-updates__card-meta">
                        <time datetime="{{ $release['date'] }}">{{ $release['date'] }}</time>
                        <span class="erp-lista-updates__chevron" aria-hidden="true">▾</span>
                    </span>
                </button>

                <div
                    class="erp-lista-updates__card-body"
                    x-show="open === {{ $index }}"
                    x-cloak
                >
                    <ul class="erp-lista-updates__list">
                        @foreach ($items as $item)
                            <li>
                                <span @class([
                                    'erp-lista-updates__tag',
                                    'erp-lista-updates__tag--'.$item['tone'],
                                ])>
                                    {{ match ($item['tone']) {
                                        'novidade' => 'Novo',
                                        'correcao' => 'Fix',
                                        default => 'Melhoria',
                                    } }}
                                </span>
                                <span>{{ $item['text'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </article>
        @empty
            <div class="erp-lista-updates__empty">
                Nenhuma atualização registrada ainda.
            </div>
        @endforelse
    </div>
</div>
