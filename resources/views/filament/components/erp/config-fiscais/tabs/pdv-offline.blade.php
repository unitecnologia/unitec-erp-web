<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--nfce">
    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfce-group">
        <legend class="erp-pcad__group-title">NFC-e por Caixa (PDVs Offline)</legend>

        <p class="erp-pcad-form__hint">
            Cada PDV offline emite NFC-e com uma <strong>série exclusiva</strong> para evitar colisão de numeração
            quando os caixas operam sem o servidor. O CSC (ID Token/Token), ambiente e versão do QR-code são
            compartilhados da empresa (aba NFC-e). A série definida aqui é enviada ao PDV na carga.
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
                        <th>Nº inicial</th>
                        <th>Usar nº inicial</th>
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
                                    wire:model="terminais.{{ $index }}.serie"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    maxlength="3"
                                    inputmode="numeric"
                                    placeholder="—"
                                >
                            </td>
                            <td>
                                <input
                                    type="number"
                                    wire:model="terminais.{{ $index }}.numero_inicial"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    min="1"
                                >
                            </td>
                            <td>
                                <input
                                    type="checkbox"
                                    wire:model="terminais.{{ $index }}.usar_numero_inicial"
                                >
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
