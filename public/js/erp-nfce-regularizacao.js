/**
 * Regularização Fiscal (NFC-e): seleção e soma 100% no navegador.
 * Servidor só é chamado ao filtrar/paginar e ao emitir (1 request por venda, em sequência).
 */
(function () {
    function factory() {
        return {
            sel: {},
            numeros: {},
            fase: 'lista',
            resultados: [],
            bloqueio: null,
            progresso: { atual: 0, total: 0, numero: '' },

            get qtd() {
                return Object.keys(this.sel).length;
            },

            get total() {
                return Object.values(this.sel).reduce((soma, valor) => soma + valor, 0);
            },

            get ocupado() {
                return this.fase === 'validando' || this.fase === 'emitindo';
            },

            fmt(valor) {
                return (Math.round(valor * 100) / 100).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            },

            isSel(id) {
                return Object.prototype.hasOwnProperty.call(this.sel, id);
            },

            toggle(id, total) {
                if (this.ocupado) {
                    return;
                }

                if (this.isSel(id)) {
                    delete this.sel[id];

                    return;
                }

                this.sel[id] = Number(total) || 0;
            },

            marcarPagina() {
                if (this.ocupado) {
                    return;
                }

                this.$root.querySelectorAll('[data-reg-row]').forEach((row) => {
                    const id = Number(row.dataset.id);

                    if (id > 0 && ! this.isSel(id)) {
                        this.sel[id] = Number(row.dataset.total) || 0;
                    }
                });
            },

            limpar() {
                if (! this.ocupado) {
                    this.sel = {};
                }
            },

            contagem(status) {
                return this.resultados.filter((r) => r.status === status).length;
            },

            rotulo(r) {
                if (r.status === 'autorizada') {
                    return 'Autorizada' + (r.nfce_numero ? ' — NFC-e nº ' + r.nfce_numero : '');
                }

                if (r.status === 'ignorada') {
                    return 'Não emitida: ' + r.mensagem;
                }

                return 'Rejeitada: ' + r.mensagem;
            },

            fecharSePuder() {
                if (! this.ocupado) {
                    this.$wire.fechar();
                }
            },

            async emitir() {
                const ids = Object.keys(this.sel).map(Number).filter((id) => id > 0);

                if (ids.length === 0 || this.ocupado) {
                    return;
                }

                if (ids.length > 500) {
                    this.bloqueio = 'Selecione no máximo 500 vendas por lote.';

                    return;
                }

                this.bloqueio = null;
                this.resultados = [];
                this.fase = 'validando';

                let validacao;

                try {
                    validacao = await this.$wire.validarSelecionadas(ids);
                } catch (e) {
                    validacao = null;
                }

                if (! validacao) {
                    this.bloqueio = 'Não foi possível validar as vendas. Tente novamente.';
                    this.fase = 'lista';

                    return;
                }

                if (validacao.bloqueio) {
                    this.bloqueio = validacao.bloqueio;
                    this.fase = 'lista';

                    return;
                }

                (validacao.erros || []).forEach((erro) => {
                    this.resultados.push({ id: erro.id, numero: erro.numero, status: 'rejeitada', mensagem: erro.mensagem, nfce_numero: null });
                });

                const aptas = validacao.aptas || [];
                this.fase = 'emitindo';
                this.progresso = { atual: 0, total: aptas.length, numero: '' };

                for (const apta of aptas) {
                    this.progresso.atual += 1;
                    this.progresso.numero = apta.numero;

                    let r = null;

                    try {
                        r = await this.$wire.emitirVenda(apta.id);
                    } catch (e) {
                        r = null;
                    }

                    if (! r || ! r.status) {
                        r = { status: 'rejeitada', mensagem: 'Falha de comunicação com o servidor.', nfce_numero: null };
                    }

                    this.resultados.push({
                        id: apta.id,
                        numero: apta.numero,
                        status: r.status,
                        mensagem: r.mensagem || '',
                        nfce_numero: r.nfce_numero || null,
                    });

                    if (r.status === 'autorizada') {
                        delete this.sel[apta.id];
                    }
                }

                this.fase = 'resultado';

                try {
                    await this.$wire.concluirEmissao();
                } catch (e) {
                    // lista é atualizada no próximo filtro
                }
            },
        };
    }

    function register() {
        window.Alpine.data('erpNfceRegularizacao', factory);
    }

    if (window.Alpine) {
        register();
    } else {
        document.addEventListener('alpine:init', register);
    }
})();
