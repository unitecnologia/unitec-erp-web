@if (is_array($this->ofxImportSummary))
    @php $s = $this->ofxImportSummary; @endphp
    <div class="erp-conciliacao__ofx-summary" role="status">
        <div class="erp-conciliacao__ofx-summary-title">
            Resumo da importação OFX
            @if (! empty($s['reimportacao']))
                <span>(arquivo já importado — idempotente)</span>
            @endif
        </div>
        <div class="erp-conciliacao__ofx-summary-grid">
            <span>Lidos: <strong>{{ (int) ($s['lidos'] ?? 0) }}</strong></span>
            <span>Importados: <strong>{{ (int) ($s['importados'] ?? 0) }}</strong></span>
            <span>Já existentes: <strong>{{ (int) ($s['ja_existentes'] ?? 0) }}</strong></span>
            <span>Erros: <strong>{{ (int) ($s['erros'] ?? 0) }}</strong></span>
            <span>Ambiguidade: <strong>{{ (int) ($s['ambiguos'] ?? 0) }}</strong></span>
            <span>Entradas: <strong>R$ {{ number_format((float) ($s['total_entradas'] ?? 0), 2, ',', '.') }}</strong></span>
            <span>Saídas: <strong>R$ {{ number_format((float) ($s['total_saidas'] ?? 0), 2, ',', '.') }}</strong></span>
        </div>
        @if (! empty($s['periodo_de']) && ! empty($s['periodo_ate']))
            @php
                $deBr = \Carbon\Carbon::parse($s['periodo_de'])->format('d/m/Y');
                $ateBr = \Carbon\Carbon::parse($s['periodo_ate'])->format('d/m/Y');
            @endphp
            <p class="erp-conciliacao__ofx-summary-period">
                Período do extrato importado: <strong>{{ $deBr }}</strong> a <strong>{{ $ateBr }}</strong>.
                O filtro de datas da tela foi ajustado para esse intervalo.
            </p>
            <button type="button" class="erp-rh-treinamentos__btn" wire:click="consultarPeriodoImportado">
                Consultar período importado
            </button>
        @endif
        @if (! empty($s['alerta_conta']))
            <p class="erp-conciliacao__ofx-summary-alert">
                Conta do OFX incompatível com a selecionada.
            </p>
        @endif
        @foreach (($s['avisos'] ?? []) as $aviso)
            <p class="erp-conciliacao__ofx-summary-alert">{{ $aviso }}</p>
        @endforeach
        <button type="button" class="erp-rh-treinamentos__btn erp-rh-treinamentos__btn--secondary" wire:click="limparResumoOfx">
            Fechar resumo
        </button>
    </div>
@endif
