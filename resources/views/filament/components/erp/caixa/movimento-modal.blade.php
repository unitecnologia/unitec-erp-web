@if ($this->caixaAbrirOpen)
    <div class="erp-caixa-form-modal" x-data x-on:keydown.escape.window="$wire.closeCaixaAbertura()">
        <div class="erp-caixa-form-modal__backdrop" wire:click="closeCaixaAbertura"></div>
        <section class="erp-caixa-form-modal__dialog" role="dialog" aria-modal="true" wire:click.stop>
            <header class="erp-caixa-form-modal__titlebar">
                <div>
                    <span class="erp-caixa-form-modal__eyebrow">Livro Caixa</span>
                    <h2>Abrir caixa</h2>
                </div>
                <button type="button" wire:click="closeCaixaAbertura" title="Fechar">×</button>
            </header>

            <div class="erp-caixa-form-modal__body">
                @if ($this->caixaMovimentoAlert !== '')
                    <div class="erp-caixa-form-modal__alert" role="alert">
                        <span aria-hidden="true">!</span>
                        <div>
                            <strong>Atenção</strong>
                            <p>{{ $this->caixaMovimentoAlert }}</p>
                        </div>
                        <button type="button" wire:click="$set('caixaMovimentoAlert', '')" aria-label="Fechar aviso">×</button>
                    </div>
                @endif

                <div class="erp-caixa-form-modal__grid">
                    <div class="erp-caixa-ro erp-caixa-form-modal__field--full" data-erp-locked>
                        <span class="erp-caixa-ro__label">Empresa</span>
                        <div class="erp-caixa-ro__value">{{ $this->caixaAbrirForm['empresa'] ?? '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro" data-erp-locked>
                        <span class="erp-caixa-ro__label">Conta</span>
                        <div class="erp-caixa-ro__value">{{ $this->caixaAbrirForm['conta'] ?? '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro" data-erp-locked>
                        <span class="erp-caixa-ro__label">Data / hora</span>
                        <div class="erp-caixa-ro__value">{{ $this->caixaAbrirForm['data_hora'] ?? '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro" data-erp-locked>
                        <span class="erp-caixa-ro__label">Responsável</span>
                        <div class="erp-caixa-ro__value">{{ $this->caixaAbrirForm['usuario'] ?? '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro" data-erp-locked>
                        <span class="erp-caixa-ro__label">Saldo financeiro</span>
                        <div class="erp-caixa-ro__value erp-caixa-ro__value--money">{{ $this->caixaAbrirForm['saldo'] ?? '0,00' }}</div>
                    </div>
                    @if (! empty($this->caixaAbrirForm['exige_troco']))
                        <label class="erp-caixa-form-modal__money erp-caixa-field--editavel">
                            <span>Troco inicial (conferência)</span>
                            <input type="text" wire:model="caixaAbrirForm.troco" data-mask="money-br" inputmode="decimal">
                        </label>
                        <p class="erp-caixa-form-modal__hint erp-caixa-form-modal__field--full">
                            O troco não cria lançamento financeiro. Se for dinheiro novo na conta, use F7 Transferir (ou suprimento) antes/depois — não gera receita automática.
                        </p>
                    @endif
                    <label class="erp-caixa-form-modal__field--full erp-caixa-field--editavel">
                        <span>Observação</span>
                        <input type="text" wire:model="caixaAbrirForm.observacao" data-erp-uppercase maxlength="500" placeholder="Opcional">
                    </label>
                </div>
            </div>

            <footer class="erp-caixa-form-modal__footer">
                <button type="button" class="erp-caixa-form-modal__cancel" wire:click="closeCaixaAbertura">Cancelar</button>
                <button
                    type="button"
                    class="erp-caixa-form-modal__save"
                    wire:click="confirmarCaixaAbertura"
                    wire:loading.attr="disabled"
                    wire:target="confirmarCaixaAbertura"
                >✓ Confirmar abertura</button>
            </footer>
        </section>
    </div>
@endif

@if ($this->caixaFecharOpen)
    @php
        $locked = $this->caixaFecharLocked;
        $modalidades = is_array($locked['modalidades'] ?? null) ? $locked['modalidades'] : [];
        $resumo = is_array($this->caixaFecharResumo) ? $this->caixaFecharResumo : [];
        $linhasResumo = is_array($resumo['linhas'] ?? null) ? $resumo['linhas'] : [];
        $diffPorChave = [];
        foreach ($linhasResumo as $ln) {
            $diffPorChave[(string) ($ln['chave'] ?? '')] = $ln;
        }
        $snapshotOk = (int) ($locked['movimento_id'] ?? 0) > 0
            && (int) ($locked['conta_id'] ?? 0) > 0
            && filled($locked['conta'] ?? null)
            && filled($locked['empresa'] ?? null);
    @endphp
    <div
        class="erp-caixa-form-modal"
        x-data
        x-on:keydown.escape.window="$wire.closeCaixaFechamento()"
        x-on:keydown.f6.window="if ($wire.caixaFecharConcluido) { $event.preventDefault(); $wire.openCaixaFechamentoPrint() }"
    >
        <div class="erp-caixa-form-modal__backdrop" wire:click="closeCaixaFechamento"></div>
        <section class="erp-caixa-form-modal__dialog erp-caixa-form-modal__dialog--fechar" role="dialog" aria-modal="true" wire:click.stop>
            <header class="erp-caixa-form-modal__titlebar">
                <div>
                    <span class="erp-caixa-form-modal__eyebrow">Livro Caixa · Encerramento</span>
                    <h2>Fechar caixa</h2>
                </div>
                <button type="button" wire:click="closeCaixaFechamento" title="Fechar">×</button>
            </header>

            <div class="erp-caixa-form-modal__body erp-caixa-form-modal__body--fechar">
                @if ($this->caixaMovimentoAlert !== '')
                    <div class="erp-caixa-form-modal__alert" role="alert">
                        <span aria-hidden="true">!</span>
                        <div>
                            <strong>Atenção</strong>
                            <p>{{ $this->caixaMovimentoAlert }}</p>
                        </div>
                        <button type="button" wire:click="$set('caixaMovimentoAlert', '')" aria-label="Fechar aviso">×</button>
                    </div>
                @endif

                @if (! $snapshotOk)
                    <div class="erp-caixa-form-modal__alert" role="alert">
                        <span aria-hidden="true">!</span>
                        <div>
                            <strong>Fechamento bloqueado</strong>
                            <p>Dados do movimento incompletos. Feche e abra novamente com F8.</p>
                        </div>
                    </div>
                @endif

                <div class="erp-caixa-fechamento-kpis" aria-label="Totais do movimento (somente leitura)" data-erp-locked>
                    <div class="erp-caixa-fechamento-kpi erp-caixa-fechamento-kpi--locked">
                        <span>Entradas</span>
                        <strong>R$ {{ $locked['entradas'] ?? '0,00' }}</strong>
                    </div>
                    <div class="erp-caixa-fechamento-kpi erp-caixa-fechamento-kpi--locked">
                        <span>Saídas</span>
                        <strong>R$ {{ $locked['saidas'] ?? '0,00' }}</strong>
                    </div>
                    <div class="erp-caixa-fechamento-kpi erp-caixa-fechamento-kpi--locked">
                        <span>Saldo movimento</span>
                        <strong>R$ {{ $locked['saldo_movimento'] ?? '0,00' }}</strong>
                    </div>
                    <div class="erp-caixa-fechamento-kpi erp-caixa-fechamento-kpi--accent">
                        <span>Total informado</span>
                        <strong>R$ {{ $resumo['total_informado'] ?? '0,00' }}</strong>
                    </div>
                </div>

                <div class="erp-caixa-ro-grid" aria-label="Dados do sistema (somente leitura)" data-erp-locked>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Empresa</span>
                        <div class="erp-caixa-ro__value">{{ $locked['empresa'] ?: '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Conta</span>
                        <div class="erp-caixa-ro__value">{{ $locked['conta'] ?: '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Abertura</span>
                        <div class="erp-caixa-ro__value">{{ $locked['aberto_em'] ?: '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Responsável (abertura)</span>
                        <div class="erp-caixa-ro__value">{{ $locked['aberto_por'] ?: '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Troco inicial</span>
                        <div class="erp-caixa-ro__value erp-caixa-ro__value--money">{{ $locked['troco_inicial'] ?? '0,00' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Fechando como</span>
                        <div class="erp-caixa-ro__value">{{ $locked['usuario'] ?: '—' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Saldo financeiro (abertura)</span>
                        <div class="erp-caixa-ro__value erp-caixa-ro__value--money">{{ $locked['saldo_abertura_fin'] ?? '0,00' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Saldo financeiro atual</span>
                        <div class="erp-caixa-ro__value erp-caixa-ro__value--money">{{ $locked['saldo_financeiro'] ?? '0,00' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Transferências (entrada)</span>
                        <div class="erp-caixa-ro__value erp-caixa-ro__value--money">{{ $locked['trf_entrada'] ?? '0,00' }}</div>
                    </div>
                    <div class="erp-caixa-ro">
                        <span class="erp-caixa-ro__label">Transferências (saída)</span>
                        <div class="erp-caixa-ro__value erp-caixa-ro__value--money">{{ $locked['trf_saida'] ?? '0,00' }}</div>
                    </div>
                </div>

                <div class="erp-caixa-fechamento-panel erp-caixa-fechamento-panel--editavel">
                    <div class="erp-caixa-fechamento-panel__head">
                        <h3>
                            Conferência por forma de pagamento
                            @unless ($this->caixaFecharConcluido)
                                <em>editável</em>
                            @else
                                <em style="color:#166534">fechado</em>
                            @endunless
                        </h3>
                        <p>
                            @unless ($this->caixaFecharConcluido)
                                Só a coluna Contado e a observação podem ser alterados. Demais valores vêm do banco e não são editáveis.
                            @else
                                Fechamento confirmado. Use F6 — Imprimir para o relatório A4.
                            @endunless
                        </p>
                    </div>

                    <div class="erp-caixa-fechamento-table-wrap">
                        <table class="erp-caixa-fechamento-table">
                            <thead>
                                <tr>
                                    <th>Forma</th>
                                    <th class="is-num">Esperado</th>
                                    <th class="is-num">Contado</th>
                                    <th class="is-num">Diferença</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($modalidades as $mod)
                                    @php
                                        $chave = (string) ($mod['chave'] ?? '');
                                        $ln = $diffPorChave[$chave] ?? null;
                                        $esperadoOk = (bool) ($mod['esperado_disponivel'] ?? false);
                                        $grupo = (string) ($mod['grupo'] ?? '');
                                        $dif = $ln['diferenca'] ?? null;
                                        $difClass = '';
                                        if ($dif !== null) {
                                            $difClass = abs((float) $dif) < 0.005
                                                ? 'is-ok'
                                                : ((float) $dif > 0 ? 'is-plus' : 'is-minus');
                                        }
                                    @endphp
                                    <tr class="erp-caixa-fechamento-row--{{ $grupo }}">
                                        <td>
                                            <span class="erp-caixa-fechamento-forma">{{ $mod['label'] ?? $chave }}</span>
                                            @if ($grupo === 'eletronico')
                                                <span class="erp-caixa-fechamento-tag">eletrônico</span>
                                            @elseif ($grupo === 'fisico')
                                                <span class="erp-caixa-fechamento-tag erp-caixa-fechamento-tag--fisico">físico</span>
                                            @endif
                                            @if (! empty($mod['destino_outra_conta']))
                                                <span class="erp-caixa-fechamento-tag erp-caixa-fechamento-tag--warn">outra conta</span>
                                            @endif
                                        </td>
                                        <td class="is-num erp-caixa-fechamento-esperado" data-erp-locked>
                                            @if ($esperadoOk)
                                                <span title="{{ $mod['esperado_nota'] ?? '' }}">
                                                    {{ number_format((float) ($mod['esperado'] ?? 0), 2, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="erp-caixa-fechamento-na" title="{{ $mod['esperado_nota'] ?? 'Não apurável' }}">Não apurável</span>
                                            @endif
                                        </td>
                                        <td class="is-num is-input">
                                            @if ($this->caixaFecharConcluido)
                                                <span class="erp-caixa-fechamento-contado-ro">
                                                    {{ number_format((float) ($ln['informado'] ?? 0), 2, ',', '.') }}
                                                </span>
                                            @else
                                                <input
                                                    type="text"
                                                    wire:model.blur="caixaFecharForm.formas.{{ $chave }}"
                                                    data-mask="money-br"
                                                    inputmode="decimal"
                                                    class="erp-caixa-fechamento-input erp-caixa-field-input--editavel"
                                                    autocomplete="off"
                                                    aria-label="Contado {{ $mod['label'] ?? $chave }}"
                                                >
                                            @endif
                                        </td>
                                        <td class="is-num {{ $difClass }}" data-erp-locked>
                                            @if ($dif !== null)
                                                {{ number_format((float) $dif, 2, ',', '.') }}
                                            @else
                                                <span class="erp-caixa-fechamento-na">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="is-empty">Nenhuma forma de pagamento disponível.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot data-erp-locked>
                                <tr>
                                    <th>Totais</th>
                                    <th class="is-num">
                                        @if (! empty($locked['exige_troco']))
                                            <span title="Esperado físico = saldo do movimento">{{ $locked['saldo_movimento'] ?? '0,00' }}</span>
                                        @else
                                            <span class="erp-caixa-fechamento-na">—</span>
                                        @endif
                                    </th>
                                    <th class="is-num" title="Calculado automaticamente">{{ $resumo['total_informado'] ?? '0,00' }}</th>
                                    <th class="is-num {{ ! empty($resumo['diferenca_disponivel']) ? (! empty($resumo['diferenca_ok']) ? 'is-ok' : 'is-minus') : '' }}">
                                        @if (! empty($resumo['diferenca_disponivel']))
                                            {{ $resumo['diferenca'] ?? '0,00' }}
                                        @else
                                            <span class="erp-caixa-fechamento-na">—</span>
                                        @endif
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="erp-caixa-fechamento-notes">
                        <p>Troco inicial entra só na conferência do dinheiro — não é receita e não gera lançamento.</p>
                        <p>PIX e cartões são conferência informativa; não somam ao saldo físico desta conta quando o destino for bancário/outra conta.</p>
                        <p>Diferenças são registradas no fechamento — sem ajuste automático de saldo.</p>
                    </div>

                    <div class="erp-caixa-fechamento-mini" aria-label="Resumo da conferência (calculado)" data-erp-locked>
                        <div>
                            <span>Informado físico</span>
                            <strong>R$ {{ $resumo['total_fisico'] ?? '0,00' }}</strong>
                        </div>
                        <div>
                            <span>Informado eletrônico</span>
                            <strong>R$ {{ $resumo['total_eletronico'] ?? '0,00' }}</strong>
                        </div>
                        <div>
                            <span>Contagem (fechamento)</span>
                            <strong>R$ {{ $resumo['valor_contado'] ?? '0,00' }}</strong>
                        </div>
                    </div>
                </div>

                @unless ($this->caixaFecharConcluido)
                    <label class="erp-caixa-form-modal__field--full erp-caixa-fechamento-obs erp-caixa-field--editavel">
                        <span>Observações <em>(editável)</em></span>
                        <input type="text" wire:model="caixaFecharForm.observacao" data-erp-uppercase maxlength="500" placeholder="Opcional — fica no histórico do fechamento">
                    </label>
                @else
                    <div class="erp-caixa-ro erp-caixa-form-modal__field--full" data-erp-locked>
                        <span class="erp-caixa-ro__label">Observações</span>
                        <div class="erp-caixa-ro__value">{{ filled($this->caixaFecharForm['observacao'] ?? null) ? $this->caixaFecharForm['observacao'] : '—' }}</div>
                    </div>
                @endunless
            </div>

            <footer class="erp-caixa-form-modal__footer erp-caixa-form-modal__footer--fechar">
                <button
                    type="button"
                    class="erp-caixa-form-modal__print"
                    wire:click="openCaixaFechamentoPrint"
                    @disabled(! $this->caixaFecharConcluido)
                    title="{{ $this->caixaFecharConcluido ? 'Imprimir fechamento' : 'Disponível após confirmar o fechamento' }}"
                ><kbd>F6</kbd> Imprimir</button>
                <div class="erp-caixa-form-modal__footer-right">
                    <button type="button" class="erp-caixa-form-modal__cancel" wire:click="closeCaixaFechamento">
                        {{ $this->caixaFecharConcluido ? 'Fechar' : 'Cancelar' }}
                    </button>
                    @unless ($this->caixaFecharConcluido)
                        <button
                            type="button"
                            class="erp-caixa-form-modal__save"
                            wire:click="confirmarCaixaFechamento"
                            wire:loading.attr="disabled"
                            wire:target="confirmarCaixaFechamento"
                            @disabled(! $snapshotOk)
                        >✓ Confirmar fechamento</button>
                    @endunless
                </div>
            </footer>
        </section>
    </div>
@endif

@if ($this->caixaFecharPrintOpen)
    @php $flags = $this->caixaFecharPrintFlags; @endphp
    <div class="erp-caixa-form-modal erp-caixa-print-opts" x-data x-on:keydown.escape.window="$wire.closeCaixaFechamentoPrint()">
        <div class="erp-caixa-form-modal__backdrop" wire:click="closeCaixaFechamentoPrint"></div>
        <section class="erp-caixa-form-modal__dialog erp-caixa-form-modal__dialog--print" role="dialog" aria-modal="true" wire:click.stop>
            <header class="erp-caixa-form-modal__titlebar">
                <div>
                    <span class="erp-caixa-form-modal__eyebrow">Impressão A4</span>
                    <h2>Opções do fechamento</h2>
                </div>
                <button type="button" wire:click="closeCaixaFechamentoPrint" title="Fechar">×</button>
            </header>
            <div class="erp-caixa-form-modal__body">
                <div class="erp-caixa-print-opts__modos" role="group" aria-label="Modalidade">
                    <button
                        type="button"
                        class="{{ ($this->caixaFecharPrintModo ?? '') === 'resumido' ? 'is-active' : '' }}"
                        wire:click="aplicarModoImpressaoFechamento('resumido')"
                    >Resumido</button>
                    <button
                        type="button"
                        class="{{ ($this->caixaFecharPrintModo ?? '') === 'completo' ? 'is-active' : '' }}"
                        wire:click="aplicarModoImpressaoFechamento('completo')"
                    >Completo</button>
                </div>
                <ul class="erp-caixa-print-opts__list">
                    <li><label><input type="checkbox" wire:model="caixaFecharPrintFlags.resumo"> Resumo do fechamento</label></li>
                    <li><label><input type="checkbox" wire:model="caixaFecharPrintFlags.formas"> Detalhar formas de pagamento</label></li>
                    <li><label><input type="checkbox" wire:model="caixaFecharPrintFlags.vendas"> Detalhar vendas</label></li>
                    <li><label><input type="checkbox" wire:model="caixaFecharPrintFlags.clientes"> Mostrar clientes</label></li>
                    <li><label><input type="checkbox" wire:model="caixaFecharPrintFlags.entradas_saidas"> Detalhar entradas e saídas</label></li>
                    <li><label><input type="checkbox" wire:model="caixaFecharPrintFlags.transferencias"> Detalhar transferências entre contas</label></li>
                    <li><label><input type="checkbox" wire:model="caixaFecharPrintFlags.operadores"> Mostrar operadores, horários e observações</label></li>
                </ul>
                <p class="erp-caixa-form-modal__hint">Detalhes de vendas/clientes só aparecem com vínculo real nos lançamentos do período. Nada é inventado.</p>
            </div>
            <footer class="erp-caixa-form-modal__footer">
                <button type="button" class="erp-caixa-form-modal__cancel" wire:click="closeCaixaFechamentoPrint">Cancelar</button>
                <button
                    type="button"
                    class="erp-caixa-form-modal__save"
                    wire:click="confirmarImpressaoFechamento"
                    wire:loading.attr="disabled"
                    wire:target="confirmarImpressaoFechamento"
                >🖨 Imprimir</button>
            </footer>
        </section>
    </div>
@endif

@if ($this->caixaFecharHistOpen)
    <div class="erp-caixa-form-modal" x-data x-on:keydown.escape.window="$wire.closeCaixaFechamentoHistorico()">
        <div class="erp-caixa-form-modal__backdrop" wire:click="closeCaixaFechamentoHistorico"></div>
        <section class="erp-caixa-form-modal__dialog erp-caixa-form-modal__dialog--hist" role="dialog" aria-modal="true" wire:click.stop>
            <header class="erp-caixa-form-modal__titlebar">
                <div>
                    <span class="erp-caixa-form-modal__eyebrow">Livro Caixa</span>
                    <h2>Histórico de fechamentos</h2>
                </div>
                <button type="button" wire:click="closeCaixaFechamentoHistorico" title="Fechar">×</button>
            </header>
            <div class="erp-caixa-form-modal__body">
                <div class="erp-caixa-fechamento-table-wrap">
                    <table class="erp-caixa-fechamento-table">
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>Abertura</th>
                                <th>Fechamento</th>
                                <th>Responsável</th>
                                <th class="is-num">Informado</th>
                                <th class="is-num">Dif.</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->caixaFecharHistLista as $row)
                                <tr>
                                    <td>{{ $row['id'] }}</td>
                                    <td>{{ $row['aberto_em'] }}</td>
                                    <td>{{ $row['fechado_em'] }}</td>
                                    <td>{{ $row['fechado_por'] }}</td>
                                    <td class="is-num">{{ $row['total_informado'] }}</td>
                                    <td class="is-num">{{ $row['diferenca'] }}</td>
                                    <td class="is-num">
                                        <button
                                            type="button"
                                            class="erp-caixa-hist-print-btn"
                                            wire:click="selecionarHistoricoFechamento({{ (int) $row['id'] }})"
                                        >Imprimir</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="is-empty">Nenhum fechamento nesta conta/empresa.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <footer class="erp-caixa-form-modal__footer">
                <button type="button" class="erp-caixa-form-modal__cancel" wire:click="closeCaixaFechamentoHistorico">Fechar</button>
            </footer>
        </section>
    </div>
@endif
