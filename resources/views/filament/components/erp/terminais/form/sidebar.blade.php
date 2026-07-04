<aside class="erp-terminais-master__sidebar">

    @php

        $machineName = \App\Support\Erp\Pdv\TerminalResolver::make()->resolveMachineName();

    @endphp



    <div class="erp-terminais-master__sidebar-head">

        <span class="erp-terminais-master__sidebar-title">NOME</span>

        @if ($this->isNewTerminal)

            <input

                type="text"

                wire:model="data.nome"

                class="erp-terminais-master__nome-input"

                placeholder="Nome do terminal"

                autocomplete="off"

            >

        @endif

    </div>



    <ul class="erp-terminais-master__list" role="listbox" aria-label="Terminais">

        @forelse ($this->terminals as $terminal)

            <li>

                <button

                    type="button"

                    wire:click="selectTerminal({{ $terminal->id }})"

                    @class([

                        'erp-terminais-master__item',

                        'erp-terminais-master__item--selected' => ! $this->isNewTerminal && (int) ($this->editingTerminalId ?? $this->highlightedRecordId) === (int) $terminal->id,

                        'erp-terminais-master__item--machine' => strtoupper((string) $terminal->nome) === $machineName,

                    ])

                    role="option"

                    aria-selected="{{ ! $this->isNewTerminal && (int) ($this->editingTerminalId ?? $this->highlightedRecordId) === (int) $terminal->id ? 'true' : 'false' }}"

                >

                    <span class="erp-terminais-master__item-label">{{ $terminal->nome }}</span>

                </button>

            </li>

        @empty

            <li class="erp-terminais-master__empty">Nenhum terminal cadastrado</li>

        @endforelse

    </ul>

</aside>

