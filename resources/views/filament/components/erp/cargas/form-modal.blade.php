@if ($this->cargaModalOpen)
    @php
        $readonly = $this->cargaModalReadonly;
        $pedidosCarga = $this->pedidosNaCargaRows();
        $pedidosDisponiveis = $readonly ? [] : $this->pedidosDisponiveisRows();
        $qtd = $this->cargaQtdPedidos();
        $valor = $this->cargaValorTotal();
        $peso = $this->cargaPesoTotal();
        $statusLabel = \App\Models\Carga::statusLabels()[$this->cargaForm['status']] ?? $this->cargaForm['status'];
        $resumoEntregas = $this->cargaResumoEntregas();
    @endphp

    <div
        class="erp-lookup-modal erp-contador-form-modal erp-carga-form-modal"
        wire:keydown.escape.window="closeCargaModal"
        @if (! $readonly)
            wire:keydown.f5.window.prevent="saveCarga"
        @endif
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeCargaModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window erp-carga-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-carga-form-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-carga-form-title">
                    {{ $this->cargaModalRecordId ? 'Carga / Romaneio' : 'Nova Carga' }}
                    @if ($readonly)
                        <small>({{ $statusLabel }})</small>
                    @endif
                </span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeCargaModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-carga-form-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-carga-form-modal__form">
                    <fieldset class="erp-carga-form__section">
                        <legend class="erp-carga-form__section-title">Dados da carga</legend>
                        <div class="erp-carga-form__header-grid">
                            <label class="erp-carga-form__field erp-carga-form__field--num">
                                <span>Nº</span>
                                <input
                                    type="text"
                                    class="erp-pcad-form__input erp-carga-form__input--locked"
                                    value="{{ $this->cargaForm['numero'] }}"
                                    readonly
                                    tabindex="-1"
                                    data-erp-locked
                                    aria-readonly="true"
                                >
                            </label>
                            @if (! $readonly)
                                <div class="erp-carga-form__field erp-carga-form__field--periodo">
                                    <span>De</span>
                                    <div class="erp-carga-form__periodo">
                                        <div class="erp-carga-form__periodo-wrap">
                                            <input
                                                type="date"
                                                wire:model.live="pedidosDisponiveisFiltroDe"
                                                data-erp-native-date
                                                class="erp-pcad-form__input erp-carga-form__periodo-input"
                                                aria-label="Data inicial do filtro de pedidos"
                                            >
                                            <span class="erp-carga-form__periodo-icon" aria-hidden="true"></span>
                                        </div>
                                        <span class="erp-carga-form__periodo-sep" aria-hidden="true">até</span>
                                        <div class="erp-carga-form__periodo-wrap">
                                            <input
                                                type="date"
                                                wire:model.live="pedidosDisponiveisFiltroAte"
                                                data-erp-native-date
                                                class="erp-pcad-form__input erp-carga-form__periodo-input"
                                                aria-label="Data final do filtro de pedidos"
                                            >
                                            <span class="erp-carga-form__periodo-icon" aria-hidden="true"></span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <label class="erp-carga-form__field erp-carga-form__field--grow">
                                <span>Motorista / Transportador</span>
                                <select wire:model="cargaForm.motorista_id" class="erp-pcad-form__input" @disabled($readonly)>
                                    <option value="">— Selecione —</option>
                                    @foreach ($this->motoristaOptions() as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-carga-form__field erp-carga-form__field--grow">
                                <span>Entregador / Usuário do App</span>
                                <select wire:model="cargaForm.entregador_user_id" class="erp-pcad-form__input" @disabled($readonly)>
                                    <option value="">— Selecione —</option>
                                    @foreach ($this->entregadorOptions() as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                                <small class="erp-carga-form__hint">Quem recebe as entregas no app</small>
                            </label>
                            <label class="erp-carga-form__field erp-carga-form__field--grow">
                                <span>Veículo</span>
                                <select wire:model="cargaForm.veiculo_id" class="erp-pcad-form__input" @disabled($readonly)>
                                    <option value="">— Selecione —</option>
                                    @foreach ($this->veiculoOptions() as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="erp-carga-form__field erp-carga-form__field--full">
                                <span>Observação</span>
                                <input
                                    type="text"
                                    wire:model="cargaForm.observacao"
                                    class="erp-pcad-form__input"
                                    maxlength="2000"
                                    @disabled($readonly)
                                >
                            </label>
                        </div>
                    </fieldset>

                    @if (! $readonly)
                        @php
                            $disponiveisSelecionados = array_map('strval', $this->pedidosDisponiveisSelecionados);
                            $todosDisponiveisMarcados = $this->todosPedidosDisponiveisMarcados();
                        @endphp
                        <fieldset class="erp-carga-form__section">
                            <legend class="erp-carga-form__section-title">Pedidos disponíveis</legend>
                            <div class="erp-carga-form__section-head erp-carga-form__section-head--actions">
                                <label class="erp-carga-form__limit">
                                    <span>Visualizando</span>
                                    <select
                                        wire:model.live="pedidosDisponiveisLimite"
                                        class="erp-pcad-form__input erp-carga-form__limit-select"
                                        aria-label="Limite de pedidos disponíveis"
                                    >
                                        @foreach ($this->pedidosDisponiveisLimiteOptions() as $limiteOpt)
                                            <option value="{{ $limiteOpt }}">{{ $limiteOpt }}</option>
                                        @endforeach
                                    </select>
                                    <span>pedidos</span>
                                    @if (count($pedidosDisponiveis) >= (int) $this->pedidosDisponiveisLimite)
                                        <span class="erp-carga-form__limit-note">· aumente o limite se faltar algum</span>
                                    @endif
                                </label>
                                <div class="erp-carga-form__disp-tools">
                                    <label class="erp-carga-form__add-by-numero">
                                        <span>Nº pedido</span>
                                        <input
                                            id="carga-pedido-numero-digitar"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            class="erp-pcad-form__input erp-carga-form__add-by-numero-input"
                                            wire:model="cargaPedidoNumeroDigitar"
                                            wire:keydown.enter.prevent="adicionarPedidoPorNumero"
                                            aria-label="Número do pedido para incluir na carga"
                                        >
                                    </label>
                                    <button type="button" class="erp-carga-form__mini-btn" wire:click="adicionarPedidoPorNumero">
                                        Incluir
                                    </button>
                                    <button type="button" class="erp-carga-form__mini-btn" wire:click="adicionarPedidosSelecionados">
                                        + Adicionar à carga
                                    </button>
                                </div>
                            </div>
                            <div class="erp-carga-form__table-wrap erp-carga-form__table-wrap--disponiveis">
                                <table class="erp-carga-form__table">
                                    <colgroup>
                                        <col class="col-chk">
                                        <col class="col-pedido">
                                        <col class="col-cliente">
                                        <col class="col-data">
                                        <col class="col-valor">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th class="chk" title="Marcar / desmarcar todos">
                                                <input
                                                    type="checkbox"
                                                    wire:key="carga-toggle-disp-{{ $todosDisponiveisMarcados ? 'on' : 'off' }}-{{ count($pedidosDisponiveis) }}"
                                                    wire:click.prevent="toggleTodosPedidosDisponiveis"
                                                    @checked($todosDisponiveisMarcados)
                                                    @disabled($pedidosDisponiveis === [])
                                                    aria-label="Marcar ou desmarcar todos"
                                                >
                                            </th>
                                            <th class="col-pedido">Pedido</th>
                                            <th>Cliente</th>
                                            <th class="col-data">Data</th>
                                            <th class="num">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($pedidosDisponiveis as $row)
                                            @php
                                                $rowSelected = in_array((string) $row['id'], $disponiveisSelecionados, true);
                                            @endphp
                                            <tr @class(['is-selected' => $rowSelected])>
                                                <td class="chk">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $row['id'] }}"
                                                        wire:key="carga-disp-{{ $row['id'] }}"
                                                        @checked($rowSelected)
                                                        x-data
                                                        @click.stop="
                                                            const row = $el.closest('tr');
                                                            if (row) row.classList.toggle('is-selected', $el.checked);
                                                            const table = $el.closest('table');
                                                            const header = table?.querySelector('thead input[type=checkbox]');
                                                            if (header) {
                                                                const boxes = table.querySelectorAll('tbody input[type=checkbox]');
                                                                header.checked = boxes.length > 0 && Array.from(boxes).every((b) => b.checked);
                                                            }
                                                            $wire.syncPedidoDisponivelSelecionado({{ (int) $row['id'] }}, $el.checked);
                                                        "
                                                        aria-label="Selecionar pedido {{ $row['numero'] }}"
                                                    >
                                                </td>
                                                <td class="col-pedido">{{ $row['numero'] }}</td>
                                                <td class="nome">{{ $row['cliente'] }}</td>
                                                <td class="col-data">{{ $row['data'] }}</td>
                                                <td class="num">{{ number_format($row['valor'], 2, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="empty">Nenhum pedido disponível.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="erp-carga-form__qtd-pedido">
                                QTD PEDIDO: {{ count($pedidosDisponiveis) }}
                            </div>
                        </fieldset>
                    @endif

                    @php
                        $cargaSelecionados = array_map('strval', $this->pedidosCargaSelecionados);
                        $todosCargaMarcados = $this->todosPedidosCargaMarcados();
                    @endphp
                    <fieldset class="erp-carga-form__section">
                        <legend class="erp-carga-form__section-title">Pedidos da carga</legend>
                        @if (! $readonly)
                            <div class="erp-carga-form__section-head erp-carga-form__section-head--actions">
                                <button type="button" class="erp-carga-form__mini-btn erp-carga-form__mini-btn--danger" wire:click="removerPedidosSelecionados">
                                    Remover
                                </button>
                            </div>
                        @endif
                        <div class="erp-carga-form__table-wrap erp-carga-form__table-wrap--carga">
                            <table class="erp-carga-form__table">
                                <colgroup>
                                    @if (! $readonly)
                                        <col class="col-chk">
                                    @endif
                                    <col class="col-pedido">
                                    <col class="col-cliente">
                                    <col class="col-data">
                                    <col class="col-valor">
                                </colgroup>
                                <thead>
                                    <tr>
                                        @if (! $readonly)
                                            <th class="chk" title="Marcar / desmarcar todos">
                                                <input
                                                    type="checkbox"
                                                    wire:key="carga-toggle-carga-{{ $todosCargaMarcados ? 'on' : 'off' }}-{{ count($pedidosCarga) }}"
                                                    wire:click.prevent="toggleTodosPedidosCarga"
                                                    @checked($todosCargaMarcados)
                                                    @disabled($pedidosCarga === [])
                                                    aria-label="Marcar ou desmarcar todos"
                                                >
                                            </th>
                                        @endif
                                        <th class="col-pedido">Pedido</th>
                                        <th>Cliente</th>
                                        <th class="col-status">Status</th>
                                        <th class="col-data">Data</th>
                                        <th class="num">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($pedidosCarga as $row)
                                        @php
                                            $rowSelected = in_array((string) $row['id'], $cargaSelecionados, true);
                                            $entregaStatus = $row['entrega_status'] ?? 'pendente';
                                        @endphp
                                        <tr @class(['is-selected' => $rowSelected])>
                                            @if (! $readonly)
                                                <td class="chk">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $row['id'] }}"
                                                        wire:key="carga-carga-{{ $row['id'] }}"
                                                        @checked($rowSelected)
                                                        x-data
                                                        @click.stop="
                                                            const row = $el.closest('tr');
                                                            if (row) row.classList.toggle('is-selected', $el.checked);
                                                            const table = $el.closest('table');
                                                            const header = table?.querySelector('thead input[type=checkbox]');
                                                            if (header) {
                                                                const boxes = table.querySelectorAll('tbody input[type=checkbox]');
                                                                header.checked = boxes.length > 0 && Array.from(boxes).every((b) => b.checked);
                                                            }
                                                            $wire.syncPedidoCargaSelecionado({{ (int) $row['id'] }}, $el.checked);
                                                        "
                                                        aria-label="Selecionar pedido da carga {{ $row['numero'] }}"
                                                    >
                                                </td>
                                            @endif
                                            <td class="col-pedido">{{ $row['numero'] }}</td>
                                            <td class="nome">{{ $row['cliente'] }}</td>
                                            <td class="col-status">
                                                @if ($entregaStatus === 'entregue')
                                                    <span class="erp-carga-entrega-status erp-carga-entrega-status--ok">✓ Entregue</span>
                                                @elseif ($entregaStatus === 'parcial')
                                                    <span class="erp-carga-entrega-status erp-carga-entrega-status--parcial">◐ Parcial</span>
                                                @elseif ($entregaStatus === 'nao_entregue')
                                                    <span class="erp-carga-entrega-status erp-carga-entrega-status--nao">⚠ Não entregue</span>
                                                @else
                                                    <span class="erp-carga-entrega-status erp-carga-entrega-status--pendente">● Pendente</span>
                                                @endif
                                            </td>
                                            <td class="col-data">{{ $row['data'] }}</td>
                                            <td class="num">{{ number_format($row['valor'], 2, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $readonly ? 5 : 6 }}" class="empty">Nenhum pedido na carga.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="erp-carga-form__footer">
                            <span class="erp-carga-form__footer-left">
                                <strong>Peso total:</strong> {{ number_format($peso, 3, ',', '.') }} kg
                            </span>
                            <span class="erp-carga-form__footer-right">
                                <span><strong>Pedidos:</strong> {{ $qtd }}</span>
                                <span><strong>Entregues:</strong> {{ $resumoEntregas['entregues'] }}</span>
                                <span><strong>Parciais:</strong> {{ $resumoEntregas['parciais'] ?? 0 }}</span>
                                <span><strong>Não entregues:</strong> {{ $resumoEntregas['nao_entregues'] }}</span>
                                <span><strong>Pendentes:</strong> {{ $resumoEntregas['pendentes'] }}</span>
                                <span><strong>Valor total:</strong> R$ {{ number_format($valor, 2, ',', '.') }}</span>
                            </span>
                        </div>
                    </fieldset>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-carga-form-modal__actions">
                @if (! $readonly)
                    <button type="button" wire:click="saveCarga" wire:loading.attr="disabled" wire:target="saveCarga" class="erp-pcad-actions__btn" data-erp-key="F5">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label" wire:loading.remove wire:target="saveCarga"><kbd>F5</kbd> | Gravar</span>
                        <span class="erp-pcad-actions__label" wire:loading wire:target="saveCarga">Salvando…</span>
                    </button>
                @endif
                <button type="button" wire:click="closeCargaModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
