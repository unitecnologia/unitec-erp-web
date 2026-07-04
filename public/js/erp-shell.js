(function () {
    const updateModal = document.getElementById('erp-system-update-modal');
    let pollTimer = null;
    let updateRunning = false;
    let updateStuck = false;
    let updateFailed = false;
    let pollCount = 0;
    let launchStartedAt = 0;
    let lastStatusSignature = '';
    let lastProgressAt = 0;

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
        extracting: [38, 58],
        applying: [58, 82],
        migrating: [82, 92],
        finalizing: [92, 100],
        completed: [100, 100],
    };

    document.addEventListener(
        'click',
        (event) => {
            const updateButton = event.target.closest('[data-erp-action="system-update"]');
            if (updateButton) {
                event.preventDefault();
                event.stopPropagation();
                openSystemUpdateModal();
                return;
            }

            const moduleButton = event.target.closest('[data-erp-module]');
            if (moduleButton) {
                event.preventDefault();
                event.stopPropagation();
                notifyErp('Em implementação.', 'info', `Módulo: ${moduleButton.getAttribute('data-erp-module') ?? ''}`);
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
            }

            if (event.target.closest('[data-erp-update-start]')) {
                startSystemUpdate();
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

    function bindMenuUi() {
        document.querySelectorAll('.erp-menu-bar__details').forEach((details) => {
            if (details.dataset.erpMenuBound === '1') {
                return;
            }

            details.dataset.erpMenuBound = '1';
            details.addEventListener('toggle', () => {
                if (! details.open) {
                    return;
                }

                document.querySelectorAll('.erp-menu-bar__details[open]').forEach((other) => {
                    if (other !== details) {
                        other.removeAttribute('open');
                    }
                });
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
        });
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-erp-action], [data-erp-module]')) {
            return;
        }

        if (! event.target.closest('.erp-menu-bar__details')) {
            document.querySelectorAll('.erp-menu-bar__details[open]').forEach((details) => {
                details.removeAttribute('open');
            });
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindMenuUi);
    } else {
        bindMenuUi();
    }

    function canCloseUpdateModal() {
        return ! updateRunning || updateStuck || updateFailed;
    }

    function openSystemUpdateModal() {
        if (! updateModal) {
            return;
        }

        updateStuck = false;
        updateFailed = false;
        pollCount = 0;
        launchStartedAt = 0;
        lastStatusSignature = '';
        lastProgressAt = 0;
        showResetButton(false);
        resetUpdateSteps();
        resetUpdateInfo();

        showUpdatePanel('confirm');
        setUpdateProgress(8, false);
        setUpdatePercent(0);
        setUpdateStatus('Preparando atualização...', false);
        resetUpdateHint();
        updateModal.hidden = false;
        updateModal.setAttribute('aria-hidden', 'false');
        updateModal.querySelector('[data-erp-update-start]')?.focus();
    }

    function closeSystemUpdateModal() {
        if (! updateModal || ! canCloseUpdateModal()) {
            return;
        }

        stopPolling();
        updateRunning = false;
        updateStuck = false;
        updateFailed = false;
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
            hint.textContent = 'Não feche o navegador até a atualização terminar.';
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

        bar.classList.toggle('is-animating', animate);
        bar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
        bar.style.marginLeft = '0';
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

            const isActive = state === stepName;
            const isIndeterminate = isActive && value <= 0 && ['starting', 'downloading', 'extracting'].includes(state);

            bar.classList.toggle('is-indeterminate', isIndeterminate);

            if (pctLabel) {
                if (isIndeterminate) {
                    pctLabel.textContent = '…';
                } else {
                    pctLabel.textContent = `${Math.round(value)}%`;
                }
            }

            if (isIndeterminate) {
                bar.style.width = '40%';

                return;
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

        setUpdateStatus(message, isError || state === 'failed');
        setUpdateProgress(percent, ! ['completed', 'failed'].includes(state) && percent <= 0);
        setUpdatePercent(percent);
        renderUpdateSteps(state);
        renderUpdateStepBars(state, stepProgress);
        setUpdateInfo(
            detail,
            command,
            updateRunning ? formatElapsed(launchStartedAt) : ''
        );
    }

    function setUpdatePercent(percent) {
        const label = updateModal?.querySelector('[data-erp-update-percent]');
        if (label) {
            label.textContent = `${Math.round(Math.max(0, Math.min(100, percent)))}%`;
        }
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
        stopPolling();
        setUpdateProgress(0, false);
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
        stopPolling();
        setUpdateProgress(0, false);
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
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: '{}',
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(payload.message ?? 'Não foi possível limpar o estado.');
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
        if (state === 'downloading') {
            return Number(config.downloadStallSeconds ?? 900);
        }

        if (state === 'applying') {
            return Number(config.applyingStallSeconds ?? 600);
        }

        return Number(config.stallSeconds ?? 180);
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

    function getCsrfToken() {
        return (
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
            document.querySelector('input[name="_token"]')?.value ??
            ''
        );
    }

    function startSystemUpdate() {
        const config = window.__erpUpdateConfig ?? {};
        const launchUrl = config.launchUrl;
        const statusUrl = config.statusUrl;
        const maxMinutes = Number(config.maxMinutes ?? 45);

        if (! launchUrl || ! statusUrl) {
            showUpdatePanel('progress');
            setUpdateStatus('Configuração de atualização indisponível.', true);
            showResetButton(true);
            return;
        }

        updateRunning = true;
        updateStuck = false;
        updateFailed = false;
        pollCount = 0;
        launchStartedAt = Date.now();
        lastStatusSignature = '';
        lastProgressAt = Date.now();
        showResetButton(false);
        resetUpdateSteps();
        resetUpdateInfo();

        showUpdatePanel('progress');
        renderUpdateSteps('starting');
        setUpdateStatus('Iniciando atualização...');
        setUpdateInfo(
            'Enviando solicitação ao servidor...',
            'php artisan unitec:apply-update',
            ''
        );
        setUpdateProgress(8, true);
        setUpdatePercent(0);

        fetch(launchUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: '{}',
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(payload.message ?? 'Não foi possível iniciar a atualização.');
                }

                setUpdateInfo(
                    'Processo aceito. Aguardando início do download...',
                    'php artisan unitec:apply-update',
                    formatElapsed(launchStartedAt)
                );

                startPolling(statusUrl, config, maxMinutes);
            })
            .catch((error) => {
                updateRunning = false;
                updateFailed = true;
                setUpdateProgress(0, false);
                setUpdatePercent(0);
                setUpdateStatus(error.message ?? 'Erro ao iniciar a atualização.', true);
                showResetButton(true);

                const hint = updateModal?.querySelector('[data-erp-update-hint]');
                if (hint) {
                    hint.textContent = 'Verifique storage/logs/erp-update-spawn.log, instalacao.log e UNITEC_UPDATE_DOWNLOAD_URL no .env.';
                }
            });
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
        pollCount += 1;

        if (launchStartedAt > 0) {
            const elapsedMinutes = (Date.now() - launchStartedAt) / 60000;
            if (elapsedMinutes > maxMinutes) {
                markUpdateStuck('A atualização excedeu o tempo máximo. O processo pode ter parado.');

                return;
            }
        }

        fetch(statusUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));
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
                            state === 'downloading'
                                ? 'Sem progresso no download há mais de 15 minutos.'
                                : state === 'applying'
                                  ? 'Sem progresso ao copiar arquivos há mais de 10 minutos.'
                                  : 'Sem progresso há mais de 3 minutos.';

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
                }

                applyUpdatePayload(payload, state === 'failed');

                if (state === 'completed') {
                    updateRunning = false;
                    stopPolling();
                    setUpdateProgress(100, false);
                    setUpdatePercent(100);

                    window.setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }

                if (state === 'failed') {
                    markUpdateFailed(message);
                }
            })
            .catch(() => {
                // Durante migrate o servidor pode demorar a responder; mantém polling.
            });
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
