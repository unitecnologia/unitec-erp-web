@php
    $info = $this->info;
    $versao = collect($info)->firstWhere('label', 'Versão do ERP')['value'] ?? '';
    $opcache = collect($info)->firstWhere('label', 'OPcache')['value'] ?? '';
    $opcacheOn = is_string($opcache) && str_starts_with($opcache, 'Ativo');
@endphp

<div
    class="erp-comandos"
    wire:keydown.escape.window="
        if ($wire.busy) { $event.preventDefault(); return; }
        $event.preventDefault();
        $wire.closeScreen();
    "
>
    <header class="erp-comandos__header">
        <div class="erp-comandos__brand">
            <span class="erp-comandos__mark" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
            </span>
            <div class="erp-comandos__header-text">
                <div class="erp-comandos__title-row">
                    <h1 class="erp-comandos__title">Comandos do Sistema</h1>
                    @if ($versao !== '')
                        <span class="erp-comandos__version">{{ $versao }}</span>
                    @endif
                </div>
                <p class="erp-comandos__subtitle">Manutenção rápida · cache, aquecimento e diagnóstico</p>
            </div>
        </div>
        <button
            type="button"
            class="erp-comandos__close"
            wire:click="closeScreen"
            title="Fechar (Esc)"
            aria-label="Fechar"
            @disabled($this->busy)
        >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>
    </header>

    <div class="erp-comandos__body">
        @if (filled($this->feedbackMsg))
            <div
                class="erp-comandos__feedback erp-comandos__feedback--{{ $this->feedbackTipo }}"
                role="status"
                wire:key="comandos-feedback-{{ md5($this->feedbackMsg.$this->feedbackTipo) }}"
            >
                <span class="erp-comandos__feedback-icon" aria-hidden="true">
                    @if ($this->feedbackTipo === 'ok')
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                    @elseif ($this->feedbackTipo === 'erro')
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    @else
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    @endif
                </span>
                <p>{{ $this->feedbackMsg }}</p>
                <button type="button" class="erp-comandos__feedback-close" wire:click="dismissFeedback" aria-label="Fechar">&times;</button>
            </div>
        @endif

        <section class="erp-comandos__actions">
            <article @class(['erp-comandos__action', 'erp-comandos__action--warm', 'is-focus' => $this->foco === 'importar'])>
                <div class="erp-comandos__action-top">
                    <span class="erp-comandos__action-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v3h16v-3"/>
                        </svg>
                    </span>
                    <div class="erp-comandos__action-text">
                        <h2>Importar dados</h2>
                        <p>Cria produtos novos e, quando marcado abaixo, atualiza campos de produtos existentes pelo código. Estoque negativo é zerado.</p>
                    </div>
                </div>
                <div class="erp-comandos__import">
                    <select wire:model="importArquivo" @disabled($this->busy)>
                        <option value="">Escolha uma planilha da pasta importar</option>
                        @foreach ($this->importArquivosDisponiveis as $arquivo)
                            <option value="{{ $arquivo }}">{{ $arquivo }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="erp-comandos__btn erp-comandos__btn--ghost" wire:click="refreshImportArquivos" @disabled($this->busy)>Atualizar lista</button>
                    <button type="button" class="erp-comandos__btn erp-comandos__btn--secondary" wire:click="analisarImportacao" wire:loading.attr="disabled" wire:target="analisarImportacao,confirmarImportacao" @disabled($this->busy)>
                        <span wire:loading.remove wire:target="analisarImportacao">Analisar planilha</span>
                        <span wire:loading wire:target="analisarImportacao">Analisando…</span>
                    </button>
                </div>
                <label class="erp-comandos__import-reset">
                    <input type="checkbox" wire:model.live="importZerarTabela" @disabled($this->busy)>
                    <span>
                        <strong>Zerar tabela de produtos antes de importar</strong>
                        <small>Apaga todos os produtos atuais e recria somente os registros desta planilha.</small>
                    </span>
                </label>
                <details class="erp-comandos__import-fields">
                    <summary>
                        Atualizar produtos existentes
                        <span>{{ count($this->importCamposAtualizar) }} campos selecionados</span>
                    </summary>
                    <p>O código identifica o produto e nunca é alterado. Marque somente os dados da planilha que deseja substituir.</p>
                    <div class="erp-comandos__import-fields-actions">
                        <button type="button" wire:click="marcarTodosCamposImportacao" @disabled($this->busy)>Marcar todos</button>
                        <button type="button" wire:click="desmarcarTodosCamposImportacao" @disabled($this->busy)>Desmarcar todos</button>
                    </div>
                    <div class="erp-comandos__import-field-groups">
                        @foreach ($this->importGruposCampos() as $grupo => $campos)
                            <fieldset>
                                <legend>{{ $grupo }}</legend>
                                <ul>
                                    @foreach ($campos as $campo => $descricao)
                                        <label>
                                            <input type="checkbox" wire:model.live="importCamposAtualizar" value="{{ $campo }}" @disabled($this->busy)>
                                            <span>{{ $descricao }}</span>
                                        </label>
                                    @endforeach
                                </ul>
                            </fieldset>
                        @endforeach
                    </div>
                </details>
                @if ($this->importResumo)
                    <div class="erp-comandos__feedback erp-comandos__feedback--info">
                        <p>
                            {{ $this->importResumo['novos'] }} novos
                            · {{ $this->importResumo['existentes'] }} existentes
                            @if (($this->importResumo['atualizaveis'] ?? 0) > 0)
                                · {{ $this->importResumo['atualizaveis'] }} serão atualizados nos campos marcados
                            @else
                                · existentes sem alteração
                            @endif
                            · {{ $this->importResumo['estoque_negativo_zerado'] }} estoques negativos serão zerados.
                        </p>
                    </div>
                    <button type="button" class="erp-comandos__btn erp-comandos__btn--primary" wire:click="toggleConfirmacaoImportacao" @disabled($this->busy || (($this->importResumo['novos'] ?? 0) <= 0 && ($this->importResumo['atualizaveis'] ?? 0) <= 0))>
                        {{ ($this->importResumo['novos'] ?? 0) > 0
                            ? 'Importar '.$this->importResumo['novos'].' novos'
                            : 'Importar atualização' }}{{ ($this->importResumo['atualizaveis'] ?? 0) > 0
                                ? ' e atualizar '.$this->importResumo['atualizaveis'].' existentes'
                                : '' }}
                    </button>
                    @if ($this->importConfirmacaoAberta)
                        <div class="erp-comandos__feedback erp-comandos__feedback--erro">
                            <p>
                                @if ($this->importZerarTabela)
                                    Atenção: todos os produtos atuais serão apagados antes da importação. Confirma?
                                @else
                                    Confirma a importação? Produtos existentes serão atualizados apenas nos campos marcados.
                                @endif
                            </p>
                            <button type="button" class="erp-comandos__btn erp-comandos__btn--primary" wire:click="confirmarImportacao" wire:loading.attr="disabled" wire:target="confirmarImportacao">
                                <span wire:loading.remove wire:target="confirmarImportacao">Confirmar importação</span>
                                <span wire:loading wire:target="confirmarImportacao">Importando…</span>
                            </button>
                        </div>
                    @endif
                @endif
            </article>

            <article @class(['erp-comandos__action', 'erp-comandos__action--warm', 'is-focus' => $this->foco === 'aquecer'])>
                <div class="erp-comandos__action-top">
                    <span class="erp-comandos__action-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/>
                        </svg>
                    </span>
                    <div class="erp-comandos__action-text">
                        <h2>Aquecer Sistema</h2>
                        <p>Compila telas do menu e OPcache. Roda automaticamente ao iniciar o ERP; use aqui após atualização ou reinício do PC.</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="erp-comandos__btn erp-comandos__btn--secondary"
                    wire:click="aquecerSistema"
                    wire:loading.attr="disabled"
                    wire:target="aquecerSistema"
                    @disabled($this->busy)
                >
                    <span wire:loading.remove wire:target="aquecerSistema">Aquecer agora</span>
                    <span wire:loading wire:target="aquecerSistema">Aquecendo…</span>
                </button>
            </article>
        </section>

        <section @class(['erp-comandos__info', 'is-focus' => $this->foco === 'info'])>
            <div class="erp-comandos__info-head">
                <div class="erp-comandos__info-title">
                    <h2>Info do Sistema</h2>
                    <span @class(['erp-comandos__pill', 'is-on' => $opcacheOn, 'is-off' => ! $opcacheOn])>
                        OPcache {{ $opcacheOn ? 'ativo' : 'desligado' }}
                    </span>
                </div>
                <button
                    type="button"
                    class="erp-comandos__btn erp-comandos__btn--ghost"
                    wire:click="refreshInfo"
                    wire:loading.attr="disabled"
                    @disabled($this->busy)
                >Atualizar</button>
            </div>

            <dl class="erp-comandos__grid">
                @foreach ($info as $row)
                    <div class="erp-comandos__row">
                        <dt>{{ $row['label'] }}</dt>
                        <dd title="{{ $row['value'] }}">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</div>
