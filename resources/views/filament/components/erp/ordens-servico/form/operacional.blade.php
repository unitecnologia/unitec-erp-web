@php
    $readOnly = $this->osReadOnly();
    $atendenteRotulo = 'Selecione';
    $atendenteIdAtual = (int) ($this->atendenteId ?? 0);
    // Mesma fonte da TV (VendedorEstoque) já embutida em atendenteOptions — sem query extra.
    $atendenteItens = collect($this->atendenteOptions)
        ->map(fn ($op) => [
            'id' => (int) $op['id'],
            'nome' => (string) $op['nome'],
        ])
        ->values()
        ->all();
    foreach ($atendenteItens as $op) {
        if ((int) $op['id'] === $atendenteIdAtual) {
            $atendenteRotulo = (string) $op['nome'];
            break;
        }
    }
@endphp

{{-- Espelho da faixa operacional da Tela de Venda: Estoque em Blade puro (fora do Alpine),
     para o remorph do Livewire atualizar o rótulo ao trocar/abrir o técnico. --}}
<div class="erp-os-shell__oper erp-fv-tv__oper">
    <label class="erp-fv-tv__oper-field erp-fv-tv__oper-field--vendedor">
        <span>Técnico</span>
        <div
            class="erp-fv-tv__combo"
            wire:key="os-atendente-{{ $this->atendenteId ?? 0 }}"
            x-data="{
                open: false,
                ativo: 0,
                valor: @js($this->atendenteId),
                rotulo: @js($atendenteRotulo),
                itens: @js($atendenteItens),
                abrir() {
                    if (@js($readOnly)) return;
                    this.open = true;
                    const idx = this.itens.findIndex(o => o.id === this.valor);
                    this.ativo = idx >= 0 ? idx : 0;
                },
                mover(d) {
                    if (! this.open) { this.abrir(); return; }
                    const total = this.itens.length;
                    if (total === 0) return;
                    this.ativo = (this.ativo + d + total) % total;
                    this.$nextTick(() => this.$refs.panel?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' }));
                },
                confirmar() {
                    const op = this.itens[this.ativo];
                    if (op) this.escolher(op.id, op.nome);
                },
                escolher(id, nome) {
                    this.valor = id;
                    this.rotulo = nome;
                    this.open = false;
                    $wire.set('atendenteId', id);
                    this.$nextTick(() => {
                        const el = document.getElementById('os-item-descricao');
                        if (! el || el.disabled) return;
                        el.removeAttribute('readonly');
                        el.focus();
                    });
                },
            }"
            @click.outside="open = false"
            @keydown.escape.stop="open = false"
        >
            <button
                type="button"
                id="os-atendente"
                @class(['erp-fv-tv__combo-btn', 'is-warning' => ! $this->atendenteId])
                @disabled($readOnly)
                @click="open ? open = false : abrir()"
                @keydown.arrow-down.prevent="mover(1)"
                @keydown.arrow-up.prevent="mover(-1)"
                @keydown.enter.prevent="open ? confirmar() : (valor ? (() => { const el = document.getElementById('os-item-descricao'); if (!el) return; el.removeAttribute('readonly'); el.focus(); })() : abrir())"
                :aria-expanded="open.toString()"
            >
                <span class="erp-fv-tv__combo-btn-label" x-text="rotulo"></span>
                <span class="erp-fv-tv__combo-btn-caret" aria-hidden="true">▾</span>
            </button>
            <div class="erp-fv-tv__combo-panel" x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                <template x-for="(op, i) in itens" :key="op.id">
                    <button
                        type="button"
                        class="erp-fv-tv__combo-item"
                        :class="{ 'is-active': i === ativo || op.id === valor }"
                        @mouseenter="ativo = i"
                        @click="escolher(op.id, op.nome)"
                        x-text="op.nome"
                    ></button>
                </template>
                <template x-if="itens.length === 0">
                    <div class="erp-fv-tv__combo-empty">Nenhum técnico</div>
                </template>
            </div>
        </div>
    </label>

    <label class="erp-fv-tv__oper-field erp-fv-tv__oper-field--caixa">
        <span>Caixa</span>
        <div class="erp-fv-tv__oper-static" title="Destino atual do faturamento da OS">
            <span>{{ $this->osCaixaLabel !== '' ? $this->osCaixaLabel : 'CAIXA GERAL' }}</span>
        </div>
    </label>

    <div
        class="erp-fv-tv__oper-field erp-fv-tv__oper-field--estoque"
        wire:key="os-estoque-{{ $this->atendenteId ?? 0 }}-{{ $this->osEstoqueId ?? 0 }}"
    >
        <span>Estoque</span>
        <div
            @class(['erp-fv-tv__oper-static', 'is-warning' => ! $this->osEstoqueId])
            title="Estoque vinculado ao técnico (somente leitura)"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 9l9-6 9 6"/>
                <path d="M4 10v10h16V10"/>
                <path d="M9 20v-6h6v6"/>
            </svg>
            <span>{{ $this->osEstoqueLabel !== '' ? $this->osEstoqueLabel : 'Não definido' }}</span>
        </div>
    </div>

    <label class="erp-fv-tv__oper-field erp-fv-tv__oper-field--tabela">
        <span>Tabela</span>
        <div
            class="erp-fv-tv__combo"
            wire:key="os-tabela-{{ $this->tabelaPrecoId ?? 0 }}"
            x-data="{
                open: false,
                ativo: 0,
                valor: @js($this->tabelaPrecoId),
                rotulo: @js($this->tabelaPrecoLabel !== '' ? $this->tabelaPrecoLabel : 'Varejo'),
                itens: @js(collect($this->tabelaPrecoOpcoes)->map(fn ($op) => ['id' => (int) $op['id'], 'nome' => $op['label']])->values()->all()),
                abrir() {
                    if (@js($readOnly)) return;
                    this.open = true;
                    const idx = this.itens.findIndex(o => o.id === this.valor);
                    this.ativo = idx >= 0 ? idx : 0;
                },
                mover(d) {
                    if (! this.open) { this.abrir(); return; }
                    const total = this.itens.length;
                    if (total === 0) return;
                    this.ativo = (this.ativo + d + total) % total;
                    this.$nextTick(() => this.$refs.panel?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' }));
                },
                confirmar() {
                    const op = this.itens[this.ativo];
                    if (op) this.escolher(op.id, op.nome);
                },
                escolher(id, nome) {
                    this.valor = id;
                    this.rotulo = nome;
                    this.open = false;
                    $wire.aplicarTabelaPrecoNaGradeOs(id);
                },
            }"
            @click.outside="open = false"
            @keydown.escape.stop="open = false"
        >
            <button
                type="button"
                class="erp-fv-tv__combo-btn"
                @disabled($readOnly)
                @click="open ? open = false : abrir()"
                @keydown.arrow-down.prevent="mover(1)"
                @keydown.arrow-up.prevent="mover(-1)"
                @keydown.enter.prevent="open ? confirmar() : abrir()"
                :aria-expanded="open.toString()"
            >
                <span class="erp-fv-tv__combo-btn-label" x-text="rotulo"></span>
                <span class="erp-fv-tv__combo-btn-caret" aria-hidden="true">▾</span>
            </button>
            <div class="erp-fv-tv__combo-panel" x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                <template x-for="(op, i) in itens" :key="op.id">
                    <button
                        type="button"
                        class="erp-fv-tv__combo-item"
                        :class="{ 'is-active': i === ativo || op.id === valor }"
                        @mouseenter="ativo = i"
                        @click="escolher(op.id, op.nome)"
                        x-text="op.nome"
                    ></button>
                </template>
                <template x-if="itens.length === 0">
                    <div class="erp-fv-tv__combo-empty">Sem tabelas</div>
                </template>
            </div>
        </div>
    </label>
</div>
