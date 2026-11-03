@php
    $regimeLabel = \App\Support\Fiscal\ImpostoPadraoSugestoes::rotulo($this->impostoPadraoRegimePendente);
    $orientacao = \App\Support\Fiscal\ImpostoPadraoSugestoes::orientacao($this->impostoPadraoRegimePendente);
    $diffCount = count($this->impostoPadraoRegimeDiff);
@endphp

@include('filament.components.erp.aviso-modal', [
    'open' => $this->impostoPadraoRegimeConfirmOpen,
    'tone' => 'warning',
    'titleId' => 'erp-imp-padrao-regime-title',
    'title' => 'Revisar Imposto Padrão',
    'lines' => [
        'O regime foi alterado para <strong>'.e($regimeLabel).'</strong>.',
        e($orientacao),
        $diffCount > 0
            ? 'Há <strong>'.$diffCount.'</strong> campo(s) do Imposto Padrão diferentes da sugestão. Nada será alterado sem a sua confirmação.'
            : 'Os parâmetros atuais já coincidem com a sugestão.',
    ],
    'hint' => 'Esc ou clique fora cancela e restaura o regime anterior. Produtos cadastrados não são alterados.',
    'primaryLabel' => 'Aplicar sugestão',
    'primaryAction' => 'confirmarAplicarSugestaoImpostoPadraoRegime',
    'secondaryLabel' => 'Manter parâmetros',
    'secondaryAction' => 'manterImpostoPadraoAoTrocarRegime',
    'escapeAction' => 'cancelarRevisaoImpostoPadraoRegime',
    'backdropAction' => 'cancelarRevisaoImpostoPadraoRegime',
    'extraView' => 'filament.components.erp.empresas.form.partials.imposto-padrao-regime-diff',
])
