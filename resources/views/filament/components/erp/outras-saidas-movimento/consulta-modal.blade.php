<div
    class="erp-lookup-modal erp-mov-saidas-consulta"
    wire:keydown.escape.window="fecharConsultaMovimentos"
>
    <div class="erp-lookup-modal__backdrop" wire:click="fecharConsultaMovimentos"></div>

    <div
        class="erp-lookup-modal__window erp-mov-saidas-consulta__window"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-mov-saidas-consulta-title"
        wire:click.stop
    >
        <div class="erp-lookup-modal__titlebar erp-mov-saidas-consulta__titlebar">
            <span id="erp-mov-saidas-consulta-title">Consulta — Outras saídas de estoque</span>
            <button
                type="button"
                class="erp-lookup-modal__close"
                wire:click="fecharConsultaMovimentos"
                title="Fechar"
            >✕</button>
        </div>

        <div class="erp-mov-saidas-consulta__body">
            <div class="erp-mov-saidas-consulta__toolbar">
                <div class="erp-mov-saidas-consulta__search">
                    <label for="mov-saidas-consulta-busca">Localizar</label>
                    <input
                        id="mov-saidas-consulta-busca"
                        type="search"
                        wire:model.live.debounce.250ms="consultaBusca"
                        placeholder="Código, fornecedor, observação ou estoque"
                        autocomplete="off"
                    >
                    @if ($this->consultaFornecedores !== [])
                        <div class="erp-mov-saidas-consulta__fornecedores">
                            @foreach ($this->consultaFornecedores as $fornecedor)
                                <button
                                    type="button"
                                    wire:key="mov-consulta-fornecedor-{{ md5($fornecedor) }}"
                                    wire:click="selecionarConsultaFornecedor(@js($fornecedor))"
                                >{{ $fornecedor }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <span class="erp-mov-saidas-consulta__count">{{ count($this->consultaMovimentos) }} movimento(s)</span>
            </div>

            <div class="erp-mov-saidas-consulta__table-wrap">
                <table class="erp-mov-saidas-consulta__table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Data</th>
                            <th>Hora</th>
                            <th>Movimentação</th>
                            <th>Fornecedor</th>
                            <th>Observação</th>
                            <th>Status</th>
                            <th>Estoque</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->consultaMovimentos as $index => $movimento)
                            <tr
                                wire:key="mov-consulta-{{ $movimento['id'] }}"
                                wire:click="selecionarConsultaMovimento({{ $index }})"
                                wire:dblclick="abrirConsultaMovimento({{ $movimento['id'] }})"
                                @class(['is-selected' => $this->consultaSelecionadoIndex === $index])
                            >
                                <td class="is-code">{{ $movimento['codigo'] }}</td>
                                <td>{{ $movimento['data'] }}</td>
                                <td>{{ $movimento['hora'] }}</td>
                                <td class="is-movimento">{{ $movimento['movimentacao'] }}</td>
                                <td class="is-fornecedor">{{ $movimento['fornecedor'] !== '' ? $movimento['fornecedor'] : '—' }}</td>
                                <td class="is-obs">{{ $movimento['observacao'] !== '' ? $movimento['observacao'] : '—' }}</td>
                                <td>
                                    <span @class([
                                        'erp-mov-saidas-consulta__badge',
                                        'is-aberto' => $movimento['situacao'] === 'aberta',
                                        'is-fechado' => $movimento['situacao'] === 'finalizada',
                                        'is-cancelado' => $movimento['situacao'] === 'cancelada',
                                    ])>{{ $movimento['status'] }}</span>
                                </td>
                                <td>{{ $movimento['estoque'] !== '' ? $movimento['estoque'] : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="is-empty">Nenhum movimento encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="erp-mov-saidas-consulta__hint">
                Duplo clique abre o movimento · Esc fecha
            </div>
        </div>

        <footer class="erp-os-actions erp-mov-saidas-consulta__actions">
            <button
                type="button"
                class="erp-os-actions__btn"
                wire:click="abrirConsultaMovimentoSelecionado"
                @disabled($this->consultaSelecionadoIndex < 0)
            >
                <span class="erp-os-actions__icon">↗</span>
                <span class="erp-os-actions__label">Abrir</span>
            </button>
            <button
                type="button"
                class="erp-os-actions__btn erp-os-actions__btn--exit"
                wire:click="fecharConsultaMovimentos"
            >
                <span class="erp-os-actions__icon">✕</span>
                <span class="erp-os-actions__label"><kbd>ESC</kbd> | Fechar</span>
            </button>
        </footer>
    </div>
</div>
