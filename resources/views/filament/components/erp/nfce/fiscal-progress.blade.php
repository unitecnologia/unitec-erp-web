@php
    $sefazJs = 'js/erp-sefaz-progress.js';
    $sefazJsVersion = @filemtime(public_path($sefazJs)) ?: \App\Support\Erp\ErpAssetVersion::bundle();

    // Chave da etapa = "etapa" enviada pelo servidor na falha (ManagesNfceFiscalActions::sinalizarSefazFalha).
    $operacoes = [
        'confirmCancelarNfce' => ['Cancelando NFC-e', [
            'validacao' => 'Validando cancelamento',
            'assinatura' => 'Assinando evento de cancelamento',
            'sefaz' => 'Enviando à SEFAZ (aguardando resposta)',
            'pos' => 'Estornando a venda',
        ]],
        'confirmInutilizarNfce' => ['Inutilizando numeração NFC-e', [
            'validacao' => 'Validando faixa de numeração',
            'assinatura' => 'Assinando pedido de inutilização',
            'sefaz' => 'Enviando à SEFAZ (aguardando resposta)',
            'pos' => 'Registrando protocolo',
        ]],
        'recuperarNfce' => ['Consultando NFC-e na SEFAZ', [
            'validacao' => 'Conectando à SEFAZ',
            'sefaz' => 'Consultando situação da NFC-e (aguardando resposta)',
            'pos' => 'Atualizando registro',
        ]],
        'transmitirNfce' => ['Transmitindo NFC-e', [
            'validacao' => 'Validando dados da NFC-e',
            'assinatura' => 'Assinando digitalmente',
            'sefaz' => 'Enviando à SEFAZ (aguardando resposta)',
            'pos' => 'Processando autorização',
        ]],
    ];
@endphp
<div class="erp-nfce-sefaz-progress-host" wire:ignore>
    <script src="{{ asset($sefazJs) }}?v={{ $sefazJsVersion }}" defer data-navigate-track></script>

    @foreach ($operacoes as $metodo => [$titulo, $etapas])
        @include('pdvui::fiscal-progress', [
            'fiscalProgressOperacao' => $metodo,
            'fiscalProgressTitulo' => $titulo,
            'fiscalProgressEtapas' => $etapas,
        ])
    @endforeach
</div>
