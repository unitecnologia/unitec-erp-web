@if ($this->activeModal === 'estorno_venda')
  @php
      $motivoLength = mb_strlen(trim($this->consultaVendaMotivoEstorno), 'UTF-8');
      $minMotivo = 15;
      $maxMotivo = 255;
  @endphp
    <div class="erp-pdv-modal" role="dialog" aria-label="Motivo do estorno">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelEstornoVenda"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>Estorno de venda</h2>
            </header>
            <div class="erp-pdv-modal__body erp-pdv-estorno-venda">
                <p class="erp-pdv-modal__hint">
                    Venda #{{ $this->consultaVendaEstornoNumero ?? '—' }}
                    @if ($this->pdvConfig()->motivoEstornoAutomatico())
                        — motivo preenchido automaticamente.
                    @else
                        — informe o motivo do estorno.
                    @endif
                </p>
                <label class="erp-pdv-modal__label" for="erp-pdv-estorno-motivo">Motivo do estorno</label>
                <textarea
                    id="erp-pdv-estorno-motivo"
                    wire:model.live.debounce.150ms="consultaVendaMotivoEstorno"
                    wire:keydown.ctrl.enter.prevent="confirmEstornarConsultaVenda"
                    class="erp-pdv-modal__input erp-pdv-estorno-venda__motivo"
                    rows="4"
                    maxlength="{{ $maxMotivo }}"
                    placeholder="Descreva o motivo do estorno (mínimo {{ $minMotivo }} caracteres)"
                    autocomplete="off"
                ></textarea>
                <p @class([
                    'erp-pdv-estorno-venda__counter',
                    'erp-pdv-estorno-venda__counter--ok' => $motivoLength >= $minMotivo,
                    'erp-pdv-estorno-venda__counter--warn' => $motivoLength > 0 && $motivoLength < $minMotivo,
                ])>
                    {{ $motivoLength }}/{{ $maxMotivo }}
                    @if ($motivoLength < $minMotivo)
                        — faltam {{ $minMotivo - $motivoLength }} caracteres
                    @endif
                </p>
                @if ($this->consultaVendaDetalhe && (int) ($this->consultaVendaDetalhe['venda_id'] ?? 0) === (int) $this->consultaVendaEstornoId)
                    <p class="erp-pdv-estorno-venda__meta">
                        Total: R$ {{ $this->consultaVendaDetalhe['total'] ?? '0,00' }}
                        @if (! empty($this->consultaVendaDetalhe['cliente']))
                            | {{ $this->consultaVendaDetalhe['cliente'] }}
                        @endif
                    </p>
                @endif
            </div>
            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    wire:click="confirmEstornarConsultaVenda"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--danger"
                >Confirmar estorno</button>
                <button type="button" wire:click="cancelEstornoVenda" class="erp-pdv-modal__btn">Voltar</button>
            </footer>
        </div>
    </div>
@endif
