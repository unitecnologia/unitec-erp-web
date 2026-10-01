{{-- Reserva o espaço final dos widgets pesados enquanto o wire:init carrega. --}}
<section class="erp-dash__gauges-row erp-dash__gauges-row--no-sellers" aria-hidden="true">
    <div class="erp-dash__gauges erp-dash__gauges--n4">
        @for ($i = 0; $i < 4; $i++)
            <article class="erp-dash-gauge">
                <header class="erp-dash-gauge__head">
                    <h2 class="erp-dash-gauge__title">&nbsp;</h2>
                </header>
                <div class="erp-dash-gauge__body">
                    <div class="erp-dash-gauge__meter"></div>
                </div>
            </article>
        @endfor
    </div>
</section>

<div class="erp-dash__layout">
    <div class="erp-dash__main">
        <section class="erp-dash__charts">
            <article class="erp-dash-panel erp-dash-panel--chart">
                <header class="erp-dash-panel__head">
                    <h2 class="erp-dash-panel__title">Vendas por período</h2>
                </header>
                <div class="erp-dash-panel__body erp-dash-panel__body--chart">
                    <div class="erp-dash__skel-block"></div>
                </div>
            </article>
            <article class="erp-dash-panel erp-dash-panel--chart">
                <header class="erp-dash-panel__head">
                    <h2 class="erp-dash-panel__title">Entradas x saídas</h2>
                </header>
                <div class="erp-dash-panel__body erp-dash-panel__body--chart">
                    <div class="erp-dash__skel-block"></div>
                </div>
            </article>
            <div class="erp-dash__pies">
                <article class="erp-dash-panel erp-dash-panel--chart erp-dash-panel--pie">
                    <header class="erp-dash-panel__head">
                        <h2 class="erp-dash-panel__title">Mix do mês</h2>
                    </header>
                    <div class="erp-dash-panel__body erp-dash-panel__body--chart erp-dash-panel__body--pie">
                        <div class="erp-dash__skel-block"></div>
                    </div>
                </article>
                <article class="erp-dash-panel erp-dash-panel--chart erp-dash-panel--pie">
                    <header class="erp-dash-panel__head">
                        <h2 class="erp-dash-panel__title">Docs. eletrônicos</h2>
                    </header>
                    <div class="erp-dash-panel__body erp-dash-panel__body--chart erp-dash-panel__body--pie">
                        <div class="erp-dash__skel-block"></div>
                    </div>
                </article>
                <article class="erp-dash-panel erp-dash-panel--chart erp-dash-panel--pie">
                    <header class="erp-dash-panel__head">
                        <h2 class="erp-dash-panel__title">Meios de pagamento</h2>
                    </header>
                    <div class="erp-dash-panel__body erp-dash-panel__body--chart erp-dash-panel__body--pie">
                        <div class="erp-dash__skel-block"></div>
                    </div>
                </article>
            </div>
        </section>
        <div class="erp-dash__sales-row">
            <article class="erp-dash-panel erp-dash__sales">
                <header class="erp-dash-panel__head">
                    <h2 class="erp-dash-panel__title">Últimas vendas</h2>
                </header>
                <div class="erp-dash-panel__body">
                    <div class="erp-dash__skel-block"></div>
                </div>
            </article>
            <article class="erp-dash-panel erp-dash__highlights">
                <header class="erp-dash-panel__head">
                    <h2 class="erp-dash-panel__title">Destaques do mês</h2>
                </header>
                <div class="erp-dash-panel__body">
                    <div class="erp-dash__skel-block"></div>
                </div>
            </article>
        </div>
    </div>
    <aside class="erp-dash__aside" aria-hidden="true">
        <article class="erp-dash-panel erp-dash-panel--alerts">
            <header class="erp-dash-panel__head">
                <h2 class="erp-dash-panel__title">Alertas importantes</h2>
            </header>
            <div class="erp-dash-panel__body">
                <div class="erp-dash__skel-block"></div>
            </div>
        </article>
        <article class="erp-dash-panel">
            <header class="erp-dash-panel__head">
                <h2 class="erp-dash-panel__title">Contas a pagar vencidas</h2>
            </header>
            <div class="erp-dash-panel__body">
                <div class="erp-dash__skel-block"></div>
            </div>
        </article>
        <article class="erp-dash-panel">
            <header class="erp-dash-panel__head">
                <h2 class="erp-dash-panel__title">Estoque mínimo</h2>
            </header>
            <div class="erp-dash-panel__body">
                <div class="erp-dash__skel-block"></div>
            </div>
        </article>
    </aside>
</div>
