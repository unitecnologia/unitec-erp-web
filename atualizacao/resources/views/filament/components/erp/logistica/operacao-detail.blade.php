@if ($this->detalheModalOpen)
    <div class="erp-logistica-modal" wire:keydown.escape.window="fecharDetalhe">
        <div class="erp-logistica-modal__backdrop" wire:click="fecharDetalhe"></div>
        <div class="erp-logistica-modal__panel">
            <div class="erp-logistica-modal__head">
                <h3>{{ $this->detalheTitulo }}</h3>
                <button type="button" class="erp-logistica-modal__close" wire:click="fecharDetalhe">✕</button>
            </div>

            <div class="erp-logistica-modal__body">
                <table class="erp-logistica-modal__table">
                    <thead>
                        <tr>
                            <th>Cód.</th>
                            <th>Produto</th>
                            <th>Loc.</th>
                            <th>Qtd</th>
                            <th>Sep.</th>
                            <th>Conf.</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->detalheItens as $item)
                            <tr wire:key="entrega-item-{{ $item['id'] }}">
                                <td>{{ $item['codigo'] ?? '—' }}</td>
                                <td>{{ $item['descricao'] }}</td>
                                <td>{{ $item['localizacao'] ?? '—' }}</td>
                                <td>{{ number_format($item['quantidade_pedida'], 3, ',', '.') }}</td>
                                <td>{{ $item['separado'] ? 'Sim' : 'Não' }}</td>
                                <td>{{ $item['conferido'] ? 'Sim' : 'Não' }}</td>
                                <td class="erp-logistica-modal__actions">
                                    @if ($this->modo === 'separacao')
                                        <button type="button" wire:click="marcarItemSeparado({{ $item['id'] }})">Separar</button>
                                    @elseif ($this->modo === 'conferencia')
                                        <button type="button" wire:click="marcarItemConferido({{ $item['id'] }})">Conferir</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="erp-logistica-modal__foot">
                @if ($this->modo === 'separacao')
                    <button type="button" class="erp-logistica-actions__btn" wire:click="concluirSeparacao">Concluir separação</button>
                @elseif ($this->modo === 'conferencia')
                    <button type="button" class="erp-logistica-actions__btn" wire:click="concluirConferencia">Concluir conferência</button>
                @endif
                <button type="button" class="erp-logistica-actions__btn erp-logistica-actions__btn--close" wire:click="fecharDetalhe">Fechar</button>
            </div>
        </div>
    </div>
@endif
