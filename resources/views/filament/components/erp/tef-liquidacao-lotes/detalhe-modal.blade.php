@if ($this->loteDetalheOpen)
    @php $r = $this->loteDetalheResumo; @endphp
    <div class="erp-lookup-modal erp-contador-form-modal" wire:keydown.escape.window="closeLoteDetalhe">
        <div class="erp-lookup-modal__backdrop" wire:click="closeLoteDetalhe"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window erp-tef-liq-lote-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Lote #{{ $r['id'] ?? '' }} — {{ $r['situacao'] ?? '' }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeLoteDetalhe">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <p class="erp-tef-liq-lote-form-modal__hint">
                    Terminal: {{ $r['terminal'] ?? '—' }} · Conta: {{ $r['conta'] ?? '—' }} · Adquirente: {{ $r['adquirente'] ?? '—' }}<br>
                    Bruto R$ {{ $r['bruto'] ?? '0,00' }} · Taxas R$ {{ $r['taxas'] ?? '0,00' }} ({{ $r['taxa_origem'] ?? '' }}) ·
                    Líquido R$ {{ $r['liquido'] ?? '0,00' }} · Prevista: {{ $r['prevista'] ?? '—' }}
                </p>
                <div class="erp-tef-liq-lote-form-modal__table-wrap">
                    <table class="erp-tef-liq-lote-form-modal__table">
                        <thead>
                            <tr>
                                <th>Intenção</th>
                                <th>NSU</th>
                                <th>Parc.</th>
                                <th>Pago</th>
                                <th>Bruto</th>
                                <th>Taxa</th>
                                <th>Líquido</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->loteDetalheItens as $item)
                                <tr>
                                    <td>{{ $item['intencao'] }}</td>
                                    <td>{{ $item['nsu'] }}</td>
                                    <td>{{ $item['parcela'] }}</td>
                                    <td>{{ $item['pago_em'] }}</td>
                                    <td class="is-right">{{ $item['bruto'] }}</td>
                                    <td class="is-right">{{ $item['taxa'] }}</td>
                                    <td class="is-right">{{ $item['liquido'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn" wire:click="closeLoteDetalhe">Fechar</button>
            </div>
        </div>
    </div>
@endif
