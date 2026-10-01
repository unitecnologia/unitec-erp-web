<div class="erp-devvenda-window">
    <header class="erp-devvenda-titlebar">
        <div class="erp-devvenda-titlebar__text">
            <span class="erp-devvenda-titlebar__title">Troca / Devolução</span>
            <span class="erp-devvenda-titlebar__sub">Lance devoluções de vendas e gere crédito/vale troca para o cliente.</span>
        </div>
        <button
            type="button"
            class="erp-devvenda-close"
            wire:click="handleDevolucaoFormEscape"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-devvenda-body">
        <div class="erp-devvenda-layout">
            @include('filament.components.erp.devolucoes-venda.form.shell')
            @include('filament.components.erp.devolucoes-venda.form.vale-preview')
        </div>
        @include('filament.components.erp.devolucoes-venda.form.action-bar')
    </div>
</div>

@if ($this->creditoDevolucaoModal)
    <div class="erp-devvenda-credito" role="dialog" aria-modal="true" aria-labelledby="erp-devvenda-credito-title">
        <div class="erp-devvenda-credito__backdrop" wire:click="cancelarCreditoDevolucao"></div>
        <div class="erp-devvenda-credito__window">
            <h2 id="erp-devvenda-credito-title">Crédito do cliente</h2>
            <dl class="erp-devvenda-credito__resumo">
                <div>
                    <dt>Total da devolução</dt>
                    <dd>R$ {{ $this->creditoDevolucaoTotal }}</dd>
                </div>
                <div>
                    <dt>(-) Abatimento de títulos</dt>
                    <dd>R$ {{ $this->creditoDevolucaoAbatimentos }}</dd>
                </div>
                <div>
                    <dt>(=) Valor a devolver / gerar crédito</dt>
                    <dd>R$ {{ $this->creditoDevolucaoValor }}</dd>
                </div>
            </dl>
            <p>
                Será gerado um crédito de <strong>R$ {{ $this->creditoDevolucaoValor }}</strong>
                para {{ $this->creditoDevolucaoCliente }}.
            </p>
            <p class="erp-devvenda-credito__hint">O dinheiro não sai do caixa. O cliente usa o saldo numa venda nova.</p>
            <footer>
                <button type="button" class="erp-devvenda-credito__btn" wire:click="cancelarCreditoDevolucao">Cancelar</button>
                <button type="button" class="erp-devvenda-credito__btn" wire:click="confirmarDinheiroDevolucao">Devolver em dinheiro</button>
                <button type="button" class="erp-devvenda-credito__btn erp-devvenda-credito__btn--primary" wire:click="confirmarCreditoDevolucao">Confirmar crédito</button>
            </footer>
        </div>
    </div>
@endif

@include('filament.components.erp.form-scripts')

@php
    $jsPath = public_path('js/erp-devolucao-venda-form.js');
    $jsVersion = is_file($jsPath) ? (string) filemtime($jsPath) : (string) time();
@endphp
<script src="{{ asset('js/erp-devolucao-venda-form.js') }}?v={{ $jsVersion }}" defer></script>
