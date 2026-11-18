@if ($this->showTransferForm)
    @php
        $somenteLeitura = (bool) $this->transferFormSomenteLeitura;
        $depositos = $this->depositosEmpresaAtiva();
    @endphp
    <div
        class="erp-fpgto-modal erp-ajuste-modal"
        x-data
        x-on:keydown.escape.window="$wire.handleTransferEscape()"
        x-on:keydown.window="
            if ($event.key === 'F5' && ! {{ $somenteLeitura ? 'true' : 'false' }}) {
                $event.preventDefault();
                $wire.confirmarTransferencia();
            }
        "
        x-on:erp-transf-focus-codigo.window="
            $nextTick(() => {
                const el = document.getElementById('erp-transf-codigo');
                if (!el || el.disabled) return;
                el.removeAttribute('readonly');
                el.focus();
                el.select?.();
            })
        "
        x-on:erp-transf-focus-qtd.window="
            $nextTick(() => {
                const el = document.getElementById('erp-transf-qtd');
                if (!el || el.disabled) return;
                el.focus();
                el.select?.();
            })
        "
        x-init="
            @if (! $somenteLeitura)
                $nextTick(() => {
                    const el = document.getElementById('erp-transf-codigo');
                    if (!el || el.disabled) return;
                    el.removeAttribute('readonly');
                    el.focus();
                    el.select?.();
                })
            @endif
        "
    >
        <div class="erp-fpgto-modal__backdrop erp-ajuste-modal__backdrop" wire:click="closeTransferForm"></div>

        <div class="erp-fpgto-modal__dialog erp-ajuste-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="erp-transf-modal-title" style="max-width: 920px;">
            <header class="erp-ajuste-modal__header">
                <div>
                    <p class="erp-ajuste-modal__eyebrow">Estoque</p>
                    <h2 id="erp-transf-modal-title" class="erp-ajuste-modal__title">
                        {{ $somenteLeitura ? 'Transferência '.$this->transferForm['numero'] : 'Nova transferência interna' }}
                    </h2>
                </div>
                <button type="button" class="erp-ajuste-modal__close" wire:click="closeTransferForm" aria-label="Fechar">✕</button>
            </header>

            <div class="erp-fpgto-modal__body erp-ajuste-modal__body">
                <section class="erp-ajuste-modal__section">
                    <div class="erp-ajuste-modal__section-head">
                        <span>Cabeçalho</span>
                        <small>Mesma empresa — depósitos distintos</small>
                    </div>
                    <div class="erp-ajuste-modal__meta">
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Número</span>
                            <input type="text" readonly tabindex="-1" value="{{ $this->transferForm['numero'] ?? '' }}" class="erp-ajuste-modal__input erp-ajuste-modal__input--readonly erp-ajuste-modal__input--sm">
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Data</span>
                            <input type="date" wire:model="transferForm.data" @disabled($somenteLeitura) class="erp-ajuste-modal__input erp-ajuste-modal__input--date">
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Situação</span>
                            <input type="text" readonly tabindex="-1" value="{{ $this->transferForm['situacao'] ?? '' }}" class="erp-ajuste-modal__input erp-ajuste-modal__input--readonly erp-ajuste-modal__input--sm">
                        </label>
                    </div>

                    <div class="erp-ajuste-modal__row3" style="margin-top: .5rem;">
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Depósito origem</span>
                            <select wire:model.live="transferForm.estoque_origem_id" @disabled($somenteLeitura) class="erp-ajuste-modal__input">
                                <option value="">Selecione…</option>
                                @foreach ($depositos as $dep)
                                    <option value="{{ $dep['id'] }}">{{ $dep['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Depósito destino</span>
                            <select wire:model="transferForm.estoque_destino_id" @disabled($somenteLeitura) class="erp-ajuste-modal__input">
                                <option value="">Selecione…</option>
                                @foreach ($depositos as $dep)
                                    <option value="{{ $dep['id'] }}">{{ $dep['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="erp-ajuste-modal__field">
                            <span class="erp-ajuste-modal__label">Observações</span>
                            <input type="text" wire:model="transferForm.observacoes" @disabled($somenteLeitura) maxlength="250" class="erp-ajuste-modal__input" autocomplete="off">
                        </label>
                    </div>
                </section>

                @unless ($somenteLeitura)
                    <section class="erp-ajuste-modal__section">
                        <div class="erp-ajuste-modal__section-head">
                            <span>Incluir produto</span>
                            <small>Pesquisa limitada — disponível = saldo explícito na origem</small>
                        </div>
                        <div class="erp-ajuste-modal__row3">
                            <label class="erp-ajuste-modal__field">
                                <span class="erp-ajuste-modal__label">Cód. int.</span>
                                <input
                                    id="erp-transf-codigo"
                                    type="text"
                                    wire:model="transferForm.codigo_interno"
                                    wire:keydown.enter.prevent="resolveProdutoCodigoInterno"
                                    class="erp-ajuste-modal__input"
                                    placeholder="Código"
                                    data-erp-uppercase
                                    autocomplete="off"
                                >
                            </label>
                            <label class="erp-ajuste-modal__field" style="position: relative; grid-column: span 1;">
                                <span class="erp-ajuste-modal__label">Descrição</span>
                                <input
                                    type="text"
                                    wire:model.live.debounce.250ms="transferForm.descricao_busca"
                                    wire:keydown.enter.prevent="confirmarProdutoSugestao"
                                    class="erp-ajuste-modal__input"
                                    placeholder="Buscar…"
                                    data-erp-uppercase
                                    autocomplete="off"
                                >
                                @if ($this->produtoSugestoes !== [])
                                    <ul class="erp-ajuste-modal__sugestoes" role="listbox" style="position:absolute;z-index:5;left:0;right:0;top:100%;max-height:180px;overflow:auto;background:#fff;border:1px solid #cbd5e1;margin:0;padding:0;list-style:none;">
                                        @foreach ($this->produtoSugestoes as $i => $sug)
                                            <li>
                                                <button
                                                    type="button"
                                                    wire:click="selecionarProdutoSugestao({{ $sug['id'] }})"
                                                    style="display:block;width:100%;text-align:left;padding:.35rem .5rem;border:0;background:{{ $i === $this->selectedProdutoSugestaoIndex ? '#e2e8f0' : 'transparent' }};cursor:pointer;font-size:.8rem;"
                                                >
                                                    <strong>{{ $sug['codigo'] }}</strong> — {{ $sug['descricao'] }}
                                                    <span style="float:right;opacity:.75;">disp. {{ $sug['disponivel'] }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </label>
                            <label class="erp-ajuste-modal__field">
                                <span class="erp-ajuste-modal__label">Disponível</span>
                                <input type="text" readonly tabindex="-1" value="{{ $this->transferForm['disponivel'] ?? '' }}" class="erp-ajuste-modal__input erp-ajuste-modal__input--readonly">
                            </label>
                        </div>
                        <div class="erp-ajuste-modal__meta" style="margin-top:.4rem;">
                            <label class="erp-ajuste-modal__field">
                                <span class="erp-ajuste-modal__label">Quantidade</span>
                                <input
                                    id="erp-transf-qtd"
                                    type="text"
                                    wire:model="transferForm.qtd"
                                    wire:keydown.enter.prevent="adicionarItemTransferencia"
                                    class="erp-ajuste-modal__input erp-ajuste-modal__input--sm"
                                    inputmode="decimal"
                                    autocomplete="off"
                                >
                            </label>
                            <button type="button" class="erp-ajuste-modal__btn erp-ajuste-modal__btn--primary" wire:click="adicionarItemTransferencia" style="align-self:end;margin-bottom:.15rem;">
                                Adicionar
                            </button>
                        </div>
                    </section>
                @endunless

                <section class="erp-ajuste-modal__section">
                    <div class="erp-ajuste-modal__section-head">
                        <span>Itens</span>
                        <small>{{ count($this->transferItens) }} produto(s)</small>
                    </div>
                    <div style="max-height: 220px; overflow: auto; border: 1px solid #e2e8f0;">
                        <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                            <thead>
                                <tr style="background:#f8fafc;text-align:left;">
                                    <th style="padding:.35rem .5rem;">Código</th>
                                    <th style="padding:.35rem .5rem;">Descrição</th>
                                    <th style="padding:.35rem .5rem;text-align:right;">Disponível</th>
                                    <th style="padding:.35rem .5rem;text-align:right;">Qtd</th>
                                    @unless ($somenteLeitura)
                                        <th style="padding:.35rem .5rem;width:2.5rem;"></th>
                                    @endunless
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->transferItens as $idx => $item)
                                    <tr style="border-top:1px solid #e2e8f0;">
                                        <td style="padding:.35rem .5rem;">{{ $item['codigo'] }}</td>
                                        <td style="padding:.35rem .5rem;">{{ $item['descricao'] }}</td>
                                        <td style="padding:.35rem .5rem;text-align:right;">{{ $item['disponivel'] }}</td>
                                        <td style="padding:.35rem .5rem;text-align:right;font-weight:600;">{{ $item['qtd'] }}</td>
                                        @unless ($somenteLeitura)
                                            <td style="padding:.35rem .5rem;text-align:center;">
                                                <button type="button" wire:click="removerItemTransferencia({{ $idx }})" aria-label="Remover" style="border:0;background:transparent;cursor:pointer;color:#b91c1c;">✕</button>
                                            </td>
                                        @endunless
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $somenteLeitura ? 4 : 5 }}" style="padding:.75rem;text-align:center;color:#64748b;">Nenhum item</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                @if ($somenteLeitura && $this->transferMovimentacoes !== [])
                    <section class="erp-ajuste-modal__section">
                        <div class="erp-ajuste-modal__section-head">
                            <span>Histórico de movimentações</span>
                        </div>
                        <div style="max-height: 180px; overflow: auto; border: 1px solid #e2e8f0;">
                            <table style="width:100%;border-collapse:collapse;font-size:.75rem;">
                                <thead>
                                    <tr style="background:#f8fafc;text-align:left;">
                                        <th style="padding:.3rem .45rem;">Data</th>
                                        <th style="padding:.3rem .45rem;">Tipo</th>
                                        <th style="padding:.3rem .45rem;">Depósito</th>
                                        <th style="padding:.3rem .45rem;text-align:right;">Qtd</th>
                                        <th style="padding:.3rem .45rem;text-align:right;">Saldo ant.</th>
                                        <th style="padding:.3rem .45rem;text-align:right;">Saldo at.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->transferMovimentacoes as $mov)
                                        <tr style="border-top:1px solid #e2e8f0;">
                                            <td style="padding:.3rem .45rem;">{{ $mov['data'] }}</td>
                                            <td style="padding:.3rem .45rem;">{{ $mov['tipo'] }}</td>
                                            <td style="padding:.3rem .45rem;">{{ $mov['deposito'] }}</td>
                                            <td style="padding:.3rem .45rem;text-align:right;">{{ $mov['qtd'] }}</td>
                                            <td style="padding:.3rem .45rem;text-align:right;">{{ $mov['saldo_antes'] }}</td>
                                            <td style="padding:.3rem .45rem;text-align:right;">{{ $mov['saldo_depois'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>

            <footer class="erp-ajuste-modal__footer" style="display:flex;gap:.5rem;justify-content:flex-end;padding:.75rem 1rem;border-top:1px solid #e2e8f0;">
                <button type="button" class="erp-ajuste-modal__btn" wire:click="closeTransferForm">
                    {{ $somenteLeitura ? 'Fechar' : 'Cancelar' }}
                </button>
                @unless ($somenteLeitura)
                    <button
                        type="button"
                        class="erp-ajuste-modal__btn erp-ajuste-modal__btn--primary"
                        wire:click="confirmarTransferencia"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="confirmarTransferencia">Confirmar (F5)</span>
                        <span wire:loading wire:target="confirmarTransferencia">Confirmando…</span>
                    </button>
                @endunless
            </footer>
        </div>
    </div>
@endif
