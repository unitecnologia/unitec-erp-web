@php
    $bloqueados = $this->caixasBloqueados();
    $liberados = $this->caixasLiberados();
    $empresaOptions = $this->caixaEmpresaOptions();
    $totalCaixas = count($this->caixasCatalogo());
    $isAdminUser = $selectedUser?->is_admin ?? false;
@endphp

<div class="erp-permissoes__empresas erp-permissoes__caixas">
    <div class="erp-permissoes__empresas-head">
        <div>
            <h2>Caixa por usuário</h2>
            <p>Empresa ativa do ERP e todos os caixas operacionais cadastrados. Libere os que este usuário pode operar e marque um como padrão.</p>
        </div>
        <div class="erp-permissoes__empresas-stats">
            <span><em>{{ count($bloqueados) }}</em> bloqueados</span>
            <span><em>{{ count($liberados) }}</em> liberados</span>
            <span><em>{{ $totalCaixas }}</em> cadastrados</span>
        </div>
    </div>

    <div class="erp-permissoes__caixa-empresa">
        <label for="permissoes-caixa-empresa">Empresa</label>
        <select id="permissoes-caixa-empresa" wire:model.live="caixaEmpresaId" disabled>
            @forelse ($empresaOptions as $id => $label)
                <option value="{{ $id }}">{{ $label }}</option>
            @empty
                <option value="">Nenhuma empresa disponível</option>
            @endforelse
        </select>
    </div>

    @if ($isAdminUser)
        <p class="erp-permissoes__notice">
            Administrador acessa todos os caixas <strong>tipo PDV</strong>. A lista ainda define o padrão e o vínculo por empresa. Subcaixas (ex.: CAIXA GERAL) não entram aqui — só destino de sangria/fechamento.
        </p>
    @endif

    @if ($totalCaixas === 0)
        <p class="erp-permissoes__notice">
            Nenhum caixa operacional cadastrado. Cadastre em Financeiro → Contas Caixa (tipo PDV/Subcaixa).
        </p>
    @endif

    <div class="erp-permissoes__transfer">
        <section class="erp-permissoes__transfer-panel erp-permissoes__transfer-panel--blocked">
            <header>
                <div>
                    <strong>Bloqueados</strong>
                    <small>Sem acesso</small>
                </div>
                <span>{{ count($bloqueados) }}</span>
            </header>
            <div class="erp-permissoes__transfer-toolbar">
                <input
                    type="search"
                    wire:model.live.debounce.250ms="caixaSearchBlocked"
                    class="erp-permissoes__transfer-search"
                    placeholder="Pesquisar caixa..."
                >
            </div>
            <div class="erp-permissoes__transfer-list" role="listbox" aria-label="Caixas bloqueados">
                @forelse ($bloqueados as $caixa)
                    <button
                        type="button"
                        role="option"
                        wire:click="selectBlockedCaixa({{ $caixa['id'] }})"
                        wire:dblclick="liberarCaixa({{ $caixa['id'] }})"
                        @class(['erp-permissoes__transfer-item', 'is-selected' => $this->selectedBlockedCaixaId === $caixa['id']])
                    >
                        <span class="erp-permissoes__transfer-code">{{ $caixa['codigo'] }}</span>
                        <span class="erp-permissoes__transfer-name">{{ $caixa['nome'] }}</span>
                    </button>
                @empty
                    <div class="erp-permissoes__transfer-empty">
                        <strong>Nada bloqueado</strong>
                        <span>Todos os caixas estão liberados para esta empresa.</span>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="erp-permissoes__transfer-controls" aria-label="Mover caixas">
            <button type="button" class="is-allow-all" wire:click="liberarTodosCaixas" title="Liberar todos">
                <span>≫</span>
                <small>Todos</small>
            </button>
            <button type="button" class="is-allow" wire:click="liberarCaixaSelecionado" title="Liberar selecionado">
                <span>›</span>
                <small>Liberar</small>
            </button>
            <button type="button" class="is-block" wire:click="bloquearCaixaSelecionado" title="Bloquear selecionado">
                <span>‹</span>
                <small>Bloquear</small>
            </button>
            <button type="button" class="is-block-all" wire:click="bloquearTodosCaixas" title="Bloquear todos">
                <span>≪</span>
                <small>Todos</small>
            </button>
        </div>

        <section class="erp-permissoes__transfer-panel erp-permissoes__transfer-panel--allowed">
            <header>
                <div>
                    <strong>Liberados</strong>
                    <small>Com acesso</small>
                </div>
                <span>{{ count($liberados) }}</span>
            </header>
            <div class="erp-permissoes__transfer-toolbar">
                <input
                    type="search"
                    wire:model.live.debounce.250ms="caixaSearchLiberated"
                    class="erp-permissoes__transfer-search"
                    placeholder="Pesquisar caixa..."
                >
            </div>
            <div class="erp-permissoes__transfer-list" role="listbox" aria-label="Caixas liberados">
                @forelse ($liberados as $caixa)
                    <div
                        @class([
                            'erp-permissoes__transfer-row',
                            'is-selected' => $this->selectedLiberatedCaixaId === $caixa['id'],
                            'is-padrao' => $caixa['padrao'],
                        ])
                    >
                        <button
                            type="button"
                            class="erp-permissoes__transfer-row-main"
                            wire:click="selectLiberatedCaixa({{ $caixa['id'] }})"
                            wire:dblclick="bloquearCaixa({{ $caixa['id'] }})"
                        >
                            <span class="erp-permissoes__transfer-code">{{ $caixa['codigo'] }}</span>
                            <span class="erp-permissoes__transfer-name">{{ $caixa['nome'] }}</span>
                        </button>
                        <label class="erp-permissoes__transfer-padrao" title="Definir como caixa padrão">
                            <input
                                type="radio"
                                name="caixa_padrao"
                                value="{{ $caixa['id'] }}"
                                @checked($caixa['padrao'])
                                wire:click="definirCaixaPadrao({{ $caixa['id'] }})"
                            >
                            <span>{{ $caixa['padrao'] ? 'Padrão' : 'Tornar padrão' }}</span>
                        </label>
                    </div>
                @empty
                    <div class="erp-permissoes__transfer-empty">
                        <strong>Nenhum liberado</strong>
                        <span>Selecione à esquerda e clique em Liberar, ou use ≫ para liberar todos.</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <p class="erp-permissoes__empresas-foot">
        A lista usa a <strong>empresa ativa do ERP</strong>. Troque a empresa no topo do sistema para administrar os caixas de outra empresa.
    </p>
</div>
