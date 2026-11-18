@if ($this->ofxHistoricoOpen)
    <div class="erp-lookup-modal erp-contador-form-modal erp-tef-liq-lote-form-modal" wire:keydown.escape.window="closeOfxHistorico">
        <div class="erp-lookup-modal__backdrop" wire:click="closeOfxHistorico"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window erp-tef-liq-lote-form-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Histórico OFX — Lote #{{ $this->ofxVinculoLoteId }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeOfxHistorico">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <div class="erp-tef-liq-lote-form-modal__table-wrap">
                    <table class="erp-tef-liq-lote-form-modal__table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Extrato</th>
                                <th>Valor</th>
                                <th>Dif.</th>
                                <th>Motivo</th>
                                <th>Status</th>
                                <th>Vinculado</th>
                                <th>Revertido</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->ofxHistorico as $row)
                                <tr>
                                    <td>{{ $row['id'] }}</td>
                                    <td>{{ $row['extrato'] }}</td>
                                    <td class="is-right">{{ $row['valor'] }}</td>
                                    <td class="is-right">{{ $row['diferenca'] }}</td>
                                    <td>{{ $row['motivo'] }}</td>
                                    <td>{{ $row['status'] }}</td>
                                    <td>{{ $row['em'] }}</td>
                                    <td>{{ $row['rev'] }}</td>
                                    <td>
                                        @if ($row['ativo'])
                                            <button type="button" class="erp-contador-form-modal__btn erp-contador-form-modal__btn--secondary" wire:click="pedirReverterOfx({{ (int) $row['id'] }})">Desfazer</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9">Nenhum vínculo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="erp-lookup-modal__footer erp-contador-form-modal__footer">
                <button type="button" class="erp-contador-form-modal__btn" wire:click="closeOfxHistorico">Fechar</button>
            </div>
        </div>
    </div>
@endif
