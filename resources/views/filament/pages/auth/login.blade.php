<div class="unitec-login-root">
    <div class="unitec-login" aria-label="Tela de acesso">
        <div class="unitec-login__modal">
            <div class="unitec-login__left">
                <p class="unitec-login__eyebrow">Acesso ao ERP</p>
                <h1 class="unitec-login__welcome">Bem-vindo ao Sistema</h1>
                <p class="unitec-login__tagline">Gestão completa para o seu negócio</p>

                <div class="unitec-login__brand" aria-label="Unitecnologia Sistemas">
                    <img
                        src="{{ asset('img/erp/brand/unitecnologia-logo.png') }}"
                        alt="Unitecnologia Sistemas"
                        class="unitec-login__brand-img"
                        width="480"
                        height="160"
                        decoding="async"
                    >
                </div>

                <div class="unitec-login__meta">
                    <p class="unitec-login__version" aria-label="Versão do sistema">
                        Versão {{ \App\Support\Erp\ErpUpdateService::readInstalledVersion() }}
                    </p>
                    <p class="unitec-login__copyright">
                        © Unitecnologia Sistemas LTDA
                    </p>
                </div>
            </div>

            <div class="unitec-login__right">
                <div class="unitec-login__right-header">
                    <div>
                        <p class="unitec-login__instruction">Entrar</p>
                        <p class="unitec-login__instruction-hint">Informe empresa, usuário e senha</p>
                    </div>
                    <button
                        type="button"
                        class="unitec-login__close"
                        wire:click="cancel"
                        aria-label="Fechar"
                    >
                        &times;
                    </button>
                </div>

                <div class="unitec-login__form" autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-bwignore="true">
                    @if (filled($schemaMigrateError))
                        <div class="unitec-login__schema-alert unitec-login__schema-alert--erro" role="alert">
                            {{ $schemaMigrateError }}
                        </div>
                    @elseif (filled($schemaMigrateOk))
                        <div class="unitec-login__schema-alert unitec-login__schema-alert--ok" role="status">
                            {{ $schemaMigrateOk }}
                        </div>
                    @endif
                    {{ $this->content }}
                </div>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />

    @if ($showUpdatePrompt)
        <div
            class="unitec-update-prompt"
            role="dialog"
            aria-modal="true"
            aria-labelledby="unitec-update-prompt-title"
            data-unitec-update-dialog
            data-progress-url="{{ \Illuminate\Support\Facades\Route::has('erp.atualizacao.progress') ? route('erp.atualizacao.progress') : '' }}"
        >
            <div class="unitec-update-prompt__card">
                <h2 id="unitec-update-prompt-title">Existem atualizações</h2>
                <p>Deseja fazer a atualização agora{{ $pendingUpdateVersion ? ' (versão '.$pendingUpdateVersion.')' : '' }}?</p>
                <div class="unitec-update-prompt__actions" data-unitec-update-actions @if ($applyingUpdate) hidden @endif>
                    <button
                        type="button"
                        class="unitec-update-prompt__yes"
                        wire:click="aceitarAtualizacao"
                        wire:loading.attr="disabled"
                        x-on:click="window.UnitecUpdateProgress.start(
                            $el.closest('[data-unitec-update-dialog]'),
                            () => $wire.finalizarAtualizacaoAplicada(),
                            (message) => $wire.falharAtualizacaoAplicada(message)
                        )"
                    >Sim</button>
                    <button
                        type="button"
                        class="unitec-update-prompt__no"
                        wire:click="recusarAtualizacao"
                        wire:loading.attr="disabled"
                    >Não</button>
                </div>
                <div class="unitec-update-prompt__progress" data-unitec-update-progress @if (! $applyingUpdate) hidden @endif>
                    <div class="unitec-update-prompt__progress-head">
                        <span data-unitec-update-message>Preparando atualização…</span>
                        <strong><span data-unitec-update-percent>1</span>%</strong>
                    </div>
                    <div class="unitec-update-prompt__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="1">
                        <div class="unitec-update-prompt__bar" data-unitec-update-bar style="width: 1%"></div>
                    </div>
                    <p class="unitec-update-prompt__counter" data-unitec-update-counter>Aguarde…</p>
                </div>
                @if ($updateApplyError)
                    <p class="unitec-update-prompt__error">{{ $updateApplyError }}</p>
                @endif
            </div>
        </div>
        <style>
            .unitec-update-prompt {
                position: fixed; inset: 0; z-index: 99999;
                display: flex; align-items: center; justify-content: center;
                background: rgba(15, 23, 42, 0.55);
            }
            .unitec-update-prompt__card {
                width: min(420px, 92vw);
                background: #fff;
                border-radius: 12px;
                padding: 1.5rem 1.35rem;
                box-shadow: 0 18px 50px rgba(0,0,0,.25);
                color: #0f172a;
            }
            .unitec-update-prompt__card h2 { margin: 0 0 .5rem; font-size: 1.15rem; }
            .unitec-update-prompt__card p { margin: 0 0 1.1rem; color: #334155; line-height: 1.45; }
            .unitec-update-prompt__actions { display: flex; gap: .65rem; justify-content: flex-end; }
            .unitec-update-prompt__yes, .unitec-update-prompt__no {
                border-radius: 8px; padding: .55rem 1.1rem; font-weight: 650; cursor: pointer;
            }
            .unitec-update-prompt__yes { background: #0f766e; color: #fff; border: 0; }
            .unitec-update-prompt__no { background: #e2e8f0; color: #0f172a; border: 1px solid #94a3b8; }
            .unitec-update-prompt__progress { margin-top: 1rem; }
            .unitec-update-prompt__progress-head {
                display: flex; justify-content: space-between; gap: 1rem; align-items: center;
                margin-bottom: .55rem; color: #334155; font-size: .9rem;
            }
            .unitec-update-prompt__progress-head strong { color: #0f766e; font-variant-numeric: tabular-nums; }
            .unitec-update-prompt__track {
                width: 100%; height: 10px; overflow: hidden; border-radius: 999px;
                background: #e2e8f0; box-shadow: inset 0 1px 2px rgba(15, 23, 42, .08);
            }
            .unitec-update-prompt__bar {
                height: 100%; border-radius: inherit;
                background: linear-gradient(90deg, #0f766e, #14b8a6);
                transition: width .2s linear;
            }
            .unitec-update-prompt__counter {
                margin: .5rem 0 0 !important; color: #64748b !important;
                font-size: .8rem; font-variant-numeric: tabular-nums;
            }
            .unitec-update-prompt__error {
                margin: .8rem 0 0 !important; color: #b91c1c !important; font-size: .88rem;
            }
        </style>
    @endif

    {{-- Override final — garante contraste após CSS do Filament --}}
    <style>
        .unitec-login__form .unitec-login__senha-wrap input {
            -webkit-text-security: disc !important;
            text-security: disc !important;
            padding-right: 2.75rem !important;
        }

        .unitec-login__form .unitec-login__senha-wrap.is-revealed input {
            -webkit-text-security: none !important;
            text-security: none !important;
        }

        .unitec-login__form .unitec-login__senha-wrap .fi-input-wrp {
            position: relative !important;
        }

        .unitec-login__senha-toggle {
            position: absolute;
            top: 50%;
            right: 0.45rem;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 0.45rem;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            transform: translateY(-50%);
        }

        .unitec-login__senha-toggle:hover {
            color: #1e5a9e;
            background: rgba(30, 90, 158, 0.08);
        }

        .unitec-login__senha-toggle-icon {
            width: 1.15rem;
            height: 1.15rem;
            display: block;
        }

        .unitec-login__senha-toggle-icon--hide {
            display: none;
        }

        .unitec-login__senha-toggle.is-visible .unitec-login__senha-toggle-icon--show {
            display: none;
        }

        .unitec-login__senha-toggle.is-visible .unitec-login__senha-toggle-icon--hide {
            display: block;
        }

        .unitec-login__form .fi-select-input-value-label,
        .unitec-login__form .fi-input-wrp input {
            color: #0f172a !important;
            -webkit-text-fill-color: #0f172a !important;
            font-weight: 650 !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="button"],
        .unitec-login__form .fi-ac .unitec-login__btn-cancel {
            background: #e2e8f0 !important;
            border: 1px solid #94a3b8 !important;
            color: #0f172a !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="button"] *,
        .unitec-login__form .fi-ac .unitec-login__btn-cancel * {
            color: #0f172a !important;
            opacity: 1 !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="submit"],
        .unitec-login__form .fi-ac .fi-color-primary.fi-ac-btn-action {
            background: linear-gradient(180deg, #2f6fbf 0%, #1e5a9e 100%) !important;
            border-color: #164a82 !important;
            color: #ffffff !important;
        }

        .unitec-login__form .fi-ac .fi-ac-btn-action[type="submit"] *,
        .unitec-login__form .fi-ac .fi-color-primary.fi-ac-btn-action * {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
        }
    </style>

    <script>
        window.UnitecUpdateProgress = window.UnitecUpdateProgress || (function () {
            let pollTimer = 0;
            let tickTimer = 0;
            let ticks = 0;
            let progressUrl = '';
            let onComplete = null;
            let onFailed = null;
            let displayPercent = 1;
            let targetPercent = 1;
            let pendingComplete = false;
            let lastMeta = {
                message: 'Preparando atualização…',
                done: 0,
                total: 0,
            };

            function root() {
                return document.querySelector('[data-unitec-update-dialog]');
            }

            function setRunning(running) {
                const el = root();
                if (!el) return;

                const actions = el.querySelector('[data-unitec-update-actions]');
                const progress = el.querySelector('[data-unitec-update-progress]');
                if (actions) actions.hidden = running;
                if (progress) progress.hidden = !running;
            }

            function paintUi() {
                const el = root();
                if (!el) return;

                const percent = Math.max(0, Math.min(100, Math.round(displayPercent)));
                const bar = el.querySelector('[data-unitec-update-bar]');
                const pct = el.querySelector('[data-unitec-update-percent]');
                const message = el.querySelector('[data-unitec-update-message]');
                const counter = el.querySelector('[data-unitec-update-counter]');
                const track = el.querySelector('[role="progressbar"]');

                if (bar) bar.style.width = percent + '%';
                if (pct) pct.textContent = String(percent);
                if (message) message.textContent = lastMeta.message || 'Aplicando atualização…';
                if (track) track.setAttribute('aria-valuenow', String(percent));

                const done = Number(lastMeta.done || 0);
                const total = Number(lastMeta.total || 0);
                if (counter) {
                    counter.textContent = total > 0
                        ? done.toLocaleString('pt-BR') + ' de ' + total.toLocaleString('pt-BR') + ' arquivos'
                        : 'Aguarde…';
                }
            }

            function applyMeta(data) {
                if (!data || typeof data !== 'object') return;
                if (typeof data.message === 'string' && data.message !== '') {
                    lastMeta.message = data.message;
                }
                if (data.done != null) lastMeta.done = Number(data.done) || 0;
                if (data.total != null) lastMeta.total = Number(data.total) || 0;
            }

            function setTarget(percent) {
                targetPercent = Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));
            }

            function finishComplete() {
                stopAll();
                window.setTimeout(function () {
                    if (typeof onComplete === 'function') onComplete();
                }, 350);
            }

            function tickDisplay() {
                if (displayPercent < targetPercent) {
                    // Em conclusão, sobe mais rápido (ainda 1 em 1) para não atrasar o login.
                    const step = pendingComplete ? Math.min(3, targetPercent - displayPercent) : 1;
                    displayPercent = Math.min(targetPercent, displayPercent + step);
                    paintUi();
                }

                if (pendingComplete && displayPercent >= 100) {
                    finishComplete();
                }
            }

            function stopAll() {
                window.clearInterval(pollTimer);
                window.clearInterval(tickTimer);
                pollTimer = 0;
                tickTimer = 0;
                pendingComplete = false;
            }

            async function poll() {
                ticks++;
                if (ticks > 2250) {
                    stopAll();
                    lastMeta.message = 'A atualização excedeu 15 minutos. Verifique o log do sistema.';
                    displayPercent = 0;
                    targetPercent = 0;
                    paintUi();
                    if (typeof onFailed === 'function') onFailed(lastMeta.message);
                    return;
                }

                try {
                    const response = await fetch(progressUrl + '?t=' + Date.now(), {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) return;

                    const data = await response.json();
                    applyMeta(data);
                    setTarget(data.percent);
                    paintUi();

                    if (data.state === 'completed') {
                        window.clearInterval(pollTimer);
                        pollTimer = 0;
                        setTarget(100);
                        pendingComplete = true;
                        lastMeta.message = data.message || 'Atualização concluída.';
                        paintUi();
                    } else if (data.state === 'failed') {
                        stopAll();
                        const message = data.error || data.message || 'Falha ao aplicar atualização.';
                        lastMeta.message = message;
                        paintUi();
                        if (typeof onFailed === 'function') onFailed(message);
                    }
                } catch (e) {
                    // O Laravel pode ficar indisponivel por instantes ao regenerar caches.
                    // Mantem o polling; o limite global de 15 minutos evita loop infinito.
                }
            }

            function start(dialog, complete, failed) {
                progressUrl = dialog?.dataset?.progressUrl || progressUrl;
                onComplete = complete;
                onFailed = failed;
                ticks = 0;
                pendingComplete = false;
                displayPercent = 1;
                targetPercent = 1;
                lastMeta = {
                    message: 'Preparando atualização…',
                    done: 0,
                    total: 0,
                };
                setRunning(true);
                paintUi();
                window.clearInterval(pollTimer);
                window.clearInterval(tickTimer);
                pollTimer = 0;
                tickTimer = 0;
                tickTimer = window.setInterval(tickDisplay, 100);
                pollTimer = window.setInterval(poll, 400);
                window.setTimeout(poll, 120);
            }

            return { start: start };
        })();

        (function () {
            if (window.UnitecLoginBoot && window.UnitecLoginBoot.__ready) {
                return;
            }

            const logoUrl = @json(asset('img/erp/brand/unitecnologia-logo.png'));
            const messages = [
                'Validando acesso...',
                'Verificando atualização na pasta...',
                'Conferindo licença...',
                'Preparando ambiente...',
                'Abrindo o sistema...',
            ];

            let progress = 0;
            let raf = 0;
            let messageTimer = 0;
            let stuckTimer = 0;
            let messageIndex = 0;
            let finishing = false;
            let active = false;
            let overlay = null;

            function ensureOverlay() {
                if (overlay && document.body.contains(overlay)) {
                    return overlay;
                }

                overlay = document.createElement('div');
                overlay.id = 'unitec-login-boot';
                overlay.className = 'unitec-login-boot';
                overlay.setAttribute('role', 'status');
                overlay.setAttribute('aria-live', 'polite');
                overlay.setAttribute('aria-busy', 'false');
                overlay.setAttribute('aria-hidden', 'true');
                overlay.innerHTML =
                    '<div class="unitec-login-boot__glow" aria-hidden="true"></div>' +
                    '<div class="unitec-login-boot__card">' +
                    '<img src="' + logoUrl + '" alt="" class="unitec-login-boot__logo" width="280" height="94" decoding="async">' +
                    '<p class="unitec-login-boot__eyebrow">Unitec ERP</p>' +
                    '<h2 class="unitec-login-boot__title">Abrindo o sistema</h2>' +
                    '<p class="unitec-login-boot__status" data-unitec-boot-status>Validando acesso...</p>' +
                    '<div class="unitec-login-boot__track" aria-hidden="true">' +
                    '<div class="unitec-login-boot__bar" data-unitec-boot-bar></div>' +
                    '</div>' +
                    '<p class="unitec-login-boot__percent"><span data-unitec-boot-pct>0</span>%</p>' +
                    '</div>';

                document.body.appendChild(overlay);
                return overlay;
            }

            function qs(sel) {
                return ensureOverlay().querySelector(sel);
            }

            function paint() {
                const b = qs('[data-unitec-boot-bar]');
                const p = qs('[data-unitec-boot-pct]');
                if (b) b.style.width = Math.max(0, Math.min(100, progress)) + '%';
                if (p) p.textContent = String(Math.round(Math.max(0, Math.min(100, progress))));
            }

            function setMessage(text) {
                const s = qs('[data-unitec-boot-status]');
                if (s) s.textContent = text;
            }

            function tick() {
                if (!active || finishing) return;

                const ceiling = 88;
                if (progress < ceiling) {
                    const remaining = ceiling - progress;
                    const step = Math.max(0.08, remaining * 0.028);
                    progress = Math.min(ceiling, progress + step);
                    paint();
                }

                raf = window.requestAnimationFrame(tick);
            }

            function startMessages() {
                messageIndex = 0;
                setMessage(messages[0]);
                window.clearInterval(messageTimer);
                messageTimer = window.setInterval(function () {
                    if (!active || finishing) return;
                    messageIndex = Math.min(messages.length - 1, messageIndex + 1);
                    setMessage(messages[messageIndex]);
                }, 1500);
            }

            function armStuckWatchdog() {
                window.clearTimeout(stuckTimer);
                stuckTimer = window.setTimeout(function () {
                    if (active && !finishing) {
                        hide(true);
                    }
                }, 20000);
            }

            function show() {
                const el = ensureOverlay();

                if (!active) {
                    active = true;
                    finishing = false;
                    progress = 6;
                    startMessages();
                    window.cancelAnimationFrame(raf);
                    raf = window.requestAnimationFrame(tick);
                    armStuckWatchdog();
                }

                paint();
                el.hidden = false;
                el.setAttribute('aria-hidden', 'false');
                el.setAttribute('aria-busy', 'true');
                el.classList.add('is-visible');
                document.documentElement.classList.add('unitec-login-booting');
            }

            function hide(force) {
                if (finishing && !force) {
                    return;
                }

                active = false;
                finishing = false;
                window.cancelAnimationFrame(raf);
                window.clearInterval(messageTimer);
                window.clearTimeout(stuckTimer);

                const el = ensureOverlay();
                el.classList.remove('is-visible');
                el.hidden = true;
                el.setAttribute('aria-hidden', 'true');
                el.setAttribute('aria-busy', 'false');
                document.documentElement.classList.remove('unitec-login-booting');
                progress = 0;
                paint();
            }

            function hasLoginErrors() {
                return !!document.querySelector(
                    '.unitec-login__form .fi-fo-field-wrp-error-message, ' +
                    '.unitec-login__form .fi-fo-field-wrp-error, ' +
                    '.unitec-login__form .fi-fo-field-wrp-error-list, ' +
                    '.unitec-login__form [data-validation-error], ' +
                    '.unitec-login__form .fi-fo-field-wrp-label .text-danger-600, ' +
                    '.unitec-login__form .fi-fo-field-wrp [role="alert"]'
                );
            }

            function formReadyForLogin() {
                const root = document.querySelector('.unitec-login__form');
                if (!root) {
                    return true;
                }

                const fields = root.querySelectorAll('select[required], input[required], textarea[required]');
                for (let i = 0; i < fields.length; i++) {
                    const field = fields[i];
                    if (!field.checkValidity()) {
                        try {
                            field.reportValidity();
                        } catch (e) {}
                        return false;
                    }
                }

                const form = root.closest('form') || root.querySelector('form');
                if (form && typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    try {
                        form.reportValidity();
                    } catch (e) {}
                    return false;
                }

                return true;
            }

            function tryShowBoot() {
                if (!formReadyForLogin()) {
                    hide(true);
                    return;
                }

                show();
            }

            function succeed(url) {
                show();
                finishing = true;
                window.clearInterval(messageTimer);
                window.clearTimeout(stuckTimer);
                setMessage('Quase lá...');

                const target = typeof url === 'string' && url !== '' ? url : '/admin';
                const start = performance.now();
                const from = Math.max(progress, 40);
                progress = from;
                paint();
                const duration = 900;

                function finishFrame(now) {
                    const t = Math.min(1, (now - start) / duration);
                    const eased = 1 - Math.pow(1 - t, 3);
                    progress = from + (100 - from) * eased;
                    paint();

                    if (t < 1) {
                        raf = window.requestAnimationFrame(finishFrame);
                        return;
                    }

                    setMessage('Sistema pronto');
                    window.setTimeout(function () {
                        window.location.replace(target);
                    }, 220);
                }

                window.cancelAnimationFrame(raf);
                raf = window.requestAnimationFrame(finishFrame);
            }

            function isAuthenticateTrigger(target) {
                if (!(target instanceof Element)) return false;

                return !!target.closest(
                    '.unitec-login__form button[type="submit"], ' +
                    '.unitec-login__form .fi-ac-btn-action[type="submit"], ' +
                    '.unitec-login__form .fi-color-primary.fi-ac-btn-action'
                );
            }

            document.addEventListener('click', function (event) {
                if (isAuthenticateTrigger(event.target)) {
                    tryShowBoot();
                }
            }, true);

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') return;
                if (!event.target || !(event.target instanceof Element)) return;
                if (!event.target.closest('.unitec-login__form')) return;
                if (event.target.closest('.unitec-login__btn-cancel, .unitec-login__close')) return;
                tryShowBoot();
            }, true);

            document.addEventListener('invalid', function (event) {
                if (!event.target || !(event.target instanceof Element)) return;
                if (!event.target.closest('.unitec-login__form')) return;
                hide(true);
            }, true);

            function bindLivewire() {
                if (!window.Livewire || typeof Livewire.hook !== 'function') {
                    return;
                }

                Livewire.hook('request', function ({ fail }) {
                    fail(function () {
                        if (active && !finishing) {
                            hide(true);
                        }
                    });
                });

                Livewire.hook('commit', function ({ succeed, fail }) {
                    succeed(function () {
                        window.setTimeout(function () {
                            if (active && !finishing && hasLoginErrors()) {
                                hide(true);
                            }
                        }, 80);
                    });

                    fail(function () {
                        if (active && !finishing) {
                            hide(true);
                        }
                    });
                });

                Livewire.hook('morph.updated', function () {
                    if (!active || finishing) {
                        return;
                    }

                    window.setTimeout(function () {
                        if (active && !finishing && hasLoginErrors()) {
                            hide(true);
                        }
                    }, 40);
                });
            }

            if (window.Livewire) {
                bindLivewire();
            } else {
                document.addEventListener('livewire:init', bindLivewire);
            }

            window.UnitecLoginBoot = {
                __ready: true,
                show: show,
                hide: hide,
                succeed: succeed,
            };
        })();
    </script>

    <script>
        (function bindUnitecLoginSenhaToggle() {
            if (window.__unitecLoginSenhaToggleBound) {
                return;
            }
            window.__unitecLoginSenhaToggleBound = true;

            var eyeShow = '<svg class="unitec-login__senha-toggle-icon unitec-login__senha-toggle-icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>';
            var eyeHide = '<svg class="unitec-login__senha-toggle-icon unitec-login__senha-toggle-icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>';

            function ensure() {
                document.querySelectorAll('.unitec-login__senha-wrap').forEach(function (wrap) {
                    if (wrap.querySelector('[data-unitec-login-senha-toggle]')) {
                        return;
                    }

                    var input = wrap.querySelector('input');
                    if (!input) {
                        return;
                    }

                    if (!input.id) {
                        input.id = 'unitec-login-senha';
                    }

                    var inputWrp = wrap.querySelector('.fi-input-wrp') || wrap;
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'unitec-login__senha-toggle';
                    btn.setAttribute('data-unitec-login-senha-toggle', '1');
                    btn.setAttribute('aria-label', 'Exibir senha');
                    btn.setAttribute('title', 'Exibir senha');
                    btn.innerHTML = eyeShow + eyeHide;
                    btn.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        var revealed = wrap.classList.toggle('is-revealed');
                        btn.classList.toggle('is-visible', revealed);
                        btn.setAttribute('aria-label', revealed ? 'Ocultar senha' : 'Exibir senha');
                        btn.setAttribute('title', revealed ? 'Ocultar senha' : 'Exibir senha');
                        input.focus();
                    });
                    inputWrp.appendChild(btn);
                });
            }

            document.addEventListener('DOMContentLoaded', ensure);
            document.addEventListener('livewire:navigated', ensure);
            document.addEventListener('livewire:init', function () {
                window.Livewire?.hook?.('morph.updated', function () {
                    ensure();
                });
            });
            if (document.readyState !== 'loading') {
                ensure();
            }
        })();
    </script>
</div>
