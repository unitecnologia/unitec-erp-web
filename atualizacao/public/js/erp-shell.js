(function () {
    const updateModal = document.getElementById('erp-system-update-modal');
    let pollTimer = null;
    let packagePollTimer = null;
    let updateRunning = false;
    let updateStuck = false;
    let updateFailed = false;
    let updateCompletedHandled = false;
    let pollCount = 0;
    let launchStartedAt = 0;
    let lastStatusSignature = '';
    let lastProgressAt = 0;
    let packageReady = false;
    let installFromLocal = false;
    let displayedPercent = 0;
    let targetPercent = 0;
    let displayedStepProgress = {};
    let targetStepProgress = {};
    let progressAnimRaf = 0;
    let progressAnimTimer = 0;
    let softTickTimer = 0;
    let currentUpdateState = 'idle';
    let hasServerStepPercent = false;

    const UPDATE_STEP_ORDER = [
        'starting',
        'downloading',
        'extracting',
        'applying',
        'migrating',
        'finalizing',
        'completed',
    ];

    const UPDATE_STEP_RANGES = {
        starting: [0, 8],
        downloading: [8, 38],
        extracting: [38, 52],
        applying: [52, 82],
        migrating: [82, 92],
        finalizing: [92, 100],
        completed: [100, 100],
    };

    document.addEventListener(
        'click',
        (event) => {
            // PWA: clique no atalho PDV pede tela cheia no mesmo gesto (esconde taskbar).
            const pdvLink = event.target.closest?.('a[href*="/pdv"]');
            if (
                pdvLink
                && ! document.fullscreenElement
                && (window.matchMedia('(display-mode: standalone)').matches
                    || window.matchMedia('(display-mode: fullscreen)').matches
                    || window.navigator.standalone === true)
            ) {
                const target = document.documentElement;
                if (typeof target.requestFullscreen === 'function') {
                    target.requestFullscreen({ navigationUI: 'hide' }).catch(() => {
                        target.requestFullscreen().catch(() => {});
                    });
                }
            }

            const updateButton = event.target.closest('[data-erp-action="system-update"]');
            if (updateButton) {
                event.preventDefault();
                event.stopPropagation();
                openSystemUpdateModal();
                return;
            }

            const alterarSenhaButton = event.target.closest('[data-erp-action="alterar-senha"]');
            if (alterarSenhaButton) {
                event.preventDefault();
                event.stopPropagation();
                closeAllTopMenus();
                window.Livewire?.dispatch('erp-open-alterar-senha');
                return;
            }

            const trocarUsuarioButton = event.target.closest('[data-erp-action="trocar-usuario"]');
            if (trocarUsuarioButton) {
                event.preventDefault();
                event.stopPropagation();
                closeAllTopMenus();
                window.Livewire?.dispatch('erp-open-trocar-usuario');
                return;
            }

            const moduleButton = event.target.closest('[data-erp-module]');
            if (moduleButton) {
                // Stubs do menu agora usam badge "Em breve" (sem toast).
                // Mantém o handler só por compatibilidade com markup legado.
                event.preventDefault();
                event.stopPropagation();
            }
        },
        true
    );

    if (updateModal) {
        updateModal.addEventListener('click', (event) => {
            if (event.target.closest('[data-erp-update-reset]')) {
                resetUpdateState();
                return;
            }

            if (event.target.closest('[data-erp-update-dismiss]')) {
                closeSystemUpdateModal();
                return;
            }

            if (event.target.closest('[data-erp-update-download]')) {
                startPackageDownload();
                return;
            }

            if (event.target.closest('[data-erp-update-run-manual]')) {
                startManualUpdater();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && updateModal && ! updateModal.hidden && canCloseUpdateModal()) {
            closeSystemUpdateModal();
        }

        if (event.altKey && event.key.toLowerCase() === 's') {
            event.preventDefault();
            document.querySelector('.erp-shortcut-bar__form button[type="submit"]')?.click();
        }
    });

    function closeAllTopMenus(except = null) {
        document.querySelectorAll('.erp-menu-bar__details[open]').forEach((other) => {
            if (other !== except) {
                other.removeAttribute('open');
            }
        });
    }

    function bindMenuUi() {
        document.querySelectorAll('.erp-menu-bar__details').forEach((details) => {
            if (details.dataset.erpMenuBound === '1') {
                return;
            }

            details.dataset.erpMenuBound = '1';

            const summary = details.querySelector(':scope > summary');

            summary?.addEventListener('click', (event) => {
                // Em desktop o hover controla o menu; no toque o clique continua abrindo.
                if (window.matchMedia('(hover: hover)').matches) {
                    event.preventDefault();
                }
            });

            details.addEventListener('toggle', () => {
                if (! details.open) {
                    return;
                }

                closeAllTopMenus(details);
            });

            details.addEventListener('mouseenter', () => {
                if (details.open) {
                    return;
                }

                details.setAttribute('open', '');
            });

            details.addEventListener('mouseleave', () => {
                details.removeAttribute('open');
            });
        });

        document.querySelectorAll('.erp-menu-bar__submenu').forEach((submenu) => {
            if (submenu.dataset.erpMenuBound === '1') {
                return;
            }

            submenu.dataset.erpMenuBound = '1';
            submenu.addEventListener('toggle', () => {
                if (! submenu.open) {
                    return;
                }

                const parent = submenu.closest('.erp-menu-bar__dropdown, .erp-menu-bar__submenu-panel');

                parent?.querySelectorAll('.erp-menu-bar__submenu[open]').forEach((other) => {
                    if (other !== submenu) {
                        other.removeAttribute('open');
                    }
                });
            });

            submenu.addEventListener('mouseenter', () => {
                if (submenu.open) {
                    return;
                }

                submenu.setAttribute('open', '');
            });

            submenu.addEventListener('mouseleave', () => {
                submenu.removeAttribute('open');
            });
        });
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-erp-action], [data-erp-module]')) {
            return;
        }

        if (! event.target.closest('.erp-menu-bar__details')) {
            closeAllTopMenus();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindMenuUi);
    } else {
        bindMenuUi();
    }

    function canCloseUpdateModal() {
        if (updateCompletedHandled) {
            return false;
        }

        return ! updateRunning || updateStuck || updateFailed;
    }

    function formatBytes(bytes) {
        const value = Number(bytes || 0);
        if (value < 1024) {
            return `${value} B`;
        }
        if (value < 1024 * 1024) {
            return `${(value / 1024).toFixed(1)} KB`;
        }

        return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    }

    function stopPackagePolling() {
        if (packagePollTimer) {
            clearInterval(packagePollTimer);
            packagePollTimer = null;
        }
    }

    function applyPackageStatus(payload) {
        const localEl = updateModal?.querySelector('[data-erp-update-local-version]');
        const remoteEl = updateModal?.querySelector('[data-erp-update-remote-version]');
        const statusEl = updateModal?.querySelector('[data-erp-update-package-status]');
        const downloadBtn = updateModal?.querySelector('[data-erp-update-download]');
        const runManualBtn = updateModal?.querySelector('[data-erp-update-run-manual]');
        const successBox = updateModal?.querySelector('[data-erp-update-success]');
        const progressBox = updateModal?.querySelector('[data-erp-update-download-progress]');
        const hint = updateModal?.querySelector('[data-erp-update-hint]');

        const localVersion = payload.local_version || window.__erpUpdateConfig?.appVersion || '—';
        const remoteVersion = payload.remote_version || '—';
        packageReady = Boolean(payload.package_ready);
        const downloadState = String(payload.download_state || 'idle');
        const downloading = Boolean(payload.download_running) || ['checking', 'downloading'].includes(downloadState);

        if (localEl) {
            localEl.textContent = localVersion;
        }
        if (remoteEl) {
            remoteEl.textContent = remoteVersion;
        }

        let message = payload.check_message || 'Nenhum pacote baixado ainda.';
        let tone = '';

        if (packageReady) {
            message = payload.check_message
                || `Pacote pronto${remoteVersion !== '—' ? ' (' + remoteVersion + ')' : ''}${payload.package_bytes ? ' · ' + formatBytes(payload.package_bytes) : ''}. Execute a atualização manual.`;
            tone = 'is-ready';
        } else if (downloadState === 'failed') {
            message = payload.check_message || 'Falha ao baixar o pacote.';
            tone = 'is-error';
        } else if (downloading) {
            message = payload.check_message || 'Baixando pacote em segundo plano...';
            tone = 'is-warn';
        } else if (! payload.update_available && remoteVersion !== '—' && remoteVersion === localVersion) {
            message = payload.check_message || 'Sistema já está na versão mais recente.';
        }

        if (statusEl) {
            statusEl.textContent = message;
            statusEl.classList.remove('is-ready', 'is-warn', 'is-error');
            if (tone) {
                statusEl.classList.add(tone);
            }
        }

        if (progressBox) {
            progressBox.hidden = ! downloading;
        }

        if (downloading) {
            const pct = parseDownloadPercent(message);
            const statusLine = updateModal?.querySelector('[data-erp-update-status]');
            if (statusLine) {
                statusLine.textContent = message;
            }
            setUpdateProgress(pct > 0 ? pct : 12, pct <= 0);
            setUpdatePercent(pct > 0 ? pct : 0);
            if (hint) {
                hint.textContent = 'Pode continuar usando o sistema durante o download.';
            }
        } else if (packageReady) {
            setUpdateProgress(100, false);
            setUpdatePercent(100);
        }

        if (successBox) {
            successBox.hidden = ! packageReady || downloading;
        }

        if (runManualBtn) {
            runManualBtn.hidden = ! packageReady || downloading;
            runManualBtn.disabled = downloading;
        }

        if (downloadBtn) {
            downloadBtn.hidden = packageReady && ! downloading;
            downloadBtn.disabled = downloading;
            downloadBtn.textContent = downloading ? 'Baixando...' : (packageReady ? 'Baixar de novo' : 'Baixar atualização');
            if (packageReady && ! downloading) {
                downloadBtn.hidden = true;
            }
        }

        if (hint && packageReady && ! downloading) {
            hint.textContent = 'O Atualizador encerra o sistema para aplicar os arquivos com segurança.';
        }
    }

    function parseDownloadPercent(text) {
        const match = String(text || '').match(/\((\d+(?:[.,]\d+)?)\s*%\)/);
        if (! match) {
            return 0;
        }
        return Math.max(0, Math.min(100, Math.round(Number(String(match[1]).replace(',', '.')))));
    }

    function refreshPackageStatus() {
        const config = window.__erpUpdateConfig ?? {};
        const statusUrl = config.statusUrl;
        if (! statusUrl) {
            return Promise.resolve();
        }

        return fetch(statusUrl, {
            method: 'GET',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));
                applyPackageStatus(payload);

                return payload;
            })
            .catch(() => null);
    }

    function startPackagePolling() {
        stopPackagePolling();
        refreshPackageStatus();
        packagePollTimer = setInterval(() => {
            if (updateRunning || updateModal?.hidden) {
                return;
            }
            refreshPackageStatus();
        }, 4000);
    }

    function openSystemUpdateModal() {
        if (! updateModal) {
            return;
        }

        updateStuck = false;
        updateFailed = false;
        updateCompletedHandled = false;
        installFromLocal = false;
        pollCount = 0;
        launchStartedAt = 0;
        lastStatusSignature = '';
        lastProgressAt = 0;
        showResetButton(false);
        resetUpdateSteps();
        resetUpdateInfo();

        showUpdatePanel('confirm');
        setUpdateProgress(0, false);
        setUpdatePercent(0);
        setUpdateStatus('Baixando pacote...', false);
        resetUpdateHint();

        const successBox = updateModal.querySelector('[data-erp-update-success]');
        const progressBox = updateModal.querySelector('[data-erp-update-download-progress]');
        const runManualBtn = updateModal.querySelector('[data-erp-update-run-manual]');
        if (successBox) successBox.hidden = true;
        if (progressBox) progressBox.hidden = true;
        if (runManualBtn) runManualBtn.hidden = true;

        updateModal.hidden = false;
        updateModal.setAttribute('aria-hidden', 'false');
        startPackagePolling();
        updateModal.querySelector('[data-erp-update-download]')?.focus();
    }

    function closeSystemUpdateModal() {
        if (! updateModal || ! canCloseUpdateModal()) {
            return;
        }

        stopPolling();
        stopPackagePolling();
        updateRunning = false;
        updateStuck = false;
        updateFailed = false;
        updateCompletedHandled = false;
        installFromLocal = false;
        showResetButton(false);
        resetUpdateSteps();
        resetUpdateInfo();
        showUpdatePanel('confirm');
        setUpdateProgress(8, false);
        setUpdatePercent(0);
        resetUpdateHint();
        updateModal.hidden = true;
        updateModal.setAttribute('aria-hidden', 'true');
    }

    function resetUpdateHint() {
        const hint = updateModal?.querySelector('[data-erp-update-hint]');
        if (hint) {
            hint.textContent = 'Pode continuar usando o sistema durante o download.';
        }
    }

    function showUpdatePanel(name) {
        updateModal?.querySelectorAll('[data-erp-update-panel]').forEach((panel) => {
            const active = panel.getAttribute('data-erp-update-panel') === name;
            panel.hidden = ! active;
        });
    }

    function setUpdateProgress(percent, animate) {
        const bar = updateModal?.querySelector('[data-erp-update-bar]');
        if (! bar) {
            return;
        }

        bar.classList.toggle('is-animating', !!animate);
        bar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
        bar.style.marginLeft = '0';
    }

    function setUpdatePercent(percent) {
        const label = updateModal?.querySelector('[data-erp-update-percent]');
        if (label) {
            label.textContent = `${Math.round(Math.max(0, Math.min(100, percent)))}%`;
        }
    }

    function paintDisplayedProgress() {
        setUpdateProgress(displayedPercent, false);
        setUpdatePercent(displayedPercent);
        renderUpdateStepBars(currentUpdateState, displayedStepProgress);
    }

    function ensureStepProgressMaps() {
        UPDATE_STEP_ORDER.forEach((step) => {
            if (typeof displayedStepProgress[step] !== 'number') {
                displayedStepProgress[step] = 0;
            }
            if (typeof targetStepProgress[step] !== 'number') {
                targetStepProgress[step] = 0;
            }
        });
    }

    function tickProgressAnimation() {
        ensureStepProgressMaps();
        let changed = false;

        // 1% por ciclo (~120ms) — lento o bastante para o usuário ver a contagem.
        if (displayedPercent < targetPercent) {
            displayedPercent = Math.min(targetPercent, displayedPercent + 1);
            changed = true;
        } else if (displayedPercent > targetPercent) {
            displayedPercent = targetPercent;
            changed = true;
        }

        UPDATE_STEP_ORDER.forEach((step) => {
            const target = Math.max(0, Math.min(100, Number(targetStepProgress[step] ?? 0)));
            let shown = Math.max(0, Math.min(100, Number(displayedStepProgress[step] ?? 0)));

            if (shown < target) {
                shown = Math.min(target, shown + 1);
                changed = true;
            } else if (shown > target) {
                shown = target;
                changed = true;
            }

            displayedStepProgress[step] = shown;
        });

        paintDisplayedProgress();

        if (! changed) {
            progressAnimTimer = 0;
            return;
        }

        progressAnimTimer = window.setTimeout(tickProgressAnimation, 120);
    }

    function startProgressAnimation() {
        if (progressAnimTimer) {
            return;
        }

        progressAnimTimer = window.setTimeout(tickProgressAnimation, 30);
    }

    function setProgressTargets(percent, stepProgress, state, serverActiveStepPercent) {
        const previousState = currentUpdateState;
        currentUpdateState = state || currentUpdateState;
        ensureStepProgressMaps();

        const hasExplicit =
            typeof serverActiveStepPercent === 'number' && ! Number.isNaN(serverActiveStepPercent);

        if (previousState !== currentUpdateState) {
            hasServerStepPercent = hasExplicit;
        } else if (hasExplicit) {
            hasServerStepPercent = true;
        }

        const incoming = { ...(stepProgress || {}) };

        // Preferência: % real da etapa vindo do servidor (1 em 1).
        if (currentUpdateState && hasExplicit) {
            incoming[currentUpdateState] = Math.max(0, Math.min(100, serverActiveStepPercent));
        }

        const activeIndex = UPDATE_STEP_ORDER.indexOf(currentUpdateState);

        UPDATE_STEP_ORDER.forEach((step) => {
            const serverValue = Math.max(0, Math.min(100, Number(incoming[step] ?? 0)));
            const shown = Number(displayedStepProgress[step] ?? 0);
            const stepIndex = UPDATE_STEP_ORDER.indexOf(step);

            if (step === currentUpdateState) {
                if (currentUpdateState === previousState) {
                    targetStepProgress[step] = Math.max(serverValue, shown);
                } else {
                    displayedStepProgress[step] = Math.max(0, Math.min(shown, Math.max(1, serverValue)));
                    targetStepProgress[step] = Math.max(1, serverValue);
                }
            } else if (activeIndex !== -1 && stepIndex < activeIndex) {
                targetStepProgress[step] = 100;
                displayedStepProgress[step] = 100;
            } else {
                targetStepProgress[step] = 0;
                if (previousState !== currentUpdateState) {
                    displayedStepProgress[step] = 0;
                }
            }
        });

        const nextPercent = Math.max(0, Math.min(100, Number(percent) || 0));
        if (currentUpdateState === previousState) {
            targetPercent = Math.max(nextPercent, displayedPercent);
        } else {
            targetPercent = Math.max(nextPercent, displayedPercent);
        }

        if (
            currentUpdateState &&
            UPDATE_STEP_ORDER.includes(currentUpdateState) &&
            Number(targetStepProgress[currentUpdateState] ?? 0) <= 0 &&
            ! ['completed', 'failed', 'idle'].includes(currentUpdateState)
        ) {
            targetStepProgress[currentUpdateState] = 1;
        }

        startProgressAnimation();
    }

    function resetProgressCounters() {
        window.clearTimeout(progressAnimTimer);
        progressAnimTimer = 0;
        window.cancelAnimationFrame(progressAnimRaf);
        progressAnimRaf = 0;
        window.clearInterval(softTickTimer);
        softTickTimer = 0;
        displayedPercent = 0;
        targetPercent = 0;
        displayedStepProgress = {};
        targetStepProgress = {};
        currentUpdateState = 'idle';
        hasServerStepPercent = false;
        UPDATE_STEP_ORDER.forEach((step) => {
            displayedStepProgress[step] = 0;
            targetStepProgress[step] = 0;
        });
        paintDisplayedProgress();
    }

    function startSoftProgressTick() {
        window.clearInterval(softTickTimer);
        softTickTimer = window.setInterval(function () {
            if (! updateRunning || updateStuck || updateFailed) {
                return;
            }

            // Com progresso real do servidor, não inventa %.
            if (hasServerStepPercent) {
                return;
            }

            const state = currentUpdateState;
            if (! state || ['completed', 'failed', 'idle'].includes(state)) {
                return;
            }

            ensureStepProgressMaps();
            const shown = Number(displayedStepProgress[state] ?? 0);
            const target = Number(targetStepProgress[state] ?? 0);
            const ceiling = 90;

            if (Math.max(shown, target) < ceiling && shown >= target) {
                targetStepProgress[state] = Math.min(ceiling, target + 1);
                startProgressAnimation();
            }
        }, 900);
    }

    function resetUpdateSteps() {
        updateModal?.querySelectorAll('[data-erp-update-steps] li').forEach((item) => {
            item.classList.remove('is-active', 'is-done');
        });
        resetUpdateStepBars();
    }

    function resetUpdateStepBars() {
        updateModal?.querySelectorAll('[data-step-bar]').forEach((bar) => {
            bar.style.width = '0%';
            bar.classList.remove('is-indeterminate');
        });
        updateModal?.querySelectorAll('[data-step-pct]').forEach((label) => {
            label.textContent = '0%';
        });
    }

    function computeStepProgressFromPercent(state, percent) {
        const progress = {};

        if (state === 'completed') {
            UPDATE_STEP_ORDER.forEach((step) => {
                progress[step] = 100;
            });

            return progress;
        }

        const activeIndex = UPDATE_STEP_ORDER.indexOf(state);

        UPDATE_STEP_ORDER.forEach((step, index) => {
            if (activeIndex === -1) {
                progress[step] = 0;

                return;
            }

            if (index < activeIndex) {
                progress[step] = 100;

                return;
            }

            if (index > activeIndex) {
                progress[step] = 0;

                return;
            }

            const range = UPDATE_STEP_RANGES[step] ?? [0, 100];
            const span = range[1] - range[0];

            if (span <= 0) {
                progress[step] = 100;

                return;
            }

            progress[step] = Math.max(
                0,
                Math.min(100, Math.round(((percent - range[0]) / span) * 100))
            );
        });

        return progress;
    }

    function renderUpdateStepBars(state, stepProgress) {
        UPDATE_STEP_ORDER.forEach((stepName) => {
            const bar = updateModal?.querySelector(`[data-step-bar="${stepName}"]`);
            const pctLabel = updateModal?.querySelector(`[data-step-pct="${stepName}"]`);
            if (! bar) {
                return;
            }

            const activeIndex = UPDATE_STEP_ORDER.indexOf(state);
            const stepIndex = UPDATE_STEP_ORDER.indexOf(stepName);
            let value = Math.max(0, Math.min(100, Number(stepProgress?.[stepName] ?? 0)));

            if (state === 'completed') {
                value = 100;
            } else if (activeIndex !== -1 && stepIndex < activeIndex) {
                value = 100;
            } else if (activeIndex !== -1 && stepIndex > activeIndex) {
                value = 0;
            }

            bar.classList.toggle('is-indeterminate', false);

            if (state === stepName && value <= 0) {
                value = 1;
            }

            if (pctLabel) {
                pctLabel.textContent = `${Math.round(value)}%`;
            }

            bar.style.width = `${value}%`;
        });
    }

    function renderUpdateSteps(state) {
        const steps = updateModal?.querySelectorAll('[data-erp-update-steps] li');
        if (! steps?.length) {
            return;
        }

        const activeIndex = UPDATE_STEP_ORDER.indexOf(state);

        steps.forEach((item) => {
            const stepName = item.getAttribute('data-step');
            const stepIndex = UPDATE_STEP_ORDER.indexOf(stepName ?? '');

            item.classList.remove('is-active', 'is-done');

            if (activeIndex === -1) {
                if (state === 'failed' && stepIndex !== -1) {
                    return;
                }

                return;
            }

            if (stepIndex < activeIndex) {
                item.classList.add('is-done');
            } else if (stepIndex === activeIndex) {
                item.classList.add('is-active');
            }
        });
    }

    function resetUpdateInfo() {
        setUpdateInfo('', '', '');
    }

    function setUpdateInfo(detail, command, elapsedLabel) {
        const detailEl = updateModal?.querySelector('[data-erp-update-detail]');
        const commandEl = updateModal?.querySelector('[data-erp-update-command]');
        const elapsedEl = updateModal?.querySelector('[data-erp-update-elapsed]');

        if (detailEl) {
            if (detail) {
                detailEl.hidden = false;
                detailEl.textContent = detail;
            } else {
                detailEl.hidden = true;
                detailEl.textContent = '';
            }
        }

        if (commandEl) {
            if (command) {
                commandEl.hidden = false;
                commandEl.textContent = `Executando: ${command}`;
            } else {
                commandEl.hidden = true;
                commandEl.textContent = '';
            }
        }

        if (elapsedEl) {
            if (elapsedLabel) {
                elapsedEl.hidden = false;
                elapsedEl.textContent = elapsedLabel;
            } else {
                elapsedEl.hidden = true;
                elapsedEl.textContent = '';
            }
        }
    }

    function formatElapsed(sinceMs) {
        if (! sinceMs) {
            return '';
        }

        const totalSeconds = Math.max(0, Math.floor((Date.now() - sinceMs) / 1000));
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;

        if (minutes > 0) {
            return `Tempo decorrido: ${minutes} min ${String(seconds).padStart(2, '0')} s`;
        }

        return `Tempo decorrido: ${seconds} s`;
    }

    function applyUpdatePayload(payload, isError = false) {
        const state = payload.state ?? 'idle';
        const message = payload.message ?? 'Atualizando...';
        const percent = Number(payload.percent ?? 0);
        const detail = payload.detail ?? '';
        const command = payload.command ?? '';
        const stepProgress =
            payload.step_progress && typeof payload.step_progress === 'object'
                ? payload.step_progress
                : computeStepProgressFromPercent(state, percent);
        const activeStepPercent =
            payload.active_step_percent === null || payload.active_step_percent === undefined
                ? null
                : Number(payload.active_step_percent);

        setUpdateStatus(message, isError || state === 'failed');
        renderUpdateSteps(state);
        setProgressTargets(percent, stepProgress, state, activeStepPercent);
        setUpdateInfo(
            detail,
            command,
            updateRunning ? formatElapsed(launchStartedAt) : ''
        );
    }

    function showResetButton(visible) {
        const button = updateModal?.querySelector('[data-erp-update-reset]');
        if (button) {
            button.hidden = ! visible;
        }
    }

    function markUpdateStuck(message) {
        updateStuck = true;
        updateRunning = false;
        window.clearInterval(softTickTimer);
        softTickTimer = 0;
        stopPolling();
        setUpdateStatus(message, true);
        showResetButton(true);

        const hint = updateModal?.querySelector('[data-erp-update-hint]');
        if (hint) {
            hint.textContent = 'Use "Limpar e tentar de novo" ou feche e abra novamente pelo menu Ajuda.';
        }
    }

    function markUpdateFailed(message) {
        updateFailed = true;
        updateRunning = false;
        window.clearInterval(softTickTimer);
        softTickTimer = 0;
        stopPolling();
        setUpdateStatus(message, true);
        showResetButton(true);

        const hint = updateModal?.querySelector('[data-erp-update-hint]');
        if (hint) {
            hint.textContent = 'Corrija o problema, limpe o estado e tente novamente.';
        }
    }

    function resetUpdateState() {
        const config = window.__erpUpdateConfig ?? {};
        const resetUrl = config.resetUrl;

        if (! resetUrl) {
            markUpdateFailed('Endpoint de reset indisponível.');
            return;
        }

        setUpdateStatus('Limpando estado...');

        fetch(resetUrl, {
            method: 'POST',
            headers: updateRequestHeaders({
                'Content-Type': 'application/json',
            }),
            credentials: 'same-origin',
            body: '{}',
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(resolveUpdateHttpError(response, payload));
                }

                updateStuck = false;
                updateFailed = false;
                updateRunning = false;
                showResetButton(false);
                showUpdatePanel('confirm');
                setUpdateProgress(8, false);
                setUpdatePercent(0);
                setUpdateStatus('Preparando atualização...', false);
                resetUpdateHint();
            })
            .catch((error) => {
                markUpdateFailed(error.message ?? 'Erro ao limpar estado.');
            });
    }

    function setUpdateStatus(message, isError = false) {
        const status = updateModal?.querySelector('[data-erp-update-status]');
        if (status) {
            status.textContent = message;
            status.classList.toggle('is-error', isError);
        }
    }

    function resolveStallLimit(state, config) {
        // 10 minutos de tolerância SEM progresso em CADA etapa.
        const tenMinutes = 600;

        if (state === 'starting') {
            return Number(config.startingStallSeconds ?? tenMinutes);
        }

        if (state === 'downloading') {
            return Number(config.downloadStallSeconds ?? tenMinutes);
        }

        if (state === 'extracting') {
            return Number(config.extractingStallSeconds ?? tenMinutes);
        }

        if (state === 'applying') {
            return Number(config.applyingStallSeconds ?? tenMinutes);
        }

        if (state === 'migrating') {
            return Number(config.migratingStallSeconds ?? tenMinutes);
        }

        if (state === 'finalizing') {
            return Number(config.finalizingStallSeconds ?? tenMinutes);
        }

        return Number(config.stallSeconds ?? tenMinutes);
    }

    function buildStatusSignature(payload) {
        const stepProgress = payload.step_progress ?? {};

        return [
            payload.state ?? 'idle',
            payload.percent ?? 0,
            payload.updated_at ?? '',
            payload.message ?? '',
            payload.detail ?? '',
            payload.command ?? '',
            payload.download_bytes ?? '',
            payload.download_total ?? '',
            JSON.stringify(stepProgress),
        ].join('|');
    }

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : '';
    }

    function getCsrfToken() {
        return (
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
            document.querySelector('input[name="_token"]')?.value ??
            getCookie('XSRF-TOKEN') ??
            ''
        );
    }

    function updateRequestHeaders(extra = {}) {
        const csrf = getCsrfToken();
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...extra,
        };

        if (csrf) {
            headers['X-CSRF-TOKEN'] = csrf;
            headers['X-XSRF-TOKEN'] = csrf;
        }

        return headers;
    }

    function resolveUpdateHttpError(response, payload) {
        const fallback = payload?.message || payload?.error || '';

        if (response.status === 401 || /unauthenticated/i.test(String(fallback))) {
            return 'Sessão expirada ou inválida. Faça login novamente e tente atualizar.';
        }

        if (response.status === 419) {
            return 'Token de segurança expirado. Recarregue a página (F5) e tente novamente.';
        }

        if (response.status === 403) {
            return 'Sem permissão para atualizar o sistema.';
        }

        if (response.status === 409) {
            return fallback || 'Já existe uma atualização em andamento.';
        }

        return fallback || ('Erro HTTP ' + response.status + ' ao comunicar com o servidor.');
    }

    function startPackageDownload() {
        const config = window.__erpUpdateConfig ?? {};
        const downloadUrl = config.downloadUrl;
        const statusEl = updateModal?.querySelector('[data-erp-update-package-status]');

        if (! downloadUrl) {
            if (statusEl) {
                statusEl.textContent = 'Endpoint de download indisponível.';
                statusEl.classList.add('is-error');
            }
            return;
        }

        if (statusEl) {
            statusEl.textContent = 'Iniciando download em segundo plano...';
            statusEl.classList.remove('is-ready', 'is-error');
            statusEl.classList.add('is-warn');
        }

        const downloadBtn = updateModal?.querySelector('[data-erp-update-download]');
        if (downloadBtn) {
            downloadBtn.disabled = true;
            downloadBtn.textContent = 'Baixando...';
        }

        fetch(downloadUrl, {
            method: 'POST',
            headers: updateRequestHeaders({
                'Content-Type': 'application/json',
            }),
            credentials: 'same-origin',
            body: JSON.stringify({ force: false }),
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(resolveUpdateHttpError(response, payload));
                }

                if (payload.package) {
                    applyPackageStatus(payload.package);
                } else {
                    refreshPackageStatus();
                }
                startPackagePolling();
            })
            .catch((error) => {
                if (statusEl) {
                    statusEl.textContent = error.message ?? 'Erro ao iniciar download.';
                    statusEl.classList.remove('is-ready', 'is-warn');
                    statusEl.classList.add('is-error');
                }
                if (downloadBtn) {
                    downloadBtn.disabled = false;
                    downloadBtn.textContent = 'Baixar atualização';
                }
            });
    }

    function startManualUpdater() {
        const config = window.__erpUpdateConfig ?? {};
        const runManualUrl = config.runManualUrl;
        const statusEl = updateModal?.querySelector('[data-erp-update-package-status]');
        const runManualBtn = updateModal?.querySelector('[data-erp-update-run-manual]');
        const hint = updateModal?.querySelector('[data-erp-update-hint]');

        if (! runManualUrl) {
            if (statusEl) {
                statusEl.textContent = 'Endpoint de atualização manual indisponível.';
                statusEl.classList.add('is-error');
            }
            return;
        }

        if (! packageReady) {
            if (statusEl) {
                statusEl.textContent = 'Baixe o pacote primeiro.';
                statusEl.classList.remove('is-ready');
                statusEl.classList.add('is-warn');
            }
            return;
        }

        if (runManualBtn) {
            runManualBtn.disabled = true;
            runManualBtn.textContent = 'Abrindo Atualizador...';
        }
        if (statusEl) {
            statusEl.textContent = 'Abrindo Unitec Atualizador.exe...';
            statusEl.classList.remove('is-error');
            statusEl.classList.add('is-warn');
        }

        fetch(runManualUrl, {
            method: 'POST',
            headers: updateRequestHeaders({
                'Content-Type': 'application/json',
            }),
            credentials: 'same-origin',
            body: JSON.stringify({}),
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(resolveUpdateHttpError(response, payload));
                }

                if (statusEl) {
                    statusEl.textContent = payload.message
                        || 'Atualizador iniciado. O sistema será encerrado.';
                    statusEl.classList.remove('is-warn', 'is-error');
                    statusEl.classList.add('is-ready');
                }
                if (hint) {
                    hint.textContent = 'Feche o navegador se a aba não fechar sozinha. Aguarde o Atualizador concluir.';
                }
                if (runManualBtn) {
                    runManualBtn.textContent = 'Atualizador aberto';
                }

                window.setTimeout(() => {
                    try {
                        window.close();
                    } catch (e) {
                        // ignore
                    }
                }, 800);
            })
            .catch((error) => {
                if (statusEl) {
                    statusEl.textContent = error.message ?? 'Erro ao abrir o Atualizador.';
                    statusEl.classList.remove('is-ready', 'is-warn');
                    statusEl.classList.add('is-error');
                }
                if (runManualBtn) {
                    runManualBtn.disabled = false;
                    runManualBtn.textContent = 'Executar atualização manual';
                }
            });
    }

    function startSystemUpdate() {
        // Instalação in-app desativada — mantido só por compatibilidade.
        startManualUpdater();
    }

    function startPolling(statusUrl, config, maxMinutes) {
        stopPolling();
        pollTimer = window.setInterval(
            () => pollUpdateStatus(statusUrl, config, maxMinutes),
            1500
        );
        pollUpdateStatus(statusUrl, config, maxMinutes);
    }

    function stopPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function pollUpdateStatus(statusUrl, config, maxMinutes) {
        if (updateCompletedHandled) {
            return;
        }

        pollCount += 1;

        if (launchStartedAt > 0) {
            const elapsedMinutes = (Date.now() - launchStartedAt) / 60000;
            if (elapsedMinutes > maxMinutes) {
                markUpdateStuck('A atualização excedeu o tempo máximo. O processo pode ter parado.');

                return;
            }
        }

        fetch(statusUrl, {
            headers: updateRequestHeaders(),
            credentials: 'same-origin',
        })
            .then(async (response) => {
                if (updateCompletedHandled) {
                    return;
                }

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    const errorText = resolveUpdateHttpError(response, payload);
                    // Durante apply/migrate o PHP pode responder 500/503 (manutenção ou
                    // arquivos no meio da troca). Não aborta — o processo segue em background.
                    const transientHttp =
                        [500, 502, 503, 504].includes(response.status) ||
                        /failed to open stream|no such file or directory|sessions|service unavailable|server error/i.test(
                            errorText
                        );

                    if (transientHttp) {
                        const hint = updateModal?.querySelector('[data-erp-update-hint]');
                        if (hint) {
                            hint.textContent =
                                'Servidor temporariamente indisponível durante a atualização. Continuando a monitorar...';
                        }
                        setUpdateStatus('Aguardando o servidor voltar…', false);

                        return;
                    }

                    markUpdateFailed(errorText);

                    return;
                }

                const state = payload.state ?? 'idle';
                const message = payload.message ?? 'Atualizando...';
                const signature = buildStatusSignature(payload);
                const stallLimit = resolveStallLimit(state, config);

                if (pollCount >= 3 && state === 'idle' && updateRunning) {
                    markUpdateStuck('A atualização não iniciou em segundo plano. Verifique os logs do servidor.');
                    renderUpdateSteps('starting');

                    return;
                }

                if (signature !== lastStatusSignature) {
                    lastStatusSignature = signature;
                    lastProgressAt = Date.now();
                } else if (updateRunning && ! ['completed', 'failed', 'idle'].includes(state)) {
                    const stalledFor = (Date.now() - lastProgressAt) / 1000;

                    if (stalledFor >= stallLimit) {
                        const stallLabel =
                            'Sem progresso nesta etapa há mais de 10 minutos.';

                        markUpdateStuck(`${stallLabel} A atualização pode estar travada.`);

                        return;
                    }

                    if (stalledFor >= Math.min(stallLimit, 120) && state === 'downloading') {
                        const hint = updateModal?.querySelector('[data-erp-update-hint]');
                        if (hint) {
                            hint.textContent =
                                'Download em andamento (pacote grande). Aguarde — o progresso atualiza a cada poucos segundos.';
                        }
                    }

                    if (stalledFor >= Math.min(stallLimit, 90) && (state === 'extracting' || state === 'applying')) {
                        const hint = updateModal?.querySelector('[data-erp-update-hint]');
                        if (hint) {
                            hint.textContent =
                                'Etapa demorada em andamento (ZIP grande / muitos arquivos). Aguarde — cada etapa pode levar até 10 minutos.';
                        }
                    }

                    if (stalledFor >= Math.min(stallLimit, 90) && state === 'migrating') {
                        const hint = updateModal?.querySelector('[data-erp-update-hint]');
                        if (hint) {
                            hint.textContent =
                                'Migrations em andamento. Esta etapa não mostra % intermediário — aguarde até concluir (pode levar vários minutos).';
                        }
                    }
                }

                applyUpdatePayload(payload, state === 'failed');

                if (state === 'completed') {
                    finishUpdateSuccessfully(payload);
                }

                if (state === 'failed') {
                    markUpdateFailed(message);
                }
            })
            .catch(() => {
                // Durante migrate o servidor pode demorar a responder; mantém polling.
            });
    }

    /**
     * Após update: limpa PWA/cache, faz logout e abre o login (sessão limpa).
     * location.reload() no PWA costuma demorar ou “travar” com assets/sessão velhos.
     */
    function finishUpdateSuccessfully(payload) {
        if (updateCompletedHandled) {
            return;
        }

        updateCompletedHandled = true;
        updateRunning = false;
        updateStuck = false;
        updateFailed = false;
        window.clearInterval(softTickTimer);
        softTickTimer = 0;
        stopPolling();
        showResetButton(false);
        setProgressTargets(100, computeStepProgressFromPercent('completed', 100), 'completed', 100);

        const hint = updateModal?.querySelector('[data-erp-update-hint]');
        if (hint) {
            hint.textContent = 'Atualização ok. Abrindo o login…';
        }

        setUpdateStatus('Atualização concluída', false);
        setUpdateInfo(
            (payload && payload.detail) || 'Atualização aplicada. Abrindo o login…',
            '',
            formatElapsed(launchStartedAt)
        );

        // Fluxo simples: não reinicia PHP. Só limpa sessão e abre o login.
        window.setTimeout(() => {
            void goToLoginAfterUpdate();
        }, 1500);
    }

    async function goToLoginAfterUpdate() {
        const config = window.__erpUpdateConfig ?? {};
        const logoutUrl = config.logoutUrl || '/admin/logout';
        const loginUrl = (config.loginUrl || '/admin/login') + '?updated=1&_=' + Date.now();

        try {
            if ('serviceWorker' in navigator) {
                const regs = await navigator.serviceWorker.getRegistrations();
                await Promise.all(regs.map((reg) => reg.unregister().catch(() => false)));
            }
        } catch (e) {}

        try {
            if (window.caches && typeof caches.keys === 'function') {
                const keys = await caches.keys();
                await Promise.all(keys.map((key) => caches.delete(key).catch(() => false)));
            }
        } catch (e) {}

        const goLogin = () => {
            window.location.replace(loginUrl);
        };

        const hardTimeout = window.setTimeout(goLogin, 3000);

        try {
            const csrf = getCsrfToken();
            const body = new URLSearchParams();
            if (csrf) {
                body.set('_token', csrf);
            }

            await Promise.race([
                fetch(logoutUrl, {
                    method: 'POST',
                    headers: updateRequestHeaders({
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }),
                    credentials: 'same-origin',
                    body: body.toString(),
                    redirect: 'manual',
                }),
                new Promise((resolve) => window.setTimeout(resolve, 2000)),
            ]);
        } catch (e) {
            // segue para o login mesmo se o POST falhar
        }

        window.clearTimeout(hardTimeout);
        goLogin();
    }
})();

function notifyErp(body, type, title = 'Unitec ERP') {
    if (window.Filament?.notifications && typeof FilamentNotification !== 'undefined') {
        const notification = new FilamentNotification().title(title).body(body);

        if (type === 'success') {
            notification.success();
        } else if (type === 'danger') {
            notification.danger();
        } else {
            notification.info();
        }

        notification.send();
        return;
    }

    window.alert(`${title}\n\n${body}`);
}

(function bindErpPasswordToggles() {
    if (window.__erpPasswordToggleBound) {
        return;
    }

    window.__erpPasswordToggleBound = true;

    function syncPlainPasswordMask(input) {
        if (! input || input.dataset.erpPasswordMask !== 'plain') {
            return;
        }

        const button = document.querySelector(`[data-erp-password-toggle="${input.id}"]`);

        if (! button) {
            input.classList.add('is-masked');
            return;
        }

        input.classList.toggle('is-masked', ! button.classList.contains('is-visible'));
    }

    function syncPlainPasswordMasks(root = document) {
        root.querySelectorAll?.('[data-erp-password-mask="plain"]').forEach(syncPlainPasswordMask);
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-erp-password-toggle]');

        if (! button) {
            return;
        }

        const inputId = button.getAttribute('data-erp-password-toggle');

        if (! inputId) {
            return;
        }

        const input = document.getElementById(inputId);

        if (! input) {
            return;
        }

        if (input.dataset.erpPasswordMask === 'plain') {
            const masked = input.classList.toggle('is-masked');
            button.classList.toggle('is-visible', ! masked);
            button.setAttribute('aria-label', masked ? 'Exibir senha' : 'Ocultar senha');
            button.setAttribute('title', masked ? 'Exibir senha' : 'Ocultar senha');
            input.focus();
            return;
        }

        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        button.classList.toggle('is-visible', showPassword);
        button.setAttribute('aria-label', showPassword ? 'Ocultar senha' : 'Exibir senha');
        button.setAttribute('title', showPassword ? 'Ocultar senha' : 'Exibir senha');
    });

    document.addEventListener('livewire:init', () => {
        if (! window.Livewire?.hook) {
            return;
        }

        window.Livewire.hook('morph.updated', ({ el }) => {
            syncPlainPasswordMasks(el);
        });
    });

    document.addEventListener('DOMContentLoaded', () => syncPlainPasswordMasks());
    document.addEventListener('livewire:navigated', () => syncPlainPasswordMasks());
})();

(function bindErpStatusBarClock() {
    function formatSaoPauloNow() {
        const parts = new Intl.DateTimeFormat('pt-BR', {
            timeZone: 'America/Sao_Paulo',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).formatToParts(new Date());

        const get = (type) => parts.find((part) => part.type === type)?.value ?? '';

        return `${get('day')}/${get('month')}/${get('year')} ${get('hour')}:${get('minute')}:${get('second')}`;
    }

    function tickErpStatusBarClock() {
        const clockEl = document.getElementById('erp-status-updated-at');

        if (! clockEl) {
            return;
        }

        clockEl.textContent = formatSaoPauloNow();
    }

    if (! window.__erpStatusBarClockTimer) {
        window.__erpStatusBarClockTimer = window.setInterval(tickErpStatusBarClock, 1000);
    }

    document.addEventListener('DOMContentLoaded', tickErpStatusBarClock);
    document.addEventListener('livewire:navigated', tickErpStatusBarClock);
    tickErpStatusBarClock();
})();
