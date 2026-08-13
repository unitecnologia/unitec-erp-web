window.erpPersonWebcam = function () {
    return {
        openModal: false,
        stream: null,
        error: '',

        open() {
            this.error = '';
            this.openModal = true;

            this.$nextTick(() => {
                navigator.mediaDevices
                    .getUserMedia({ video: { facingMode: 'user' }, audio: false })
                    .then((stream) => {
                        this.stream = stream;
                        this.$refs.video.srcObject = stream;
                    })
                    .catch(() => {
                        this.error = 'Não foi possível acessar a webcam.';
                    });
            });
        },

        close() {
            this.stopStream();
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
            this.handleCapture(base64);
            this.close();
        },

        handleCapture(base64) {
            const page = document.querySelector('.erp-pessoas-form-page');
            const componentEl = page?.closest('[wire\\:id]');
            const component = componentEl
                ? window.Livewire?.find(componentEl.getAttribute('wire:id'))
                : null;

            if (component) {
                component.call('capturePersonPhoto', base64);
            }
        },

        stopStream() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }

            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }
        },
    };
};

document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('[x-data="erpPersonWebcam()"]').forEach((element) => {
        if (element.__x) {
            element.__x.$data.stopStream?.();
        }
    });
});
