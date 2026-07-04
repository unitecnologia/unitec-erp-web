<div class="erp-pessoas-contatos">
    <div class="erp-pessoas-contatos__grid-wrap">
        <table class="erp-pessoas-contatos__grid">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Data Retorno</th>
                    <th>Pessoa</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->personContacts as $index => $contact)
                    <tr
                        wire:click="selectPersonContact({{ $index }})"
                        @class(['erp-pessoas-contatos__row', 'erp-pessoas-contatos__row--selected' => $this->selectedContactIndex === $index])
                    >
                        <td>{{ $contact['contato_em'] ?? '—' }}</td>
                        <td>
                            @if (filled($contact['data_retorno'] ?? null))
                                {{ \Illuminate\Support\Carbon::parse($contact['data_retorno'])->format('d/m/Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $contact['pessoa'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="erp-pessoas-contatos__empty">Nenhum contato registrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="erp-pessoas-contatos__form">
        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="contact-data-retorno">Data do Retorno</label>
            <input
                id="contact-data-retorno"
                type="date"
                wire:model.blur="contactDataRetorno"
                class="erp-pcad-form__input erp-pcad-form__input--date"
            >
            <div class="erp-pessoas-contatos__mini-actions">
                <button type="button" wire:click="startPersonContact" class="erp-pessoas-contatos__mini-btn" title="Novo">+</button>
                <button type="button" wire:click="deletePersonContact" class="erp-pessoas-contatos__mini-btn" title="Excluir">−</button>
                <button type="button" wire:click="confirmPersonContact" class="erp-pessoas-contatos__mini-btn erp-pessoas-contatos__mini-btn--ok" title="Confirmar">✓</button>
                <button type="button" wire:click="cancelPersonContact" class="erp-pessoas-contatos__mini-btn erp-pessoas-contatos__mini-btn--cancel" title="Cancelar">✕</button>
            </div>
        </div>

        <div class="erp-pcad-form__row">
            <label class="erp-pcad-form__label" for="contact-motivo">Motivo do Contato</label>
            <input id="contact-motivo" type="text" wire:model="contactMotivo" class="erp-pcad-form__input erp-pcad-form__input--grow">
        </div>

        <div class="erp-pcad-form__row erp-pcad-form__row--top erp-pcad-form__row--grow">
            <label class="erp-pcad-form__label" for="contact-descricao">Descrição do Contato</label>
            <textarea id="contact-descricao" wire:model="contactDescricao" rows="8" class="erp-pcad-form__textarea"></textarea>
        </div>
    </div>
</div>
