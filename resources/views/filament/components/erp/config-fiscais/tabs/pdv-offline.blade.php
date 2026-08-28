<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--nfce">
    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfce-group">
        <legend class="erp-pcad__group-title">NFC-e por caixa</legend>

        <p class="erp-pcad-form__hint">
            Cada caixa (PDV offline e o da <strong>retaguarda</strong>) emite NFC-e com série exclusiva,
            para não colidir a numeração. CSC, ambiente e QR-code vêm da aba NFC-e.
            A série e o próximo número daqui vão na carga do PDV offline (o caixa que já emitiu
            continua a sequência local).
            <strong>F2 | Gravar</strong> salva o CSC e as séries dos caixas juntos.
        </p>

        @if (empty($this->terminais))
            <p class="erp-pcad-form__hint">Nenhum caixa cadastrado para esta empresa.</p>
        @else
            <table class="erp-config-fiscais-form__nfce-grid" aria-label="Série NFC-e por caixa">
                <thead>
                    <tr>
                        <th>Caixa</th>
                        <th>Terminal</th>
                        <th>Série NFC-e</th>
                        <th>Próx. nº</th>
                        <th>Últ. NFC-e</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->terminais as $index => $terminal)
                        <tr wire:key="pdv-offline-terminal-{{ $terminal['id'] }}">
                            <td>{{ $terminal['nome'] }}</td>
                            <td>{{ $terminal['terminal'] }}</td>
                            <td>
                                <input
                                    type="text"
                                    wire:model.live="terminais.{{ $index }}.serie"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    maxlength="3"
                                    inputmode="numeric"
                                    placeholder="—"
                                >
                            </td>
                            <td>
                                <input
                                    type="number"
                                    wire:model="terminais.{{ $index }}.proximo_numero"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    min="1"
                                >
                            </td>
                            <td>
                                <output class="erp-config-fiscais-form__ult-nfce" aria-live="polite">
                                    {{ $terminal['ultimo_nfce'] }}
                                </output>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="erp-config-fiscais-form__nfce-inline-row">
                <button type="button" class="erp-pcad-form__button" wire:click="saveTerminaisSeries">
                    Gravar séries dos caixas
                </button>
            </div>
        @endif
    </fieldset>
</div>
