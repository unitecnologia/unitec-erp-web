@php
    use App\Support\Erp\Backup\DatabaseBackupService;
@endphp

<div
    class="erp-backup"
    wire:ignore.self
    x-data="{
        async runBackup() {
            if ($wire.running || $wire.progressActive) return;
            try {
                await $wire.atualizarProgresso(8, 'Iniciando backup…', 'Preparando ambiente', 'preparar');
                const prep = await $wire.prepararBackup();
                if (! prep || ! prep.ok) return;

                await $wire.atualizarProgresso(28, 'Exportando banco de dados…', 'Conectando ao MySQL e gerando dump', 'exportar');
                const dump = await $wire.executarDump();
                if (! dump || ! dump.ok) return;

                await $wire.atualizarProgresso(90, 'Finalizando…', 'Atualizando status e lista', 'finalizar');
                await $wire.finalizarBackup(dump);
            } catch (e) {
                await $wire.failBackup((e && e.message) ? e.message : 'Erro inesperado ao gerar backup.');
            }
        },
        async runRestore() {
            if ($wire.running || $wire.progressActive) return;
            try {
                await $wire.atualizarProgresso(8, 'Iniciando restauração…', 'Validando confirmação e arquivo', 'preparar');
                const prep = await $wire.prepararRestore();
                if (! prep || ! prep.ok) return;

                await $wire.atualizarProgresso(30, 'Restaurando banco…', 'Backup de segurança + importação do dump', 'exportar');
                const result = await $wire.executarRestore(prep.path || '');
                if (! result || ! result.ok) return;

                await $wire.atualizarProgresso(90, 'Finalizando…', 'Atualizando status e lista', 'finalizar');
                await $wire.finalizarRestore(result);
            } catch (e) {
                await $wire.failRestore((e && e.message) ? e.message : 'Erro inesperado ao restaurar backup.');
            }
        }
    }"
    x-init="
        window.__erpBackupRun = () => runBackup();
        window.__erpBackupRestore = () => runRestore();
    "
    x-on:keydown.escape.window="
        if ($wire.running || $wire.progressActive) { $event.preventDefault(); return; }
        $event.preventDefault();
        $wire.handleEscape();
    "
>
    <header class="erp-backup__header">
        <div class="erp-backup__header-text">
            <h1 class="erp-backup__title">Backup do banco de dados</h1>
            <p class="erp-backup__subtitle">Gere, configure, restaure e acompanhe cópias do MySQL</p>
        </div>
        <button
            type="button"
            class="erp-backup__close"
            wire:click="handleEscape"
            title="Fechar (ESC)"
            aria-label="Fechar"
            @disabled($this->running || $this->progressActive)
        >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>
    </header>

    <div class="erp-backup__body">
        @if (filled($this->feedbackMsg))
            <div
                class="erp-backup__feedback erp-backup__feedback--{{ $this->feedbackTipo }}"
                role="status"
                wire:key="backup-feedback-{{ md5($this->feedbackMsg.$this->feedbackTipo) }}"
            >
                <div class="erp-backup__feedback-icon" aria-hidden="true">
                    @if ($this->feedbackTipo === 'ok')
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
                    @elseif ($this->feedbackTipo === 'erro')
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    @else
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    @endif
                </div>
                <p class="erp-backup__feedback-text">{{ $this->feedbackMsg }}</p>
                <button type="button" class="erp-backup__feedback-close" wire:click="dismissFeedback" aria-label="Fechar mensagem">&times;</button>
            </div>
        @endif

        <section class="erp-backup__card">
            <h2 class="erp-backup__section-title">Configuração</h2>

            <div class="erp-backup__fields">
                <div class="erp-backup__field erp-backup__field--full">
                    <span class="erp-backup__label">Pasta de destino</span>
                    <div class="erp-backup__path-row">
                        <input
                            type="text"
                            wire:model="pastaDestino"
                            class="erp-backup__input"
                            placeholder="Ex.: C:\Sistema\Backup"
                            spellcheck="false"
                            @disabled($this->running || $this->progressActive)
                        >
                        <button
                            type="button"
                            class="erp-backup__browse"
                            wire:click="selecionarPasta"
                            wire:loading.attr="disabled"
                            wire:target="selecionarPasta"
                            title="Escolher pasta"
                            @disabled($this->running || $this->progressActive)
                        >
                            <span wire:loading.remove wire:target="selecionarPasta">Escolher…</span>
                            <span wire:loading wire:target="selecionarPasta">Abrindo…</span>
                        </button>
                    </div>
                </div>

                <label class="erp-backup__field">
                    <span class="erp-backup__label">Intervalo (horas)</span>
                    <input
                        type="number"
                        min="1"
                        max="720"
                        wire:model="intervaloHoras"
                        class="erp-backup__input erp-backup__input--sm"
                        @disabled($this->running || $this->progressActive)
                    >
                </label>

                <label class="erp-backup__field erp-backup__field--full">
                    <span class="erp-backup__label">Token Portal — Log de BKP</span>
                    <input
                        type="password"
                        wire:model="portalBkpToken"
                        class="erp-backup__input"
                        placeholder="Token copiado no portal, aba Log de BKP"
                        autocomplete="off"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        @disabled($this->running || $this->progressActive)
                    >
                </label>

                <label class="erp-backup__toggle">
                    <input
                        type="checkbox"
                        wire:model="habilitarAutomatico"
                        class="erp-backup__toggle-input"
                        @disabled($this->running || $this->progressActive)
                    >
                    <span class="erp-backup__toggle-track" aria-hidden="true"></span>
                    <span class="erp-backup__toggle-copy">
                        <span class="erp-backup__toggle-title">Backup automático</span>
                        <span class="erp-backup__toggle-hint">Executa no intervalo configurado</span>
                    </span>
                </label>
            </div>

            <div class="erp-backup__stats">
                <div class="erp-backup__stat">
                    <span class="erp-backup__stat-label">Último backup</span>
                    <span class="erp-backup__stat-value">{{ $this->ultimoEm !== '' ? $this->ultimoEm : '—' }}</span>
                </div>
                <div class="erp-backup__stat">
                    <span class="erp-backup__stat-label">Status</span>
                    <span @class([
                        'erp-backup__chip',
                        'erp-backup__chip--ok' => $this->ultimoStatus === 'ok',
                        'erp-backup__chip--failed' => $this->ultimoStatus === 'failed',
                        'erp-backup__chip--running' => $this->ultimoStatus === 'running' || $this->progressActive,
                        'erp-backup__chip--idle' => ! in_array($this->ultimoStatus, ['ok', 'failed', 'running'], true) && ! $this->progressActive,
                    ])>{{ $this->progressActive ? 'Em andamento' : $this->statusLabel() }}</span>
                </div>
                <div class="erp-backup__stat">
                    <span class="erp-backup__stat-label">Retenção</span>
                    <span class="erp-backup__stat-value">{{ DatabaseBackupService::RETENTION_DAYS }} dias</span>
                </div>
                <div class="erp-backup__stat erp-backup__stat--wide">
                    <span class="erp-backup__stat-label">Conteúdo</span>
                    <span class="erp-backup__stat-value">Dump MySQL (.sql) + cópia do .env</span>
                </div>
                <div class="erp-backup__stat erp-backup__stat--wide" title="{{ $this->mysqldumpPath }}">
                    <span class="erp-backup__stat-label">mysqldump</span>
                    <span class="erp-backup__stat-value erp-backup__stat-value--mono">
                        {{ $this->mysqldumpPath !== '' ? $this->mysqldumpPath : 'não encontrado' }}
                    </span>
                </div>
            </div>
        </section>

        <section class="erp-backup__card">
            <div class="erp-backup__section-head">
                <h2 class="erp-backup__section-title">Arquivos recentes</h2>
                <button
                    type="button"
                    class="erp-backup__text-btn"
                    wire:click="refreshArquivos"
                    @disabled($this->running || $this->progressActive)
                >
                    Atualizar lista
                </button>
            </div>

            <div class="erp-backup__table-wrap">
                <table class="erp-backup__table">
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Tamanho</th>
                            @if (erp_can('backup.restore'))
                                <th class="erp-backup__th-action">Ação</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->arquivos as $arquivo)
                            <tr>
                                <td class="erp-backup__file" title="{{ $arquivo['path'] }}">{{ $arquivo['name'] }}</td>
                                <td>
                                    <span @class([
                                        'erp-backup__kind',
                                        'erp-backup__kind--env' => ($arquivo['kind'] ?? '') === 'env',
                                        'erp-backup__kind--sql' => ($arquivo['kind'] ?? 'sql') === 'sql',
                                    ])>{{ ($arquivo['kind'] ?? 'sql') === 'env' ? '.env' : 'SQL' }}</span>
                                </td>
                                <td class="erp-backup__muted">{{ $arquivo['modified_at'] }}</td>
                                <td class="erp-backup__size">{{ $arquivo['size_label'] }}</td>
                                @if (erp_can('backup.restore'))
                                    <td class="erp-backup__action">
                                        @if (($arquivo['kind'] ?? 'sql') === 'sql')
                                            <button
                                                type="button"
                                                class="erp-backup__row-btn"
                                                wire:click="abrirRestoreModal({{ \Illuminate\Support\Js::from($arquivo['path']) }})"
                                                @disabled($this->running || $this->progressActive)
                                                title="Restaurar este backup"
                                            >
                                                Restaurar
                                            </button>
                                        @else
                                            <span class="erp-backup__muted">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ erp_can('backup.restore') ? 5 : 4 }}" class="erp-backup__empty">Nenhum backup encontrado nesta pasta.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <footer class="erp-backup__footer">
        @if (erp_can('backup.create'))
            <button
                type="button"
                class="erp-backup__btn erp-backup__btn--primary"
                x-on:click="runBackup()"
                :disabled="$wire.running || $wire.progressActive"
                data-erp-key="F2"
            >
                <span x-show="! ($wire.running || $wire.progressActive)">F2 · Gerar backup</span>
                <span x-show="$wire.running || $wire.progressActive" x-cloak>Gerando…</span>
            </button>
        @endif
        @if (erp_can('backup.restore'))
            <button
                type="button"
                class="erp-backup__btn erp-backup__btn--warn"
                wire:click="abrirRestoreModal"
                @disabled($this->running || $this->progressActive)
            >
                Restaurar backup
            </button>
        @endif
        @if (erp_can('backup.update'))
            <button
                type="button"
                class="erp-backup__btn erp-backup__btn--secondary"
                wire:click="salvarConfig"
                data-erp-key="F5"
                @disabled($this->running || $this->progressActive)
            >
                F5 · Salvar
            </button>
        @endif
        <button
            type="button"
            class="erp-backup__btn erp-backup__btn--ghost"
            wire:click="abrirPasta"
            @disabled($this->running || $this->progressActive)
        >
            Abrir pasta
        </button>
        <button
            type="button"
            class="erp-backup__btn erp-backup__btn--ghost erp-backup__btn--end"
            wire:click="handleEscape"
            @disabled($this->running || $this->progressActive)
        >
            Fechar
        </button>
    </footer>

    @if ($this->showRestoreModal && ! $this->running && ! $this->progressActive)
        <div class="erp-backup__overlay erp-backup__overlay--modal" role="dialog" aria-modal="true" aria-labelledby="erp-backup-restore-title">
            <div class="erp-backup__restore-panel">
                <div class="erp-backup__restore-head">
                    <h2 id="erp-backup-restore-title" class="erp-backup__restore-title">Restaurar backup</h2>
                    <button type="button" class="erp-backup__restore-close" wire:click="fecharRestoreModal" aria-label="Fechar">&times;</button>
                </div>

                <p class="erp-backup__restore-warn">
                    Esta operação sobrescreve o banco atual. Um backup de segurança será gerado antes.
                    Peça para outros usuários saírem do sistema durante a restauração.
                </p>

                <div class="erp-backup__field erp-backup__field--full">
                    <span class="erp-backup__label">Pasta do backup</span>
                    <div class="erp-backup__path-row">
                        <input
                            type="text"
                            wire:model="pastaRestore"
                            wire:change="atualizarPastaRestore"
                            class="erp-backup__input"
                            placeholder="Ex.: C:\BKP"
                            spellcheck="false"
                        >
                        <button
                            type="button"
                            class="erp-backup__browse"
                            wire:click="selecionarPastaRestore"
                            wire:loading.attr="disabled"
                            wire:target="selecionarPastaRestore"
                        >
                            <span wire:loading.remove wire:target="selecionarPastaRestore">Escolher…</span>
                            <span wire:loading wire:target="selecionarPastaRestore">Abrindo…</span>
                        </button>
                    </div>
                </div>

                <div class="erp-backup__field erp-backup__field--full" style="margin-top: 0.55rem;">
                    <span class="erp-backup__label">Arquivo .sql</span>
                    @if ($this->arquivosRestore === [])
                        <p class="erp-backup__restore-empty">Nenhum backup Unitec (.sql) encontrado nesta pasta.</p>
                    @else
                        <select wire:model="arquivoRestorePath" class="erp-backup__input erp-backup__select">
                            @foreach ($this->arquivosRestore as $item)
                                <option value="{{ $item['path'] }}">
                                    {{ $item['name'] }} — {{ $item['modified_at'] }} ({{ $item['size_label'] }})
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div class="erp-backup__field erp-backup__field--full" style="margin-top: 0.55rem;">
                    <span class="erp-backup__label">Digite RESTAURAR para confirmar</span>
                    <input
                        type="text"
                        wire:model="confirmacaoRestore"
                        class="erp-backup__input"
                        placeholder="RESTAURAR"
                        autocomplete="off"
                        spellcheck="false"
                    >
                </div>

                <div class="erp-backup__restore-actions">
                    <button type="button" class="erp-backup__btn erp-backup__btn--ghost" wire:click="fecharRestoreModal">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="erp-backup__btn erp-backup__btn--warn"
                        wire:click="confirmarRestoreEExecutar"
                        @disabled($this->arquivosRestore === [] || trim($this->arquivoRestorePath) === '')
                    >
                        Confirmar restauração
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($this->progressActive || $this->running)
        <div class="erp-backup__overlay" role="dialog" aria-modal="true" aria-labelledby="erp-backup-progress-title">
            <div class="erp-backup__overlay-panel">
                <div class="erp-backup__spinner" aria-hidden="true"></div>
                <h2 id="erp-backup-progress-title" class="erp-backup__overlay-title">
                    {{ $this->progressLabel !== '' ? $this->progressLabel : ($this->progressMode === 'restore' ? 'Restaurando…' : 'Gerando backup…') }}
                </h2>
                @if (filled($this->progressDetail))
                    <p class="erp-backup__overlay-detail">{{ $this->progressDetail }}</p>
                @endif

                <div
                    class="erp-backup__progress"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ $this->progressPct }}"
                >
                    <div class="erp-backup__progress-track">
                        <div class="erp-backup__progress-bar" style="width: {{ max(4, min(100, $this->progressPct)) }}%"></div>
                    </div>
                    <div class="erp-backup__progress-meta">
                        <span>{{ $this->progressPct }}%</span>
                    </div>
                </div>

                <ol class="erp-backup__steps">
                    @if ($this->progressMode === 'restore')
                        <li @class(['is-done' => in_array($this->progressStep, ['exportar', 'finalizar'], true), 'is-active' => $this->progressStep === 'preparar'])>Preparar</li>
                        <li @class(['is-done' => $this->progressStep === 'finalizar', 'is-active' => $this->progressStep === 'exportar'])>Importar</li>
                        <li @class(['is-active' => $this->progressStep === 'finalizar', 'is-done' => $this->progressPct >= 100 && $this->progressStep === 'finalizar'])>Finalizar</li>
                    @else
                        <li @class(['is-done' => in_array($this->progressStep, ['exportar', 'finalizar'], true), 'is-active' => $this->progressStep === 'preparar'])>Preparar</li>
                        <li @class(['is-done' => $this->progressStep === 'finalizar', 'is-active' => $this->progressStep === 'exportar'])>Exportar</li>
                        <li @class(['is-active' => $this->progressStep === 'finalizar', 'is-done' => $this->progressPct >= 100 && $this->progressStep === 'finalizar'])>Finalizar</li>
                    @endif
                </ol>

                <p class="erp-backup__overlay-hint">Aguarde, não feche esta tela.</p>
            </div>
        </div>
    @endif
</div>

@include('filament.components.erp.list-scripts', [
    'config' => [
        'create' => 'gerarBackup',
        'extraKeys' => [
            'F5' => ['method' => 'salvarConfig'],
            'Escape' => ['method' => 'handleEscape'],
        ],
    ],
])
