/**
 * Ctrl+D — modal compartilhado (Desconto / Acréscimo).
 * % e R$ sempre visíveis e sincronizados; último campo editado manda no Aplicar/Enter.
 */
(function () {
  'use strict';

  let applying = false;
  let activeApi = null;

  function parseBr(v) {
    let s = String(v ?? '').trim();
    if (!s) return 0;
    s = s.replace(/[^\d.,-]/g, '');
    if (s.indexOf(',') >= 0) {
      s = s.replace(/\./g, '').replace(',', '.');
    }
    const n = Number.parseFloat(s);
    return Number.isFinite(n) ? n : 0;
  }

  function formatBr(n, digits) {
    const d = digits == null ? 2 : digits;
    const v = Number.isFinite(n) ? n : 0;
    return v.toLocaleString('pt-BR', {
      minimumFractionDigits: d,
      maximumFractionDigits: d,
    });
  }

  function calcAjuste(base, qtd, valor, tipo, modoEntrada, modoReais) {
    base = Math.max(0, Number(base) || 0);
    qtd = Math.max(0, Number(qtd) || 0);
    valor = Math.max(0, Number(valor) || 0);
    const ehAcrescimo = tipo === 'acrescimo';
    let deltaUnit = 0;
    let ajusteLinha = 0;

    if (modoEntrada === 'percentual') {
      deltaUnit = Math.round(base * (valor / 100) * 100) / 100;
      ajusteLinha = Math.round(deltaUnit * qtd * 100) / 100;
    } else if (modoReais === 'linha') {
      ajusteLinha = valor;
      deltaUnit = qtd > 0 ? Math.round((ajusteLinha / qtd) * 10000) / 10000 : valor;
    } else {
      deltaUnit = valor;
      ajusteLinha = Math.round(deltaUnit * qtd * 100) / 100;
    }

    let novoPreco = ehAcrescimo
      ? Math.round((base + deltaUnit) * 100) / 100
      : Math.round((base - deltaUnit) * 100) / 100;
    if (novoPreco < 0) novoPreco = 0;

    const bruto = Math.round(qtd * base * 100) / 100;
    const total = ehAcrescimo
      ? Math.round((bruto + ajusteLinha) * 100) / 100
      : Math.round(Math.max(0, bruto - ajusteLinha) * 100) / 100;

    return { deltaUnit, ajusteLinha, novoPreco, total, bruto };
  }

  function findWire(el) {
    try {
      const root = el?.closest?.('[wire\\:id]') || document.querySelector('[wire\\:id]');
      if (root && window.Livewire) {
        return window.Livewire.find(root.getAttribute('wire:id'));
      }
    } catch (e) {
      /* ignore */
    }
    return null;
  }

  function invokeWire(wire, method, args) {
    if (!wire || !method) return false;
    try {
      if (typeof wire[method] === 'function') {
        wire[method](...args);
        return true;
      }
      if (typeof wire.call === 'function') {
        wire.call(method, ...args);
        return true;
      }
    } catch (e) {
      console.error('erpDescontoItemModal', e);
    }
    return false;
  }

  window.erpDescontoItemModalAplicar = function () {
    if (activeApi && typeof activeApi.aplicar === 'function') {
      activeApi.aplicar();
    }
  };

  /** Factory Alpine (não depende de alpine:init). */
  window.erpDescontoItemModalFactory = function (cfg) {
    cfg = cfg || {};

    return {
      tipo: cfg.tipo || 'desconto',
      modoReais: cfg.modoReais || 'unitario',
      base: Number(cfg.base) || 0,
      qtd: Number(cfg.qtd) || 0,
      pctRaw: String(cfg.pctInicial ?? '0,00'),
      reaisRaw: String(cfg.reaisInicial ?? '0,00'),
      lastField: cfg.lastField || 'percentual',
      confirmMethod: cfg.confirmMethod || 'confirmarItemAjuste',
      closeMethod: cfg.closeMethod || 'fecharModalDescontoItem',
      pctId: cfg.pctId || 'erp-desconto-pct',
      reaisId: cfg.reaisId || 'erp-desconto-reais',
      syncing: false,
      aplicando: false,

      init() {
        activeApi = this;
        this.$nextTick(() => {
          const pct = document.getElementById(this.pctId);
          if (pct) {
            pct.removeAttribute('readonly');
            try {
              pct.focus({ preventScroll: true });
            } catch (e) {
              pct.focus();
            }
            pct.select?.();
          }
        });
      },

      destroy() {
        if (activeApi === this) {
          activeApi = null;
        }
      },

      formatBr,
      parseBr,

      preview() {
        const modo = this.lastField === 'valor' ? 'valor' : 'percentual';
        const valor = modo === 'percentual' ? this.parseBr(this.pctRaw) : this.parseBr(this.reaisRaw);
        return calcAjuste(this.base, this.qtd, valor, this.tipo, modo, this.modoReais);
      },

      setTipo(tipo) {
        this.tipo = tipo === 'acrescimo' ? 'acrescimo' : 'desconto';
      },

      onPctInput() {
        if (this.syncing) return;
        this.lastField = 'percentual';
        this.syncFromPct();
      },

      onReaisInput() {
        if (this.syncing) return;
        this.lastField = 'valor';
        this.syncFromReais();
      },

      syncFromPct() {
        const pct = this.parseBr(this.pctRaw);
        const r = calcAjuste(this.base, this.qtd, pct, this.tipo, 'percentual', this.modoReais);
        this.syncing = true;
        this.reaisRaw = formatBr(r.ajusteLinha);
        this.syncing = false;
      },

      syncFromReais() {
        const reais = this.parseBr(this.reaisRaw);
        const bruto = Math.max(0, this.base * this.qtd);
        const pct = bruto > 0 ? (reais / bruto) * 100 : 0;
        this.syncing = true;
        this.pctRaw = formatBr(pct);
        this.syncing = false;
      },

      onEnter(e) {
        e?.preventDefault?.();
        e?.stopPropagation?.();
        this.aplicar();
      },

      fechar() {
        const wire = this.$wire || findWire(this.$el);
        invokeWire(wire, this.closeMethod, []);
      },

      aplicar() {
        if (this.aplicando || applying) {
          return;
        }
        this.aplicando = true;
        applying = true;

        const modo = this.lastField === 'valor' ? 'valor' : 'percentual';
        const valor = modo === 'percentual' ? String(this.pctRaw ?? '') : String(this.reaisRaw ?? '');
        const tipo = this.tipo === 'acrescimo' ? 'acrescimo' : 'desconto';

        if (this.$el) {
          this.$el.style.display = 'none';
          this.$el.setAttribute('aria-hidden', 'true');
        }

        const wire = this.$wire || findWire(this.$el);
        const ok = invokeWire(wire, this.confirmMethod, [valor, modo, tipo]);

        if (!ok) {
          this.aplicando = false;
          applying = false;
          if (this.$el) {
            this.$el.style.display = '';
            this.$el.removeAttribute('aria-hidden');
          }
          return;
        }

        setTimeout(() => {
          this.aplicando = false;
          applying = false;
          if (activeApi === this) {
            activeApi = null;
          }
        }, 1500);
      },
    };
  };
})();
