{{--
    Ajuda informativa: códigos de origem da mercadoria (0–8) — Tabela A / MOC NF-e.
    Sem Livewire / sem requisição ao servidor. Uso: produto Impostos e Imposto Padrão.
    Painel em position:fixed para não ser cortado por overflow dos containers.
--}}
@php
    $origensAjuda = [
        0 => 'Nacional, exceto as indicadas nos códigos 3, 4, 5 e 8',
        1 => 'Estrangeira — Importação direta, exceto a indicada no código 6',
        2 => 'Estrangeira — Adquirida no mercado interno, exceto a indicada no código 7',
        3 => 'Nacional, mercadoria ou bem com Conteúdo de Importação superior a 40% e inferior ou igual a 70%',
        4 => 'Nacional, cuja produção tenha sido feita em conformidade com os processos produtivos básicos de que tratam as legislações das Zonas de Processamento de Exportação',
        5 => 'Nacional, mercadoria ou bem com Conteúdo de Importação inferior ou igual a 40%',
        6 => 'Estrangeira — Importação direta, sem similar nacional, constante em lista da CAMEX e gás natural',
        7 => 'Estrangeira — Adquirida no mercado interno, sem similar nacional, constante em lista da CAMEX e gás natural',
        8 => 'Nacional, mercadoria ou bem com Conteúdo de Importação superior a 70%',
    ];
@endphp
<span
    class="erp-origem-help"
    x-data="{
        open: false,
        top: 0,
        left: 0,
        place() {
            const btn = this.$refs.btn;
            const panel = this.$refs.panel;
            if (! btn || ! panel) return;
            const r = btn.getBoundingClientRect();
            const pad = 8;
            const panelW = panel.offsetWidth || Math.min(448, window.innerWidth * 0.92);
            const panelH = panel.offsetHeight || 0;
            // Preferir abrir à direita do ícone; se não couber, à esquerda.
            let left = r.right + 6;
            if (left + panelW > window.innerWidth - pad) {
                left = Math.max(pad, r.left - panelW - 6);
            }
            left = Math.min(left, window.innerWidth - panelW - pad);
            left = Math.max(pad, left);
            // Centralizar verticalmente em relação ao ícone, mantendo tudo na tela (sem scroll).
            let top = r.top + (r.height / 2) - (panelH / 2);
            top = Math.min(top, window.innerHeight - panelH - pad);
            top = Math.max(pad, top);
            this.top = top;
            this.left = left;
        },
        toggle() {
            this.open = ! this.open;
            if (this.open) {
                this.$nextTick(() => {
                    this.place();
                    this.$nextTick(() => this.place());
                });
            }
        },
    }"
    @keydown.escape.window="open = false"
    @resize.window="if (open) place()"
    @scroll.window.capture="if (open) place()"
>
    <button
        type="button"
        class="erp-origem-help__btn"
        x-ref="btn"
        @mousedown.stop
        @click.stop="toggle()"
        :aria-expanded="open.toString()"
        aria-label="Ajuda: códigos de origem da mercadoria"
        title="Códigos de origem da mercadoria (0 a 8)"
    >?</button>
    <template x-teleport="body">
        <div
            class="erp-origem-help__panel"
            x-ref="panel"
            x-show="open"
            x-cloak
            x-transition.opacity.duration.100ms
            role="dialog"
            aria-label="Códigos de origem da mercadoria"
            :style="`top:${top}px;left:${left}px`"
            @click.stop
            @mousedown.outside="open = false"
        >
            <div class="erp-origem-help__head">
                <strong>Origem da mercadoria</strong>
                <button type="button" class="erp-origem-help__close" @click="open = false" aria-label="Fechar">×</button>
            </div>
            <p class="erp-origem-help__intro">Tabela oficial (códigos 0 a 8) — apenas consulta.</p>
            <table class="erp-origem-help__table">
                <thead>
                    <tr>
                        <th scope="col">Cód.</th>
                        <th scope="col">Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($origensAjuda as $cod => $desc)
                        <tr>
                            <td class="erp-origem-help__cod">{{ $cod }}</td>
                            <td>{{ $desc }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </template>
</span>
