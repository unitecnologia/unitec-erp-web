@php
    $webcamJsPath = public_path('js/erp-pessoas-webcam.js');
    $webcamJsVersion = file_exists($webcamJsPath) ? filemtime($webcamJsPath) : time();
@endphp

{{-- Wrapper: preview remonta; host da webcam permanece irmão com wire:ignore. --}}
<div class="erp-pessoas-foto-tab">
    <div class="erp-pessoas-foto erp-pessoas-panel erp-pessoas-panel--foto" x-data="{}">
        <div
            class="erp-pessoas-foto__preview-wrap"
            wire:loading.class="erp-pessoas-foto__preview-wrap--loading"
            wire:target="personFotoUpload,capturePersonPhoto,clearPersonPhoto"
        >
            @if ($this->fotoPreviewUrl)
                <img
                    src="{{ $this->fotoPreviewUrl }}"
                    alt="Foto da pessoa"
                    class="erp-pessoas-foto__preview"
                    wire:key="foto-preview-{{ md5($this->fotoPreviewUrl) }}"
                >
            @else
                <div class="erp-pessoas-foto__preview erp-pessoas-foto__preview--empty"></div>
            @endif
        </div>

        <div class="erp-pessoas-foto__actions">
            <button
                type="button"
                class="erp-pcad-form__btn"
                x-on:click.prevent.stop="window.dispatchEvent(new CustomEvent('person-webcam-open'))"
            >Webcam</button>
            <button
                type="button"
                class="erp-pcad-form__btn"
                x-on:click.prevent.stop="$refs.personPhotoFile.click()"
            >Procurar</button>
            <input
                x-ref="personPhotoFile"
                type="file"
                accept=".jpg,.jpeg,image/jpeg"
                hidden
                wire:model.live="personFotoUpload"
            >
            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click.stop="clearPersonPhoto"
            >Limpar Imagem</button>
            <span class="erp-pessoas-foto__hint">*Somente imagens no formato .jpg ou .jpeg</span>
        </div>
    </div>

    {{-- DENTRO do Livewire, FORA da área remorphada do preview. --}}
    <div
        class="erp-pessoas-webcam-host"
        wire:ignore
        x-data="erpPessoasWebcam(typeof $wire !== 'undefined' ? $wire : null)"
        x-on:person-webcam-open.window="openWebcam()"
    >
        <div
            class="erp-pessoas-foto__modal"
            x-show="openModal"
            x-cloak
            x-bind:style="openModal ? 'display: flex' : 'display: none'"
        >
            <div class="erp-pessoas-foto__modal-backdrop" x-on:click.prevent.stop="closeWebcam()"></div>
            <div class="erp-pessoas-foto__modal-panel">
                <div class="erp-pessoas-foto__modal-header">
                    <span>Capturar foto</span>
                    <button type="button" class="erp-pessoas-foto__modal-close" x-on:click.prevent.stop="closeWebcam()">✕</button>
                </div>
                <video x-ref="video" autoplay playsinline class="erp-pessoas-foto__video"></video>
                <canvas x-ref="canvas" class="erp-pessoas-foto__canvas"></canvas>
                <div class="erp-pessoas-foto__modal-actions">
                    <button type="button" class="erp-pcad-form__btn" x-on:click.prevent.stop="capture()">Capturar</button>
                    <button type="button" class="erp-pcad-form__btn" x-on:click.prevent.stop="closeWebcam()">Fechar</button>
                </div>
                <p class="erp-pessoas-foto__modal-error" x-text="error" x-show="error"></p>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/erp-pessoas-webcam.js') }}?v={{ $webcamJsVersion }}"></script>
