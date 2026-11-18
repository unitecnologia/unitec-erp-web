@php
    use App\Support\Erp\Terminais\TerminalFormOptions;
@endphp

<div class="erp-pcad-form erp-terminais-form erp-terminais-form--config">
    <input type="hidden" wire:model="data.velocidade">
    <input type="hidden" wire:model="data.empresa_id">
    <input type="hidden" wire:model="data.ativo">
    <input type="hidden" wire:model="data.ip">

    <fieldset class="erp-pcad__group erp-terminais-form__ativo-bar">
        <legend class="erp-pcad__group-title">{{ $this->terminalConfigGrupoTitulo() }}</legend>
        <div class="erp-terminais-form__ativo-grid">
            <div class="erp-terminais-form__field erp-terminais-form__field--nome">
                <label class="erp-pcad-form__label" for="term-nome-pdv">Nome no PDV</label>
                <input
                    id="term-nome-pdv"
                    type="text"
                    wire:model.live="data.nome"
                    class="erp-pcad-form__input"
                    placeholder="Ex.: pdv1"
                    title="{{ $this->terminalIdentidadeFixa ? 'Definido pelo número do caixa offline (PDVn). Não pode ser alterado aqui.' : 'Mesmo valor do campo PDV/Terminal no caixa offline' }}"
                    autocomplete="off"
                    @disabled($this->terminalIdentidadeFixa)
                >
            </div>
            <div class="erp-terminais-form__field erp-terminais-form__field--narrow">
                <label class="erp-pcad-form__label" for="term-num-logico">Nº lógico</label>
                <input
                    id="term-num-logico"
                    type="text"
                    wire:model="data.numero_logico_terminal"
                    data-mask="integer"
                    class="erp-pcad-form__input"
                    placeholder="1"
                    title="{{ $this->terminalIdentidadeFixa ? 'Número do caixa offline. Para trocar, exclua o terminal (F4) e configure o novo número no PDV.' : 'Use este número no campo PDV/Terminal do caixa offline' }}"
                    @disabled($this->terminalIdentidadeFixa)
                >
            </div>
            <p class="erp-terminais-form__hint erp-terminais-form__hint--inline">
                Liberar/bloquear na grade (flag <strong>Ativo</strong>). Detalhes no <strong>olhinho</strong>.
                @if ($this->terminalIdentidadeFixa)
                    Caixa offline: nome e nº vêm do número configurado no PDV.
                @elseif ($this->terminalConfigEhPdvOffline())
                    Offline: <strong>Nome</strong> ou <strong>nº lógico</strong>.
                @endif
            </p>
        </div>
    </fieldset>

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Configurações de Impressão</legend>
        @php($printerReadonly = $this->terminalConfigEhPdvOffline())
        <div class="erp-terminais-form__printer-compact @if ($printerReadonly) erp-terminais-form__printer--readonly @endif">
            <div class="erp-terminais-form__radios erp-terminais-form__radios--2x2">
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="0" @disabled($printerReadonly)> Pedido A4</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="1" @disabled($printerReadonly)> ESC/POS</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_impressora" value="3" @disabled($printerReadonly)> NFC-e - A4</label>
            </div>
            <div class="erp-terminais-form__field erp-terminais-form__field--nvias">
                <label class="erp-pcad-form__label" for="term-nvias">Nº Vias</label>
                <input id="term-nvias" type="text" wire:model="data.nvias" data-mask="integer" class="erp-pcad-form__input" @disabled($printerReadonly)>
            </div>
            <div class="erp-terminais-form__field erp-terminais-form__field--caminho">
                <label class="erp-pcad-form__label" for="term-porta">Caminho</label>
                <div class="erp-terminais-form__porta-wrap">
                    <select id="term-porta" wire:model.live="data.porta" class="erp-pcad-form__select" @disabled($printerReadonly)>
                        @if ($printerReadonly)
                            <option value="">—</option>
                        @endif
                        @foreach (($this->portasImpressoraLista ?: TerminalFormOptions::portasImpressora()) as $porta)
                            <option value="{{ $porta }}">{{ $porta }}</option>
                        @endforeach
                    </select>
                    @unless ($printerReadonly)
                        <button type="button" wire:click="moduleStubListaImpressoras" class="erp-terminais-form__porta-btn" title="Listar impressoras do Windows (RAW)">🖨</button>
                    @endunless
                </div>
            </div>
            <input type="hidden" wire:model="data.impressora_nome">
        </div>
        @if ($printerReadonly)
            <p class="erp-terminais-form__hint">
                Impressora deste caixa: altere na <strong>engrenagem do PDV offline</strong>. Estes campos atualizam na carga.
            </p>
        @else
            <p class="erp-terminais-form__hint">
                🖨 lista as impressoras deste Windows como <strong>RAW:Nome</strong> (ex.: RAW:POS-80C). A impressão silenciosa usa o Device Service do PC.
            </p>
        @endif
    </fieldset>

    @if ($this->canManageTerminalTef())
        <details class="erp-pcad__group erp-terminais-tef">
            <summary class="erp-terminais-tef__summary">
                <span class="erp-pcad__group-title">TEF eletrônico</span>
                <span class="erp-terminais-tef__hint">por terminal · SuperTEF ou Scope TEF · Scope em preparação</span>
            </summary>
            <div class="erp-terminais-tef__body">
                <div class="erp-terminais-tef__row">
                    <label class="erp-pcad__check">
                        <input type="checkbox" wire:model="tefAtivo"> Configuração ativa neste PDV
                    </label>
                    <div class="erp-terminais-tef__field erp-terminais-tef__field--sm">
                        <label class="erp-pcad-form__label" for="tef-provedor">Provedor</label>
                        <select id="tef-provedor" class="erp-pcad-form__select" wire:model.live="tefProvedor">
                            @foreach (\App\Support\Erp\Tef\TefProviderId::optionsForUi() as $provValue => $provLabel)
                                <option value="{{ $provValue }}">{{ $provLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (! $this->isTefProvedorScope())
                        <div class="erp-terminais-tef__field erp-terminais-tef__field--sm">
                            <label class="erp-pcad-form__label" for="tef-pos-id">POS_ID</label>
                            <input id="tef-pos-id" type="text" inputmode="numeric" class="erp-pcad-form__input" wire:model="tefPosId" placeholder="Ex.: 5665" autocomplete="off">
                        </div>
                        <div class="erp-terminais-tef__field erp-terminais-tef__field--sm">
                            <label class="erp-pcad-form__label" for="tef-credito-max">Crédito máx. parcelas</label>
                            <input id="tef-credito-max" type="text" inputmode="numeric" class="erp-pcad-form__input" wire:model="tefCreditoMaxParcelas" placeholder="12" autocomplete="off" title="Limite SuperTEF Crédito (1–18). Parcelamento loja.">
                        </div>
                    @else
                        <div class="erp-terminais-tef__field erp-terminais-tef__field--sm">
                            <label class="erp-pcad-form__label" for="scope-ambiente">Ambiente</label>
                            <select id="scope-ambiente" class="erp-pcad-form__select" wire:model="scopeAmbiente">
                                <option value="homologacao">Homologação</option>
                                <option value="producao">Produção</option>
                            </select>
                        </div>
                    @endif
                </div>

                @if (! $this->isTefProvedorScope())
                <div class="erp-terminais-tef__grid">
                    <div class="erp-terminais-tef__field">
                        <label class="erp-pcad-form__label" for="tef-cliente-chave">
                            Chave do cliente
                            @if ($this->tefClienteChaveConfigured)
                                <span class="erp-terminais-tef__badge">gravada</span>
                            @endif
                        </label>
                        <div class="erp-terminais-tef__secret">
                            <input
                                id="tef-cliente-chave"
                                type="password"
                                class="erp-pcad-form__input"
                                wire:model="tefClienteChaveInput"
                                placeholder="{{ $this->tefClienteChaveConfigured ? 'Deixe em branco para manter' : 'cliente_chave SuperTEF' }}"
                                autocomplete="new-password"
                            >
                            @if ($this->tefClienteChaveConfigured)
                                <button type="button" class="erp-terminais-tef__clear" wire:click="clearTefClienteChave" title="Remover chave gravada">Limpar</button>
                            @endif
                        </div>
                    </div>
                    <div class="erp-terminais-tef__field">
                        <label class="erp-pcad-form__label" for="tef-sh-token">
                            Token software house (instalação)
                            @if ($this->tefSofthouseTokenConfigured)
                                <span class="erp-terminais-tef__badge">gravado</span>
                            @endif
                        </label>
                        <div class="erp-terminais-tef__secret">
                            <input
                                id="tef-sh-token"
                                type="password"
                                class="erp-pcad-form__input"
                                wire:model="tefSofthouseTokenInput"
                                placeholder="{{ $this->tefSofthouseTokenConfigured ? 'Deixe em branco para manter' : 'Bearer da software house' }}"
                                autocomplete="new-password"
                            >
                            @if ($this->tefSofthouseTokenConfigured)
                                <button type="button" class="erp-terminais-tef__clear" wire:click="clearTefSofthouseToken" title="Remover token gravado">Limpar</button>
                            @endif
                        </div>
                    </div>
                </div>
                @else
                <p class="erp-terminais-tef__hint" style="margin:0 0 .5rem;">
                    Scope TEF (Conexão Tech): prepare identificação e credenciais. Cobranças reais ficam bloqueadas até o SDK oficial.
                </p>
                <div class="erp-terminais-tef__grid">
                    <div class="erp-terminais-tef__field">
                        <label class="erp-pcad-form__label" for="scope-empresa">Código empresa</label>
                        <input id="scope-empresa" type="text" class="erp-pcad-form__input" wire:model="scopeEmpresaCodigo" autocomplete="off">
                    </div>
                    <div class="erp-terminais-tef__field">
                        <label class="erp-pcad-form__label" for="scope-loja">Código loja</label>
                        <input id="scope-loja" type="text" class="erp-pcad-form__input" wire:model="scopeLojaCodigo" autocomplete="off">
                    </div>
                    <div class="erp-terminais-tef__field">
                        <label class="erp-pcad-form__label" for="scope-terminal">Código terminal</label>
                        <input id="scope-terminal" type="text" class="erp-pcad-form__input" wire:model="scopeTerminalCodigo" autocomplete="off">
                    </div>
                    <div class="erp-terminais-tef__field">
                        <label class="erp-pcad-form__label" for="scope-host">Serviço local (host)</label>
                        <input id="scope-host" type="text" class="erp-pcad-form__input" wire:model="scopeServicoHost" placeholder="127.0.0.1" autocomplete="off">
                    </div>
                    <div class="erp-terminais-tef__field erp-terminais-tef__field--sm">
                        <label class="erp-pcad-form__label" for="scope-porta">Porta</label>
                        <input id="scope-porta" type="text" inputmode="numeric" class="erp-pcad-form__input" wire:model="scopeServicoPorta" placeholder="(oficial)" autocomplete="off">
                    </div>
                    <div class="erp-terminais-tef__field">
                        <label class="erp-pcad-form__label" for="scope-credencial">
                            Credencial
                            @if ($this->scopeCredencialConfigured)
                                <span class="erp-terminais-tef__badge">gravada</span>
                            @endif
                        </label>
                        <div class="erp-terminais-tef__secret">
                            <input
                                id="scope-credencial"
                                type="password"
                                class="erp-pcad-form__input"
                                wire:model="scopeCredencialInput"
                                placeholder="{{ $this->scopeCredencialConfigured ? 'Deixe em branco para manter' : 'Credencial Scope (cifrada)' }}"
                                autocomplete="new-password"
                            >
                            @if ($this->scopeCredencialConfigured)
                                <button type="button" class="erp-terminais-tef__clear" wire:click="clearScopeCredencial" title="Remover credencial">Limpar</button>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                <div class="erp-terminais-tef__actions">
                    <button type="button" class="erp-terminais-balanca__test-btn" wire:click="testTerminalTefConnection" wire:loading.attr="disabled" wire:target="testTerminalTefConnection">
                        {{ $this->isTefProvedorScope() ? 'Diagnóstico Scope' : 'Testar conexão' }}
                    </button>
                    <button type="button" class="erp-terminais-balanca__test-btn" wire:click="saveTerminalTefConfig" wire:loading.attr="disabled" wire:target="saveTerminalTefConfig">
                        Salvar configurações
                    </button>
                    <span
                        class="erp-terminais-balanca__test-status"
                        data-state="{{ $this->tefTestStatus !== '' ? $this->tefTestStatus : 'idle' }}"
                    >{{ $this->tefTestMessage !== '' ? $this->tefTestMessage : ($this->isTefProvedorScope() ? 'Diagnóstico local — sem cobrança Scope.' : 'Somente GET /pos — sem cobrança.') }}</span>
                </div>
            </div>
        </details>
    @endif

    <fieldset class="erp-pcad__group">
        <legend class="erp-pcad__group-title">Tipo de Fechamento Caixa</legend>
        <div class="erp-terminais-form__fechamento-row">
            <div class="erp-terminais-form__radios">
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="0"> A4 - Padrão</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="1"> A4 - Detalhado</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="2"> Bobina - Detalhado</label>
                <label class="erp-pcad__check"><input type="radio" wire:model.live="data.tipo_fechamento" value="3"> Bobina - Sintético</label>
            </div>
        </div>
    </fieldset>

    <div class="erp-terminais-form__bottom">
        <fieldset class="erp-pcad__group erp-terminais-form__bottom-left">
            <legend class="erp-pcad__group-title">Operações no fechamento da venda</legend>
            <ul class="erp-terminais-form__operacao-list">
                @foreach (TerminalFormOptions::botoesOperacaoPadrao() as $field => $label)
                    <li>
                        <label class="erp-pcad__check">
                            <input type="checkbox" wire:model="data.{{ $field }}"> {{ $label }}
                        </label>
                    </li>
                @endforeach
            </ul>
        </fieldset>

        <div class="erp-terminais-form__bottom-right">
            <fieldset class="erp-pcad__group">
                <legend class="erp-pcad__group-title">Configurações do PDV</legend>
                <div class="erp-terminais-form__checks erp-terminais-form__checks--compact">
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.usa_gaveta"> Usa Gaveta</label>
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.eh_caixa"> Controle de Caixa</label>
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.imprime"> Perguntar Imprimir</label>
                </div>
            </fieldset>

            <fieldset class="erp-pcad__group">
                <legend class="erp-pcad__group-title">Abas no PDV</legend>
                <div class="erp-terminais-form__checks erp-terminais-form__checks--compact">
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.pdv"> PDV</label>
                    <label class="erp-pcad__check"><input type="checkbox" wire:model="data.restaurante"> Mesas</label>
                </div>
            </fieldset>
        </div>
    </div>
</div>
