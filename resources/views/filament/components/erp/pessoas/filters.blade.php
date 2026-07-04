<div class="erp-pessoas__filters" wire:ignore>

    <a

        href="{{ $this->pessoasListUrl(null, 'ativos') }}"

        wire:navigate="false"

        @class(['erp-pessoas__filter', 'erp-pessoas__filter--active' => $this->statusFilter === 'ativos'])

    >Ativos</a>

    <a

        href="{{ $this->pessoasListUrl(null, 'inativos') }}"

        wire:navigate="false"

        @class(['erp-pessoas__filter', 'erp-pessoas__filter--active' => $this->statusFilter === 'inativos'])

    >Inativos</a>

    <a

        href="{{ $this->pessoasListUrl(null, 'todos') }}"

        wire:navigate="false"

        @class(['erp-pessoas__filter', 'erp-pessoas__filter--active' => $this->statusFilter === 'todos'])

    >Todos</a>

</div>

