(function () {
    const updateModal = document.getElementById('erp-system-update-modal');
    let pollTimer = null;
    let packagePollTimer = null;
    let updateRunning = false;
    let updateStuck = false;
    let updateFailed = false;
    let pollCount = 0;
    let launchStartedAt = 0;
    let lastStatusSignature = '';
    let lastProgressAt = 0;
    let packageReady = false;
    let installFromLocal = false;

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
        const installBtn = updateModal?.querySelector('[data-erp-update-start]');
        const downloadBtn = updateModal?.querySelector('[data-erp-update-download]');

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
                || `Pacote pronto${remoteVersion !== '—' ? ' (' + remoteVersion + ')' : ''}${payload.package_bytes ? ' · ' + formatBytes(payload.package_bytes) : ''}. Pode instalar.`;
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

        if (installBtn) {
            installBtn.hidden = ! packageReady;
            installBtn.disabled = downloading;
        }

        if (downloadBtn) {
            downloadBtn.hidden = packageReady && ! downloading;
            downloadBtn.disabled = downloading;
            downloadBtn.textContent = downloading ? 'Baixando...' : (packageReady ? 'Baixar de novo' : 'Baixar agora');
            if (packageReady && ! downloading) {
                downloadBtn.hidden = true;
            }
        }
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
        installFromLocal = false;
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
            hint.textContent = 'Não feche o navegador até a instalação terminar.';
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
        if (state === 'downloading') {
            return Number(config.downloadStallSeconds ?? 900);
        }

        if (state === 'applying') {
            return Number(config.applyingStallSeconds ?? 600);
        }

        if (state === 'migrating') {
            return Number(config.migratingStallSeconds ?? 1200);
        }

        if (state === 'finalizing') {
            return Number(config.finalizingStallSeconds ?? 300);
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
                    downloadBtn.textContent = 'Baixar agora';
                }
            });
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

        if (! packageReady) {
            const statusEl = updateModal?.querySelector('[data-erp-update-package-status]');
            if (statusEl) {
                statusEl.textContent = 'Baixe o pacote primeiro. A instalação só começa com o ZIP local pronto.';
                statusEl.classList.remove('is-ready');
                statusEl.classList.add('is-warn');
            }
            return;
        }

        stopPackagePolling();
        updateRunning = true;
        updateStuck = false;
        updateFailed = false;
        installFromLocal = true;
        pollCount = 0;
        launchStartedAt = Date.now();
        lastStatusSignature = '';
        lastProgressAt = Date.now();
        showResetButton(false);
        resetUpdateSteps();
        resetUpdateInfo();

        showUpdatePanel('progress');
        renderUpdateSteps('starting');
        setUpdateStatus('Instalando pacote já baixado...');
        setUpdateInfo(
            'Usando ZIP em storage/app/private/updates (sem baixar de novo)',
            'php artisan unitec:apply-update',
            ''
        );
        setUpdateProgress(8, true);
        setUpdatePercent(0);

        // Marca "Baixar pacote" como concluído — já está no disco.
        const downloadStep = updateModal?.querySelector('[data-step="downloading"]');
        downloadStep?.classList.add('is-done');
        const downloadPct = updateModal?.querySelector('[data-step-pct="downloading"]');
        if (downloadPct) {
            downloadPct.textContent = '100%';
        }
        const downloadBar = updateModal?.querySelector('[data-step-bar="downloading"]');
        if (downloadBar) {
            downloadBar.style.width = '100%';
            downloadBar.classList.remove('is-indeterminate');
        }

        fetch(launchUrl, {
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
                    if (response.status === 422 && payload.needs_download) {
                        updateRunning = false;
                        showUpdatePanel('confirm');
                        applyPackageStatus(payload.package || {});
                        startPackagePolling();
                        const statusEl = updateModal?.querySelector('[data-erp-update-package-status]');
                        if (statusEl) {
                            statusEl.textContent = payload.message || 'Pacote ainda não está pronto.';
                            statusEl.classList.add('is-warn');
                        }
                        return;
                    }

                    throw new Error(resolveUpdateHttpError(response, payload));
                }

                setUpdateInfo(
                    'Processo aceito. Extraindo e aplicando arquivos...',
                    'php artisan unitec:apply-update',
                    formatElapsed(launchStartedAt)
                );

                startPolling(statusUrl, config, maxMinutes);
            })
            .catch((error) => {
                updateRunning = false;
                updateFailed = true;
                markUpdateFailed(error.message ?? 'Erro ao iniciar instalação.');
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
            headers: updateRequestHeaders(),
            credentials: 'same-origin',
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    const errorText = resolveUpdateHttpError(response, payload);
                    // Erro de pasta/sessão no poll não aborta o processo em segundo plano.
                    if (/failed to open stream|no such file or directory|sessions/i.test(errorText)) {
                        const hint = updateModal?.querySelector('[data-erp-update-hint]');
                        if (hint) {
                            hint.textContent =
                                'Servidor temporariamente indisponível durante a atualização. Continuando a monitorar...';
                        }

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
