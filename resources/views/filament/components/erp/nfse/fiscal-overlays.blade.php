@php
    $confirma = $this->nfseConfirmarProducao;
    $sucesso = filled($this->nfseFiscalSucessoDetalhe) && ! $this->nfseEnviarModalOpen;
    $erro = filled($this->nfseFiscalErroTitulo);
@endphp

<div
    id="erp-nfse-fiscal-confirma"
    class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--warning erp-nfse-fiscal-overlay"
    role="alertdialog"
    aria-labelledby="erp-nfse-producao-title"
    aria-hidden="{{ $confirma ? 'false' : 'true' }}"
    style="display: {{ $confirma ? 'grid' : 'none' }};"
    data-erp-nfse-confirma
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>
        <h2 id="erp-nfse-producao-title" class="erp-nfe-fiscal-overlay__title">Emitir em produção</h2>
        <div class="erp-nfe-fiscal-overlay__text">Esta NFS-e será emitida em PRODUÇÃO e terá validade fiscal. Deseja continuar?</div>
        <div class="erp-nfe-fiscal-overlay__actions">
            <button type="button" class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--confirm" wire:click="confirmarTransmissaoProducao" wire:loading.attr="disabled" wire:target="confirmarTransmissaoProducao">Sim</button>
            <button type="button" id="erp-nfse-producao-nao" class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit" wire:click="cancelarTransmissaoProducao">Não</button>
        </div>
    </div>
</div>

<div
    id="erp-nfse-fiscal-sucesso"
    class="erp-nfe-fiscal-overlay erp-nfe-fiscal-overlay--sucesso erp-nfse-fiscal-overlay{{ $sucesso ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-nfse-sucesso-title"
    aria-hidden="{{ $sucesso ? 'false' : 'true' }}"
    style="display: {{ $sucesso ? 'grid' : 'none' }};"
    data-erp-nfse-sucesso
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">✓</div>
        <h2 id="erp-nfse-sucesso-title" class="erp-nfe-fiscal-overlay__title">NFS-E TRANSMITIDA COM SUCESSO</h2>
        <p class="erp-nfe-fiscal-overlay__codigo">{!! nl2br(e((string) ($this->nfseFiscalSucessoDetalhe ?? ''))) !!}</p>
        <div class="erp-nfe-fiscal-overlay__actions erp-nfe-fiscal-overlay__actions--cce">
            <button type="button" class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--print" id="erp-nfse-sucesso-imprimir" wire:click="imprimirNfseAutorizada">Imprimir</button>
            <button type="button" class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--email" wire:click="abrirNfseEnviar">Enviar</button>
            @if ($this->nfseFiscalSucessoPodeGerarNfe)
                <button
                    type="button"
                    class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--nfe"
                    id="erp-nfse-sucesso-gerar-nfe"
                    wire:click="gerarNfeDasPecasOs"
                >Gerar NF-e</button>
            @endif
            <button type="button" class="erp-nfe-fiscal-overlay__btn erp-nfe-fiscal-overlay__btn--exit" wire:click="closeNfseFiscalSucesso">Sair</button>
        </div>
        <p class="erp-nfe-fiscal-overlay__hint">A nota já consta como autorizada na SEFIN.</p>
    </div>
</div>

<div
    id="erp-nfse-fiscal-erro"
    class="erp-nfe-fiscal-overlay erp-nfse-fiscal-overlay erp-nfse-fiscal-overlay--erro{{ $erro ? ' is-visible' : '' }}"
    role="alertdialog"
    aria-labelledby="erp-nfse-erro-title"
    aria-hidden="{{ $erro ? 'false' : 'true' }}"
    style="display: {{ $erro ? 'grid' : 'none' }};"
    data-erp-nfse-erro
>
    <div class="erp-nfe-fiscal-overlay__box">
        <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>
        <h2 id="erp-nfse-erro-title" class="erp-nfe-fiscal-overlay__title">{{ $this->nfseFiscalErroTitulo }}</h2>
        <p class="erp-nfe-fiscal-overlay__codigo" style="display: {{ filled($this->nfseFiscalErroCodigo) ? 'block' : 'none' }};">
            {{ $this->nfseFiscalErroRotulo !== '' ? $this->nfseFiscalErroRotulo : 'Código SEFIN' }}: <strong>{{ $this->nfseFiscalErroCodigo }}</strong>
        </p>
        <div class="erp-nfe-fiscal-overlay__text">{!! nl2br(e((string) ($this->nfseFiscalErroMensagem ?? ''))) !!}</div>
        <p class="erp-nfe-fiscal-overlay__origem" data-erp-nfse-erro-origem style="display: {{ $this->nfseFiscalErroSefin || $this->nfseFiscalErroOrigem !== '' ? 'block' : 'none' }};">{{ $this->nfseFiscalErroOrigem !== '' ? $this->nfseFiscalErroOrigem : 'Esta é uma mensagem da SEFIN Nacional.' }}</p>
        <button type="button" class="erp-nfe-fiscal-overlay__btn" id="erp-nfse-erro-entendido" wire:click="closeNfseFiscalErro">Entendido</button>
        <p class="erp-nfe-fiscal-overlay__hint">Clique em Entendido para continuar.</p>
    </div>
</div>

@include('filament.components.erp.nfse.fiscal-progress')

<script>
    window.__erpNfseShowOverlay = function (id) {
        const overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.style.display = 'grid';
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
    };
    window.__erpNfseShowSucesso = function (payload) {
        window.__erpNfseHideFiscalProgress && window.__erpNfseHideFiscalProgress();
        const overlay = document.getElementById('erp-nfse-fiscal-sucesso');
        const detalhe = overlay && overlay.querySelector('.erp-nfe-fiscal-overlay__codigo');
        if (detalhe && payload && payload.detalhe) detalhe.textContent = payload.detalhe;
        const gerar = document.getElementById('erp-nfse-sucesso-gerar-nfe');
        if (gerar) {
            gerar.style.display = payload && payload.podeGerarNfe ? '' : 'none';
        }
        window.__erpNfseShowOverlay('erp-nfse-fiscal-sucesso');
        document.getElementById('erp-nfse-sucesso-imprimir')?.focus();
    };
    window.__erpNfseShowErro = function (payload) {
        window.__erpNfseHideFiscalProgress && window.__erpNfseHideFiscalProgress();
        const data = payload && typeof payload === 'object' ? payload : {};
        const overlay = document.getElementById('erp-nfse-fiscal-erro');
        if (!overlay) return;
        const titulo = overlay.querySelector('.erp-nfe-fiscal-overlay__title');
        const texto = overlay.querySelector('.erp-nfe-fiscal-overlay__text');
        const codigo = overlay.querySelector('.erp-nfe-fiscal-overlay__codigo');
        const origem = overlay.querySelector('[data-erp-nfse-erro-origem]');
        if (titulo && data.titulo) titulo.textContent = data.titulo;
        if (texto) texto.textContent = data.mensagem || '';
        if (codigo) {
            const rotulo = data.rotuloCodigo || 'Código SEFIN';
            codigo.style.display = data.codigo ? 'block' : 'none';
            codigo.textContent = data.codigo ? rotulo + ': ' + data.codigo : '';
        }
        if (origem) {
            origem.style.display = data.sefin || data.origem ? 'block' : 'none';
            origem.textContent = data.origem || 'Esta é uma mensagem da SEFIN Nacional.';
        }
        window.__erpNfseShowOverlay('erp-nfse-fiscal-erro');
        document.getElementById('erp-nfse-erro-entendido')?.focus();
    };
    window.__erpNfseBindOverlay = function () {
        if (window.__erpNfseOverlayBound || !window.Livewire) return;
        window.__erpNfseOverlayBound = true;
        let confirmaFocado = false;
        Livewire.hook('morph.updated', function () {
            const confirma = document.getElementById('erp-nfse-fiscal-confirma');
            const visivel = !!(confirma && confirma.style.display !== 'none');
            if (visivel && !confirmaFocado) {
                document.getElementById('erp-nfse-producao-nao')?.focus();
                confirmaFocado = true;
            }
            if (!visivel) confirmaFocado = false;
        });
    };
    window.__erpNfseBindOverlay();
    document.addEventListener('livewire:init', window.__erpNfseBindOverlay);
</script>
