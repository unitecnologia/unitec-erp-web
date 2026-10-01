{{-- Overlay de transmissão NF-e em lote — permanece no Monitor --}}
<div
    class="erp-nfe-fiscal-progress erp-fv-mon-nfe-lote {{ $this->nfeLoteProgressOpen ? 'is-visible' : '' }}"
    aria-live="polite"
    aria-busy="{{ $this->nfeLoteProgressOpen ? 'true' : 'false' }}"
    role="status"
    data-erp-fv-mon-nfe-lote-progress
>
    <div class="erp-nfe-fiscal-progress__backdrop" aria-hidden="true"></div>

    <div class="erp-nfe-fiscal-progress__panel" wire:ignore data-erp-fv-mon-nfe-lote-panel>
        <div class="erp-nfe-fiscal-progress__spinner" aria-hidden="true"></div>

        <p class="erp-nfe-fiscal-progress__title">Transmitindo NF-e em lote</p>

        <p class="erp-nfe-fiscal-progress__status" data-erp-fv-mon-nfe-lote-status>
            {{ $this->nfeLoteProgressStatus !== '' ? $this->nfeLoteProgressStatus : 'Aguarde…' }}
        </p>

        <div class="erp-nfe-fiscal-progress__track" aria-hidden="true">
            @php
                $pct = $this->nfeLoteTotal > 0
                    ? min(100, (int) round((($this->nfeLoteAtual - 1) + (($this->nfeLoteProgressStep + 1) / 5)) / $this->nfeLoteTotal * 100))
                    : 8;
                if ($pct < 8) {
                    $pct = 8;
                }
            @endphp
            <div class="erp-nfe-fiscal-progress__bar" data-erp-fv-mon-nfe-lote-bar style="width: {{ $pct }}%"></div>
        </div>

        <ol class="erp-nfe-fiscal-progress__steps">
            @foreach ([
                'Validando dados da NF-e',
                'Montando XML do documento',
                'Assinando digitalmente',
                'Enviando à SEFAZ (aguardando resposta)',
                'Processando autorização',
            ] as $i => $label)
                <li
                    class="{{ $this->nfeLoteProgressStep > $i ? 'is-done' : ($this->nfeLoteProgressStep === $i ? 'is-active' : '') }}"
                    data-erp-fv-mon-nfe-lote-step
                    data-step="{{ $i }}"
                >{{ $label }}</li>
            @endforeach
        </ol>

        <p class="erp-nfe-fiscal-progress__hint">
            Nota {{ max(1, $this->nfeLoteAtual) }} de {{ max(1, $this->nfeLoteTotal) }} — Aguarde, não feche esta tela.
        </p>
    </div>
</div>

@if ($this->nfeLoteResumoOpen)
    <div class="erp-fv-mon-nfe-lote-resumo" role="dialog" aria-modal="true" aria-labelledby="erp-fv-mon-nfe-lote-resumo-title">
        <div class="erp-fv-mon-nfe-lote-resumo__backdrop" wire:click="fecharNfeLoteResumo"></div>
        <div class="erp-fv-mon-nfe-lote-resumo__panel">
            <h2 id="erp-fv-mon-nfe-lote-resumo-title">{{ $this->nfeLoteResumoTitulo }}</h2>
            <p class="erp-fv-mon-nfe-lote-resumo__text">{{ $this->nfeLoteResumoTexto }}</p>

            @php
                $errosLote = collect($this->nfeLoteResultados)->where('ok', false)->values();
            @endphp
            @if ($errosLote->isNotEmpty())
                <ul class="erp-fv-mon-nfe-lote-resumo__erros">
                    @foreach ($errosLote as $erroItem)
                        @php
                            $dav = filled($erroItem['dav'] ?? null)
                                ? (string) $erroItem['dav']
                                : (string) ($erroItem['venda_id'] ?? '');
                            $msg = (string) ($erroItem['erro'] ?? 'erro');
                            $abrirId = $this->nfeIdAbertaDoResumoLote(
                                isset($erroItem['nfe_id']) ? (int) $erroItem['nfe_id'] : null
                            );
                        @endphp
                        <li class="erp-fv-mon-nfe-lote-resumo__erro">
                            <span class="erp-fv-mon-nfe-lote-resumo__erro-msg">
                                DAV {{ $dav }} — Erro: {{ $msg }}
                            </span>
                            @if ($abrirId)
                                <a
                                    class="erp-fv-mon-nfe-lote-resumo__abrir"
                                    href="{{ \App\Filament\Resources\NfeResource::getUrl('index') }}?nfe_id={{ $abrirId }}"
                                >Abrir NF-e</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <button type="button" class="erp-fv-mon-nfe-lote-resumo__btn" wire:click="fecharNfeLoteResumo" id="erp-fv-mon-nfe-lote-resumo-ok">
                OK
            </button>
        </div>
    </div>
@endif

@once
    @php
        $jsPath = public_path('js/erp-fv-monitor-nfe-lote.js');
        $jsV = is_file($jsPath) ? (string) filemtime($jsPath) : '1';
    @endphp
    <script src="{{ asset('js/erp-fv-monitor-nfe-lote.js') }}?v={{ $jsV }}"></script>
@endonce
