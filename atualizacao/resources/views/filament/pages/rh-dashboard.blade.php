@php
    $k = $this->kpis;
    $cards = [
        ['label' => 'Funcionários', 'value' => (int) ($k['funcionarios'] ?? 0), 'hint' => 'Cadastro total', 'tone' => 'blue', 'icon' => 'heroicon-o-user-group'],
        ['label' => 'Ativos', 'value' => (int) ($k['ativos'] ?? 0), 'hint' => 'Sem demissão', 'tone' => 'green', 'icon' => 'heroicon-o-check-circle'],
        ['label' => 'Demitidos', 'value' => (int) ($k['demitidos'] ?? 0), 'hint' => 'Com data demissão', 'tone' => 'slate', 'icon' => 'heroicon-o-x-circle'],
        ['label' => 'Férias', 'value' => (int) ($k['ferias'] ?? 0), 'hint' => 'Em breve', 'tone' => 'teal', 'icon' => 'heroicon-o-sun'],
        ['label' => 'Exames vencendo', 'value' => (int) ($k['exames_vencendo'] ?? 0), 'hint' => 'Próximos 30 dias', 'tone' => (($k['exames_vencendo'] ?? 0) > 0 ? 'orange' : 'blue'), 'icon' => 'heroicon-o-heart'],
        ['label' => 'EPIs vencendo', 'value' => (int) ($k['epis_vencendo'] ?? 0), 'hint' => 'Próximos 30 dias', 'tone' => (($k['epis_vencendo'] ?? 0) > 0 ? 'orange' : 'blue'), 'icon' => 'heroicon-o-shield-check'],
        ['label' => 'Docs vencendo', 'value' => (int) ($k['docs_vencendo'] ?? 0), 'hint' => 'Próximos 30 dias', 'tone' => (($k['docs_vencendo'] ?? 0) > 0 ? 'orange' : 'blue'), 'icon' => 'heroicon-o-document-text'],
        ['label' => 'Aniversariantes', 'value' => (int) ($k['aniversariantes'] ?? 0), 'hint' => 'Hoje', 'tone' => 'indigo', 'icon' => 'heroicon-o-cake'],
        ['label' => 'Folga hoje', 'value' => (int) ($k['folga_hoje'] ?? 0), 'hint' => 'Pela escala', 'tone' => 'slate', 'icon' => 'heroicon-o-calendar'],
        ['label' => 'Plantão hoje', 'value' => (int) ($k['plantao_hoje'] ?? 0), 'hint' => 'Pela escala', 'tone' => 'blue', 'icon' => 'heroicon-o-clock'],
    ];
@endphp

<div class="erp-rh-dashboard erp-home" wire:keydown.escape.window="closeScreen">
    <header class="erp-rh-dashboard__toolbar">
        <div>
            <h1 class="erp-rh-dashboard__title">RH — Painel</h1>
            <p class="erp-rh-dashboard__sub">Gestão de pessoas (sem folha / sem ponto)</p>
        </div>
        <div class="erp-rh-dashboard__actions">
            <button type="button" class="erp-rh-dashboard__btn" wire:click="refreshKpis" wire:loading.attr="disabled">
                <kbd>F5</kbd> | Atualizar
            </button>
            <a class="erp-rh-dashboard__btn erp-rh-dashboard__btn--primary" href="{{ \App\Filament\Resources\RhFuncionarioResource::getUrl('index') }}">
                Funcionários
            </a>
            <button type="button" class="erp-rh-dashboard__btn erp-rh-dashboard__btn--exit" wire:click="closeScreen">
                <kbd>ESC</kbd> | Sair
            </button>
        </div>
    </header>

    <section
        class="erp-dash__kpis"
        aria-label="Indicadores do RH"
        style="--erp-kpi-cols: {{ max(1, min(5, count($cards))) }};"
    >
        @foreach ($cards as $kpi)
            <article class="erp-dash-kpi erp-dash-kpi--{{ $kpi['tone'] }}">
                <span class="erp-dash-kpi__accent" aria-hidden="true"></span>
                <div class="erp-dash-kpi__icon-wrap">
                    <x-filament::icon :icon="$kpi['icon']" class="erp-dash-kpi__icon" />
                </div>
                <div class="erp-dash-kpi__body">
                    <p class="erp-dash-kpi__label">{{ $kpi['label'] }}</p>
                    <p class="erp-dash-kpi__value">{{ $kpi['value'] }}</p>
                    <p class="erp-dash-kpi__hint">{{ $kpi['hint'] }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <p class="erp-rh-dashboard__note">
        Use o menu <strong>RH</strong> para Funcionários, Cargos e Departamentos. Demais módulos aparecem como “Em breve”.
    </p>
</div>
