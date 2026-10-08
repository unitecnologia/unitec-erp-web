<div class="erp-pcad-form erp-config-fiscais-form erp-config-fiscais-form--nfce">
    <fieldset class="erp-pcad__group erp-config-fiscais-form__nfce-group">
        <legend class="erp-pcad__group-title">NFC-e por caixa</legend>

        <p class="erp-pcad-form__hint">
            Só aparecem caixas que emitem NFC-e (PDV do ERP e PDV offline); aparelhos da Força de Vendas não entram.
            Série em branco usa a série da empresa (aba NFC-e). Cada PDV offline precisa de série exclusiva.
            Próx. nº e Últ. NFC-e são do ambiente atual.
            <strong>F2 | Gravar</strong> salva o restante das configurações mesmo com a série vazia.
        </p>

        @if (empty($this->terminais))
            <p class="erp-pcad-form__hint">Nenhum caixa emissor de NFC-e cadastrado para esta empresa.</p>
        @else
            <div class="erp-config-fiscais-form__nfce-inline-row">
                <span class="erp-config-fiscais-form__nfce-badge erp-config-fiscais-form__nfce-badge--amb">
                    {{ $this->terminaisAmbienteLabel }}
                </span>
            </div>

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
                        <tr wire:key="pdv-offline-terminal-{{ $terminal['id'] }}" @class(['erp-config-fiscais-form__nfce-row--alerta' => filled($terminal['alerta'] ?? null)])>
                            <td>
                                {{ $terminal['nome'] }}
                                <span class="erp-config-fiscais-form__caixa-tipo">
                                    {{ ($terminal['tipo'] ?? 'web') === 'offline' ? 'PDV offline' : 'PDV ERP' }}{{ ($terminal['ativo'] ?? true) ? '' : ' · inativo' }}
                                </span>
                            </td>
                            <td>{{ $terminal['terminal'] }}</td>
                            <td>
                                <input
                                    type="text"
                                    wire:model.live.debounce.400ms="terminais.{{ $index }}.serie"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    maxlength="3"
                                    inputmode="numeric"
                                    placeholder="{{ $terminal['serie_efetiva'] ?? '—' }}"
                                    title="Em branco: série {{ $terminal['serie_efetiva'] ?? '1' }} da empresa"
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
                        @if (filled($terminal['alerta'] ?? null))
                            <tr wire:key="pdv-offline-terminal-alerta-{{ $terminal['id'] }}" class="erp-config-fiscais-form__nfce-row-alerta">
                                <td colspan="5">{{ $terminal['alerta'] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>

            <div class="erp-config-fiscais-form__nfce-inline-row erp-config-fiscais-form__series-actions">
                <button
                    type="button"
                    class="erp-config-fiscais-form__series-btn"
                    wire:click="saveTerminaisSeries"
                    wire:loading.attr="disabled"
                    wire:target="saveTerminaisSeries"
                >
                    <svg class="erp-config-fiscais-form__series-btn-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                    <span wire:loading.remove wire:target="saveTerminaisSeries">Gravar séries dos caixas</span>
                    <span wire:loading wire:target="saveTerminaisSeries">Gravando…</span>
                </button>
            </div>
        @endif
    </fieldset>
</div>
