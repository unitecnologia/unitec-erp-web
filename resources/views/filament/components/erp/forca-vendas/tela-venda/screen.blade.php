@php
    $etapa = $this->etapa;
    $finOpen = $etapa === 'finalizacao';
    $isEdicaoPedido = (bool) $this->pedidoId;
@endphp

<div
    class="erp-fv-tv-root"
    x-data
    x-init="$nextTick(() => {
        if (@js($finOpen) || @js($this->descontoModalOpen) || @js($this->excluirItemModalOpen) || @js($this->fvImportarOrcamentoOpen) || @js($this->boletoContaPickOpen) || @js($this->margemModalOpen)) return;
        // Após lançar item / fechar modal o Livewire remonta: não roubar o foco de volta ao cliente.
        if (@js(count($this->itens) > 0) || window.__erpFvTvInitialFocusDone) {
            return;
        }
        window.__erpFvTvInitialFocusDone = true;
        const clienteEl = document.getElementById('fv-tv-cliente-busca');
        if (clienteEl && !@js($isEdicaoPedido)) {
            clienteEl.focus();
            clienteEl.select?.();
            return;
        }
        $refs.barcode?.focus();
    })"
    x-on:keydown.window.capture="
        const finOpen = $wire.etapa === 'finalizacao';
        const descontoOpen = !!$wire.descontoModalOpen;
        const excluirOpen = !!$wire.excluirItemModalOpen;
        const importarOpen = !!$wire.fvImportarOrcamentoOpen;
        const margemOpen = !!$wire.margemModalOpen;
        const canhotoOpen = !!$wire.finalizarCartaoCanhotoAberta;
        const boletoPickOpen = !!$wire.boletoContaPickOpen;
        const boletoSucessoOpen = !!(typeof $wire.boletoSucessoDetalhe === 'string' && $wire.boletoSucessoDetalhe);
        const tecla = ($event.key || '');

        // Ctrl+D: sempre bloquear favorito do Chrome (mesmo com foco em input).
        if ($event.ctrlKey && (tecla === 'd' || tecla === 'D')) {
            $event.preventDefault();
            $event.stopPropagation();
            if (!finOpen && !descontoOpen && !excluirOpen && !importarOpen && !margemOpen && !boletoPickOpen && !boletoSucessoOpen) {
                $wire.abrirModalDescontoItem();
            }
            return;
        }

        if (boletoPickOpen) {
            if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmarGerarBoletoComConta(); return; }
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.closeBoletoContaPickModal(); return; }
            return;
        }
        if (boletoSucessoOpen) {
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.acknowledgeBoletoSucessoOverlay(); }
            return;
        }

        if (margemOpen) {
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.fecharMargemVenda(); }
            return;
        }
        if (excluirOpen) {
            if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmarExcluirItem(); return; }
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelarExcluirItem(); return; }
            return;
        }
        if (importarOpen) {
            if ($event.key === 'ArrowDown') { $event.preventDefault(); $wire.moveFvImportarOrcamentoSelection(1); return; }
            if ($event.key === 'ArrowUp') { $event.preventDefault(); $wire.moveFvImportarOrcamentoSelection(-1); return; }
            if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmarImportarOrcamento(); return; }
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.fecharImportarOrcamento(); return; }
            return;
        }

        const target = $event.target;
        const inField = target.closest?.('input, textarea, select, button.erp-fv-tv__combo-btn');
        const inClienteFin = target?.id === 'erp-fv-fin-cliente';
        const inAjusteFin = !!target.closest?.('.erp-fv-fin__ajuste');
        const teclaAtalho = tecla.length === 1 && /[a-zA-Z0-9]/.test(tecla) && !$event.ctrlKey && !$event.altKey && !$event.metaKey;

        // Finalização: atalhos A/B/C… preenchem o restante (padrão PDV), mesmo com foco no Valor.
        if (finOpen) {
            const parcelasOpen = !!$wire.fvTabelaPrazoConsulta;
            const tabelasPrazoOpen = !!$wire.fvTabelasPrazoListaAberta;
            const transporteOpen = !!$wire.fvTransporteModalOpen;

            if (transporteOpen) {
                if ($event.key === 'Escape') { $event.preventDefault(); $wire.fecharFvTransporteModal(); return; }
                if ($event.key === 'Enter' && !$event.target?.closest?.('textarea, select')) {
                    // Enter nos campos de busca já tem wire:keydown; não forçar confirmar.
                }
                return;
            }

            if (canhotoOpen) {
                if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelFinalizarCartaoCanhoto(); return; }
                if ($event.key === 'F2') { $event.preventDefault(); $wire.gerarParcelasCartaoCanhoto(); return; }
                if ($event.key === 'F7') { $event.preventDefault(); $wire.concluirCartaoCanhoto(); return; }
                return;
            }

            if (parcelasOpen) {
                if (tabelasPrazoOpen) {
                    if ($event.key === 'ArrowDown' || $event.key === 'ArrowUp') {
                        $event.preventDefault();
                        $wire.moveFvTabelaPredefinidaSelection($event.key === 'ArrowDown' ? 1 : -1);
                        return;
                    }
                    if ($event.key === 'Enter') { $event.preventDefault(); $wire.aplicarFvTabelaPrazoPredefinida(); return; }
                    if ($event.key === 'Escape' || $event.key === 'F4') { $event.preventDefault(); $wire.fecharFvTabelasPrazoPredefinidas(); return; }
                    return;
                }
                if ($event.key === 'F2') { $event.preventDefault(); $wire.gerarFvParcelasCrediario(); return; }
                if ($event.key === 'F8') { $event.preventDefault(); $wire.abrirFvTabelasPrazoPredefinidas(); return; }
                if ($event.key === 'F3') { $event.preventDefault(); $wire.excluirFvParcelaCrediario(); return; }
                if ($event.key === 'F4' || $event.key === 'Escape') { $event.preventDefault(); $wire.cancelarFvTabelaPrazoConsulta(); return; }
                if ($event.key === 'F7') { $event.preventDefault(); $wire.concluirFvParcelasCrediario(); return; }
                if ($event.key === 'Enter') {
                    const typing = document.activeElement?.id === 'erp-fv-parcelas-qtd'
                        || document.activeElement?.id === 'erp-fv-parcelas-intervalo';
                    $event.preventDefault();
                    if (typing) { $wire.gerarFvParcelasCrediario(); } else { $wire.concluirFvParcelasCrediario(); }
                    return;
                }
                if ($event.key === 'ArrowDown' || $event.key === 'ArrowUp') {
                    $event.preventDefault();
                    $wire.moveFvParcelaSelection($event.key === 'ArrowDown' ? 1 : -1);
                    return;
                }
                return;
            }

            if ($event.key === 'Escape') {
                $event.preventDefault();
                if (document.querySelector('.erp-fv-fin__suggest')) {
                    $wire.fecharSugestoesCliente();
                    return;
                }
                $wire.voltarParaVenda();
                return;
            }
            if ($event.key === 'F10') { $event.preventDefault(); $wire.faturarPedido(); return; }
            if ($event.key === 'F9') { $event.preventDefault(); $wire.abrirFvTransporteModal(); return; }
            // F8 = próxima forma (padrão PDV); Concluir é F10.
            if ($event.key === 'F8') {
                $event.preventDefault();
                if (! inClienteFin && ! inAjusteFin) {
                    $wire.movePagamentoSelection(1);
                }
                return;
            }

            if (inClienteFin || inAjusteFin) {
                return;
            }
            if ($event.key === 'ArrowDown' || $event.key === 'ArrowUp') {
                $event.preventDefault();
                $wire.movePagamentoSelection($event.key === 'ArrowDown' ? 1 : -1);
                return;
            }
            // Só letras/atalhos das formas (A, B, C…). Dígitos e vírgula ficam livres no Valor.
            if (teclaAtalho) {
                const atalhos = Array.from(document.querySelectorAll('.erp-fv-fin .erp-pdv-finalizar__kbd'))
                    .map((el) => (el.textContent || '').trim().toUpperCase())
                    .filter((v) => v.length === 1);
                if (atalhos.includes(tecla.toUpperCase())) {
                    $event.preventDefault();
                    $event.stopPropagation();
                    $wire.selectPagamentoByAtalho(tecla);
                }
            }
            return;
        }

        if (inField) {
            if ($event.key === 'Escape') {
                $event.preventDefault();
                return;
            }
            // Atalhos de ação também com foco em input (F2 Importar, F3 Pendente, F4 Fechar…).
            if ($event.key === 'F2' || $event.key === 'F3' || $event.key === 'F4' || $event.key === 'F5' || $event.key === 'F8' || $event.key === 'F9' || $event.key === 'F10') {
                $event.preventDefault();
            } else {
                return;
            }
        }
        if (descontoOpen) {
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.fecharModalDescontoItem(); }
            return;
        }
        if ($event.key === 'F2') { $event.preventDefault(); $wire.abrirImportarOrcamento(); return; }
        if ($event.key === 'F3') { $event.preventDefault(); $wire.gravarPedidoPendente(); return; }
        if ($event.key === 'F4') { $event.preventDefault(); $wire.irParaFinalizacao(); return; }
        if ($event.key === 'F5' || $event.key === 'Escape') { $event.preventDefault(); $wire.cancelarVenda(); return; }
    "
    x-on:fv-tela-venda-focus-cliente.window="$nextTick(() => { const el = document.getElementById('fv-tv-cliente-busca'); el?.focus(); el?.select?.(); })"
    x-on:fv-tela-venda-focus-barcode.window="
        $nextTick(() => {
            requestAnimationFrame(() => {
                const el = document.getElementById('fv-tv-barcode') || $refs.barcode;
                el?.focus();
                el?.select?.();
            });
        })
    "
    x-on:fv-tela-venda-focus-qtd.window="$nextTick(() => { const el = document.getElementById('fv-tv-qtd'); el?.focus(); el?.select?.(); })"
    x-on:fv-tela-venda-focus-preco.window="$nextTick(() => { const el = document.getElementById('fv-tv-preco'); el?.focus(); el?.select?.(); })"
    x-on:erp-fv-scroll-cliente-sugestao.window="
        $nextTick(() => {
            const i = $event.detail.index ?? 0;
            document.getElementById('fv-tv-cliente-sug-' + i)?.scrollIntoView({ block: 'nearest' });
            document.getElementById('erp-fv-fin-cliente-sug-' + i)?.scrollIntoView({ block: 'nearest' });
        })
    "
    x-on:erp-fv-scroll-produto-sugestao.window="
        $nextTick(() => {
            const i = $event.detail.index ?? 0;
            document.getElementById('fv-tv-produto-sug-' + i)?.scrollIntoView({ block: 'nearest' });
        })
    "
    x-on:erp-fv-focus-desconto-item.window="$nextTick(() => { const el = document.getElementById('erp-fv-desconto-preco'); el?.focus(); el?.select?.(); })"
    x-on:erp-fv-focus-excluir-item-sim.window="$nextTick(() => document.getElementById('erp-fv-excluir-sim')?.focus())"
    x-on:erp-fv-focus-importar-orcamento.window="$nextTick(() => { const el = document.getElementById('erp-fv-importar-orc-search'); el?.focus(); el?.select?.(); })"
    x-on:erp-fv-scroll-importar-orcamento.window="
        $nextTick(() => {
            const i = $event.detail.index ?? 0;
            document.getElementById('erp-fv-importar-orc-row-' + i)?.scrollIntoView({ block: 'nearest' });
        })
    "
>
    <div class="erp-nfe erp-fv-tv {{ $finOpen || $this->descontoModalOpen || $this->excluirItemModalOpen || $this->fvImportarOrcamentoOpen || $this->margemModalOpen || $this->boletoContaPickOpen || filled($this->boletoSucessoDetalhe) ? 'is-dimmed' : '' }}">
        @include('filament.components.erp.forca-vendas.tela-venda.venda')
    </div>

    @unless ($this->boletoContaPickOpen || filled($this->boletoSucessoDetalhe))
        @include('filament.components.erp.forca-vendas.tela-venda.action-bar')
    @endunless

    @if ($finOpen)
        @include('filament.components.erp.forca-vendas.tela-venda.finalizacao')
    @endif

    @include('filament.components.erp.forca-vendas.tela-venda.desconto-item')
    @include('filament.components.erp.forca-vendas.tela-venda.excluir-item')
    @include('filament.components.erp.forca-vendas.tela-venda.importar-orcamento')
    @include('filament.components.erp.forca-vendas.tela-venda.margem-venda')
    @include('filament.components.erp.boleto-pos-documento')

    <div
        wire:ignore
        x-data="{
            open: false,
            pct: 0,
            label: '',
            detail: '',
            atual: 0,
            total: 0,
            async run(id, nome) {
                this.open = true;
                this.pct = 0;
                this.atual = 0;
                this.total = 0;
                this.label = 'Atualizando preços — ' + (nome || 'tabela');
                this.detail = 'Preparando…';

                try {
                    const start = await $wire.iniciarAtualizacaoTabelaPreco(id);
                    if (! start?.ok) {
                        this.detail = 'Não foi possível aplicar a tabela';
                        this.pct = 100;
                        await new Promise((r) => setTimeout(r, 600));
                        this.open = false;
                        return;
                    }

                    this.total = Number(start.total || 0);
                    this.label = 'Atualizando preços — ' + (start.label || nome || 'tabela');

                    if (this.total <= 0) {
                        this.pct = 100;
                        this.detail = 'Nenhum item na grid';
                        await $wire.finalizarAtualizacaoTabelaPreco(0);
                        await new Promise((r) => setTimeout(r, 450));
                        this.open = false;
                        return;
                    }

                    let alterados = 0;
                    for (let i = 0; i < this.total; i++) {
                        const res = await $wire.aplicarPrecoItemNaGrid(i);
                        this.atual = Number(res?.atual || (i + 1));
                        this.pct = Number(res?.pct || Math.round(((i + 1) / this.total) * 100));
                        const nomeItem = res?.descricao ? String(res.descricao) : ('item ' + this.atual);
                        const precoTxt = res?.preco ? (' → R$ ' + res.preco) : '';
                        this.detail = 'Aplicando preço item ' + this.atual + ' de ' + this.total + ' — ' + nomeItem + precoTxt;
                        if (res?.alterado) alterados++;
                        await new Promise((r) => setTimeout(r, 70));
                    }

                    this.pct = 100;
                    this.atual = this.total;
                    this.detail = alterados > 0
                        ? (alterados + ' preço(s) atualizado(s)')
                        : 'Preços conferidos — sem alteração de valor';

                    await $wire.finalizarAtualizacaoTabelaPreco(alterados);
                    await new Promise((r) => setTimeout(r, 520));
                } catch (e) {
                    console.error(e);
                    this.detail = 'Falha ao atualizar preços';
                    this.pct = 100;
                    try { await $wire.finalizarAtualizacaoTabelaPreco(0); } catch (_) {}
                    await new Promise((r) => setTimeout(r, 700));
                } finally {
                    this.open = false;
                    this.pct = 0;
                    this.atual = 0;
                    this.total = 0;
                }
            }
        }"
        x-on:fv-tv-atualizar-tabela.window="run($event.detail.id, $event.detail.nome)"
    >
        <div
            class="erp-fv-tv__tabela-progress"
            x-show="open"
            x-cloak
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-fv-tv-tabela-progress-title"
        >
            <div class="erp-fv-tv__tabela-progress-backdrop" aria-hidden="true"></div>
            <div class="erp-fv-tv__tabela-progress-panel">
                <div class="erp-fv-tv__tabela-progress-spinner" aria-hidden="true"></div>
                <p id="erp-fv-tv-tabela-progress-title" class="erp-fv-tv__tabela-progress-title" x-text="label"></p>
                <p class="erp-fv-tv__tabela-progress-detail" x-text="detail"></p>
                <div
                    class="erp-fv-tv__tabela-progress-track"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="pct"
                >
                    <div
                        class="erp-fv-tv__tabela-progress-bar"
                        :style="'width:' + Math.max(4, Math.min(100, pct)) + '%'"
                    ></div>
                </div>
                <div class="erp-fv-tv__tabela-progress-meta">
                    <span x-text="atual + ' / ' + total"></span>
                    <strong x-text="pct + '%'"></strong>
                </div>
                <p class="erp-fv-tv__tabela-progress-hint">Aguarde, não feche esta tela.</p>
            </div>
        </div>
    </div>
</div>

@include('filament.components.erp.form-scripts')
<script>
    (function () {
        const NUM_SELECTOR = '.erp-fv-tv-root input[inputmode="decimal"], .erp-fv-tv-root input[inputmode="numeric"], .erp-fv-tv-root input[data-mask]';
        const NAV_KEYS = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];

        function isNumericField(el) {
            return el instanceof HTMLInputElement
                && el.matches(NUM_SELECTOR)
                && el.dataset.mask !== 'date-br';
        }

        document.addEventListener('keydown', (event) => {
            if (! isNumericField(event.target)) return;
            if (NAV_KEYS.includes(event.key) || event.ctrlKey || event.metaKey || event.altKey) return;
            if (event.key.length === 1 && ! /[0-9.,]/.test(event.key)) event.preventDefault();
        }, true);

        document.addEventListener('paste', (event) => {
            if (! isNumericField(event.target)) return;
            const raw = (event.clipboardData || window.clipboardData)?.getData('text') ?? '';
            if (/^[0-9.,\s]*$/.test(raw)) return;
            event.preventDefault();
            const limpo = raw.replace(/[^0-9.,]/g, '');
            document.execCommand('insertText', false, limpo);
        }, true);

        // Rede de segurança: valor colado/preenchido por outros meios.
        document.addEventListener('input', (event) => {
            if (! isNumericField(event.target)) return;
            const limpo = event.target.value.replace(/[^0-9.,]/g, '');
            if (limpo !== event.target.value) event.target.value = limpo;
        }, true);
    })();

    document.addEventListener('livewire:navigated', () => window.ErpMasks?.refresh?.(document));
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', () => window.ErpMasks?.refresh?.(document));
    });
</script>
