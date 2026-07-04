@php
    $webcamJsPath = public_path('js/erp-pessoas-webcam.js');
    $webcamJsVersion = file_exists($webcamJsPath) ? filemtime($webcamJsPath) : time();
@endphp

<div class="erp-pessoas-foto" wire:ignore.self x-data="erpPersonWebcam()">
    <div class="erp-pessoas-foto__preview-wrap">
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
        <button type="button" class="erp-pcad-form__btn" x-on:click="open()">Webcam</button>
        <button type="button" wire:click="clearPersonPhoto" class="erp-pcad-form__btn">Limpar Imagem</button>
        <span class="erp-pessoas-foto__hint">*Somente imagens no formato .jpg ou .jpeg</span>
    </div>

    <div class="erp-pessoas-foto__modal" x-show="openModal" x-cloak>
        <div class="erp-pessoas-foto__modal-backdrop" x-on:click="close()"></div>
        <div class="erp-pessoas-foto__modal-panel">
            <div class="erp-pessoas-foto__modal-header">
                <span>Capturar foto</span>
                <button type="button" class="erp-pessoas-foto__modal-close" x-on:click="close()">✕</button>
            </div>
            <video x-ref="video" autoplay playsinline class="erp-pessoas-foto__video"></video>
            <canvas x-ref="canvas" class="erp-pessoas-foto__canvas"></canvas>
            <div class="erp-pessoas-foto__modal-actions">
                <button type="button" class="erp-pcad-form__btn" x-on:click="capture()">Capturar</button>
                <button type="button" class="erp-pcad-form__btn" x-on:click="close()">Fechar</button>
            </div>
            <p class="erp-pessoas-foto__modal-error" x-text="error" x-show="error"></p>
        </div>
    </div>
</div>

<script src="{{ asset('js/erp-pessoas-webcam.js') }}?v={{ $webcamJsVersion }}" defer data-navigate-track></script>
