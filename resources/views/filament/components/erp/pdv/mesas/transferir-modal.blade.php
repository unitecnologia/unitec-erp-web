@if ($this->activeModal === 'mesa_transferir')
    <?php $mesaOrigem = \App\Support\Erp\Pdv\PdvMesaSessao::atual(); ?>
    <div class="erp-pdv-modal" role="dialog" aria-label="Transferir mesa">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelTransferirMesa"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small erp-pdv-mesas-transferir">
            <header class="erp-pdv-modal__header">
                <h2>Transferir Mesa</h2>
            </header>
            <form class="erp-pdv-modal__body" wire:submit.prevent="confirmarTransferirMesa">
                <div class="erp-pdv-mesas-transferir__origem">
                    <span class="erp-pdv-mesas-transferir__k">Origem</span>
                    <strong>{{ $mesaOrigem ? \App\Support\Erp\Pdv\PdvMesaService::rotulo($mesaOrigem['numero']) : '—' }}</strong>
                    <span class="erp-pdv-mesas-transferir__meta">{{ count($this->cupomItens) }} item(ns) · R$ {{ $this->cupomTotal }}</span>
                </div>
                <label class="erp-pdv-mesas-transferir__campo" for="erp-pdv-mesa-transferir-destino">
                    <span class="erp-pdv-mesas-transferir__k">Mesa de destino</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        maxlength="3"
                        wire:model="mesaTransferirDestino"
                        id="erp-pdv-mesa-transferir-destino"
                        class="erp-pdv-mesas-transferir__input"
                        data-erp-pdv-clickable
                        autocomplete="off"
                        x-init="$nextTick(() => { $el.removeAttribute('readonly'); $el.focus(); })"
                    >
                </label>
                <p class="erp-pdv-modal__hint">Todos os itens vão para a mesa de destino, que precisa estar livre. A mesa de origem fica livre.</p>
                <button type="submit" hidden></button>
            </form>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmarTransferirMesa" wire:loading.attr="disabled" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Transferir</button>
                <button type="button" wire:click="cancelTransferirMesa" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
