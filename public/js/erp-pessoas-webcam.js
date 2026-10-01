window.erpPersonPhotoFindWire = function (fromEl, wire) {
    if (wire && typeof wire.call === 'function') {
        return wire;
    }

    const root = fromEl?.closest?.('[wire\\:id]')
        || document.querySelector('.erp-pessoas-form-page[wire\\:id]')
        || document.querySelector('.erp-pessoas-form-page')?.closest?.('[wire\\:id]')
        || document.querySelector('[wire\\:id].erp-pessoas-form-page');

    if (! root) {
        return null;
    }

    const id = root.getAttribute('wire:id');

    return id ? window.Livewire?.find(id) : null;
};

window.erpPersonPhotoSubmit = function (base64, wire, fromEl) {
    const component = window.erpPersonPhotoFindWire(fromEl, wire);

    if (! component) {
        window.alert('Não foi possível enviar a foto. Recarregue a tela e tente novamente.');

        return;
    }

    if (typeof component.call === 'function') {
        component.call('capturePersonPhoto', base64);

        return;
    }

    if (typeof component.capturePersonPhoto === 'function') {
        component.capturePersonPhoto(base64);
    }
};

window.erpPessoasWebcam = function (livewire) {
    return {
        openModal: false,
        stream: null,
        error: '',
        wire: livewire || null,

        async openWebcam() {
            if (this._openingWebcam) {
                return;
            }

            this._openingWebcam = true;
            this.error = '';
            this.openModal = true;

            try {
                await this.$nextTick();

                if (this.stream) {
                    return;
                }

                if (! navigator.mediaDevices?.getUserMedia) {
                    this.error = 'Não foi possível acessar a webcam.';

                    return;
                }

                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false,
                });

                this.stream = stream;

                if (this.$refs.video) {
                    this.$refs.video.srcObject = stream;
                }
            } catch (error) {
                this.error = 'Não foi possível acessar a webcam.';
            } finally {
                this._openingWebcam = false;
            }
        },

        closeWebcam() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }

            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }

            this.openModal = false;
            this.error = '';
        },

        capture() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;

            if (! video || ! canvas || ! video.videoWidth) {
                this.error = 'Aguarde a webcam iniciar.';

                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            const base64 = canvas.toDataURL('image/jpeg', 0.92);
            const wire = this.wire || this.$wire || null;
            window.erpPersonPhotoSubmit(base64, wire, this.$el);
            this.closeWebcam();
        },
    };
};

window.erpPersonWebcam = window.erpPessoasWebcam;

/**
 * Fallback: se o Alpine do host não inicializou o listener .window,
 * ainda assim o botão Webcam dispara o CustomEvent e abrimos o modal.
 */
window.erpPessoasWebcamOpenFromEvent = function () {
    const host = document.querySelector('.erp-pessoas-webcam-host');

    if (! host) {
        return;
    }

    const data = window.Alpine?.$data?.(host);

    if (data && typeof data.openWebcam === 'function') {
        data.openWebcam();
    }
};

if (! window.__erpPessoasWebcamEventBound) {
    window.__erpPessoasWebcamEventBound = true;
    window.addEventListener('person-webcam-open', () => {
        window.erpPessoasWebcamOpenFromEvent();
    });
}

document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('.erp-pessoas-webcam-host').forEach((element) => {
        const data = window.Alpine?.$data?.(element);
        data?.closeWebcam?.();
    });
});
