@php
    $sefazJs = 'js/erp-sefaz-progress.js';
    $sefazJsVersion = @filemtime(public_path($sefazJs)) ?: \App\Support\Erp\ErpAssetVersion::bundle();
@endphp

{{-- Mesmo indicador SEFAZ do PDV / listagem NFC-e / OS (centralizado, etapas, bloqueio de tela). --}}
<div class="erp-fv-mon-nfce-sefaz-progress-host" wire:ignore>
    <script src="{{ asset($sefazJs) }}?v={{ $sefazJsVersion }}" defer data-navigate-track></script>

    @include('pdvui::fiscal-progress', [
        'fiscalProgressOperacao' => 'transmitirNfceDoBotao',
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

{{-- Após autorização: mesma pergunta Sim/Não do PDV (texto específico NFC-e). --}}
@if ($this->nfceImprimirConfirmOpen)
    <div class="erp-pdv-modal erp-pdv-modal--centered erp-fv-mon-nfce-imprimir" role="dialog" aria-modal="true" aria-labelledby="erp-fv-mon-nfce-imprimir-title">
        <div class="erp-pdv-modal__backdrop" wire:click="confirmImprimirNfceMonitor(false)"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-fv-mon-nfce-imprimir-title">Impressão</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__confirm-text">
                    Deseja imprimir a NFC-e?
                </p>
                @if (filled($this->nfceImprimirConfirmDetalhe))
                    <p class="erp-pdv-modal__hint">{{ $this->nfceImprimirConfirmDetalhe }}</p>
                @endif
            </div>
            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    wire:click="confirmImprimirNfceMonitor(true)"
                    wire:loading.attr="disabled"
                    wire:target="confirmImprimirNfceMonitor"
                    class="erp-pdv-modal__btn"
                    id="erp-fv-mon-nfce-imprimir-sim"
                >Sim</button>
                <button
                    type="button"
                    wire:click="confirmImprimirNfceMonitor(false)"
                    wire:loading.attr="disabled"
                    wire:target="confirmImprimirNfceMonitor"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                    id="erp-fv-mon-nfce-imprimir-nao"
                >Não</button>
            </footer>
        </div>
    </div>
@endif
