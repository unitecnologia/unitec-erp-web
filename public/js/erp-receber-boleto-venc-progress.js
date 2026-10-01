/**
 * Progresso em etapas ao alterar vencimento com boleto (Sicredi/Ailos).
 * Mantém o overlay visível o tempo mínimo das etapas e só libera o OK depois.
 */
(function () {
    const VERSION = 'v2-receber-boleto-venc-progress';

    if (window.__erpReceberBoletoVencProgressVersion === VERSION) {
        return;
    }

    window.__erpReceberBoletoVencProgressVersion = VERSION;

    const STEP_MS = 800;
    const HIDE_AFTER_LAST_MS = 450;

    let active = false;
    let requestDone = false;
    let startedAt = 0;
    let stepIndex = 0;
    let stepTimer = null;
    let hideTimer = null;
    let watchingSalvar = false;
    let pendingSucesso = false;
    let pendingErro = false;
    let overlayObserver = null;

    function progressEl() {
        return document.querySelector('[data-erp-receber-boleto-venc-progress]');
    }

    function formEl() {
        return document.getElementById('erp-receber-form-modal');
    }

    function sucessoEl() {
        return document.getElementById('erp-receber-boleto-vencimento-sucesso-overlay');
    }

    function erroEl() {
        return document.getElementById('erp-receber-boleto-vencimento-erro-overlay');
    }

    function isOverlayVisivel(el) {
        if (! el) {
            return false;
        }

        if (el.style.display === 'none') {
            return false;
        }

        return el.classList.contains('is-visible')
            || el.getAttribute('aria-hidden') === 'false'
            || el.style.display === 'grid'
            || el.style.display === 'flex';
    }

    function shouldTrackSalvar() {
        const form = formEl();

        if (! form || form.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        if (form.dataset.erpContaFormHasBoleto !== '1') {
            return false;
        }

        const original = String(form.dataset.erpContaFormVencOriginal || '').trim();
        const input = form.querySelector('input[type="date"].erp-receber-form-modal__input');
        const current = String(input?.value || '').trim();

        return original !== '' && current !== '' && current !== original;
    }

    function bancoNome() {
        return String(formEl()?.dataset.erpContaFormBanco || 'banco').trim() || 'banco';
    }

    function stepLabels(banco) {
        return [
            'Validando conta',
            'Conectando ao ' + banco,
            'Enviando instrução de vencimento',
            'Confirmando no banco',
        ];
    }

    function clearTimers() {
        if (stepTimer) {
            window.clearInterval(stepTimer);
            stepTimer = null;
        }

        if (hideTimer) {
            window.clearTimeout(hideTimer);
            hideTimer = null;
        }
    }

    function hideOverlayHard(el) {
        if (! el) {
            return;
        }

        el.style.display = 'none';
        el.classList.remove('is-visible');
        el.setAttribute('aria-hidden', 'true');
    }

    function showOverlayHard(el) {
        if (! el) {
            return;
        }

        el.style.display = 'grid';
        el.classList.add('is-visible');
        el.setAttribute('aria-hidden', 'false');
    }

    /** Enquanto o progresso roda, o OK/erro não pode aparecer por baixo. */
    function suppressResultOverlays() {
        const sucesso = sucessoEl();
        const erro = erroEl();

        if (isOverlayVisivel(sucesso) || sucesso?.dataset.erpVencPending === '1') {
            pendingSucesso = true;
            hideOverlayHard(sucesso);
            if (sucesso) {
                sucesso.dataset.erpVencPending = '1';
            }
        }

        if (isOverlayVisivel(erro) || erro?.dataset.erpVencPending === '1') {
            pendingErro = true;
            hideOverlayHard(erro);
            if (erro) {
                erro.dataset.erpVencPending = '1';
            }
        }
    }

    function revealPendingOverlays() {
        if (pendingSucesso) {
            const sucesso = sucessoEl();
            showOverlayHard(sucesso);
            if (sucesso) {
                delete sucesso.dataset.erpVencPending;
            }
            pendingSucesso = false;
        }

        if (pendingErro) {
            const erro = erroEl();
            showOverlayHard(erro);
            if (erro) {
                delete erro.dataset.erpVencPending;
            }
            pendingErro = false;
        }
    }

    function ensureOverlayObserver() {
        if (overlayObserver || typeof MutationObserver === 'undefined') {
            return;
        }

        overlayObserver = new MutationObserver(() => {
            if (! active) {
                return;
            }

            suppressResultOverlays();
        });

        const root = document.querySelector('.erp-receber-page') || document.body;
        overlayObserver.observe(root, {
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class', 'aria-hidden'],
            childList: true,
        });
    }

    function applyStep(index) {
        const overlay = progressEl();

        if (! overlay) {
            return;
        }

        const banco = bancoNome();
        const labels = stepLabels(banco);
        const steps = Array.from(overlay.querySelectorAll('[data-erp-receber-boleto-venc-step]'));
        const statusEl = overlay.querySelector('[data-erp-receber-boleto-venc-status]');
        const barEl = overlay.querySelector('[data-erp-receber-boleto-venc-bar]');
        const target = Math.max(0, Math.min(labels.length - 1, index));

        stepIndex = target;

        steps.forEach((li, i) => {
            li.classList.toggle('is-done', i < target);
            li.classList.toggle('is-active', i === target);
            if (labels[i]) {
                li.textContent = labels[i];
            }
        });

        if (statusEl) {
            statusEl.textContent = labels[target] + '…';
        }

        if (barEl && labels.length > 0) {
            const percent = Math.min(100, Math.round(((target + 1) / labels.length) * 100));
            barEl.style.width = percent + '%';
            barEl.style.animation = 'none';
        }
    }

    function showProgress() {
        const overlay = progressEl();

        if (! overlay) {
            return;
        }

        overlay.classList.remove('erp-receber-boleto-venc-progress--idle');
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-busy', 'true');
        applyStep(0);
        suppressResultOverlays();
        ensureOverlayObserver();
    }

    function hideProgress() {
        clearTimers();
        active = false;
        requestDone = false;
        watchingSalvar = false;

        const overlay = progressEl();

        if (overlay) {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-busy', 'false');
            applyStep(0);

            const barEl = overlay.querySelector('[data-erp-receber-boleto-venc-bar]');

            if (barEl) {
                barEl.style.width = '';
                barEl.style.animation = '';
            }
        }

        // Só agora libera o OK / erro.
        revealPendingOverlays();
    }

    function scheduleHide() {
        if (! active || ! requestDone) {
            return;
        }

        const labelsCount = 4;
        const minMs = labelsCount * STEP_MS;
        const elapsed = Date.now() - startedAt;
        const wait = Math.max(0, minMs - elapsed);

        clearTimers();

        hideTimer = window.setTimeout(() => {
            applyStep(labelsCount - 1);
            hideTimer = window.setTimeout(() => {
                hideProgress();
            }, HIDE_AFTER_LAST_MS);
        }, wait);
    }

    function startProgress() {
        if (active) {
            return;
        }

        clearTimers();
        active = true;
        requestDone = false;
        pendingSucesso = false;
        pendingErro = false;
        startedAt = Date.now();
        stepIndex = 0;
        showProgress();

        stepTimer = window.setInterval(() => {
            if (stepIndex < 3) {
                applyStep(stepIndex + 1);
            }
            // Garante que o OK não “vaze” no meio das etapas.
            suppressResultOverlays();
        }, STEP_MS);
    }

    function onSalvarIntent() {
        if (! shouldTrackSalvar()) {
            return;
        }

        watchingSalvar = true;
        startProgress();
    }

    document.addEventListener('click', (event) => {
        const btn = event.target instanceof Element
            ? event.target.closest('[wire\\:click="salvarContaForm"], button.erp-receber-form-modal__btn--save')
            : null;

        if (! btn) {
            return;
        }

        onSalvarIntent();
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'F5') {
            return;
        }

        const form = formEl();

        if (! form || form.getAttribute('aria-hidden') === 'true') {
            return;
        }

        onSalvarIntent();
    }, true);

    function onCommitFinished() {
        if (! watchingSalvar && ! active) {
            return;
        }

        window.setTimeout(() => {
            if (! active && ! watchingSalvar) {
                return;
            }

            // Livewire pode ter aberto o OK — segura até o progresso terminar.
            suppressResultOverlays();

            const form = formEl();
            const formAberto = !! form
                && form.getAttribute('aria-hidden') !== 'true'
                && form.style.display !== 'none';

            const temResultado = pendingSucesso || pendingErro;

            // Validação / save sem sync: fecha o progresso rápido.
            if (formAberto && ! temResultado) {
                clearTimers();
                hideTimer = window.setTimeout(() => hideProgress(), 350);

                return;
            }

            requestDone = true;
            scheduleHide();
        }, 40);
    }

    function bindLivewireHooks() {
        if (! window.Livewire || typeof window.Livewire.hook !== 'function') {
            return;
        }

        if (window.__erpReceberBoletoVencProgressHookedV2) {
            return;
        }

        window.__erpReceberBoletoVencProgressHookedV2 = true;

        window.Livewire.hook('commit', ({ succeed, fail }) => {
            succeed(() => onCommitFinished());
            fail(() => {
                suppressResultOverlays();
                hideProgress();
            });
        });
    }

    if (window.Livewire) {
        bindLivewireHooks();
    }

    document.addEventListener('livewire:init', bindLivewireHooks);
})();
