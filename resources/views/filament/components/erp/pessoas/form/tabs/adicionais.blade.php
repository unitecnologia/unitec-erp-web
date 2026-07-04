@php
    use App\Models\Person;
    use App\Models\FormaPagamento;
    use App\Models\TabelaPrazo;
    use App\Models\Vendedor;

    $formasPagamento = FormaPagamento::query()
        ->where('ativo', true)
        ->orderBy('codigo')
        ->get(['id', 'codigo', 'descricao']);

    $tabelasPorForma = TabelaPrazo::query()
        ->orderBy('ordem')
        ->get(['id', 'forma_pagamento_id', 'dias'])
        ->groupBy('forma_pagamento_id');

    $formaSelecionada = $this->data['forma_pagamento_id'] ?? null;
    $tabelasDaForma = $formaSelecionada ? ($tabelasPorForma[$formaSelecionada] ?? collect()) : collect();

    $vendedores = Vendedor::query()
        ->where('ativo', true)
        ->orderBy('nome')
        ->get(['id', 'codigo', 'nome']);
@endphp

<div class="erp-pcad-form erp-pcad-form--adicionais">
    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-mae">Nome da Mãe</label>
        <input id="pcad-mae" type="text" wire:model="data.nome_mae" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-pai">Nome do Pai</label>
        <input id="pcad-pai" type="text" wire:model="data.nome_pai" class="erp-pcad-form__input erp-pcad-form__input--grow">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-nasc">DT.Nascimento</label>
        <input id="pcad-nasc" type="date" wire:model.blur="data.data_nascimento" class="erp-pcad-form__input erp-pcad-form__input--date">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-limite">Limite de Crédito</label>
        <input id="pcad-limite" type="number" step="0.01" wire:model="data.limite_credito" class="erp-pcad-form__input erp-pcad-form__input--money">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-dia-pgto">Dia Pgto</label>
        <input id="pcad-dia-pgto" type="number" min="1" max="31" wire:model="data.dia_pgto" class="erp-pcad-form__input erp-pcad-form__input--xs">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-forma-pgto">Forma de Pagamento</label>
        <select id="pcad-forma-pgto" wire:model.live="data.forma_pagamento_id" class="erp-pcad-form__select erp-pcad-form__select--md">
            <option value="">— Selecione —</option>
            @foreach ($formasPagamento as $forma)
                <option value="{{ $forma->id }}">{{ $forma->codigo }} - {{ $forma->descricao }}</option>
            @endforeach
        </select>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-prazo">Tabela</label>
        <select id="pcad-prazo" wire:model="data.tabela_prazo_id" class="erp-pcad-form__select erp-pcad-form__select--grow" @disabled($tabelasDaForma->isEmpty())>
            <option value="">— Selecione —</option>
            @foreach ($tabelasDaForma as $tabela)
                <option value="{{ $tabela->id }}">{{ $tabela->dias }}</option>
            @endforeach
        </select>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-civil">Estado Civil</label>
        <select id="pcad-civil" wire:model="data.estado_civil" class="erp-pcad-form__select erp-pcad-form__select--md">
            <option value=""></option>
            @foreach (Person::estadosCivis() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-sexo">Sexo</label>
        <select id="pcad-sexo" wire:model="data.sexo" class="erp-pcad-form__select erp-pcad-form__select--md">
            <option value=""></option>
            @foreach (Person::sexos() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-salario">Salário</label>
        <input id="pcad-salario" type="number" step="0.01" wire:model="data.salario" class="erp-pcad-form__input erp-pcad-form__input--money">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-adm">Dt. Admissão</label>
        <input id="pcad-adm" type="date" wire:model.blur="data.data_admissao" class="erp-pcad-form__input erp-pcad-form__input--date">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-dem">Dt.Demissão</label>
        <input id="pcad-dem" type="date" wire:model.blur="data.data_demissao" class="erp-pcad-form__input erp-pcad-form__input--date">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-banco">Banco</label>
        <input id="pcad-banco" type="text" wire:model="data.banco" class="erp-pcad-form__input erp-pcad-form__input--grow">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-agencia">Agência</label>
        <input id="pcad-agencia" type="text" wire:model="data.agencia" class="erp-pcad-form__input erp-pcad-form__input--sm">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-gerente">Gerente</label>
        <input id="pcad-gerente" type="text" wire:model="data.gerente" class="erp-pcad-form__input erp-pcad-form__input--grow">
        <label class="erp-pcad-form__label erp-pcad-form__label--inline" for="pcad-fone-gerente">Fone Gerente</label>
        <input id="pcad-fone-gerente" type="text" wire:model="data.fone_gerente" data-mask="phone" class="erp-pcad-form__input erp-pcad-form__input--phone">
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-vendedor-fv">Vendedor Força de Vendas</label>
        <select id="pcad-vendedor-fv" wire:model="data.vendedor_fv_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
            <option value="">— Selecione —</option>
            @foreach ($vendedores as $vendedor)
                <option value="{{ $vendedor->id }}">{{ $vendedor->codigo }} - {{ $vendedor->nome }}</option>
            @endforeach
        </select>
    </div>

    <div class="erp-pcad-form__row">
        <label class="erp-pcad-form__label" for="pcad-vendedor-loja">Vendedor Loja</label>
        <select id="pcad-vendedor-loja" wire:model="data.vendedor_loja_id" class="erp-pcad-form__select erp-pcad-form__input--grow">
            <option value="">— Selecione —</option>
            @foreach ($vendedores as $vendedor)
                <option value="{{ $vendedor->id }}">{{ $vendedor->codigo }} - {{ $vendedor->nome }}</option>
            @endforeach
        </select>
    </div>

    <div class="erp-pcad-form__row erp-pcad-form__row--top">
        <label class="erp-pcad-form__label" for="pcad-obs">Observações</label>
        <textarea id="pcad-obs" wire:model="data.observacoes" rows="8" class="erp-pcad-form__textarea"></textarea>
    </div>
</div>
