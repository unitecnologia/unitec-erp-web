@php
    $bloqueadas = $this->empresasBloqueadas();
    $liberadas = $this->empresasLiberadas();
    $isAdminUser = $selectedUser?->is_admin ?? false;
    $totalEmpresas = count($this->empresasCatalogo());
@endphp

<div class="erp-permissoes__empresas">
    <div class="erp-permissoes__empresas-head">
        <div>
            <h2>Acesso por empresa</h2>
            <p>Libere as lojas em que o usuário pode operar e escolha a empresa padrão de entrada.</p>
        </div>
        <div class="erp-permissoes__empresas-stats">
            <span><em>{{ count($bloqueadas) }}</em> bloqueadas</span>
            <span><em>{{ count($liberadas) }}</em> liberadas</span>
            <span><em>{{ $totalEmpresas }}</em> no total</span>
        </div>
    </div>

    @if ($isAdminUser)
        <p class="erp-permissoes__notice">
            Administrador já acessa todas as empresas. Use esta aba para definir a empresa padrão e o vínculo salvo.
        </p>
    @endif

    <div class="erp-permissoes__transfer">
        <section class="erp-permissoes__transfer-panel erp-permissoes__transfer-panel--blocked">
            <header>
                <div>
                    <strong>Bloqueadas</strong>
                    <small>Sem acesso</small>
                </div>
                <span>{{ count($bloqueadas) }}</span>
            </header>
            <div class="erp-permissoes__transfer-toolbar">
                <input
                    type="search"
                    wire:model.live.debounce.250ms="empresaSearchBlocked"
                    class="erp-permissoes__transfer-search"
                    placeholder="Pesquisar empresa..."
                >
            </div>
            <div class="erp-permissoes__transfer-list" role="listbox" aria-label="Empresas bloqueadas">
                @forelse ($bloqueadas as $empresa)
                    <button
                        type="button"
                        role="option"
                        wire:click="selectBlockedEmpresa({{ $empresa['id'] }})"
                        wire:dblclick="liberarEmpresa({{ $empresa['id'] }})"
                        @class(['erp-permissoes__transfer-item', 'is-selected' => $this->selectedBlockedEmpresaId === $empresa['id']])
                    >
                        <span class="erp-permissoes__transfer-code">{{ $empresa['codigo'] }}</span>
                        <span class="erp-permissoes__transfer-name">{{ $empresa['nome'] }}</span>
                    </button>
                @empty
                    <div class="erp-permissoes__transfer-empty">
                        <strong>Nada bloqueado</strong>
                        <span>Todas as empresas estão liberadas para este usuário.</span>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="erp-permissoes__transfer-controls" aria-label="Mover empresas">
            <button type="button" class="is-allow-all" wire:click="liberarTodasEmpresas" title="Liberar todas">
                <span>≫</span>
                <small>Todas</small>
            </button>
            <button type="button" class="is-allow" wire:click="liberarEmpresaSelecionada" title="Liberar selecionada">
                <span>›</span>
                <small>Liberar</small>
            </button>
            <button type="button" class="is-block" wire:click="bloquearEmpresaSelecionada" title="Bloquear selecionada">
                <span>‹</span>
                <small>Bloquear</small>
            </button>
            <button type="button" class="is-block-all" wire:click="bloquearTodasEmpresas" title="Bloquear todas">
                <span>≪</span>
                <small>Todas</small>
            </button>
        </div>

        <section class="erp-permissoes__transfer-panel erp-permissoes__transfer-panel--allowed">
            <header>
                <div>
                    <strong>Liberadas</strong>
                    <small>Com acesso</small>
                </div>
                <span>{{ count($liberadas) }}</span>
            </header>
            <div class="erp-permissoes__transfer-toolbar">
                <input
                    type="search"
                    wire:model.live.debounce.250ms="empresaSearchLiberated"
                    class="erp-permissoes__transfer-search"
                    placeholder="Pesquisar empresa..."
                >
            </div>
            <div class="erp-permissoes__transfer-list" role="listbox" aria-label="Empresas liberadas">
                @forelse ($liberadas as $empresa)
                    <div
                        @class([
                            'erp-permissoes__transfer-row',
                            'is-selected' => $this->selectedLiberatedEmpresaId === $empresa['id'],
                            'is-padrao' => $empresa['padrao'],
                        ])
                    >
                        <button
                            type="button"
                            class="erp-permissoes__transfer-row-main"
                            wire:click="selectLiberatedEmpresa({{ $empresa['id'] }})"
                            wire:dblclick="bloquearEmpresa({{ $empresa['id'] }})"
                        >
                            <span class="erp-permissoes__transfer-code">{{ $empresa['codigo'] }}</span>
                            <span class="erp-permissoes__transfer-name">{{ $empresa['nome'] }}</span>
                        </button>
                        <label class="erp-permissoes__transfer-padrao" title="Definir como empresa padrão">
                            <input
                                type="radio"
                                name="empresa_padrao"
                                value="{{ $empresa['id'] }}"
                                @checked($empresa['padrao'])
                                wire:click="definirEmpresaPadrao({{ $empresa['id'] }})"
                            >
                            <span>{{ $empresa['padrao'] ? 'Padrão' : 'Tornar padrão' }}</span>
                        </label>
                    </div>
                @empty
                    <div class="erp-permissoes__transfer-empty">
                        <strong>Nenhuma liberada</strong>
                        <span>Selecione à esquerda e clique em Liberar, ou use ≫ para liberar todas.</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <p class="erp-permissoes__empresas-foot">
        Dica: duplo clique move a empresa. Depois clique em <strong>Salvar empresas</strong>.
    </p>
</div>
