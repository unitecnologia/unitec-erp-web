document.addEventListener('DOMContentLoaded', initErpTerminaisForm);
document.addEventListener('livewire:navigated', initErpTerminaisForm);

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', () => {
        const page = document.querySelector('.erp-terminais-form-page');

        if (page) {
            initErpMasks(page);
        }
    });
});

function initErpTerminaisForm() {
    const page = document.querySelector('.erp-terminais-form-page');

    if (! page) {
        return;
    }

    initErpMasks(page);
    bindErpTerminaisFormKeys();
    bindErpTerminalScaleTest();
}

function getErpTerminaisComponent(fromEl) {
    const root = fromEl?.closest?.('[wire\\:id]')
        || document.querySelector('.erp-terminais-form-page')?.closest?.('[wire\\:id]')
        || document.querySelector('.erp-terminais-form-page');

    if (! root) {
        return null;
    }

    const wireId = root.getAttribute('wire:id');

    return wireId ? window.Livewire?.find(wireId) : null;
}

function bindErpTerminaisFormKeys() {
    if (window.__erpTerminaisFormKeysBound) {
        return;
    }

    window.__erpTerminaisFormKeysBound = true;

    document.addEventListener('keydown', (event) => {
        if (! document.querySelector('.erp-terminais-form-page')) {
            return;
        }

        const component = getErpTerminaisComponent();

        if (! component) {
            return;
        }

        if (event.key === 'F2') {
            if (document.querySelector('.erp-terminais-aparelhos')) {
                event.preventDefault();
                component.call('autorizarAparelhoSelecionado');
            }

            return;
        }

        if (event.key === 'F4') {
            event.preventDefault();
            if (document.querySelector('.erp-terminais-aparelhos')) {
                component.call('revogarAparelhoSelecionado');
            } else {
                component.call('deleteTerminal');
            }

            return;
        }

        if (event.key === 'F5') {
            if (document.querySelector('.erp-terminais-aparelhos')) {
                event.preventDefault();
                component.call('$refresh');
            }

            return;
        }

        if (event.key === 'F10') {
            if (document.querySelector('.erp-terminais-aparelhos')) {
                return;
            }

            event.preventDefault();
            component.call('saveTerminalForm');

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            component.call('closeScreen');
        }
    });
}

function getScaleTestStatusEl(button) {
    return button.closest('.erp-terminais-balanca__test')?.querySelector('[data-erp-test-scale-status]') || null;
}

function setScaleTestUi(button, { busy = false, label = null, status = '', state = '' } = {}) {
    if (label != null) {
        button.textContent = label;
    }

    button.disabled = !! busy;
    button.setAttribute('aria-busy', busy ? 'true' : 'false');
    button.classList.toggle('is-busy', !! busy);

    const statusEl = getScaleTestStatusEl(button);

    if (! statusEl) {
        return;
    }

    statusEl.textContent = status || '';
    statusEl.dataset.state = state || '';
    statusEl.hidden = ! status;
}

function notifyScaleTest(component, ok, message, peso = null) {
    if (component?.call) {
        component.call('notifyBalancaTestResult', ok, message, peso);

        return;
    }

    window.alert((ok ? 'OK: ' : 'Erro: ') + message);
}

function bindErpTerminalScaleTest() {
    if (window.__erpTerminaisScaleTestBound) {
        return;
    }

    window.__erpTerminaisScaleTestBound = true;

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-erp-test-scale]');

        if (! button || button.disabled) {
            return;
        }

        if (! document.querySelector('.erp-terminais-form-page')) {
            return;
        }

        event.preventDefault();

        const component = getErpTerminaisComponent(button);
        const getValue = (id) => document.getElementById(id)?.value?.trim() ?? '';
        const settings = {
            marca: getValue('term-bal-marca'),
            port: getValue('term-bal-porta'),
            baudRate: Number(getValue('term-bal-vel')) || 9600,
            dataBits: Number(getValue('term-bal-data')) || 8,
            parity: getValue('term-bal-par') || 'None',
            stopBits: getValue('term-bal-stop') || '1',
            handshake: getValue('term-bal-hand') || 'None',
        };

        if (! settings.marca || ! settings.port) {
            setScaleTestUi(button, {
                status: 'Informe a balança e a porta COM antes de testar.',
                state: 'error',
            });
            notifyScaleTest(component, false, 'Informe a balança e a porta COM antes de testar.');

            return;
        }

        if (! window.ErpDeviceService?.readScale) {
            setScaleTestUi(button, {
                status: 'Device Service não está disponível neste navegador.',
                state: 'error',
            });
            notifyScaleTest(component, false, 'Device Service não está disponível neste navegador.');

            return;
        }

        const originalLabel = button.dataset.erpLabel || button.textContent.trim() || 'Testar balança';
        button.dataset.erpLabel = originalLabel;

        setScaleTestUi(button, {
            busy: true,
            label: 'Conectando…',
            status: 'Verificando Device Service…',
            state: 'busy',
        });

        try {
            const ensured = await window.ErpDeviceService.ensureLocal();

            if (! ensured?.ok) {
                throw new Error('Device Service local não está em execução. Verifique o serviço Windows UnitecDeviceService (scripts\\install-device-service-startup.ps1 como administrador).');
            }

            if (ensured.started) {
                setScaleTestUi(button, {
                    busy: true,
                    label: 'Iniciando…',
                    status: 'Device Service iniciado. Aguardando porta local…',
                    state: 'busy',
                });
                await new Promise((resolve) => window.setTimeout(resolve, 1200));
            }

            setScaleTestUi(button, {
                busy: true,
                label: 'Lendo peso…',
                status: `Lendo ${settings.marca} em ${settings.port}…`,
                state: 'busy',
            });

            const result = await window.ErpDeviceService.readScale(settings);
            const peso = result.weightKg == null ? null : String(result.weightKg);
            const okMessage = peso != null
                ? `Peso lido: ${Number(peso).toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} kg`
                : (result.message || 'Peso lido com sucesso.');

            setScaleTestUi(button, {
                busy: false,
                label: originalLabel,
                status: okMessage,
                state: 'ok',
            });
            notifyScaleTest(component, true, result.message || okMessage, peso);
        } catch (error) {
            const message = error?.message || 'Não foi possível comunicar com a balança.';

            setScaleTestUi(button, {
                busy: false,
                label: originalLabel,
                status: message,
                state: 'error',
            });
            notifyScaleTest(component, false, message);
        }
    });
}
