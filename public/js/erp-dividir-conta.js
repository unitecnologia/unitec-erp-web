/**
 * Stepper local de "Dividir a conta por" (PDV / Tela de Venda / OS).
 * Carregado no shell (sempre disponível) — não depende do erp-pdv.js sob demanda.
 */
(function (global) {
    'use strict';

    function parseMoney(raw) {
        if (typeof global.erpFvParseMoneyBr === 'function') {
            return global.erpFvParseMoneyBr(raw);
        }

        const s = String(raw ?? '').trim();
        if (! s) {
            return 0;
        }

        const neg = s.startsWith('-');
        const limpo = s.replace(/[^\d,.\-]/g, '').replace('-', '');
        if (! limpo) {
            return 0;
        }

        let n;
        if (limpo.includes(',')) {
            n = Number.parseFloat(limpo.replace(/\./g, '').replace(',', '.'));
        } else {
            n = Number.parseFloat(limpo);
        }

        if (! Number.isFinite(n)) {
            return 0;
        }

        return Math.round((neg ? -n : n) * 100) / 100;
    }

    /** Formatação fixa pt-BR (não usa ErpMasks — evita "0" / string vazia em números JS). */
    function formatMoney(value) {
        if (typeof global.erpFvFormatMoneyBr === 'function') {
            return global.erpFvFormatMoneyBr(value);
        }

        const n = Math.round((Number(value) || 0) * 100) / 100;
        const neg = n < 0;
        const abs = Math.abs(n);
        const [intPart, frac] = abs.toFixed(2).split('.');
        const intFmt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return (neg ? '-' : '') + intFmt + ',' + frac;
    }

    function factory(opts) {
        opts = opts || {};
        const wirePath = String(opts.wirePath || '');
        const initial = Math.max(1, Number.parseInt(String(opts.initial ?? '1'), 10) || 1);
        const initialTotal = Number(opts.total);
        const seedTotal = Number.isFinite(initialTotal) && initialTotal > 0 ? initialTotal : 0;

        return {
            pessoas: initial,
            totalValor: seedTotal,
            valorTexto: formatMoney(seedTotal > 0 ? seedTotal / initial : 0),
            _obs: null,
            _syncTimer: null,

            init() {
                this.refreshTotal(true);
                this.recalc();
                this.queueSyncWire();

                const totalEl = this.totalEl();
                if (totalEl && typeof MutationObserver !== 'undefined') {
                    this._obs = new MutationObserver(() => {
                        this.refreshTotal(false);
                        this.recalc();
                    });
                    this._obs.observe(totalEl, { characterData: true, childList: true, subtree: true });
                }

                if (this.$el && typeof MutationObserver !== 'undefined') {
                    this._attrObs = new MutationObserver(() => {
                        this.refreshTotal(false);
                        this.recalc();
                    });
                    this._attrObs.observe(this.$el, { attributes: true, attributeFilter: ['data-erp-dividir-total'] });
                }

                try {
                    if (global.Livewire && typeof global.Livewire.hook === 'function') {
                        global.Livewire.hook('morph.updated', ({ el }) => {
                            if (! this.$el || ! el) {
                                return;
                            }

                            if (el === this.$el || this.$el.contains(el) || el.contains?.(this.$el)) {
                                this.refreshTotal(false);
                                this.recalc();
                            }
                        });
                    }
                } catch (e) {
                    // observers cobrem o total.
                }
            },

            destroy() {
                this._obs?.disconnect?.();
                this._attrObs?.disconnect?.();
                this._obs = null;
                this._attrObs = null;
                if (this._syncTimer) {
                    global.clearTimeout(this._syncTimer);
                    this._syncTimer = null;
                }
            },

            totalEl() {
                return this.$el?.querySelector?.('[data-erp-dividir-total-src]') ?? null;
            },

            /**
             * Lê total do data-attr (preferencial) ou do texto do TOTAL.
             * Nunca zera um total já conhecido com leitura transitória vazia (morph).
             */
            refreshTotal(force) {
                let next = NaN;

                const attr = this.$el?.getAttribute?.('data-erp-dividir-total');
                if (attr != null && String(attr).trim() !== '') {
                    const n = Number(attr);
                    if (Number.isFinite(n)) {
                        next = n;
                    }
                }

                if (! Number.isFinite(next)) {
                    const fromDom = parseMoney(this.totalEl()?.textContent ?? '');
                    if (Number.isFinite(fromDom)) {
                        next = fromDom;
                    }
                }

                if (! Number.isFinite(next)) {
                    return;
                }

                if (force || next > 0 || !(this.totalValor > 0)) {
                    this.totalValor = Math.round(next * 100) / 100;
                }
            },

            normalizePessoas(value) {
                const n = Number.parseInt(String(value ?? ''), 10);

                return (! Number.isFinite(n) || n < 1) ? 1 : n;
            },

            recalc() {
                const pessoas = this.normalizePessoas(this.pessoas);
                this.pessoas = pessoas;
                const total = this.totalValor > 0 ? this.totalValor : 0;
                this.valorTexto = formatMoney(Math.round((total / pessoas) * 100) / 100);
            },

            bump(delta) {
                this.pessoas = this.normalizePessoas(this.pessoas) + (Number(delta) || 0);
                this.refreshTotal(false);
                this.recalc();
                this.queueSyncWire();
            },

            onInput() {
                const raw = String(this.pessoas ?? '').trim();
                if (raw === '') {
                    return;
                }

                this.refreshTotal(false);
                this.recalc();
                this.queueSyncWire();
            },

            onBlur() {
                this.pessoas = this.normalizePessoas(this.pessoas);
                this.refreshTotal(false);
                this.recalc();
                this.syncWireNow();
            },

            queueSyncWire() {
                if (this._syncTimer) {
                    global.clearTimeout(this._syncTimer);
                }

                this._syncTimer = global.setTimeout(() => {
                    this._syncTimer = null;
                    this.syncWireNow();
                }, 120);
            },

            syncWireNow() {
                if (! wirePath || ! this.$wire) {
                    return;
                }

                const val = String(this.normalizePessoas(this.pessoas));

                try {
                    // 3º arg false = sem re-render (evita morph que zera o TOTAL momentaneamente).
                    if (typeof this.$wire.$set === 'function') {
                        this.$wire.$set(wirePath, val, false);

                        return;
                    }

                    if (typeof this.$wire.set === 'function') {
                        this.$wire.set(wirePath, val, false);
                    }
                } catch (e) {
                    // Sync best-effort.
                }
            },
        };
    }

    global.erpDividirContaStepper = factory;

    function registerAlpine() {
        if (! global.Alpine || typeof global.Alpine.data !== 'function') {
            return;
        }

        global.Alpine.data('erpDividirContaStepper', factory);
    }

    if (global.Alpine) {
        registerAlpine();
    }

    document.addEventListener('alpine:init', registerAlpine);
})(window);
