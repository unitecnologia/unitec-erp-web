@php
    $sucesso = filled($this->osNfceFiscalSucessoDetalhe);
    $erro = filled($this->osNfceFiscalErroTitulo);

    $sefazJs = 'js/erp-sefaz-progress.js';
    $sefazJsVersion = @filemtime(public_path($sefazJs)) ?: \App\Support\Erp\ErpAssetVersion::bundle();
@endphp

{{-- Mesmo indicador SEFAZ do PDV / listagem NFC-e (centralizado, etapas, bloqueio de tela). --}}
<div class="erp-os-nfce-sefaz-progress-host" wire:ignore>
    <script src="{{ asset($sefazJs) }}?v={{ $sefazJsVersion }}" defer data-navigate-track></script>

    @include('pdvui::fiscal-progress', [
        'fiscalProgressOperacao' => 'confirmarOsNfceEmitirModal',
        'fiscalProgressTitulo' => 'Transmitindo NFC-e',
        'fiscalProgressEtapas' => [
            'validacao' => 'Validando dados da NFC-e',
            'xml' => 'Montando XML do documento',
            'assinatura' => 'Assinando digitalmente',
            'sefaz' => 'Enviando à SEFAZ (aguardando resposta)',
            'pos' => 'Processando autorização',
        ],
    ])
</div>

<div
    id="erp-os-nfce-fiscal-sucesso"
    class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--sucesso{{ $sucesso ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-os-nfce-sucesso-title"
    aria-hidden="{{ $sucesso ? 'false' : 'true' }}"
    style="display: {{ $sucesso ? 'grid' : 'none' }};"
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">✓</div>
        <h2 id="erp-os-nfce-sucesso-title" class="erp-nfe-fiscal-overlay__title">NFC-E TRANSMITIDA COM SUCESSO</h2>
        <p class="erp-nfe-fiscal-overlay__codigo">{!! nl2br(e((string) ($this->osNfceFiscalSucessoDetalhe ?? ''))) !!}</p>
        <div class="erp-nfe-fiscal-overlay__actions erp-nfe-fiscal-overlay__actions--cce">
            <button type="button" class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--print" wire:click="imprimirOsNfceAutorizada">Imprimir</button>
            <button type="button" class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--email" wire:click="abrirEnvioOsNfceAutorizada">Enviar</button>
            <button type="button" class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit" wire:click="closeOsNfceFiscalSucesso">Fechar</button>
        </div>
        <p class="erp-nfe-fiscal-overlay__hint">A nota já consta como autorizada na SEFAZ.</p>
    </div>
</div>

<div
    id="erp-os-nfce-fiscal-erro"
    class="erp-nfe-fiscal-overlay erp-nfse-fiscal-overlay--erro{{ $erro ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-os-nfce-erro-title"
    aria-hidden="{{ $erro ? 'false' : 'true' }}"
    style="display: {{ $erro ? 'grid' : 'none' }};"
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>
        <h2 id="erp-os-nfce-erro-title" class="erp-nfe-fiscal-overlay__title">{{ $this->osNfceFiscalErroTitulo }}</h2>
        <p class="erp-nfe-fiscal-overlay__codigo" style="display: {{ filled($this->osNfceFiscalErroCodigo) ? 'block' : 'none' }};">
            Código SEFAZ: <strong>{{ $this->osNfceFiscalErroCodigo }}</strong>
        </p>
        <div class="erp-nfe-fiscal-overlay__text">{!! nl2br(e((string) ($this->osNfceFiscalErroMensagem ?? ''))) !!}</div>
        <button type="button" class="erp-nfe-fiscal-overlay__btn" wire:click="closeOsNfceFiscalErro">Entendido</button>
        <p class="erp-nfe-fiscal-overlay__hint">Corrija o problema e tente novamente pelo F11 (a numeração não é duplicada).</p>
    </div>
</div>
