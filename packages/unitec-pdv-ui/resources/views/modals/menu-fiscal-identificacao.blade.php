@if ($this->activeModal === 'menu_fiscal_identificacao')
    @php($id = $this->menuFiscalIdentificacao())
    <x-pdvui::modal-shell
        title="Identificação do PAF-NFC-e"
        title-id="erp-pdv-menu-fiscal-identificacao-title"
        eyebrow="Menu Fiscal"
        aria-label="Identificação do PAF-NFC-e"
        close-action="closeMenuFiscalIdentificacao"
        window-class="erp-pdv-modal__window--menu-fiscal-id"
    >
        <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="3.2"/><path d="M5 19a7 7 0 0 1 14 0"/>
            </svg>
        </x-slot:icon>

        <div class="erp-pdv-menu-fiscal-id">
            <section class="erp-pdv-menu-fiscal-id__block">
                <h3>Desenvolvedora</h3>
                <dl class="erp-pdv-menu-fiscal-id__rows">
                    <div><dt>CNPJ</dt><dd>{{ $id['cnpj'] }}</dd></div>
                    <div><dt>Razão social</dt><dd>{{ $id['razao_social'] }}</dd></div>
                    <div class="is-endereco"><dt>Endereço</dt><dd>{{ $id['endereco'] }}</dd></div>
                    <div><dt>Telefone</dt><dd>{{ $id['telefone'] }}</dd></div>
                    <div><dt>Responsável técnico</dt><dd>{{ $id['responsavel_tecnico'] }}</dd></div>
                </dl>
            </section>

            <section class="erp-pdv-menu-fiscal-id__block">
                <h3>Aplicativo</h3>
                <dl class="erp-pdv-menu-fiscal-id__rows">
                    <div><dt>Nome</dt><dd>{{ $id['nome_comercial'] }}</dd></div>
                    <div><dt>Versão</dt><dd>{{ $id['versao'] }}</dd></div>
                    <div><dt>Última atualização</dt><dd>{{ $id['data_ultima_atualizacao'] }}</dd></div>
                    <div>
                        <dt>Banco de dados</dt>
                        <dd>
                            {{ $id['arquitetura_banco_valor'] }}
                            @if (filled($id['arquitetura_banco_detalhe'] ?? null))
                                <small class="erp-pdv-menu-fiscal__detalhe">{{ $id['arquitetura_banco_detalhe'] }}</small>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Execução</dt>
                        <dd>
                            {{ $id['arquitetura_execucao'] }}
                            @if (filled($id['arquitetura_execucao_detalhe'] ?? null))
                                <small class="erp-pdv-menu-fiscal__detalhe">{{ $id['arquitetura_execucao_detalhe'] }}</small>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="erp-pdv-menu-fiscal-id__block">
                <h3>Credenciamento</h3>
                <dl class="erp-pdv-menu-fiscal-id__rows">
                    <div><dt>PAF-NFC-e</dt><dd>{{ $id['credenciamento_numero'] }}</dd></div>
                    <div><dt>Início</dt><dd>{{ $id['credenciamento_inicio'] }}</dd></div>
                    <div><dt>Situação</dt><dd>{{ $id['credenciamento_situacao'] }}</dd></div>
                    <div><dt>Responsável técnico</dt><dd>{{ $id['credenciamento_responsavel'] }}</dd></div>
                </dl>
            </section>
        </div>

        <x-slot:footer>
            <button type="button" wire:click="closeMenuFiscalIdentificacao" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary erp-pdv-menu-fiscal-id__voltar">
                Voltar
            </button>
        </x-slot:footer>
    </x-pdvui::modal-shell>
@endif
