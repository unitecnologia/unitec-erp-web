@php
    $etapa = $this->etapa;
    $finOpen = $etapa === 'finalizacao';
    $isEdicaoPedido = (bool) $this->pedidoId;
@endphp

<div
    class="erp-fv-tv-root"
    data-fv-dirty="{{ count($this->itens) > 0 ? '1' : '0' }}"
    x-data
    x-init="$nextTick(() => {
        if (@js($finOpen) || @js($this->descontoModalOpen) || @js($this->excluirItemModalOpen) || @js($this->fvImportarOrcamentoOpen) || @js($this->boletoContaPickOpen) || @js($this->margemModalOpen) || @js($this->observacaoPedidoModalOpen) || @js($this->observacaoClienteModalOpen) || @js($this->tabPrecoItemModalOpen) || @js($this->fvImprimirPosVendaOpen)) return;
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
        // Preferir o DOM: após fechar o modal, o $wire pode atrasar e engolir o Enter na Qtde.
        const tabPrecoOpen = !!document.getElementById('erp-fv-tab-preco-panel');
        const excluirOpen = !!$wire.excluirItemModalOpen;
        const importarOpen = !!$wire.fvImportarOrcamentoOpen;
        const listarVendasOpen = !!$wire.fvListarVendasOpen;
        const listarCopiarConfirmOpen = !!$wire.fvListarCopiarConfirmOpen;
        const listarImprimirOverlayOpen = !!$wire.fvListarImprimirOverlayOpen;
        const margemOpen = !!$wire.margemModalOpen;
        const canhotoOpen = !!$wire.finalizarCartaoCanhotoAberta;
        const boletoPickOpen = !!$wire.boletoContaPickOpen;
        const boletoSucessoOpen = !!(typeof $wire.boletoSucessoDetalhe === 'string' && $wire.boletoSucessoDetalhe);
        const obsPedidoOpen = !!$wire.observacaoPedidoModalOpen;
        const obsClienteOpen = !!$wire.observacaoClienteModalOpen;
        const sairConfirmOpen = !!$wire.fvSairConfirmOpen;
        const imprimirPosVendaOpen = !!$wire.fvImprimirPosVendaOpen;
        const tecla = ($event.key || '');

        // F5: sempre bloqueado nesta tela (sem recarregar, sair ou mensagem).
        if (tecla === 'F5') {
            $event.preventDefault();
            return;
        }

        if (imprimirPosVendaOpen) {
            // Modal de impressão: isola teclado da TV (atalhos F* / Enter de venda não disparam).
            $event.preventDefault();
            $event.stopPropagation();
            if ($wire.fvImprimirPosVendaProcessando) { return; }

            if ($event.key === 'Escape') {
                window.erpFvImprimirPosVendaActivateNao?.();
                return;
            }
            if ($event.key === 'Enter') {
                window.erpFvImprimirPosVendaActivateFocused?.();
                return;
            }
            if ($event.key === 'Tab' || $event.key === 'ArrowLeft' || $event.key === 'ArrowRight') {
                window.erpFvImprimirPosVendaCycle?.(
                    $event.key === 'Tab' ? ($event.shiftKey ? -1 : 1) : ($event.key === 'ArrowLeft' ? -1 : 1)
                );
                return;
            }
            return;
        }

        if (sairConfirmOpen) {
            if ($event.key === 'Enter') {
                $event.preventDefault();
                if ($wire.gravando) { return; }
                window.erpFvTvBeginAuthorizedLeave?.();
                if (window.__erpFvTvEditing) {
                    const v = window.erpFvTvBarValues?.() || {};
                    $wire.confirmSairSalvarPendente(v.qtd ?? null, v.preco ?? null);
                } else {
                    $wire.confirmSairSalvarPendente();
                }
                return;
            }
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.dismissSairConfirm(); return; }
            return;
        }

        if (obsPedidoOpen || obsClienteOpen) {
            if ($event.key === 'Escape') {
                $event.preventDefault();
                if (obsPedidoOpen) {
                    const el = document.getElementById('erp-fv-obs-pedido');
                    $wire.fecharObservacaoPedido(el ? el.value : null);
                } else {
                    $wire.fecharObservacaoCliente();
                }
            }
            return;
        }

        // Ctrl+D: sempre bloquear favorito do Chrome (mesmo com foco em input).
        if ($event.ctrlKey && (tecla === 'd' || tecla === 'D')) {
            $event.preventDefault();
            $event.stopPropagation();
            if (!finOpen && !descontoOpen && !excluirOpen && !importarOpen && !listarVendasOpen && !margemOpen && !boletoPickOpen && !boletoSucessoOpen) {
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
        if (tabPrecoOpen) {
            if ($event.key === 'ArrowDown' || $event.key === 'ArrowUp') {
                $event.preventDefault();
                window.erpFvMoveTabPrecoSelection?.($event.key === 'ArrowDown' ? 1 : -1);
                return;
            }
            if ($event.key === 'Enter') {
                $event.preventDefault();
                window.erpFvConfirmTabPreco?.($wire);
                return;
            }
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelTabPrecoItem(); return; }
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

        if (listarImprimirOverlayOpen) {
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.fecharFvListarImprimirOverlay(); return; }
            return;
        }

        if (listarCopiarConfirmOpen) {
            if ($event.key === 'Enter') { $event.preventDefault(); $wire.confirmarCopiarProdutosFvListarVenda(); return; }
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelarCopiarProdutosFvListarVenda(); return; }
            return;
        }

        if (listarVendasOpen) {
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.fecharListarVendas(); return; }
            return;
        }

        const target = $event.target;
        const inField = target.closest?.('input, textarea, select, button.erp-fv-tv__combo-btn');
        const inClienteFin = target?.id === 'erp-fv-fin-cliente';
        const inCpfNotaFin = target?.id === 'erp-fv-finalizar-cpf';
        const inInfosFin = target?.id === 'erp-fv-finalizar-informacoes';
        const inAjusteFin = !!target.closest?.('.erp-fv-fin__ajuste');
        const inDividirFin = !!target.closest?.('[data-erp-dividir-conta]');
        const inNotaFooterFin = inCpfNotaFin || inInfosFin;

        // Finalização: atalhos do meio (letra/F) preenchem o restante; setas só navegam.
        if (finOpen) {
            // F5 nunca recarrega/descarta nesta tela.
            if ($event.key === 'F5') {
                $event.preventDefault();
                return;
            }

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

            if (window.__erpFvFinClosing) {
                if ($event.key === 'Escape') {
                    $event.preventDefault();
                }
                return;
            }

            if ($event.key === 'Escape') {
                $event.preventDefault();
                if (document.querySelector('#erp-fv-fin-cliente-sugestoes, .erp-fv-fin__suggest')) {
                    window.erpFvCloseFinClienteLookup?.();
                    window.erpFvFinClienteRestoreCommitted?.();
                    $wire.fecharSugestoesCliente();
                    return;
                }
                window.erpFvFecharFinalizacaoInstantaneo?.($wire);
                return;
            }
            // Sempre preferir o valor do servidor (evita cache stale após mudar F9/F10 na empresa).
            const atalhoConcluir = (@js($this->atalhoConcluirVenda()) || 'F10');
            window.__erpFvAtalhoConcluir = atalhoConcluir;
            if ($event.key === atalhoConcluir) {
                $event.preventDefault();
                if ($wire.gravando || window.__erpFvConcluirLock) { return; }
                window.erpFvConcluirComFlush?.($wire);
                return;
            }
            // F9 = Transportadora (atalho original), salvo quando F9 é o Concluir.
            if ($event.key === 'F9' && atalhoConcluir !== 'F9') {
                $event.preventDefault();
                $wire.abrirFvTransporteModal();
                return;
            }
            // Navegação entre meios: somente setas ↑↓ (F8 não navega nesta tela).
            // Letras/F dos meios: Alpine window.capture do modal (0 rede no fluxo comum).

            // F5 e Fx sem operação na finalização: bloquear (não recarregar / não descartar).
            if (/^F\d{1,2}$/.test(tecla)) {
                $event.preventDefault();
                return;
            }

            if (inClienteFin || inNotaFooterFin || inAjusteFin || inDividirFin) {
                return;
            }
            if ($event.key === 'ArrowDown' || $event.key === 'ArrowUp') {
                $event.preventDefault();
                // Só DOM — sem Livewire por tecla (evita corrida de respostas atrasadas).
                window.erpFvMovePagamentoSelection?.($event.key === 'ArrowDown' ? 1 : -1);
                return;
            }
            return;
        }

        if (inField) {
            if ($event.key === 'Escape') {
                $event.preventDefault();
                // Esc na barra: cancela edição (item volta à grade) se houver.
                if (window.__erpFvTvEditing) {
                    $wire.cancelarEdicaoItem();
                }
                return;
            }
            // Atalhos Fx também com foco em input (F2/F3/F4…).
            if ($event.key === 'F2' || $event.key === 'F3' || $event.key === 'F4') {
                $event.preventDefault();
            } else if (/^F\d{1,2}$/.test(tecla)) {
                // F5 e demais Fx sem operação nesta etapa: só bloqueia (sem mensagem).
                $event.preventDefault();
                return;
            } else {
                return;
            }
        }
        if (descontoOpen) {
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.fecharModalDescontoItem(); }
            return;
        }
        if ($event.key === 'F2') { $event.preventDefault(); $wire.abrirImportarOrcamento(); return; }
        if ($event.key === 'F3') {
            $event.preventDefault();
            if (window.__erpFvTvIncluding || window.__erpFvTvTabelaUpdating || $wire.tabelaProgressOpen || $wire.gravando) { return; }
            window.erpFvTvBeginAuthorizedLeave?.();
            if (window.__erpFvTvEditing) {
                const v = window.erpFvTvBarValues?.() || {};
                $wire.gravarPedidoPendente(false, v.qtd ?? null, v.preco ?? null);
            } else {
                $wire.gravarPedidoPendente();
            }
            return;
        }
        if ($event.key === 'F4') {
            $event.preventDefault();
            if (window.__erpFvTvIncluding || window.__erpFvTvTabelaUpdating || $wire.tabelaProgressOpen || $wire.gravando) { return; }
            if (window.__erpFvTvEditing) {
                const v = window.erpFvTvBarValues?.() || {};
                $wire.irParaFinalizacao(v.qtd ?? null, v.preco ?? null);
            } else {
                $wire.irParaFinalizacao();
            }
            return;
        }
        // F5 e outras teclas de função sem operação prevista: bloquear silenciosamente.
        if (/^F\d{1,2}$/.test(tecla)) {
            $event.preventDefault();
            return;
        }
        if ($event.key === 'Escape') {
            $event.preventDefault();
            // Finalização: Escape cancela a finalização e volta à venda (não sai da tela).
            if (finOpen) {
                $wire.voltarParaVenda();
                return;
            }
            // Escape NÃO inicia saída. Só fecha sugestões abertas.
            if (document.getElementById('fv-tv-produto-sugestoes')) {
                window.erpFvCloseProdutoLookup?.();
                return;
            }
            if ($wire.clienteSugestoesOpen) {
                $wire.fecharSugestoesCliente();
            }
            return;
        }
    "
    x-on:fv-tela-venda-focus-cliente.window="
        $nextTick(() => {
            if ($wire.fvImprimirPosVendaOpen || document.querySelector('[data-erp-fv-imprimir-pos-venda]')) {
                window.erpFvImprimirPosVendaFocusDefault?.();
                return;
            }
            const el = document.getElementById('fv-tv-cliente-busca');
            el?.focus();
            el?.select?.();
        })
    "
    x-on:fv-tela-venda-focus-barcode.window="
        $nextTick(() => {
            requestAnimationFrame(() => {
                // Após faturar com “permanecer”, o reset da TV dispara focus-barcode no mesmo
                // ciclo e roubava o foco do modal de impressão (anel azul sumia).
                if ($wire.fvImprimirPosVendaOpen || document.querySelector('[data-erp-fv-imprimir-pos-venda]')) {
                    window.erpFvImprimirPosVendaFocusDefault?.();
                    return;
                }
                const el = document.getElementById('fv-tv-barcode') || $refs.barcode;
                if (el) {
                    el.value = $wire.codigoBarras ?? '';
                    window.erpFvCloseProdutoLookup?.();
                    el.focus();
                    el.select?.();
                }
            });
        })
    "
    x-on:fv-tela-venda-focus-qtd.window="
        $nextTick(() => {
            if ($wire.fvImprimirPosVendaOpen || document.querySelector('[data-erp-fv-imprimir-pos-venda]')) {
                window.erpFvImprimirPosVendaFocusDefault?.();
                return;
            }
            const barcode = document.getElementById('fv-tv-barcode');
            if (barcode) {
                barcode.value = $wire.codigoBarras ?? barcode.value;
                window.erpFvCloseProdutoLookup?.();
            }
            // Enter rápido: se já avançou p/ Preço (ou inclusão pendente), não roubar o foco.
            if (window.erpFvTvShouldAcceptFocusQtd && !window.erpFvTvShouldAcceptFocusQtd()) {
                return;
            }
            const el = document.getElementById('fv-tv-qtd');
            el?.focus();
            el?.select?.();
        })
    "
    x-on:fv-tela-venda-focus-preco.window="
        $nextTick(() => {
            if ($wire.fvImprimirPosVendaOpen || document.querySelector('[data-erp-fv-imprimir-pos-venda]')) {
                window.erpFvImprimirPosVendaFocusDefault?.();
                return;
            }
            const el = document.getElementById('fv-tv-preco');
            el?.focus();
            el?.select?.();
        })
    "
    x-on:fv-tela-venda-sync-barcode.window="
        $nextTick(() => {
            window.erpFvSyncBarcodeValue?.($event.detail?.value ?? $wire.codigoBarras ?? '');
        })
    "
    x-on:erp-fv-focus-sair-confirm.window="
        $nextTick(() => {
            document.getElementById('erp-fv-sair-confirm-sim')?.focus();
        })
    "
    x-on:erp-fv-focus-listar-vendas-busca.window="
        $nextTick(() => {
            const el = document.getElementById('erp-fv-listar-vendas-busca');
            el?.focus();
            el?.select?.();
        })
    "
    x-on:erp-fv-focus-listar-copiar-confirm.window="
        $nextTick(() => document.getElementById('erp-fv-listar-copiar-confirm')?.focus())
    "
    x-on:erp-fv-focus-imprimir-pos-venda.window="
        $nextTick(() => {
            requestAnimationFrame(() => {
                window.erpFvImprimirPosVendaFocusDefault?.();
            });
        })
    "
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
    x-on:erp-fv-focus-obs-pedido.window="$nextTick(() => { const el = document.getElementById('erp-fv-obs-pedido'); el?.focus(); })"
    x-on:erp-fv-focus-excluir-item-sim.window="$nextTick(() => document.getElementById('erp-fv-excluir-sim')?.focus())"
    x-on:erp-fv-focus-importar-orcamento.window="$nextTick(() => { const el = document.getElementById('erp-fv-importar-orc-numero'); el?.focus(); el?.select?.(); })"
    x-on:erp-fv-scroll-importar-orcamento.window="
        $nextTick(() => {
            const i = $event.detail.index ?? 0;
            document.getElementById('erp-fv-importar-orc-row-' + i)?.scrollIntoView({ block: 'nearest' });
        })
    "
    x-on:erp-fv-highlight-importar-orcamento.window="
        $nextTick(() => {
            const i = Number($event.detail.index ?? 0);
            const rows = document.querySelectorAll('.erp-fv-tv-importar__grid tbody tr.erp-pdv__grid-row');
            rows.forEach((row) => {
                const on = row.id === ('erp-fv-importar-orc-row-' + i);
                row.classList.toggle('erp-pdv__grid-row--selected', on);
                row.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            document.getElementById('erp-fv-importar-orc-row-' + i)?.scrollIntoView({ block: 'nearest' });
        })
    "
>
    <div class="erp-nfe erp-fv-tv {{ $finOpen || $this->descontoModalOpen || $this->excluirItemModalOpen || $this->fvImportarOrcamentoOpen || $this->margemModalOpen || $this->boletoContaPickOpen || $this->observacaoPedidoModalOpen || $this->observacaoClienteModalOpen || $this->tabPrecoItemModalOpen || $this->fvSairConfirmOpen || $this->fvImprimirPosVendaOpen || filled($this->boletoSucessoDetalhe) ? 'is-dimmed' : '' }}">
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
    @include('filament.components.erp.forca-vendas.tela-venda.tab-preco-item')
    @include('filament.components.erp.forca-vendas.tela-venda.importar-orcamento')
    @include('filament.components.erp.forca-vendas.tela-venda.listar-vendas')
    @include('filament.components.erp.forca-vendas.tela-venda.listar-vendas-imprimir-overlay')
    @include('filament.components.erp.forca-vendas.tela-venda.margem-venda')
    @include('filament.components.erp.forca-vendas.tela-venda.observacoes')
    @include('filament.components.erp.forca-vendas.tela-venda.sair-confirm-modal')
    @include('filament.components.erp.forca-vendas.tela-venda.imprimir-pos-venda-modal')
    @include('filament.components.erp.boleto-pos-documento')

    <div
        wire:ignore
        x-data="{
            open: false,
            pct: 0,
            label: '',
            detail: '',
            async run(id, nome) {
                if (window.__erpFvTvTabelaUpdating) {
                    return;
                }
                window.__erpFvTvTabelaUpdating = true;
                this.open = true;
                this.pct = 15;
                this.label = 'Atualizando preços…';
                this.detail = (nome ? String(nome) : 'Tabela') + ' — processando itens';

                const pulse = window.setInterval(() => {
                    if (this.pct < 90) {
                        this.pct = Math.min(90, this.pct + 8);
                    }
                }, 180);

                try {
                    const res = await $wire.aplicarTabelaPrecoNaGrid(id);
                    window.clearInterval(pulse);
                    this.pct = 100;

                    if (! res?.ok) {
                        this.detail = 'Não foi possível aplicar a tabela';
                        await new Promise((r) => setTimeout(r, 500));
                        return;
                    }

                    const alterados = Number(res.alterados || 0);
                    const total = Number(res.total || 0);
                    const tab = res.label || nome || 'tabela';
                    this.label = 'Atualizando preços — ' + tab;
                    this.detail = total <= 0
                        ? 'Nenhum item na grid'
                        : (alterados > 0
                            ? (alterados + ' preço(s) atualizado(s)')
                            : 'Preços conferidos — sem alteração de valor');
                    await new Promise((r) => setTimeout(r, 280));
                } catch (e) {
                    window.clearInterval(pulse);
                    console.error(e);
                    this.pct = 100;
                    this.detail = 'Falha ao atualizar preços';
                    try { await $wire.finalizarAtualizacaoTabelaPreco(0); } catch (_) {}
                    await new Promise((r) => setTimeout(r, 600));
                } finally {
                    window.clearInterval(pulse);
                    window.__erpFvTvTabelaUpdating = false;
                    this.open = false;
                    this.pct = 0;
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
                    aria-valuetext="Atualizando preços"
                >
                    <div
                        class="erp-fv-tv__tabela-progress-bar"
                        :style="'width:' + Math.max(4, Math.min(100, pct)) + '%'"
                    ></div>
                </div>
                <p class="erp-fv-tv__tabela-progress-hint">Aguarde, não feche esta tela.</p>
            </div>
        </div>
    </div>
</div>

@include('filament.components.erp.form-scripts')
@include('filament.components.erp.grid-sort-script')
<script>
    (function () {
        // Tab. Preço: setas locais (sem round-trip), igual ao PDV.
        window.erpFvMoveTabPrecoSelection = function (delta, forceIndex) {
            const panel = document.getElementById('erp-fv-tab-preco-panel');
            if (! panel) return;

            const rows = Array.from(panel.querySelectorAll('[id^="erp-fv-tab-preco-row-"]'));
            if (! rows.length) return;

            let current = Number.parseInt(panel.dataset.selectedIndex || '-1', 10);
            if (! Number.isFinite(current) || current < 0) {
                current = rows.findIndex((row) => row.classList.contains('erp-pdv__grid-row--selected'));
            }
            if (current < 0) current = 0;

            const next = forceIndex !== undefined && forceIndex !== null
                ? Math.max(0, Math.min(rows.length - 1, Number(forceIndex)))
                : Math.max(0, Math.min(rows.length - 1, current + Number(delta || 0)));

            panel.dataset.selectedIndex = String(next);
            rows.forEach((row, index) => {
                const on = index === next;
                row.classList.toggle('erp-pdv__grid-row--selected', on);
                row.setAttribute('aria-selected', on ? 'true' : 'false');
                row.tabIndex = on ? 0 : -1;
            });
            rows[next].focus({ preventScroll: true });
            rows[next].scrollIntoView({ block: 'nearest' });
        };

        window.erpFvConfirmTabPreco = function (wire) {
            const panel = document.getElementById('erp-fv-tab-preco-panel');
            const index = panel
                ? Number.parseInt(panel.dataset.selectedIndex || '0', 10)
                : 0;

            if (wire && typeof wire.confirmTabPrecoItemAt === 'function') {
                wire.confirmTabPrecoItemAt(Number.isFinite(index) ? index : 0);
                return;
            }

            wire?.confirmTabPrecoItem?.();
        };

        // Forma de Pagamento: setas locais (sem remorph) + restante só no atalho do meio.
        window.erpFvParseMoneyBr = function (raw) {
            const s = String(raw ?? '').trim();
            if (! s) return 0;
            const neg = s.startsWith('-');
            const limpo = s.replace(/[^\d,.\-]/g, '').replace('-', '');
            if (! limpo) return 0;
            let n;
            if (limpo.includes(',')) {
                n = Number.parseFloat(limpo.replace(/\./g, '').replace(',', '.'));
            } else {
                n = Number.parseFloat(limpo);
            }
            if (! Number.isFinite(n)) return 0;
            return Math.round((neg ? -n : n) * 100) / 100;
        };

        window.erpFvFormatMoneyBr = function (value) {
            const n = Math.round((Number(value) || 0) * 100) / 100;
            const neg = n < 0;
            const abs = Math.abs(n);
            const [intPart, frac] = abs.toFixed(2).split('.');
            const intFmt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return (neg ? '-' : '') + intFmt + ',' + frac;
        };

        window.erpFvUpdateFinTotaisUi = function (root) {
            if (! root) return;
            const total = window.erpFvParseMoneyBr(root.querySelector('[data-erp-fv-total-pagar]')?.textContent);
            let informado = 0;
            root.querySelectorAll('[id^="erp-fv-finalizar-valor-"]').forEach((el) => {
                informado += window.erpFvParseMoneyBr(el.value);
            });
            informado = Math.round(informado * 100) / 100;
            const restante = Math.max(0, Math.round((total - informado) * 100) / 100);
            const troco = Math.max(0, Math.round((informado - total) * 100) / 100);
            const restEl = root.querySelector('[data-erp-fv-restante]');
            const trocoEl = root.querySelector('[data-erp-fv-troco]');
            if (restEl) restEl.textContent = window.erpFvFormatMoneyBr(restante);
            if (trocoEl) trocoEl.textContent = window.erpFvFormatMoneyBr(troco);
        };

        /**
         * Fonte única da seleção durante navegação por setas.
         * Setas NÃO chamam Livewire — respostas atrasadas não podem mover o destaque.
         */
        window.__erpFvPayNav = window.__erpFvPayNav || { index: 0, focusEpoch: 0, navigating: false, concluirFocused: false };
        window.__erpFvFinClosing = false;
        window.__erpFvConcluirLock = false;

        window.erpFvPayRows = function (root) {
            const scope = root || document.querySelector('.erp-fv-fin');
            if (! scope) return [];
            return Array.from(scope.querySelectorAll('.erp-pdv-finalizar__grid tbody tr.erp-pdv-finalizar__pay-row'));
        };

        window.erpFvIsLivewireComponent = function (obj) {
            return !!(
                obj
                && typeof obj.call === 'function'
                && typeof obj.get === 'function'
                && (typeof obj.id === 'string' || typeof obj.id === 'number')
            );
        };

        window.erpFvResolveFinComponent = function (wire) {
            if (window.erpFvIsLivewireComponent(wire)) return wire;
            if (wire && window.erpFvIsLivewireComponent(wire.__instance)) return wire.__instance;
            try {
                const host = document.querySelector('.erp-fv-tv-root')?.closest('[wire\\:id]')
                    || document.querySelector('.fi-page [wire\\:id]');
                const id = host?.getAttribute('wire:id');
                if (id && window.Livewire) {
                    const c = window.Livewire.find(id);
                    if (window.erpFvIsLivewireComponent(c)) return c;
                }
            } catch (e) { /* ignore */ }
            return null;
        };

        window.erpFvCall = function (wire, method, ...args) {
            const comp = window.erpFvResolveFinComponent(wire);
            if (comp) return comp.call(method, ...args);
            const alpine = window.erpFvIsLivewireComponent(wire) ? null : wire;
            if (alpine && typeof alpine[method] === 'function') return alpine[method](...args);
            throw new Error('fv-fin-wire-indisponivel:' + method);
        };

        window.erpFvCollectValoresPayload = function () {
            const payload = {};
            document.querySelectorAll('[id^="erp-fv-finalizar-valor-"]').forEach((el) => {
                const m = String(el.id).match(/^erp-fv-finalizar-valor-(\d+)$/);
                if (m) payload[m[1]] = el.value;
            });
            return payload;
        };

        window.erpFvRestanteAtual = function (root) {
            const scope = root || document.querySelector('.erp-fv-fin');
            if (! scope) return 0;
            const total = window.erpFvParseMoneyBr(scope.querySelector('[data-erp-fv-total-pagar]')?.textContent);
            let informado = 0;
            scope.querySelectorAll('[id^="erp-fv-finalizar-valor-"]').forEach((el) => {
                informado += window.erpFvParseMoneyBr(el.value);
            });
            return Math.max(0, Math.round((total - informado) * 100) / 100);
        };

        window.erpFvFocusConcluirBtn = function () {
            window.__erpFvPayNav = window.__erpFvPayNav || { index: 0, focusEpoch: 0, navigating: false, concluirFocused: false };
            window.__erpFvPayNav.focusEpoch = (window.__erpFvPayNav.focusEpoch || 0) + 1;
            window.__erpFvPayNav.concluirFocused = true;
            const btn = document.getElementById('erp-fv-finalizar-op-concluir')
                || document.querySelector('.erp-fv-fin .erp-pdv-finalizar__operacao-btn:not([disabled])');
            btn?.focus({ preventScroll: true });
        };

        window.erpFvSetConcluirBusy = function (busy) {
            const btn = document.getElementById('erp-fv-finalizar-op-concluir');
            if (! btn) return;
            const idle = btn.querySelector('[data-erp-fv-concluir-idle]');
            const loading = btn.querySelector('[data-erp-fv-concluir-busy]');
            if (busy) {
                btn.disabled = true;
                btn.setAttribute('disabled', 'disabled');
                btn.setAttribute('aria-busy', 'true');
                if (idle) idle.hidden = true;
                if (loading) loading.hidden = false;
                return;
            }
            const root = document.querySelector('.erp-fv-fin');
            if (! root || root.getAttribute('data-erp-fv-fin-closing') === '1') return;
            btn.disabled = false;
            btn.removeAttribute('disabled');
            btn.removeAttribute('aria-busy');
            if (idle) idle.hidden = false;
            if (loading) loading.hidden = true;
        };

        window.erpFvApplyPagamentoSelection = function (index, opts) {
            const options = opts || {};
            const focus = options.focus !== false;
            const fromServer = !!options.fromServer;
            const valor = options.valor;
            const root = document.querySelector('.erp-fv-fin');
            if (! root) return;

            const rows = window.erpFvPayRows(root);
            if (! rows.length) return;

            const next = Math.max(0, Math.min(rows.length - 1, Number(index) || 0));

            // Navegação local invalida focos Livewire ainda pendentes ($nextTick).
            if (! fromServer) {
                window.__erpFvPayNav.focusEpoch = (window.__erpFvPayNav.focusEpoch || 0) + 1;
                // Marca navegação: blur do campo anterior não deve virar sync (rede de segurança).
                window.__erpFvPayNav.navigating = true;
            }
            window.__erpFvPayNav.index = next;

            rows.forEach((row, i) => {
                row.classList.toggle('erp-pdv__grid-row--selected', i === next);
            });

            const input = document.getElementById('erp-fv-finalizar-valor-' + next);
            if (valor != null && valor !== '' && input) {
                input.value = valor;
                delete input.dataset.erpMaskSynced;
                window.ErpMasks?.apply?.(input, { sync: false });
                rows[next]?.classList.toggle(
                    'erp-pdv-finalizar__pay-row--filled',
                    window.erpFvParseMoneyBr(input.value) > 0.009
                );
            }

            if (focus) {
                window.__erpFvPayNav.concluirFocused = false;
                input?.focus({ preventScroll: true });
                input?.select?.();
            }
            rows[next]?.scrollIntoView({ block: 'nearest' });
            window.erpFvUpdateFinTotaisUi(root);

            if (! fromServer) {
                // Libera após o blur síncrono do campo anterior (mesmo tick / microtask).
                queueMicrotask(() => {
                    window.__erpFvPayNav.navigating = false;
                });
            }
        };

        /** Atalho A/P/D… — 0 Livewire no fluxo comum. Cartão/carnê: sync background. */
        window.erpFvSelectPagamentoByAtalhoLocal = function (atalho, wire) {
            const root = document.querySelector('.erp-fv-fin');
            if (! root) return false;
            const key = String(atalho || '').trim().toUpperCase();
            const rows = window.erpFvPayRows(root);
            let index = -1;
            for (let i = 0; i < rows.length; i++) {
                const kbd = (rows[i].querySelector('.erp-pdv-finalizar__kbd')?.textContent || '').trim().toUpperCase();
                if (kbd === key) { index = i; break; }
            }
            if (index < 0) return false;

            const input = document.getElementById('erp-fv-finalizar-valor-' + index);
            const atual = window.erpFvParseMoneyBr(input?.value);
            const aPrazo = rows[index].getAttribute('data-fv-a-prazo') === '1';
            const precisaParcelas = rows[index].getAttribute('data-fv-precisa-parcelas') === '1';
            const cartao = rows[index].getAttribute('data-fv-cartao') === '1';
            let valorAplicar = null;

            if (atual <= 0.009) {
                const total = window.erpFvParseMoneyBr(root.querySelector('[data-erp-fv-total-pagar]')?.textContent);
                if (aPrazo) {
                    rows.forEach((row, i) => {
                        if (i === index) return;
                        const other = document.getElementById('erp-fv-finalizar-valor-' + i);
                        if (! other) return;
                        other.value = '0,00';
                        delete other.dataset.erpMaskSynced;
                        window.ErpMasks?.apply?.(other, { sync: false });
                        row.classList.remove('erp-pdv-finalizar__pay-row--filled');
                    });
                    valorAplicar = window.erpFvFormatMoneyBr(total);
                } else {
                    let outros = 0;
                    rows.forEach((_, i) => {
                        if (i === index) return;
                        outros += window.erpFvParseMoneyBr(document.getElementById('erp-fv-finalizar-valor-' + i)?.value);
                    });
                    valorAplicar = window.erpFvFormatMoneyBr(Math.max(0, Math.round((total - outros) * 100) / 100));
                }
            }

            window.erpFvApplyPagamentoSelection(index, { focus: true, fromServer: false, valor: valorAplicar });

            const valorFinal = window.erpFvParseMoneyBr(document.getElementById('erp-fv-finalizar-valor-' + index)?.value);
            if (wire && valorFinal > 0.009 && (precisaParcelas || cartao)) {
                const payload = window.erpFvCollectValoresPayload();
                try {
                    Promise.resolve(window.erpFvCall(wire, 'aplicarPagamentoLocalEEspeciais', payload, index)).catch(() => null);
                } catch (e) { /* ignore */ }
            }
            return true;
        };

        /** 1º Enter — local. Cartão/carnê/TEF: 1 request. */
        window.erpFvConfirmarValorEnterLocal = function (index, wire) {
            const root = document.querySelector('.erp-fv-fin');
            if (! root) return;
            const rows = window.erpFvPayRows(root);
            const idx = Math.max(0, Math.min(rows.length - 1, Number(index) || 0));
            const row = rows[idx];
            const input = document.getElementById('erp-fv-finalizar-valor-' + idx);
            if (! input) return;

            const raw = String(input.value || '');
            const valorNum = window.erpFvParseMoneyBr(raw);
            row?.classList.toggle('erp-pdv-finalizar__pay-row--filled', valorNum > 0.009);
            window.erpFvUpdateFinTotaisUi(root);
            window.__erpFvPayNav.index = idx;

            const especial = valorNum > 0.009 && (
                row?.getAttribute('data-fv-precisa-parcelas') === '1'
                || row?.getAttribute('data-fv-cartao') === '1'
                || row?.getAttribute('data-fv-tef') === '1'
            );
            if (especial && wire) {
                const payload = window.erpFvCollectValoresPayload();
                try {
                    Promise.resolve(window.erpFvCall(wire, 'confirmarValorPagamentoLinhaComFlush', payload, idx, raw)).catch(() => null);
                } catch (e) { /* ignore */ }
                return;
            }

            const restante = window.erpFvRestanteAtual(root);
            if (restante <= 0.009) {
                window.erpFvFocusConcluirBtn?.();
                return;
            }
            if (idx < rows.length - 1) {
                window.erpFvApplyPagamentoSelection(idx + 1, { focus: true, fromServer: false });
                return;
            }
            window.erpFvApplyPagamentoSelection(idx, { focus: true, fromServer: false });
        };

        /** Concluir: 1 round-trip + feedback imediato. Nunca use wire.$wire (proxy "$wire"). */
        window.erpFvConcluirComFlush = function (wire) {
            if (! wire || window.__erpFvFinClosing || window.__erpFvConcluirLock) return Promise.resolve();
            const alpine = window.erpFvIsLivewireComponent(wire) ? null : wire;
            let gravando = false;
            try {
                const comp = window.erpFvResolveFinComponent(wire);
                if (comp && typeof comp.get === 'function') gravando = !! comp.get('gravando');
                else if (alpine) gravando = !! alpine.gravando;
            } catch (e) { /* ignore */ }
            if (gravando) return Promise.resolve();

            const payload = window.erpFvCollectValoresPayload();
            const selectedIndex = Number.isInteger(window.__erpFvPayNav?.index) ? window.__erpFvPayNav.index : 0;
            const clienteEl = document.getElementById('erp-fv-fin-cliente');
            const clienteBusca = clienteEl ? String(clienteEl.value || '') : '';
            const cpfEl = document.getElementById('erp-fv-finalizar-cpf');
            const infosEl = document.getElementById('erp-fv-finalizar-informacoes');
            const cpfNota = cpfEl ? String(cpfEl.value || '') : '';
            const observacoes = infosEl ? String(infosEl.value || '') : '';

            window.__erpFvConcluirLock = true;
            window.erpFvSetConcluirBusy(true);

            return Promise.resolve(
                window.erpFvCall(wire, 'faturarPedidoComFlush', payload, selectedIndex, clienteBusca, cpfNota, observacoes)
            )
                .then(() => {
                    queueMicrotask(() => {
                        if (document.querySelector('.erp-fv-fin')) {
                            window.erpFvSetConcluirBusy(false);
                        }
                    });
                })
                .catch((err) => {
                    window.erpFvSetConcluirBusy(false);
                    try { console.warn('[fv-fin] falha ao concluir', err); } catch (e) { /* ignore */ }
                })
                .finally(() => {
                    window.__erpFvConcluirLock = false;
                });
        };

        /** Esc/Voltar/X: fecha visual na hora; 1 sync (etapa=venda). */
        window.erpFvFecharFinalizacaoInstantaneo = function (wire) {
            if (window.__erpFvFinClosing) return Promise.resolve();
            const root = document.querySelector('.erp-fv-fin');
            if (! root || root.getAttribute('data-erp-fv-fin-closing') === '1') return Promise.resolve();

            try {
                const comp = window.erpFvResolveFinComponent(wire);
                if (comp?.get?.('gravando') || (! window.erpFvIsLivewireComponent(wire) && wire?.gravando)) {
                    return Promise.resolve();
                }
            } catch (e) { /* ignore */ }

            if (document.querySelector('.erp-fv-transporte-modal')) {
                return Promise.resolve(window.erpFvCall(wire, 'fecharFvTransporteModal')).catch(() => null);
            }
            if (root.querySelector('.erp-pdv-parcelas-overlay') || document.querySelector('#erp-fv-parcelas-tabelas')) {
                if (document.querySelector('#erp-fv-parcelas-tabelas')) {
                    return Promise.resolve(window.erpFvCall(wire, 'fecharFvTabelasPrazoPredefinidas')).catch(() => null);
                }
                return Promise.resolve(window.erpFvCall(wire, 'cancelarFvTabelaPrazoConsulta')).catch(() => null);
            }
            if (document.querySelector('.erp-pdv-canhoto-overlay')) {
                // Canhoto aberto: Esc fecha só o canhoto (fluxo TV).
                return Promise.resolve(window.erpFvCall(wire, 'cancelFinalizarCartaoCanhoto')).catch(() => null);
            }

            const payload = window.erpFvCollectValoresPayload();
            const selectedIndex = Number.isInteger(window.__erpFvPayNav?.index) ? window.__erpFvPayNav.index : 0;
            const cpfEl = document.getElementById('erp-fv-finalizar-cpf');
            const infosEl = document.getElementById('erp-fv-finalizar-informacoes');
            const cpfNota = cpfEl ? String(cpfEl.value || '') : '';
            const observacoes = infosEl ? String(infosEl.value || '') : '';

            window.__erpFvFinClosing = true;
            window.erpFvFinClienteRestoreCommitted?.();
            window.erpFvCloseFinClienteLookup?.();
            root.setAttribute('data-erp-fv-fin-closing', '1');
            root.style.display = 'none';
            root.setAttribute('aria-hidden', 'true');
            window.__erpFvPayNav = { index: 0, focusEpoch: 0, navigating: false, concluirFocused: false };

            return Promise.resolve(
                window.erpFvCall(wire, 'voltarParaVendaComFlush', payload, selectedIndex, cpfNota, observacoes)
            )
                .then(() => {
                    const leftover = document.querySelector('.erp-fv-fin[data-erp-fv-fin-closing="1"]');
                    if (leftover) {
                        leftover.style.display = '';
                        leftover.removeAttribute('aria-hidden');
                        leftover.removeAttribute('data-erp-fv-fin-closing');
                    }
                })
                .catch(() => {
                    if (root.isConnected) {
                        root.style.display = '';
                        root.removeAttribute('aria-hidden');
                        root.removeAttribute('data-erp-fv-fin-closing');
                    }
                })
                .finally(() => {
                    window.__erpFvFinClosing = false;
                });
        };

        /**
         * Setas: só DOM (foco + seleção). Não preenche restante e não fala com o servidor.
         */
        window.erpFvMovePagamentoSelection = function (delta) {
            const root = document.querySelector('.erp-fv-fin');
            if (! root) return;

            const rows = window.erpFvPayRows(root);
            if (! rows.length) return;

            let current = Number.isInteger(window.__erpFvPayNav?.index)
                ? window.__erpFvPayNav.index
                : rows.findIndex((row) => row.classList.contains('erp-pdv__grid-row--selected'));
            if (current < 0 || current >= rows.length) {
                current = rows.findIndex((row) => row.classList.contains('erp-pdv__grid-row--selected'));
            }
            if (current < 0) current = 0;

            const next = Math.max(0, Math.min(rows.length - 1, current + Number(delta || 0)));
            window.erpFvApplyPagamentoSelection(next, { focus: true, fromServer: false });
        };

        document.addEventListener('input', (event) => {
            const el = event.target;
            if (! (el instanceof HTMLInputElement)) return;
            if (! el.id || ! el.id.startsWith('erp-fv-finalizar-valor-')) return;
            const root = el.closest('.erp-fv-fin');
            if (! root) return;
            const row = el.closest('tr');
            row?.classList.toggle('erp-pdv-finalizar__pay-row--filled', window.erpFvParseMoneyBr(el.value) > 0.009);
            window.erpFvUpdateFinTotaisUi(root);
        }, true);

        window.erpFvFlushPagamentosToWire = function (wire) {
            if (! wire || typeof wire.flushValoresPagamento !== 'function') {
                return Promise.resolve();
            }
            const payload = {};
            document.querySelectorAll('[id^="erp-fv-finalizar-valor-"]').forEach((el) => {
                const m = String(el.id).match(/^erp-fv-finalizar-valor-(\d+)$/);
                if (m) {
                    payload[m[1]] = el.value;
                }
            });
            const selectedIndex = Number.isInteger(window.__erpFvPayNav?.index)
                ? window.__erpFvPayNav.index
                : 0;
            const clienteEl = document.getElementById('erp-fv-fin-cliente');
            const clienteBusca = clienteEl ? String(clienteEl.value || '') : '';
            // Cliente do F4: exige cadastro selecionado (não texto livre) antes do F10.
            const syncCliente = typeof wire.sincronizarClienteFinalizacaoBusca === 'function'
                ? Promise.resolve(wire.sincronizarClienteFinalizacaoBusca(clienteBusca))
                : Promise.resolve(true);

            return syncCliente.then((ok) => {
                if (ok === false) {
                    return Promise.reject(new Error('fv-cliente-inconsistente'));
                }

                return Promise.resolve(window.erpFvFlushNotaFooterToWire?.(wire, true)).then((notaOk) => {
                    if (notaOk === false) {
                        return Promise.reject(new Error('fv-cpf-nota-invalido'));
                    }

                    // Valores + índice local — F10 nunca fatura com estado de seta não sincronizado.
                    return wire.flushValoresPagamento(payload, selectedIndex);
                });
            });
        };

        window.erpFvFlushNotaFooterToWire = function (wire, validarCpf) {
            if (! wire || typeof wire.flushCpfNotaEObservacoes !== 'function') {
                return Promise.resolve(true);
            }

            const cpfEl = document.getElementById('erp-fv-finalizar-cpf');
            const infosEl = document.getElementById('erp-fv-finalizar-informacoes');
            const cpfNota = cpfEl ? String(cpfEl.value || '') : '';
            const observacoes = infosEl ? String(infosEl.value || '') : '';

            return Promise.resolve(wire.flushCpfNotaEObservacoes(cpfNota, observacoes, validarCpf !== false));
        };

        window.erpFvSyncFinCpfNota = function (value) {
            const el = document.getElementById('erp-fv-finalizar-cpf');
            if (! el) return;
            el.value = value == null ? '' : String(value);
        };

        document.addEventListener('livewire:init', () => {
            Livewire.on('erp-fv-fin-cpf-synced', (payload) => {
                const detail = Array.isArray(payload) ? payload[0] : payload;
                const value = detail && typeof detail === 'object' && 'value' in detail
                    ? detail.value
                    : detail;
                window.erpFvSyncFinCpfNota(value);
            });
        });

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
        Livewire.hook('morph.updated', () => {
            window.ErpMasks?.refresh?.(document);
            const root = document.querySelector('.erp-fv-fin');
            if (! root) {
                // Modal fechado: zera navegação para o próximo F4 não herdar índice velho.
                window.__erpFvPayNav = { index: 0, focusEpoch: 0, navigating: false, concluirFocused: false };
                window.__erpFvFinClosing = false;
                return;
            }
            if (window.__erpFvPayNav?.concluirFocused) {
                return;
            }
            // Remorph (blur/cliente) reaplicaria selectedPagamentoIndex stale do PHP.
            // Só restaura índice local depois que o usuário navegou com seta.
            if ((window.__erpFvPayNav?.focusEpoch || 0) > 0) {
                window.erpFvApplyPagamentoSelection?.(window.__erpFvPayNav.index, {
                    focus: false,
                    fromServer: true,
                });
                return;
            }
            const rows = window.erpFvPayRows?.(root) || [];
            const selected = rows.findIndex((row) => row.classList.contains('erp-pdv__grid-row--selected'));
            if (selected >= 0) {
                window.__erpFvPayNav.index = selected;
            }
        });
    });
</script>
<script>
    (function () {
        if (window.__erpFvImprimirPosVendaNavBound) return;
        window.__erpFvImprimirPosVendaNavBound = true;

        const btns = () => Array.from(
            document.querySelectorAll('[data-erp-fv-imprimir-actions] [data-erp-fv-imprimir-btn]')
        ).filter((el) => el instanceof HTMLButtonElement && !el.disabled);

        const focusBtn = (btn) => {
            if (!(btn instanceof HTMLButtonElement) || btn.disabled) return;
            try {
                btn.focus({ preventScroll: true, focusVisible: true });
            } catch (e) {
                btn.focus({ preventScroll: true });
            }
        };

        const activateOnce = (btn) => {
            if (!btn || btn.disabled) return;
            if (window.__erpFvImprimirPosVendaBusy) return;
            window.__erpFvImprimirPosVendaBusy = true;
            focusBtn(btn);
            btn.click();
            // busy só libera na próxima abertura do modal (evita Enter repetido / duplo clique teclado).
        };

        window.erpFvImprimirPosVendaFocusDefault = () => {
            window.__erpFvImprimirPosVendaBusy = false;
            const list = btns();
            if (!list.length) return;
            const nao = list.find((el) => el.dataset.erpFvImprimirBtn === 'nao') || list[list.length - 1];
            focusBtn(nao);
        };

        window.erpFvImprimirPosVendaCycle = (delta) => {
            const list = btns();
            if (list.length < 2) {
                focusBtn(list[0]);
                return;
            }
            const active = document.activeElement;
            let idx = list.indexOf(active);
            if (idx < 0) {
                idx = list.findIndex((el) => el.dataset.erpFvImprimirBtn === 'nao');
                if (idx < 0) idx = list.length - 1;
            }
            const next = (idx + (delta < 0 ? -1 : 1) + list.length) % list.length;
            focusBtn(list[next]);
        };

        window.erpFvImprimirPosVendaActivateFocused = () => {
            const list = btns();
            const active = document.activeElement;
            if (active instanceof HTMLButtonElement && list.includes(active)) {
                activateOnce(active);
                return;
            }
            const nao = list.find((el) => el.dataset.erpFvImprimirBtn === 'nao') || list[list.length - 1];
            activateOnce(nao);
        };

        window.erpFvImprimirPosVendaActivateNao = () => {
            const list = btns();
            const nao = list.find((el) => el.dataset.erpFvImprimirBtn === 'nao') || list[list.length - 1];
            activateOnce(nao);
        };
    })();
</script>
@php
    $fvLookupJsPath = public_path('js/erp-fv-tela-venda-lookup.js');
    $fvLookupJsVersion = is_file($fvLookupJsPath) ? (string) filemtime($fvLookupJsPath) : '1';
    $fvGuardJsPath = public_path('js/erp-fv-tela-venda-guard.js');
    $fvGuardJsVersion = is_file($fvGuardJsPath) ? (string) filemtime($fvGuardJsPath) : '1';
    $fvEnterJsPath = public_path('js/erp-fv-tela-venda-enter.js');
    $fvEnterJsVersion = is_file($fvEnterJsPath) ? (string) filemtime($fvEnterJsPath) : '1';
@endphp
<script src="{{ asset('js/erp-fv-tela-venda-guard.js') }}?v={{ $fvGuardJsVersion }}" defer></script>
<script src="{{ asset('js/erp-fv-tela-venda-enter.js') }}?v={{ $fvEnterJsVersion }}" defer></script>
<script src="{{ asset('js/erp-fv-tela-venda-lookup.js') }}?v={{ $fvLookupJsVersion }}" defer></script>
