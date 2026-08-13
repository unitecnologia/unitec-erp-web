@php
    /** @var list<string> $ufs */
    $ufs = $this->ufs;
    /** @var array<string, array<string, float|string>> $matrix */
    $matrix = $this->matrix;
    $highlightUf = $this->highlightUf;
    $editOrigem = $this->editOrigem;
    $editDestino = $this->editDestino;
@endphp

<div
    class="erp-tabela-icms-window"
    wire:keydown.f6.window.prevent="focusLocate"
    wire:keydown.escape.window.prevent="handleEscape"
    x-data
    x-on:erp-tabela-icms-focus-locate.window="$nextTick(() => $refs.locateInput?.focus())"
    x-on:erp-tabela-icms-scroll-uf.window="
        const uf = ($event.detail && ($event.detail.uf || $event.detail[0])) || null;
        if (!uf) return;
        const row = $el.querySelector('[data-row-uf=' + uf + ']');
        row && row.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' });
    "
>
    <header class="erp-tabela-icms-window__titlebar">
        <span class="erp-tabela-icms-window__title">Tabela ICMS</span>
        <button
            type="button"
            class="erp-tabela-icms-window__close"
            wire:click="closeScreen"
            aria-label="Fechar"
            title="ESC | Fechar"
        >&times;</button>
    </header>

    <div class="erp-tabela-icms-locate">
        <div class="erp-tabela-icms-locate__bar">
            <span class="erp-tabela-icms-locate__label"><kbd>F6</kbd> | LOCALIZAR UF</span>
            <div class="erp-tabela-icms-locate__controls">
                <input
                    id="erp-tabela-icms-locate"
                    type="text"
                    maxlength="2"
                    class="erp-tabela-icms-locate__input"
                    placeholder="UF"
                    wire:model="locateUf"
                    wire:keydown.enter.prevent="locateUf"
                    x-ref="locateInput"
                    autocomplete="off"
                    spellcheck="false"
                />
                <button type="button" class="erp-tabela-icms-locate__btn" wire:click="locateUf">
                    Ir
                </button>
            </div>
            <span class="erp-tabela-icms-locate__hint">Matriz ORIGEM × DESTINO · padrão DIFAL 2026</span>
        </div>
    </div>

    <div class="erp-tabela-icms-window__legend">
        <span class="erp-tabela-icms-legend erp-tabela-icms-legend--interna">
            <span class="erp-tabela-icms-legend__swatch"></span>
            Diagonal = alíquota interna
        </span>
        <span class="erp-tabela-icms-legend erp-tabela-icms-legend--interestadual">
            <span class="erp-tabela-icms-legend__swatch"></span>
            Demais = interestadual (clique para editar)
        </span>
        <span class="erp-tabela-icms-window__axis-hint">
            Linhas = <strong>ORIGEM</strong> · Colunas = <strong>DESTINO</strong>
        </span>
    </div>

    <div class="erp-tabela-icms-window__body">
        <div class="erp-tabela-icms-scroll">
            <table class="erp-tabela-icms">
                <thead>
                    <tr>
                        <th class="erp-tabela-icms__corner" scope="col">
                            <span class="erp-tabela-icms__corner-origem">ORIGEM</span>
                            <span class="erp-tabela-icms__corner-destino">DESTINO →</span>
                        </th>
                        @foreach ($ufs as $destino)
                            <th
                                scope="col"
                                @class([
                                    'erp-tabela-icms__col-head',
                                    'erp-tabela-icms__col-head--hl' => $highlightUf === $destino,
                                ])
                            >{{ $destino }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ufs as $origem)
                        <tr
                            data-row-uf="{{ $origem }}"
                            @class([
                                'erp-tabela-icms__row',
                                'erp-tabela-icms__row--hl' => $highlightUf === $origem,
                            ])
                        >
                            <th scope="row" class="erp-tabela-icms__row-head">{{ $origem }}</th>
                            @foreach ($ufs as $destino)
                                @php
                                    $rate = (float) ($matrix[$origem][$destino] ?? 0);
                                    $isInterna = $origem === $destino;
                                    $display = number_format($rate, 2, ',', '');
                                    $isEditing = $editOrigem === $origem && $editDestino === $destino;
                                @endphp
                                <td
                                    @class([
                                        'erp-tabela-icms__cell',
                                        'erp-tabela-icms__cell--interna' => $isInterna,
                                        'erp-tabela-icms__cell--editing' => $isEditing,
                                    ])
                                    title="{{ $origem }} → {{ $destino }}"
                                >
                                    @if ($isEditing)
                                        <input
                                            type="text"
                                            class="erp-tabela-icms__cell-input"
                                            wire:model="editValue"
                                            wire:keydown.enter.prevent="commitEdit"
                                            maxlength="6"
                                            autofocus
                                        />
                                    @else
                                        <button
                                            type="button"
                                            class="erp-tabela-icms__cell-btn"
                                            wire:click="startEdit('{{ $origem }}', '{{ $destino }}')"
                                        >{{ $display }}</button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <footer class="erp-tabela-icms-actions">
        <span class="erp-tabela-icms-actions__hint">Clique na célula · Enter grava · Esc cancela / fecha</span>
        <button type="button" class="erp-tabela-icms-actions__btn erp-tabela-icms-actions__btn--close" wire:click="closeScreen">
            <span class="erp-tabela-icms-actions__icon">✕</span>
            <span class="erp-tabela-icms-actions__label">Fechar</span>
        </button>
    </footer>
</div>

