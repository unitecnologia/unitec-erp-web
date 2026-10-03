            (function () {
                if (window.__erpParcelasUi === 'v5') {
                    return;
                }
                window.__erpParcelasUi = 'v5';

                function modalAtual() {
                    return document.querySelector('.erp-compras-parcelas-modal');
                }

                function apiDe(origem) {
                    return origem || null;
                }

                function wireDe(modal) {
                    const root = modal ? modal.closest('[wire\\:id]') : null;
                    const id = root ? root.getAttribute('wire:id') : '';

                    return id && window.Livewire ? apiDe(window.Livewire.find(id)) : null;
                }

                function formasCaixa(modal) {
                    try {
                        const lista = JSON.parse(modal.getAttribute('data-erp-formas-caixa') || '[]');

                        return Array.isArray(lista) ? lista.map(Number) : [];
                    } catch (e) {
                        return [];
                    }
                }

                function caixasPadrao(modal) {
                    try {
                        const mapa = JSON.parse(modal.getAttribute('data-erp-caixa-padrao') || '{}');

                        return mapa && typeof mapa === 'object' ? mapa : {};
                    } catch (e) {
                        return {};
                    }
                }

                function formaExigeCaixa(forma) {
                    if (! forma || forma.selectedIndex < 0) {
                        return false;
                    }

                    const opt = forma.options[forma.selectedIndex];
                    const tipo = String((opt && opt.getAttribute('data-erp-tipo')) || '').toLowerCase();

                    if (tipo === 'dinheiro' || tipo === 'pix') {
                        return true;
                    }

                    if (tipo === 'boleto') {
                        return false;
                    }

                    const rotulo = String((opt && opt.textContent) || '').toUpperCase();

                    return rotulo.indexOf('DINHEIRO') !== -1 || rotulo.indexOf('PIX') !== -1;
                }

                function dataHojeBr() {
                    const hoje = new Date();
                    const dd = String(hoje.getDate()).padStart(2, '0');
                    const mm = String(hoje.getMonth() + 1).padStart(2, '0');

                    return dd + '/' + mm + '/' + hoje.getFullYear();
                }

                function aplicarVencimentoAVista(tr, forma) {
                    if (! tr || ! formaEhAVista(forma)) {
                        return;
                    }

                    const vencimento = tr.querySelector('[data-erp-parcela-field="vencimento"]');

                    if (vencimento) {
                        vencimento.value = dataHojeBr();
                    }
                }

                function formaEhAVista(forma) {
                    return formaExigeCaixa(forma);
                }

                function atualizarCaixa(tr, modal) {
                    if (! tr) {
                        return;
                    }

                    const forma = tr.querySelector('[data-erp-parcela-field="forma"]');
                    const caixa = tr.querySelector('[data-erp-parcela-field="caixa"]');
                    const vazio = tr.querySelector('[data-erp-parcela-caixa-vazio]');

                    if (! forma || ! caixa) {
                        return;
                    }

                    const exige = formaExigeCaixa(forma);
                    caixa.hidden = ! exige;
                    caixa.disabled = ! exige;

                    if (exige) {
                        caixa.removeAttribute('hidden');
                        caixa.removeAttribute('disabled');
                    }

                    if (vazio) {
                        vazio.hidden = exige;
                    }

                    if (! exige) {
                        caixa.value = '';

                        return;
                    }

                    if (! caixa.value) {
                        const padrao = caixasPadrao(modal)[String(forma.value)];

                        if (padrao) {
                            caixa.value = String(padrao);
                        }
                    }
                }

                function lerTela(modal) {
                    const rows = [];

                    modal.querySelectorAll('tbody tr[data-erp-parcela-index]').forEach(function (tr) {
                        const campo = function (nome) {
                            return tr.querySelector('[data-erp-parcela-field="' + nome + '"]');
                        };
                        const forma = campo('forma');
                        const caixa = campo('caixa');
                        const exigeCaixa = formaExigeCaixa(forma);

                        rows.push({
                            index: Number(tr.getAttribute('data-erp-parcela-index')),
                            documento: campo('documento') ? campo('documento').value : '',
                            vencimento: campo('vencimento') ? campo('vencimento').value : '',
                            forma_pagamento_id: forma ? forma.value : '',
                            caixa_conta_id: exigeCaixa && caixa ? caixa.value : '',
                            valor: campo('valor') ? campo('valor').value : '',
                        });
                    });

                    const selecionada = modal.querySelector('tbody tr.is-selected');

                    return {
                        subtotal: (modal.querySelector('#erp-parcela-subtotal') || {}).value || '',
                        entrada: (modal.querySelector('#erp-parcela-entrada') || {}).value || '',
                        qtd: (modal.querySelector('#erp-parcela-qtd') || {}).value || '',
                        intervalo: (modal.querySelector('#erp-parcela-intervalo') || {}).value || '',
                        selecionada: selecionada ? Number(selecionada.getAttribute('data-erp-parcela-index')) : null,
                        rows: rows,
                    };
                }

                function marcar(modal) {
                    modal.querySelectorAll('[data-erp-parcela-base], [data-erp-parcela-field="valor"]').forEach(function (el) {
                        if (el.dataset.erpLast === undefined) {
                            el.dataset.erpLast = el.value;
                        }
                    });
                }

                let geracao = 0;
                let valorSeq = 0;
                let valorApplied = 0;
                let baseSeq = 0;
                let baseApplied = 0;

                function pintarBases(data, seq) {
                    if (seq < baseApplied || ! data) {
                        return;
                    }

                    baseApplied = seq;
                    const modal = modalAtual();

                    if (! modal) {
                        return;
                    }

                    ['subtotal', 'entrada'].forEach(function (nome) {
                        const el = modal.querySelector('#erp-parcela-' + nome);

                        if (! el || document.activeElement === el) {
                            if (el) {
                                el.dataset.erpLast = el.value;
                            }

                            return;
                        }

                        el.value = data[nome] ?? el.value;
                        el.dataset.erpLast = el.value;
                    });

                    const total = modal.querySelector('#erp-parcela-total');

                    if (total && data.total !== undefined) {
                        total.value = data.total;
                    }
                }

                function enviarBases(modal, wire) {
                    const sub = modal.querySelector('#erp-parcela-subtotal');
                    const ent = modal.querySelector('#erp-parcela-entrada');

                    if (! sub || ! ent) {
                        return;
                    }

                    if (sub.dataset.erpLast === sub.value && ent.dataset.erpLast === ent.value) {
                        return;
                    }

                    const seq = ++baseSeq;
                    const gen = geracao;

                    Promise.resolve(wire.call('atualizarBasesLancamentoParcelas', lerTela(modal))).then(function (data) {
                        if (gen !== geracao) {
                            return;
                        }

                        pintarBases(data, seq);
                    }).catch(function () {});
                }

                function enviarValor(modal, wire, input) {
                    if (! input || input.dataset.erpLast === input.value) {
                        return;
                    }

                    const seq = ++valorSeq;
                    const gen = geracao;
                    const index = Number(input.getAttribute('data-erp-parcela-index'));

                    Promise.resolve(wire.call('atualizarValorLancamentoParcela', index, lerTela(modal))).then(function (data) {
                        if (gen !== geracao || seq < valorApplied || ! data || ! data.valores) {
                            return;
                        }

                        valorApplied = seq;
                        const atual = modalAtual();

                        if (! atual) {
                            return;
                        }

                        Object.keys(data.valores).forEach(function (i) {
                            const el = atual.querySelector('tr[data-erp-parcela-index="' + i + '"] [data-erp-parcela-field="valor"]');

                            if (! el || document.activeElement === el) {
                                return;
                            }

                            el.value = data.valores[i];
                            el.dataset.erpLast = el.value;
                        });

                        const totalParcelas = atual.querySelector('#erp-parcela-total-parcelas');

                        if (totalParcelas && data.total_parcelas !== undefined) {
                            totalParcelas.textContent = data.total_parcelas;
                        }
                    }).catch(function () {});
                }

                function focarProximo(modal, field) {
                    const fields = Array.from(modal.querySelectorAll('[data-erp-parcela-field]:not(:disabled)'));
                    const next = fields[fields.indexOf(field) + 1];

                    if (! next) {
                        return;
                    }

                    next.removeAttribute('readonly');
                    next.focus();

                    if (next.tagName === 'INPUT' && typeof next.select === 'function') {
                        next.select();
                    }
                }

                window.erpParcelasAcao = function (wire, nome) {
                    const modal = modalAtual();
                    wire = apiDe(wire);

                    if (! modal || ! wire) {
                        return;
                    }

                    if (nome === 'concluir' && wire.get && wire.get('lancamentoFinalizando')) {
                        return;
                    }

                    geracao++;

                    if (nome === 'cancelar') {
                        wire.cancelarLancamentoParcelas();
                        return;
                    }

                    const tela = lerTela(modal);

                    if (nome === 'gerar') {
                        wire.gerarLancamentoParcelas(tela);
                    }

                    if (nome === 'excluir') {
                        wire.excluirLancamentoParcelaSelecionada(tela);
                    }

                    if (nome === 'concluir') {
                        wire.concluirLancamentoParcelas(tela);
                    }
                };

                window.erpParcelasBind = function (modal) {
                    if (! modal) {
                        return;
                    }

                    marcar(modal);
                    modal.querySelectorAll('tbody tr[data-erp-parcela-index]').forEach(function (tr) {
                        atualizarCaixa(tr, modal);
                    });
                };

                document.addEventListener('keydown', function (event) {
                    const modal = modalAtual();

                    if (! modal) {
                        return;
                    }

                    const wire = wireDe(modal);

                    if (! wire) {
                        return;
                    }

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        event.stopPropagation();
                        window.erpParcelasAcao(wire, 'cancelar');
                        return;
                    }

                    if ((event.key === 'F2' || event.key === 'F3' || event.key === 'F4' || event.key === 'F5') && ! event.repeat) {
                        event.preventDefault();
                        event.stopPropagation();
                        const mapa = { F2: 'gerar', F3: 'excluir', F4: 'cancelar', F5: 'concluir' };
                        window.erpParcelasAcao(wire, mapa[event.key]);
                        return;
                    }

                    if (event.key !== 'Enter' && event.key !== 'NumpadEnter') {
                        return;
                    }

                    const field = event.target;

                    if (! (field instanceof HTMLElement) || ! modal.contains(field) || ! field.matches('[data-erp-parcela-field]')) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    field.removeAttribute('readonly');

                    if (field.dataset.erpParcelaField === 'valor') {
                        field.dataset.erpSkipBlur = '1';
                        enviarValor(modal, wire, field);
                    }

                    focarProximo(modal, field);
                }, true);

                document.addEventListener('focusout', function (event) {
                    const modal = modalAtual();
                    const field = event.target;

                    if (! modal || ! (field instanceof HTMLElement) || ! modal.contains(field)) {
                        return;
                    }

                    if (field.dataset.erpSkipBlur === '1') {
                        delete field.dataset.erpSkipBlur;
                        return;
                    }

                    const wire = wireDe(modal);

                    if (! wire) {
                        return;
                    }

                    if (field.dataset.erpParcelaBase === 'subtotal' || field.dataset.erpParcelaBase === 'entrada') {
                        enviarBases(modal, wire);
                        return;
                    }

                    if (field.dataset.erpParcelaField === 'valor') {
                        enviarValor(modal, wire, field);
                    }
                }, true);

                document.addEventListener('change', function (event) {
                    const modal = modalAtual();
                    const field = event.target;

                    if (! modal || ! (field instanceof HTMLElement) || ! modal.contains(field)) {
                        return;
                    }

                    if (field.dataset.erpParcelaField === 'forma') {
                        const tr = field.closest('tr');
                        atualizarCaixa(tr, modal);
                        aplicarVencimentoAVista(tr, field);
                    }
                }, true);

                document.addEventListener('click', function (event) {
                    const modal = modalAtual();
                    const alvo = event.target instanceof Element ? event.target : null;
                    const tr = alvo ? alvo.closest('tbody tr[data-erp-parcela-index]') : null;

                    if (! modal || ! tr || ! modal.contains(tr)) {
                        return;
                    }

                    modal.querySelectorAll('tbody tr.is-selected').forEach(function (row) {
                        row.classList.remove('is-selected');
                    });
                    tr.classList.add('is-selected');
                });

                function ligarMorph() {
                    if (! window.Livewire || window.__erpParcelasMorph || typeof window.Livewire.hook !== 'function') {
                        return;
                    }

                    window.__erpParcelasMorph = true;
                    window.Livewire.hook('morph.updated', function () {
                        const modal = modalAtual();

                        if (modal) {
                            marcar(modal);
                        }
                    });
                }

                ligarMorph();
                document.addEventListener('livewire:init', ligarMorph);

                const aberto = modalAtual();

                if (aberto) {
                    marcar(aberto);
                }
            })();
