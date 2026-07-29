<div
    class="erp-comando erp-migra-fb"
    wire:ignore.self
    x-data="{
        running: false,
        async runMigra(dryRun) {
            if (this.running) return;
            if (! dryRun && ! confirm('Confirma migrar os dados do Firebird para o ERP web?')) {
                return;
            }
            this.running = true;
            try {
                const prep = await $wire.prepararMigracao(dryRun);
                if (! prep || ! prep.ok) {
                    return;
                }
                const steps = prep.steps || [];
                const total = steps.length;
                for (let i = 0; i < total; i++) {
                    const step = steps[i];
                    const base = Math.round((i / total) * 100);
                    const span = Math.round((1 / total) * 100);
                    await $wire.atualizarProgresso(base, (dryRun ? 'Simulando' : 'Migrando') + ': ' + step.label + '…', (i + 1) + ' / ' + total + ' — ' + step.label);

                    if (step.key === 'produtos') {
                        let skip = 0;
                        let lote = 0;
                        while (true) {
                            lote++;
                            const mid = base + Math.min(span - 1, Math.round(span * Math.min(lote / 14, 0.95)));
                            await $wire.atualizarProgresso(mid, (dryRun ? 'Simulando' : 'Migrando') + ': Produtos (lote ' + lote + ')…', (i + 1) + ' / ' + total + ' — Produtos lote ' + lote);
                            const loteRes = await $wire.executarLoteProdutos(skip);
                            if (! loteRes || ! loteRes.ok) {
                                return;
                            }
                            if (loteRes.done) {
                                break;
                            }
                            skip = loteRes.next_skip;
                        }
                    } else if (step.key === 'pdv_nfce') {
                        let skip = 0;
                        let totalNfce = 0;
                        let lote = 0;
                        await $wire.atualizarProgresso(
                            Math.min(99, base + 1),
                            (dryRun ? 'Simulando' : 'Migrando') + ': NFC-e PDV…',
                            (i + 1) + ' / ' + total + ' — NFC-e iniciando…'
                        );
                        while (true) {
                            lote++;
                            const loteRes = await $wire.executarLotePdvNfce(skip);
                            if (! loteRes || ! loteRes.ok) {
                                return;
                            }
                            if (loteRes.total && loteRes.total > 0) {
                                totalNfce = loteRes.total;
                            }
                            const processed = loteRes.processed || (skip + (loteRes.fetched || 0));
                            let frac = 0.02;
                            if (loteRes.done) {
                                frac = 1;
                            } else if (totalNfce > 0) {
                                frac = Math.min(0.99, processed / totalNfce);
                            } else {
                                frac = Math.min(0.95, lote / 50);
                            }
                            const mid = base + Math.max(1, Math.min(span - 1, Math.round(span * frac)));
                            const detail = totalNfce > 0
                                ? ((i + 1) + ' / ' + total + ' — NFC-e ' + Math.min(processed, totalNfce) + ' / ' + totalNfce)
                                : ((i + 1) + ' / ' + total + ' — NFC-e lote ' + lote);
                            await $wire.atualizarProgresso(
                                mid,
                                (dryRun ? 'Simulando' : 'Migrando') + ': NFC-e PDV…',
                                detail
                            );
                            if (loteRes.done) {
                                break;
                            }
                            skip = loteRes.next_skip;
                        }
                    } else if (step.key === 'nfes') {
                        let skip = 0;
                        let totalNfe = 0;
                        let lote = 0;
                        await $wire.atualizarProgresso(
                            Math.min(99, base + 1),
                            (dryRun ? 'Simulando' : 'Migrando') + ': NF-e…',
                            (i + 1) + ' / ' + total + ' — NF-e iniciando…'
                        );
                        while (true) {
                            lote++;
                            const loteRes = await $wire.executarLoteNfes(skip);
                            if (! loteRes || ! loteRes.ok) {
                                return;
                            }
                            if (loteRes.total && loteRes.total > 0) {
                                totalNfe = loteRes.total;
                            }
                            const processed = loteRes.processed || (skip + (loteRes.fetched || 0));
                            let frac = 0.02;
                            if (loteRes.done) {
                                frac = 1;
                            } else if (totalNfe > 0) {
                                frac = Math.min(0.99, processed / totalNfe);
                            } else {
                                frac = Math.min(0.95, lote / 50);
                            }
                            const mid = base + Math.max(1, Math.min(span - 1, Math.round(span * frac)));
                            const detail = totalNfe > 0
                                ? ((i + 1) + ' / ' + total + ' — NF-e ' + Math.min(processed, totalNfe) + ' / ' + totalNfe)
                                : ((i + 1) + ' / ' + total + ' — NF-e lote ' + lote);
                            await $wire.atualizarProgresso(
                                mid,
                                (dryRun ? 'Simulando' : 'Migrando') + ': NF-e…',
                                detail
                            );
                            if (loteRes.done) {
                                break;
                            }
                            skip = loteRes.next_skip;
                        }
                    } else {
                        const res = await $wire.executarPasso(step.key);
                        if (! res || ! res.ok) {
                            return;
                        }
                    }

                    await $wire.atualizarProgresso(
                        Math.round(((i + 1) / total) * 100),
                        (dryRun ? 'Simulando' : 'Migrando') + ': ' + step.label + ' OK',
                        (i + 1) + ' / ' + total + ' etapas'
                    );
                }
                await $wire.finalizarMigracao();
            } catch (e) {
                console.error(e);
            } finally {
                this.running = false;
            }
        }
    }"
>
    <div class="erp-migra-fb__panel">
        <header class="erp-migra-fb__head">
            <h2 class="erp-migra-fb__title">Migra dados FB</h2>
            <p class="erp-migra-fb__subtitle">
                Importa do Firebird legado para o ERP web (Fase 1 + contas a pagar).
                Produtos (~1.300) demoram; desmarque se não precisar reimportar.
            </p>
        </header>

        <section class="erp-migra-fb__section">
            <h3 class="erp-migra-fb__section-title">Conexão Firebird</h3>
            <div class="erp-migra-fb__grid">
                <label class="erp-migra-fb__field erp-migra-fb__field--full">
                    <span>Arquivo .fdb</span>
                    <input type="text" wire:model="database" class="erp-migra-fb__input" autocomplete="off" :disabled="running || $wire.progressActive">
                </label>
                <label class="erp-migra-fb__field">
                    <span>Usuário</span>
                    <input type="text" wire:model="username" class="erp-migra-fb__input" autocomplete="off" :disabled="running || $wire.progressActive">
                </label>
                <label class="erp-migra-fb__field">
                    <span>Senha</span>
                    <div class="erp-migra-fb__password" x-data="{ show: false }">
                        <input
                            :type="show ? 'text' : 'password'"
                            wire:model="password"
                            class="erp-migra-fb__input"
                            autocomplete="current-password"
                            placeholder="masterkey"
                            :disabled="running || $wire.progressActive"
                        >
                        <button
                            type="button"
                            class="erp-migra-fb__eye"
                            @click="show = ! show"
                            :title="show ? 'Ocultar senha' : 'Mostrar senha'"
                            :aria-label="show ? 'Ocultar senha' : 'Mostrar senha'"
                        >
                            <svg x-show="! show" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg x-cloak x-show="show" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </label>
                <label class="erp-migra-fb__field">
                    <span>Host</span>
                    <input type="text" wire:model="host" class="erp-migra-fb__input" autocomplete="off" :disabled="running || $wire.progressActive">
                </label>
                <label class="erp-migra-fb__field">
                    <span>Porta</span>
                    <input type="text" wire:model="port" class="erp-migra-fb__input" autocomplete="off" :disabled="running || $wire.progressActive">
                </label>
            </div>
            <p class="erp-migra-fb__hint">
                Senha padrão do Firebird legado: <code>masterkey</code>. Pode alterar se a base usar outra.
            </p>
        </section>

        <section class="erp-migra-fb__section">
            <h3 class="erp-migra-fb__section-title">O que migrar</h3>
            <div class="erp-migra-fb__checks">
                <div class="erp-migra-fb__check-group">
                    <div class="erp-migra-fb__check-group-title">Cadastros</div>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optEmpresa" :disabled="running || $wire.progressActive">
                        <span>Empresa</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optAuxiliares" :disabled="running || $wire.progressActive">
                        <span>Grupos / Marcas / Unidades</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optProdutos" :disabled="running || $wire.progressActive">
                        <span>Produtos + estoque</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optClientes" :disabled="running || $wire.progressActive">
                        <span>Pessoas (clientes, fornecedores…)</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optFormas" :disabled="running || $wire.progressActive">
                        <span>Formas de pagamento</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optVendedores" :disabled="running || $wire.progressActive">
                        <span>Vendedores</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optUsuarios" :disabled="running || $wire.progressActive">
                        <span>Usuários</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optContador" :disabled="running || $wire.progressActive">
                        <span>Contador</span>
                    </label>
                </div>

                <div class="erp-migra-fb__check-group">
                    <div class="erp-migra-fb__check-group-title">Financeiro</div>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optContas" :disabled="running || $wire.progressActive">
                        <span>Contas caixa</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optPlanosContas" :disabled="running || $wire.progressActive">
                        <span>Plano de contas</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optContasPagar" :disabled="running || $wire.progressActive">
                        <span>Contas a pagar</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optContaPagarPagamentos" :disabled="running || $wire.progressActive">
                        <span>Baixas contas a pagar</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optContasReceber" :disabled="running || $wire.progressActive">
                        <span>Contas a receber</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optCaixa" :disabled="running || $wire.progressActive">
                        <span>Caixa (lançamentos)</span>
                    </label>
                </div>

                <div class="erp-migra-fb__check-group">
                    <div class="erp-migra-fb__check-group-title">PDV / Fiscal</div>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optTerminais" :disabled="running || $wire.progressActive">
                        <span>Terminais / PDV</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optUltimosPrecos" :disabled="running || $wire.progressActive">
                        <span>Últimos preços produtos</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optVendasParametros" :disabled="running || $wire.progressActive">
                        <span>Parâmetros fiscais</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optPdvVendas" :disabled="running || $wire.progressActive">
                        <span>Vendas PDV</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optPdvNfce" :disabled="running || $wire.progressActive">
                        <span>NFC-e PDV</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optNfes" :disabled="running || $wire.progressActive">
                        <span>NF-e (modelo 55)</span>
                    </label>
                    <label class="erp-migra-fb__check">
                        <input type="checkbox" wire:model="optPdvCaixaMovimentos" :disabled="running || $wire.progressActive">
                        <span>Movimentos caixa PDV</span>
                    </label>
                </div>

                <label class="erp-migra-fb__check erp-migra-fb__check--option">
                    <input type="checkbox" wire:model="updateExisting" :disabled="running || $wire.progressActive">
                    <span>Atualizar existentes</span>
                </label>
            </div>
        </section>

        @if ($this->progressActive || $this->progressPct > 0)
            <div class="erp-migra-fb__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $this->progressPct }}">
                <div class="erp-migra-fb__progress-head">
                    <span class="erp-migra-fb__progress-label">{{ $this->progressLabel }}</span>
                    <span class="erp-migra-fb__progress-pct">{{ $this->progressPct }}%</span>
                </div>
                <div class="erp-migra-fb__progress-track">
                    <div class="erp-migra-fb__progress-bar" style="width: {{ max(0, min(100, $this->progressPct)) }}%"></div>
                </div>
                @if (filled($this->progressDetail))
                    <div class="erp-migra-fb__progress-detail">{{ $this->progressDetail }}</div>
                @endif
            </div>
        @endif

        @if (filled($this->statusMsg))
            <div class="erp-migra-fb__status erp-migra-fb__status--{{ $this->statusTipo }}" role="status">
                {{ $this->statusMsg }}
            </div>
        @endif

        @if ($this->logLines !== [])
            <pre class="erp-migra-fb__log">{{ implode("\n", $this->logLines) }}</pre>
        @endif

        <div class="erp-migra-fb__actions">
            <button
                type="button"
                class="erp-comando__btn erp-migra-fb__btn--secondary"
                wire:click="testarConexao"
                wire:loading.attr="disabled"
                wire:target="testarConexao"
                :disabled="running || $wire.progressActive"
            >
                <span wire:loading.remove wire:target="testarConexao">Testar conexão</span>
                <span wire:loading wire:target="testarConexao">Testando…</span>
            </button>
            <button
                type="button"
                class="erp-comando__btn erp-migra-fb__btn--secondary"
                @click="runMigra(true)"
                :disabled="running || $wire.progressActive"
            >
                <span x-show="! running">Simular</span>
                <span x-cloak x-show="running">Simulando…</span>
            </button>
            <button
                type="button"
                class="erp-comando__btn"
                @click="runMigra(false)"
                :disabled="running || $wire.progressActive"
            >
                <span x-show="! running">Migrar agora</span>
                <span x-cloak x-show="running">Migrando…</span>
            </button>
        </div>
    </div>

    <div class="erp-comando__footer erp-migra-fb__footer">
        <button type="button" wire:click="closeScreen" class="erp-comando__exit" :disabled="running || $wire.progressActive">Fechar</button>
    </div>
</div>
