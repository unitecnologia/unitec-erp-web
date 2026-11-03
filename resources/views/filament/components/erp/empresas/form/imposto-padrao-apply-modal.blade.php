@include('filament.components.erp.aviso-modal', [
    'open' => $this->impostoPadraoApplyConfirmOpen,
    'tone' => 'warning',
    'titleId' => 'erp-imp-padrao-apply-title',
    'title' => 'Aplicar em todos os produtos',
    'lines' => [
        'Aplicar <strong>'.e($this->impostoPadraoApplyLabel).'</strong> = <strong>'.e($this->impostoPadraoApplyValueDisplay).'</strong> em <strong>todos</strong> os produtos cadastrados?',
        'Será atualizado <strong>somente este campo</strong>, produto a produto. Os demais impostos de cada produto permanecem iguais.',
        'Esta ação <strong>não pode ser desfeita</strong> automaticamente. Confirme apenas se o contador autorizou a alteração em massa.',
    ],
    'hint' => 'Produtos existentes só mudam com esta confirmação explícita. Novos produtos herdam o Imposto Padrão ao cadastrar.',
    'primaryLabel' => 'Sim, aplicar em massa',
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
