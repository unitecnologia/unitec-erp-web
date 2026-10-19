@php
    $linhas = [];
    foreach ($this->nomes as $numero => $nome) {
        $st = $this->status[(string) $numero] ?? 'livre';
        $linhas[] = [
            'numero' => (string) $numero,
            'nome' => $nome,
            'st' => $st,
            'stLabel' => match ($st) {
                'ocupada' => 'Ocupada',
                'atendimento' => 'Em atendimento',
                default => 'Livre',
            },
        ];
    }

    $total = count($linhas);
    // 20 mesas → 2 colunas (01–10 | 11–20). Mais mesas → mais colunas.
    $cols = match (true) {
        $total <= 12 => 1,
        $total <= 30 => 2,
        $total <= 60 => 3,
        default => 4,
    };
    $porColuna = (int) max(1, (int) ceil(max(1, $total) / $cols));
    $colunas = $total > 0 ? array_chunk($linhas, $porColuna) : [];
@endphp

<div
    class="erp-mesas-config"
    wire:ignore.self
    data-mesas-qtd="{{ $total }}"
    data-mesas-cols="{{ count($colunas) }}"
    x-on:keydown.escape.window="
        $event.preventDefault();
        $wire.handleEscape();
    "
    x-on:keydown.f5.window="
        $event.preventDefault();
        $wire.salvar();
    "
>
    <header class="erp-mesas-config__titlebar">
        <span class="erp-mesas-config__title">Configuração de Mesas</span>
        <button
            type="button"
            class="erp-mesas-config__close"
            wire:click="handleEscape"
            title="ESC | Fechar"
            aria-label="Fechar"
        >&times;</button>
    </header>

    <div class="erp-mesas-config__body">
        @if (filled($this->feedbackMsg))
            <div
                class="erp-mesas-config__feedback erp-mesas-config__feedback--{{ $this->feedbackTipo }}"
                role="status"
                wire:key="mesas-feedback-{{ md5($this->feedbackMsg.$this->feedbackTipo) }}"
            >
                <p class="erp-mesas-config__feedback-text">{{ $this->feedbackMsg }}</p>
                <button type="button" class="erp-mesas-config__feedback-close" wire:click="dismissFeedback" aria-label="Fechar mensagem">&times;</button>
            </div>
        @endif

        <div class="erp-mesas-config__toolbar">
            <label class="erp-mesas-config__field erp-mesas-config__field--inline" for="mesas-qtd">
                <span class="erp-mesas-config__label">Quantidade de mesas</span>
                <input
                    id="mesas-qtd"
                    type="number"
                    min="1"
                    max="{{ \App\Support\Erp\Pdv\PdvMesaService::QTD_MAXIMA }}"
                    class="erp-mesas-config__input erp-mesas-config__input--qtd"
                    wire:model="quantidade"
                    wire:keydown.enter.prevent="atualizarQuantidade"
                >
                <button
                    type="button"
                    class="erp-mesas-config__btn erp-mesas-config__btn--ghost"
                    wire:click="atualizarQuantidade"
                    title="Atualizar lista conforme a quantidade"
                >Aplicar</button>
            </label>
            <p class="erp-mesas-config__hint">
                Padrão: Mesa 01, Mesa 02… Reduzir a quantidade preserva o histórico; mesas ocupadas ou em atendimento não podem ser desativadas.
            </p>
        </div>

        <div
            class="erp-mesas-config__cols"
            style="--mesas-cols: {{ max(1, count($colunas)) }}"
        >
            @foreach ($colunas as $colIndex => $col)
                <div class="erp-mesas-config__col" wire:key="mesa-col-{{ $colIndex }}">
                    <table class="erp-mesas-config__table">
                        <thead>
                            <tr>
                                <th class="erp-mesas-config__th erp-mesas-config__th--num">Nº</th>
                                <th class="erp-mesas-config__th erp-mesas-config__th--nome">Nome</th>
                                <th class="erp-mesas-config__th erp-mesas-config__th--status">Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($col as $linha)
                                <tr wire:key="mesa-row-{{ $linha['numero'] }}">
                                    <td class="erp-mesas-config__td erp-mesas-config__td--num">{{ str_pad($linha['numero'], 2, '0', STR_PAD_LEFT) }}</td>
                                    <td class="erp-mesas-config__td erp-mesas-config__td--nome">
                                        <input
                                            type="text"
                                            class="erp-mesas-config__input erp-mesas-config__input--nome"
                                            maxlength="60"
                                            wire:model="nomes.{{ $linha['numero'] }}"
                                            placeholder="{{ \App\Support\Erp\Pdv\PdvMesaService::rotuloPadrao((int) $linha['numero']) }}"
                                        >
                                    </td>
                                    <td class="erp-mesas-config__td erp-mesas-config__td--status">
                                        <span class="erp-mesas-config__badge erp-mesas-config__badge--{{ $linha['st'] }}">{{ $linha['stLabel'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>

    <footer class="erp-mesas-config__footer erp-pcad-actions erp-pcad-actions--split">
        <div class="erp-mesas-config__footer-left"></div>
        <div class="erp-pcad-actions__right">
            <button
                type="button"
                class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                data-erp-key="F5"
                wire:click="salvar"
                wire:loading.attr="disabled"
            >
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.414a2 2 0 0 0-.586-1.414l-3.414-3.414A2 2 0 0 0 12.586 2H4zm8 1.414L15.586 8H12a1 1 0 0 1-1-1V4.414zM5 11a1 1 0 0 1 1-1h4a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1zm0 3a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1z"/></svg>
                </span>
                <span class="erp-pcad-actions__label">Salvar <kbd>F5</kbd></span>
            </button>
            <button
                type="button"
                class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
                data-erp-key="Escape"
                wire:click="handleEscape"
            >
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </span>
                <span class="erp-pcad-actions__label">Fechar <kbd>Esc</kbd></span>
            </button>
        </div>
    </footer>
</div>
