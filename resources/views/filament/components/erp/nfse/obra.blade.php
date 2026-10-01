@php
    $somenteLeitura = $this->nfseSomenteLeitura();
    $varios = count($linhas) > 1;
@endphp
<div class="erp-nfse-obra">
    @foreach ($linhas as $index)
        @php
            $linha = $this->nfseServicos[$index] ?? [];
            $tipo = (string) ($linha['obra_tipo'] ?? '');
            $descricao = trim((string) ($linha['descricao'] ?? ''));
        @endphp
        <section class="erp-nfse-obra__bloco" wire:key="nfse-obra-{{ $linha['key'] ?? $index }}">
            <h3 class="erp-nfse-obra__title">
                Informações da obra
                @if ($varios && $descricao !== '')
                    <span class="erp-nfse-obra__servico">{{ $descricao }}</span>
                @endif
            </h3>
            <div class="erp-nfe-lancamento-modal__form-row">
                <div class="erp-nfe-lancamento-modal__form-group erp-nfe-lancamento-modal__form-group--grow">
                    <label class="erp-nfe-lancamento-modal__form-label" for="nfse-obra-insc-{{ $index }}">Inscrição imobiliária fiscal</label>
                    <input
                        id="nfse-obra-insc-{{ $index }}"
                        type="text"
                        maxlength="30"
                        class="erp-nfe-lancamento-modal__form-input"
                        wire:model="nfseServicos.{{ $index }}.obra_insc_imob_fisc"
                        @disabled($somenteLeitura)
                        autocomplete="off"
                    >
                </div>
            </div>
            <div class="erp-nfse-obra__opcoes" role="radiogroup" aria-label="Identificação da obra">
                <label class="erp-nfse-obra__opcao">
                    <input type="radio" name="nfse-obra-tipo-{{ $index }}" value="cObra" wire:model.live="nfseServicos.{{ $index }}.obra_tipo" @disabled($somenteLeitura)>
                    <span>Código da obra (CNO/CEI)</span>
                </label>
                <label class="erp-nfse-obra__opcao">
                    <input type="radio" name="nfse-obra-tipo-{{ $index }}" value="cCIB" wire:model.live="nfseServicos.{{ $index }}.obra_tipo" @disabled($somenteLeitura)>
                    <span>CIB</span>
                </label>
                <label class="erp-nfse-obra__opcao">
                    <input type="radio" name="nfse-obra-tipo-{{ $index }}" value="end" wire:model.live="nfseServicos.{{ $index }}.obra_tipo" @disabled($somenteLeitura)>
                    <span>Endereço da obra</span>
                </label>
            </div>
            @if ($tipo === 'cObra')
                <div class="erp-nfe-lancamento-modal__form-row">
                    <div class="erp-nfe-lancamento-modal__form-group erp-nfe-lancamento-modal__form-group--grow">
                        <label class="erp-nfe-lancamento-modal__form-label" for="nfse-obra-codigo-{{ $index }}">CNO ou CEI</label>
                        <input
                            id="nfse-obra-codigo-{{ $index }}"
                            type="text"
                            maxlength="30"
                            class="erp-nfe-lancamento-modal__form-input"
                            wire:model="nfseServicos.{{ $index }}.obra_c_obra"
                            @disabled($somenteLeitura)
                            autocomplete="off"
                        >
                    </div>
                </div>
            @elseif ($tipo === 'cCIB')
                <div class="erp-nfe-lancamento-modal__form-row">
                    <div class="erp-nfe-lancamento-modal__form-group">
                        <label class="erp-nfe-lancamento-modal__form-label" for="nfse-obra-cib-{{ $index }}">CIB</label>
                        <input
                            id="nfse-obra-cib-{{ $index }}"
                            type="text"
                            maxlength="8"
                            class="erp-nfe-lancamento-modal__form-input"
                            wire:model="nfseServicos.{{ $index }}.obra_c_cib"
                            @disabled($somenteLeitura)
                            autocomplete="off"
                        >
                    </div>
                </div>
            @elseif ($tipo === 'end')
                <div class="erp-nfe-lancamento-modal__form-row">
                    <div class="erp-nfe-lancamento-modal__form-group">
                        <label class="erp-nfe-lancamento-modal__form-label" for="nfse-obra-cep-{{ $index }}">CEP</label>
                        <input
                            id="nfse-obra-cep-{{ $index }}"
                            type="text"
                            inputmode="numeric"
                            maxlength="9"
                            class="erp-nfe-lancamento-modal__form-input"
                            wire:model="nfseServicos.{{ $index }}.obra_cep"
                            @disabled($somenteLeitura)
                            autocomplete="off"
                        >
                    </div>
                    <div class="erp-nfe-lancamento-modal__form-group erp-nfe-lancamento-modal__form-group--grow">
                        <label class="erp-nfe-lancamento-modal__form-label" for="nfse-obra-lgr-{{ $index }}">Logradouro</label>
                        <input
                            id="nfse-obra-lgr-{{ $index }}"
                            type="text"
                            maxlength="255"
                            class="erp-nfe-lancamento-modal__form-input"
                            wire:model="nfseServicos.{{ $index }}.obra_logradouro"
                            @disabled($somenteLeitura)
                            autocomplete="off"
                        >
                    </div>
                    <div class="erp-nfe-lancamento-modal__form-group">
                        <label class="erp-nfe-lancamento-modal__form-label" for="nfse-obra-nro-{{ $index }}">Número</label>
                        <input
                            id="nfse-obra-nro-{{ $index }}"
                            type="text"
                            maxlength="60"
                            class="erp-nfe-lancamento-modal__form-input"
                            wire:model="nfseServicos.{{ $index }}.obra_numero"
                            @disabled($somenteLeitura)
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="erp-nfe-lancamento-modal__form-row">
                    <div class="erp-nfe-lancamento-modal__form-group">
                        <label class="erp-nfe-lancamento-modal__form-label" for="nfse-obra-cpl-{{ $index }}">Complemento</label>
                        <input
                            id="nfse-obra-cpl-{{ $index }}"
                            type="text"
                            maxlength="156"
                            class="erp-nfe-lancamento-modal__form-input"
                            wire:model="nfseServicos.{{ $index }}.obra_complemento"
                            @disabled($somenteLeitura)
                            autocomplete="off"
                        >
                    </div>
                    <div class="erp-nfe-lancamento-modal__form-group erp-nfe-lancamento-modal__form-group--grow">
                        <label class="erp-nfe-lancamento-modal__form-label" for="nfse-obra-bairro-{{ $index }}">Bairro</label>
                        <input
                            id="nfse-obra-bairro-{{ $index }}"
                            type="text"
                            maxlength="60"
                            class="erp-nfe-lancamento-modal__form-input"
                            wire:model="nfseServicos.{{ $index }}.obra_bairro"
                            @disabled($somenteLeitura)
                            autocomplete="off"
                        >
                    </div>
                </div>
            @endif
        </section>
    @endforeach
</div>
