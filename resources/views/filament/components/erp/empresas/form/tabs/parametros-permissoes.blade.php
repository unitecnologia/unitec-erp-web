@php
    use App\Support\Erp\EmpresaParametros;

    $groups = EmpresaParametros::permissionGroups();
    $fields = EmpresaParametros::permissionFields();
    $grouped = [];

    foreach ($fields as $field => $meta) {
        $group = EmpresaParametros::permissionGroupForField($field);
        $grouped[$group][$field] = $meta;
    }
@endphp

<div class="erp-empresas-parametros__perm-grid">
    @foreach ($groups as $groupKey => $groupTitle)
        <fieldset class="erp-pcad__group erp-empresas-parametros__perm-group">
            <legend class="erp-pcad__group-title">{{ $groupTitle }}</legend>
            @if ($groupKey === 'vendas_internas')
                @php
                    $tiposVendaVi = EmpresaParametros::vendasInternasTiposVendaField();
                @endphp
                <div class="erp-empresas-parametros__desconto-reais">
                    <label class="erp-pcad-form__label" for="param-param_vendas_internas_tipos_venda">
                        {{ $tiposVendaVi['label'] }}
                        <span
                            class="erp-produtos-impostos__label--hint"
                            title="{{ $tiposVendaVi['hint'] }}"
                            role="img"
                            aria-label="{{ $tiposVendaVi['hint'] }}"
                        ></span>
                    </label>
                    <select
                        id="param-param_vendas_internas_tipos_venda"
                        wire:key="emp-vi-tipos-venda"
                        wire:model.live="data.param_vendas_internas_tipos_venda"
                        class="erp-pcad-form__select"
                    >
                        @php
                            $tiposVendaSelecionado = EmpresaParametros::normalizarVendasInternasTiposVenda(
                                $this->data['param_vendas_internas_tipos_venda'] ?? null,
                            );
                        @endphp
                        @foreach ($tiposVendaVi['options'] as $value => $label)
                            <option value="{{ $value }}" @selected($tiposVendaSelecionado === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($groupKey === 'pdv')
                @php
                    $atalhoConcluir = EmpresaParametros::atalhoConcluirVendaField();
                    $atalhoConcluirSelecionado = EmpresaParametros::normalizarAtalhoConcluirVenda(
                        $this->data[\App\Support\Erp\EmpresaParametros::COLUMN_ATALHO_CONCLUIR_VENDA] ?? null,
                    );
                    $atalhoConcluirOptions = \App\Support\Erp\FormaPagamentoAtalho::opcoesSelectConcluirDetalhadas(
                        $atalhoConcluirSelecionado,
                    );
                @endphp
                <div class="erp-empresas-parametros__atalho-concluir-row">
                    <label class="erp-pcad-form__label" for="param-param_pdv_atalho_concluir_venda">
                        {{ $atalhoConcluir['label'] }}
                        <span
                            class="erp-produtos-impostos__label--hint"
                            title="{{ $atalhoConcluir['hint'] }}"
                            role="img"
                            aria-label="{{ $atalhoConcluir['hint'] }}"
                        ></span>
                    </label>
                    <select
                        id="param-param_pdv_atalho_concluir_venda"
                        wire:key="emp-pdv-atalho-concluir-{{ $atalhoConcluirSelecionado }}"
                        wire:model.live="data.param_pdv_atalho_concluir_venda"
                        class="erp-pcad-form__select erp-empresas-parametros__atalho-concluir-select"
                    >
                        @foreach ($atalhoConcluirOptions as $opt)
                            <option
                                value="{{ $opt['value'] }}"
                                @if ($atalhoConcluirSelecionado === $opt['value']) selected @endif
                                @if (! empty($opt['disabled'])) disabled @endif
                            >{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    @error('data.param_pdv_atalho_concluir_venda')
                        <p class="erp-pcad-form__error">{{ $message }}</p>
                    @enderror
                </div>
            @endif
            <div class="erp-empresas-parametros__checks">
                @foreach ($grouped[$groupKey] ?? [] as $field => $meta)
                    @if ($meta['tri'] ?? false)
                        @include('filament.components.erp.empresas.form.tri-checkbox', [
                            'field' => $field,
                            'label' => $meta['label'],
                            'hint' => $meta['hint'] ?? null,
                        ])
                    @elseif ($field === 'param_geral_bloquear_estoque_negativo')
                        @php
                            $bloquearEstoqueNegativoAtivo = filter_var(
                                $this->data['param_geral_bloquear_estoque_negativo'] ?? false,
                                FILTER_VALIDATE_BOOLEAN,
                            );
                        @endphp
                        <label class="erp-pcad__check" @if (filled($meta['hint'] ?? null)) title="{{ $meta['hint'] }}" @endif>
                            {{-- wire:key remonta o input: Livewire morph não atualiza a property checked --}}
                            <input
                                type="checkbox"
                                wire:key="emp-bloquear-estoque-negativo-{{ $bloquearEstoqueNegativoAtivo ? '1' : '0' }}"
                                wire:click.prevent="toggleBloquearEstoqueNegativo"
                                @checked($bloquearEstoqueNegativoAtivo)
                            >
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @elseif ($field === 'param_monitor_vendas_escolher_empresa_emitente_nfe')
                        <label class="erp-pcad__check">
                            <input type="checkbox" wire:model.live="data.{{ $field }}">
                            <span>
                                {{ $meta['label'] }}
                                @if (filled($meta['hint'] ?? null))
                                    <span
                                        class="erp-produtos-impostos__label--hint"
                                        title="{{ $meta['hint'] }}"
                                        role="img"
                                        aria-label="{{ $meta['hint'] }}"
                                    ></span>
                                @endif
                            </span>
                        </label>
                        @include('filament.components.erp.empresas.form.partials.nfe-emitentes-config')
                    @elseif ($field === 'param_vendas_internas_fechar_orcamento_auto')
                        @php
                            $tiposVendaAtual = EmpresaParametros::normalizarVendasInternasTiposVenda(
                                $this->data['param_vendas_internas_tipos_venda'] ?? null,
                            );
                            $fecharOrcamentoDesabilitado = $tiposVendaAtual === EmpresaParametros::VI_TIPOS_VENDA_SOMENTE_PEDIDO;
                            $fecharOrcamentoCheckClass = $fecharOrcamentoDesabilitado
                                ? 'erp-pcad__check erp-pcad__check--disabled'
                                : 'erp-pcad__check';
                        @endphp
                        <label
                            class="{{ $fecharOrcamentoCheckClass }}"
                            @if (filled($meta['hint'] ?? null)) title="{{ $meta['hint'] }}" @endif
                        >
                            <input
                                type="checkbox"
                                wire:model="data.{{ $field }}"
                                @disabled($fecharOrcamentoDesabilitado)
                            >
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @else
                        <label class="erp-pcad__check" @if (filled($meta['hint'] ?? null)) title="{{ $meta['hint'] }}" @endif>
                            <input type="checkbox" wire:model="data.{{ $field }}">
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @endif
                @endforeach
            </div>
            @if ($groupKey === 'monitor_vendas')
                @php
                    $modoDescontoReais = EmpresaParametros::monitorVendasDescontoReaisItemModoField();
                @endphp
                <div class="erp-empresas-parametros__desconto-reais">
                    <label class="erp-pcad-form__label" for="param-param_monitor_vendas_desconto_reais_item_modo">
                        {{ $modoDescontoReais['label'] }}
                        <span
                            class="erp-produtos-impostos__label--hint"
                            title="{{ $modoDescontoReais['hint'] }}"
                            role="img"
                            aria-label="{{ $modoDescontoReais['hint'] }}"
                        ></span>
                    </label>
                    <select
                        id="param-param_monitor_vendas_desconto_reais_item_modo"
                        wire:model="data.param_monitor_vendas_desconto_reais_item_modo"
                        class="erp-pcad-form__select"
                    >
                        @foreach ($modoDescontoReais['options'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </fieldset>
    @endforeach
</div>
