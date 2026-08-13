@include('filament.components.erp.aviso-modal', [
    'open' => $this->impostoPadraoApplyConfirmOpen,
    'tone' => 'warning',
    'titleId' => 'erp-imp-padrao-apply-title',
    'title' => 'Aplicar imposto nos produtos',
    'lines' => [
        'Aplicar <strong>'.e($this->impostoPadraoApplyLabel).'</strong> = <strong>'.e($this->impostoPadraoApplyValueDisplay).'</strong> em todos os produtos?',
        'Será atualizado apenas este campo, produto a produto.',
    ],
    'hint' => 'Essa ação altera os produtos já cadastrados.',
    'primaryLabel' => 'Sim',
    'primaryAction' => 'confirmAplicarImpostoPadraoEmProdutos',
    'secondaryLabel' => 'Não',
    'secondaryAction' => 'cancelAplicarImpostoPadraoEmProdutos',
    'escapeAction' => 'cancelAplicarImpostoPadraoEmProdutos',
    'backdropAction' => 'cancelAplicarImpostoPadraoEmProdutos',
])

@if ($this->impostoPadraoApplyProgressOpen)
    <div
        class="erp-emp-imp-apply-progress is-visible"
        aria-live="polite"
        aria-busy="true"
        role="status"
    >
        <div class="erp-emp-imp-apply-progress__backdrop" aria-hidden="true"></div>
        <div class="erp-emp-imp-apply-progress__panel">
            <div class="erp-emp-imp-apply-progress__spinner" aria-hidden="true"></div>
            <p class="erp-emp-imp-apply-progress__status">
                {{ $this->impostoPadraoApplyProgressLabel }}
            </p>
            <p class="erp-emp-imp-apply-progress__detail" title="{{ $this->impostoPadraoApplyProgressDetail }}">
                {{ $this->impostoPadraoApplyProgressDetail }}
            </p>
            <div class="erp-emp-imp-apply-progress__track" aria-hidden="true">
                <div
                    class="erp-emp-imp-apply-progress__bar"
                    style="width: {{ max(4, min(100, $this->impostoPadraoApplyPercent)) }}%"
                ></div>
            </div>
            <p class="erp-emp-imp-apply-progress__meta">
                {{ $this->impostoPadraoApplyCurrent }} / {{ $this->impostoPadraoApplyTotal }}
                — {{ $this->impostoPadraoApplyPercent }}%
            </p>
            <p class="erp-emp-imp-apply-progress__hint">Aguarde, não feche esta tela.</p>
        </div>
    </div>
@endif
