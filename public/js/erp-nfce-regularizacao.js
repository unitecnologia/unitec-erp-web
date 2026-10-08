/**
 * Regularização Fiscal (NFC-e): seleção e soma 100% no navegador.
 * Servidor só é chamado ao filtrar/paginar e ao emitir (1 request por venda, em sequência).
 */
(function () {
    const MSG_CNPJ = 'NFC-e não aceita CNPJ. Para pessoa jurídica emita NF-e.';

    function digitos(valor) {
        return String(valor || '').replace(/\D/g, '');
    }

    function mascaraCpf(d) {
        return d
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
    }

    function cpfValido(d) {
        if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) {
            return false;
        }

        for (let t = 9; t < 11; t++) {
            let soma = 0;

            for (let i = 0; i < t; i++) {
                soma += Number(d[i]) * (t + 1 - i);
            }

            if (Number(d[t]) !== ((10 * soma) % 11) % 10) {
                return false;
            }
        }

        return true;
    }

    function erroDoCpf(d, final) {
        if (d.length > 11) {
            return MSG_CNPJ;
        }

        if (d.length === 11) {
            return cpfValido(d) ? null : 'CPF inválido. Verifique os números digitados.';
        }

        return final && d.length > 0 ? 'Informe um CPF válido com 11 dígitos.' : null;
    }

    function factory() {
        return {
            sel: {},
            numeros: {},
            ident: {},
            busca: { aberta: false, carregando: false, itens: [], idx: -1, id: null, top: 0, left: 0, width: 0, seq: 0 },
            buscaTimer: null,
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
                // `in` passa pelo trap `has` do proxy do Alpine; hasOwnProperty não é rastreado e a flag não redesenha.
                return id in this.sel;
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

            inputNome(id) {
                return this.$root.querySelector('[data-reg-nome="' + id + '"]');
            },

            inputCpf(id) {
                return this.$root.querySelector('[data-reg-cpf="' + id + '"]');
            },

            /** Consumidor alterado na grade (criado na primeira edição a partir dos valores da linha). */
            entrada(id) {
                if (! (id in this.ident)) {
                    const nome = this.inputNome(id);
                    const cpf = this.inputCpf(id);

                    this.ident[id] = {
                        person_id: Number(nome?.dataset.person) || null,
                        nome: nome ? nome.value : '',
                        cpf: digitos(cpf?.value),
                        erro: null,
                    };
                }

                return this.ident[id];
            },

            erroCpf(id) {
                return this.ident[id]?.erro || null;
            },

            digitarNome(id, el) {
                const e = this.entrada(id);
                e.nome = el.value;
                e.person_id = null;

                clearTimeout(this.buscaTimer);

                if (el.value.trim().length < 2) {
                    this.fecharBusca();

                    return;
                }

                this.buscaTimer = setTimeout(() => this.buscarClientes(id, el), 250);
            },

            async buscarClientes(id, el) {
                const seq = ++this.busca.seq;
                const r = el.getBoundingClientRect();

                Object.assign(this.busca, {
                    aberta: true,
                    carregando: true,
                    id,
                    idx: -1,
                    top: r.bottom + 2,
                    left: r.left,
                    width: Math.max(r.width, 320),
                });

                let itens = [];

                try {
                    itens = await this.$wire.buscarClientes(el.value);
                } catch (e) {
                    itens = [];
                }

                if (seq !== this.busca.seq || this.busca.id !== id) {
                    return;
                }

                this.busca.itens = Array.isArray(itens) ? itens : [];
                this.busca.idx = this.busca.itens.length > 0 ? 0 : -1;
                this.busca.carregando = false;
            },

            fecharBusca() {
                this.busca.seq++;
                this.busca.aberta = false;
                this.busca.itens = [];
                this.busca.idx = -1;
                this.busca.carregando = false;
            },

            sairNome() {
                clearTimeout(this.buscaTimer);
                setTimeout(() => this.fecharBusca(), 150);
            },

            teclaNome(event, id) {
                const aberta = this.busca.aberta && this.busca.id === id;
                const total = this.busca.itens.length;

                if (aberta && event.key === 'ArrowDown' && total > 0) {
                    event.preventDefault();
                    this.busca.idx = (this.busca.idx + 1) % total;
                } else if (aberta && event.key === 'ArrowUp' && total > 0) {
                    event.preventDefault();
                    this.busca.idx = (this.busca.idx - 1 + total) % total;
                } else if (event.key === 'Escape' && aberta) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.fecharBusca();
                } else if (event.key === 'Enter') {
                    event.preventDefault();

                    if (aberta && this.busca.idx >= 0 && this.busca.itens[this.busca.idx]) {
                        this.escolherCliente(this.busca.itens[this.busca.idx]);

                        return;
                    }

                    this.fecharBusca();
                    this.focarCpf(id);
                }
            },

            escolherCliente(c) {
                const id = this.busca.id;

                if (id === null) {
                    return;
                }

                const e = this.entrada(id);
                const nome = this.inputNome(id);
                const cpf = this.inputCpf(id);

                e.person_id = c.id;
                e.nome = c.nome;

                if (nome) {
                    nome.value = c.nome;
                }

                if (c.cpf) {
                    e.cpf = digitos(c.cpf);
                    e.erro = null;

                    if (cpf) {
                        cpf.value = c.cpf;
                    }
                }

                this.fecharBusca();

                if (! c.cpf) {
                    this.focarCpf(id);
                }
            },

            focarCpf(id) {
                const cpf = this.inputCpf(id);

                if (cpf) {
                    cpf.removeAttribute('readonly');
                    cpf.focus();
                    cpf.select();
                }
            },

            digitarCpf(id, el) {
                const e = this.entrada(id);
                const d = digitos(el.value);

                if (d.length <= 11) {
                    el.value = mascaraCpf(d);
                }

                e.cpf = d;
                e.erro = erroDoCpf(d, false);
            },

            async confirmarCpf(id, el) {
                const e = this.entrada(id);
                e.cpf = digitos(el.value);
                e.erro = erroDoCpf(e.cpf, true);

                if (e.erro || e.cpf === '') {
                    return;
                }

                let r = null;

                try {
                    r = await this.$wire.consultarCpf(e.cpf);
                } catch (err) {
                    return;
                }

                if (! r || digitos(this.inputCpf(id)?.value) !== e.cpf) {
                    return;
                }

                e.erro = r.erro || null;

                if (r.cliente) {
                    e.person_id = r.cliente.id;
                    e.nome = r.cliente.nome;

                    const nome = this.inputNome(id);
                    if (nome) {
                        nome.value = r.cliente.nome;
                    }
                } else {
                    e.person_id = null;
                }
            },

            /** Só vai ao servidor o consumidor das vendas editadas; as demais mantêm o cliente da venda. */
            consumidoresDe(ids) {
                const mapa = {};

                ids.forEach((id) => {
                    const e = this.ident[id];

                    if (e) {
                        mapa[id] = { person_id: e.person_id, nome: String(e.nome || '').trim().toUpperCase(), cpf: e.cpf };
                    }
                });

                return mapa;
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

                const invalidas = ids.filter((id) => {
                    const e = this.ident[id];

                    if (e) {
                        e.erro = erroDoCpf(e.cpf, true)
                            || (e.cpf === '' && String(e.nome || '').trim() !== ''
                                ? 'Informe o CPF do consumidor (NFC-e não identifica só pelo nome).'
                                : null);
                    }

                    return e && e.erro;
                });

                if (invalidas.length > 0) {
                    this.bloqueio = 'Corrija o CPF destacado em vermelho antes de transmitir ('
                        + this.ident[invalidas[0]].erro + ')';

                    return;
                }

                const consumidores = this.consumidoresDe(ids);

                this.bloqueio = null;
                this.resultados = [];
                this.fase = 'validando';

                let validacao;

                try {
                    validacao = await this.$wire.validarSelecionadas(ids, consumidores);
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
                        r = await this.$wire.emitirVenda(apta.id, consumidores[apta.id] || null);
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
