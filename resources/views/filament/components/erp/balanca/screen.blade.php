<div
    class="erp-balanca"
    wire:ignore.self
    x-on:keydown.escape.window="
        if ($wire.showEtiquetas) { return; }
        if ($wire.running) { $event.preventDefault(); return; }
        $event.preventDefault();
        $wire.handleEscape();
    "
>
    <header class="erp-balanca__titlebar">
        <span class="erp-balanca__title">Configurações de Balança</span>
        <button
            type="button"
            class="erp-balanca__close"
            wire:click="handleEscape"
            title="ESC | Fechar"
            aria-label="Fechar"
            @disabled($this->running)
        >&times;</button>
    </header>

    <div class="erp-balanca__body">
        @if (filled($this->feedbackMsg))
            <div
                class="erp-balanca__feedback erp-balanca__feedback--{{ $this->feedbackTipo }}"
                role="status"
                wire:key="balanca-feedback-{{ md5($this->feedbackMsg.$this->feedbackTipo) }}"
            >
                <p class="erp-balanca__feedback-text">{{ $this->feedbackMsg }}</p>
                <button type="button" class="erp-balanca__feedback-close" wire:click="dismissFeedback" aria-label="Fechar mensagem">&times;</button>
            </div>
        @endif

        <div class="erp-balanca__config">
            <div class="erp-balanca__field erp-balanca__field--modelo">
                <label class="erp-balanca__label" for="balanca-modelo">Modelo</label>
                <div class="erp-balanca__modelo-row">
                    <select
                        id="balanca-modelo"
                        class="erp-balanca__select"
                        wire:model.live="modelo"
                        @disabled($this->running)
                    >
                        @foreach ($this->modeloOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <label
                        class="erp-balanca__flag {{ $this->isModeloPadrao() ? 'is-active' : '' }}"
                        title="{{ $this->isModeloPadrao() ? 'Este modelo já é o padrão' : 'Definir este modelo como padrão' }}"
                    >
                        <input
                            type="checkbox"
                            wire:model.live="usarComoPadrao"
                            @disabled($this->running)
                        >
                        <span class="erp-balanca__flag-text">Padrão</span>
                        @if ($this->isModeloPadrao())
                            <span class="erp-balanca__badge" aria-hidden="true">★</span>
                        @endif
                    </label>
                </div>
            </div>

            <div class="erp-balanca__field erp-balanca__field--dir">
                <label class="erp-balanca__label" for="balanca-dir">Diretório dos arquivos</label>
                <div class="erp-balanca__path-row">
                    <input
                        id="balanca-dir"
                        type="text"
                        class="erp-balanca__input"
                        wire:model="diretorio"
                        wire:blur="salvarDiretorio"
                        spellcheck="false"
                        placeholder="C:\UNITECNOLOGIA_WEB\balanca"
                        @disabled($this->running)
                    >
                    <button
                        type="button"
                        class="erp-balanca__browse"
                        wire:click="selecionarPasta"
                        wire:loading.attr="disabled"
                        wire:target="selecionarPasta"
                        title="Escolher pasta"
                        @disabled($this->running)
                    >
                        …
                    </button>
                </div>
            </div>
        </div>

        <p class="erp-balanca__note">
            Só entram produtos com código balança (Prefixo) preenchido no cadastro.
        </p>

        <div class="erp-balanca__status-block">
            <span class="erp-balanca__label">Status</span>
            <div class="erp-balanca__status" role="status" aria-live="polite">
                @if ($this->running)
                    Gerando arquivo…
                @elseif (filled($this->status))
                    {{ $this->status }}
                @endif
            </div>
        </div>

        @if (count($this->arquivos) > 0)
            <ul class="erp-balanca__files">
                @foreach ($this->arquivos as $file)
                    <li>
                        <strong>{{ $file['name'] }}</strong>
                        <span>{{ number_format($file['bytes'] / 1024, 1, ',', '.') }} KB</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <footer class="erp-balanca__footer erp-pcad-actions erp-pcad-actions--split">
        <div class="erp-balanca__footer-left">
            <button
                type="button"
                class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                data-erp-key="F5"
                wire:click="gerarArquivo"
                wire:loading.attr="disabled"
                wire:target="gerarArquivo"
                @disabled($this->running || $this->showEtiquetas)
            >
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                <span class="erp-pcad-actions__label" wire:loading.remove wire:target="gerarArquivo">Gerar Arquivo</span>
                <span class="erp-pcad-actions__label" wire:loading wire:target="gerarArquivo">Gerando…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-actions__btn erp-balanca__etiquetas-btn"
                wire:click="openEtiquetas"
                @disabled($this->running)
                title="Configurar layout de etiquetas / código de barras"
            >
                <span class="erp-pcad-actions__icon">▦</span>
                <span class="erp-pcad-actions__label">Config. Etiquetas</span>
            </button>

            @if (filled($this->downloadPath))
                <button
                    type="button"
                    class="erp-pcad-actions__btn erp-balanca__download-btn"
                    wire:click="downloadArquivo"
                    @disabled($this->running)
                    title="Baixar o arquivo gerado"
                >
                    <span class="erp-pcad-actions__icon">↓</span>
                    <span class="erp-pcad-actions__label">Download</span>
                </button>
            @endif
        </div>

        <button
            type="button"
            class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
            data-erp-key="Escape"
            wire:click="closeScreen"
            @disabled($this->running)
        >
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
            <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
        </button>
    </footer>

    @include('filament.components.erp.balanca.etiquetas-modal')
</div>
