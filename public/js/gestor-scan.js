// Leitor de código de barras do Gestor (BarcodeDetector + câmera traseira).
(() => {
    if (window.__unitecGestorScan) {
        return;
    }
    window.__unitecGestorScan = true;

    const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code'];

    let stream = null;
    let timer = null;
    let overlay = null;

    const stop = () => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }
        if (overlay) {
            overlay.remove();
            overlay = null;
        }
    };

    const buildOverlay = () => {
        const el = document.createElement('div');
        el.className = 'gestor-scan-overlay';
        el.innerHTML = `
            <video class="gestor-scan-overlay__video" playsinline muted autoplay></video>
            <div class="gestor-scan-overlay__frame" aria-hidden="true"></div>
            <p class="gestor-scan-overlay__hint">Aponte a câmera para o código de barras</p>
            <button type="button" class="gestor-scan-overlay__close">Cancelar</button>
        `;
        el.querySelector('.gestor-scan-overlay__close').addEventListener('click', stop);
        document.body.appendChild(el);

        return el;
    };

    const onFound = (btn, code) => {
        stop();

        try {
            const wireEl = btn.closest('[wire\\:id]');
            const wireId = wireEl ? wireEl.getAttribute('wire:id') : null;
            const component = wireId && window.Livewire ? window.Livewire.find(wireId) : null;

            if (component) {
                component.set('busca', code);
                return;
            }
        } catch (e) {}

        // Fallback: preenche o input diretamente e dispara o evento.
        const input = document.querySelector('.gestor-field__scanrow input');
        if (input) {
            input.value = code;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    const open = async (btn) => {
        if (!('BarcodeDetector' in window)) {
            alert('Este navegador não suporta leitura de código de barras. Use o Chrome no Android.');
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia) {
            alert('Câmera indisponível. Acesse via HTTPS (túnel) ou use o Chrome.');
            return;
        }

        let detector;
        try {
            const supported = await window.BarcodeDetector.getSupportedFormats();
            detector = new window.BarcodeDetector({
                formats: FORMATS.filter((f) => supported.includes(f)),
            });
        } catch (e) {
            alert('Não foi possível iniciar o leitor de código de barras.');
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
        } catch (e) {
            alert('Permissão de câmera negada ou câmera indisponível.');
            return;
        }

        overlay = buildOverlay();
        const video = overlay.querySelector('video');
        video.srcObject = stream;

        try {
            await video.play();
        } catch (e) {}

        let busy = false;
        timer = setInterval(async () => {
            if (busy || !video.videoWidth) {
                return;
            }
            busy = true;
            try {
                const codes = await detector.detect(video);
                const value = codes?.[0]?.rawValue?.trim();
                if (value) {
                    if (navigator.vibrate) {
                        navigator.vibrate(80);
                    }
                    onFound(btn, value);
                }
            } catch (e) {
            } finally {
                busy = false;
            }
        }, 220);
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-gestor-scan]');
        if (btn) {
            e.preventDefault();
            open(btn);
        }
    });

    document.addEventListener('livewire:navigating', stop);
})();
