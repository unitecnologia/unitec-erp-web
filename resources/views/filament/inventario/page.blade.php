<x-filament-panels::page>
    <div class="inv-app">
        <header class="inv-top">
            <div class="inv-top__lead">
                <p class="inv-top__eyebrow">Unitec</p>
                <h1>Inventário</h1>
            </div>
            <div class="inv-top__aside">
                @if ($bloqueio === '' && ($resumo['empresa'] ?? '') !== '')
                    <div class="inv-top__place">
                        <p class="inv-top__meta">{{ $resumo['empresa'] }}</p>
                        <p class="inv-top__meta">{{ $resumo['deposito'] }}</p>
                    </div>
                    @if ($resumo['aberto'] ?? false)
                        <span class="inv-badge inv-badge--on">Em andamento</span>
                    @else
                        <span class="inv-badge">Sem balanço</span>
                    @endif
                @endif
                <div class="inv-menu" x-data="{ aberto: false }" @keydown.escape.window="aberto = false">
                    <button type="button" class="inv-iconbtn" @click="aberto = !aberto" :aria-expanded="aberto ? 'true' : 'false'" aria-haspopup="menu" aria-label="Opções">
                        @include('filament.inventario.partials.icon', ['name' => 'dots'])
                    </button>
                    <div class="inv-menu__panel" x-show="aberto" x-cloak x-transition.opacity.duration.100ms @click.outside="aberto = false" role="menu">
                        <button type="button" role="menuitem" onclick="window.UnitecInventarioInstall && window.UnitecInventarioInstall()">
                            @include('filament.inventario.partials.icon', ['name' => 'download'])
                            Instalar app
                        </button>
                        <button type="button" role="menuitem" wire:click="sair">
                            @include('filament.inventario.partials.icon', ['name' => 'logout'])
                            Sair
                        </button>
                    </div>
                </div>
            </div>
        </header>

        @if ($bloqueio !== '')
            <section class="inv-alert" role="alert">
                <strong>Inventário bloqueado</strong>
                <p>{{ $bloqueio }}</p>
            </section>
        @elseif ($etapa === 'inicio')
            @php $empresas = $empresasOpcoes; @endphp
            @if (count($empresas) > 1)
                <label class="inv-field">
                    <span>Empresa</span>
                    <select class="inv-input" wire:change="trocarEmpresa($event.target.value)">
                        @foreach ($empresas as $id => $nome)
                            <option value="{{ $id }}" @selected((int) $empresaId === (int) $id)>{{ $nome }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="inv-grid inv-grid--split">
                <div class="inv-col">
                    @if ($resumo['aberto'] ?? false)
                        <section class="inv-card">
                            <dl class="inv-meta">
                                <div><dt>Responsável</dt><dd>{{ $resumo['responsavel'] ?? '' }}</dd></div>
                                <div><dt>Data</dt><dd>{{ $resumo['data'] ?? '' }}</dd></div>
                                <div><dt>Setor atual</dt><dd>{{ $resumo['etapa_aberta'] ?: 'Nenhum' }}</dd></div>
                                <div><dt>Contagens no setor</dt><dd>{{ $resumo['contados'] ?? 0 }}</dd></div>
                            </dl>
                            @if (! empty($resumo['etapa_aberta']))
                                <button type="button" class="inv-btn inv-btn--solid" wire:click="continuarEtapa" wire:loading.attr="disabled" wire:target="continuarEtapa">
                                    <span wire:loading.remove wire:target="continuarEtapa">
                                        @include('filament.inventario.partials.icon', ['name' => 'play'])
                                        Continuar contagem
                                    </span>
                                    <span wire:loading wire:target="continuarEtapa">Abrindo…</span>
                                </button>
                            @endif
                        </section>

                        @if ($this->podeContar() && empty($resumo['etapa_aberta']))
                            <section class="inv-open-block">
                                <label class="inv-field">
                                    <span>Nome do setor</span>
                                    <input type="text" class="inv-input" wire:model="nomeEtapa" wire:keydown.enter.prevent="criarEtapa" placeholder="Corredor A, Corredor B, Estoque" maxlength="80" autocomplete="off">
                                </label>
                                <button type="button" class="inv-btn inv-btn--solid" wire:click="criarEtapa" wire:loading.attr="disabled" wire:target="criarEtapa">
                                    <span wire:loading.remove wire:target="criarEtapa">
                                        @include('filament.inventario.partials.icon', ['name' => 'plus'])
                                        Abrir setor
                                    </span>
                                    <span wire:loading wire:target="criarEtapa">Abrindo…</span>
                                </button>
                            </section>
                        @endif
                    @else
                        <section class="inv-card">
                            <h2 class="inv-card__title">Nenhum balanço aberto</h2>
                            <p class="inv-note">Abrir o balanço não altera o estoque. Os setores organizam a contagem deste depósito.</p>
                            @if ($this->podeContar())
                                <button type="button" class="inv-btn inv-btn--solid" wire:click="abrirBalanco" wire:loading.attr="disabled" wire:target="abrirBalanco">
                                    <span wire:loading.remove wire:target="abrirBalanco">
                                        @include('filament.inventario.partials.icon', ['name' => 'plus'])
                                        Abrir balanço
                                    </span>
                                    <span wire:loading wire:target="abrirBalanco">Abrindo…</span>
                                </button>
                            @endif
                        </section>
                    @endif
                </div>

                <div class="inv-col">
                    <section class="inv-card">
                        <h2 class="inv-card__title">Setores</h2>
                        @if (! ($resumo['aberto'] ?? false))
                            <p class="inv-empty">Os setores aparecem depois de abrir o balanço.</p>
                        @elseif (($resumo['etapas'] ?? []) === [])
                            <p class="inv-empty">Nenhum setor neste balanço.</p>
                        @else
                            <ul class="inv-setores">
                                @foreach ($resumo['etapas'] as $setor)
                                    <li class="inv-setor">
                                        <span class="inv-setor__name">{{ $setor['nome'] }}</span>
                                        <span class="inv-badge {{ $setor['aberta'] ? 'inv-badge--on' : 'inv-badge--off' }}">{{ $setor['aberta'] ? 'Aberto' : 'Fechado' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <button type="button" class="inv-btn inv-btn--hist" wire:click="irHistorico" wire:loading.attr="disabled" wire:target="irHistorico">
                            @include('filament.inventario.partials.icon', ['name' => 'clock'])
                            Histórico
                        </button>
                    </section>
                    <button type="button" class="inv-btn inv-btn--sair" wire:click="sair" wire:loading.attr="disabled" wire:target="sair">
                        @include('filament.inventario.partials.icon', ['name' => 'logout'])
                        Sair
                    </button>
                </div>
            </div>

            @if ($this->podeContar() && ($resumo['aberto'] ?? false) && empty($resumo['etapa_aberta']))
                <section class="inv-close">
                    <p class="inv-note">Encerrar o balanço não aplica novos ajustes.</p>
                    <button type="button" class="inv-btn inv-btn--danger" wire:click="encerrarBalanco" wire:loading.attr="disabled" wire:target="encerrarBalanco">
                        <span wire:loading.remove wire:target="encerrarBalanco">
                            @include('filament.inventario.partials.icon', ['name' => 'lock'])
                            Encerrar balanço
                        </span>
                        <span wire:loading wire:target="encerrarBalanco">Encerrando…</span>
                    </button>
                </section>
            @endif
        @elseif ($etapa === 'setor')
            <div class="inv-context">
                <button type="button" class="inv-back" wire:click="irInicio">
                    @include('filament.inventario.partials.icon', ['name' => 'back'])
                    Voltar
                </button>
                <p class="inv-context__setor">Setor <strong>{{ $resumo['etapa_aberta'] ?: 'em uso' }}</strong></p>
            </div>

            <div class="inv-grid inv-grid--split">
                <div class="inv-col">
                    @if ($this->podeContar())
                        <div class="inv-search" x-data="{ termo: '' }">
                            <label class="inv-field">
                                <span>Buscar produto</span>
                                <div class="gestor-field__scanrow inv-search__row">
                                    <input
                                        type="search"
                                        class="inv-input"
                                        x-model="termo"
                                        x-on:keydown.enter.prevent="$wire.buscar(termo)"
                                        placeholder="Nome, código ou barras"
                                        autocomplete="off"
                                        enterkeyhint="search"
                                        wire:loading.attr="disabled"
                                        wire:target="buscar"
                                        x-on:inventario-focar-busca.window="$nextTick(() => { $el.focus(); termo = '' })"
                                    >
                                    <button type="button" class="gestor-scan-btn" data-gestor-scan title="Ler código de barras" aria-label="Ler código de barras">
                                        <svg class="inv-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
                                            <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/>
                                            <path d="M7 8v8M10.5 8v8M13.5 8v8M17 8v8"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="inv-btn inv-btn--solid inv-btn--inline" x-on:click="$wire.buscar(termo)" wire:loading.attr="disabled" wire:target="buscar">
                                        @include('filament.inventario.partials.icon', ['name' => 'search'])
                                        <span wire:loading.remove wire:target="buscar">Buscar</span>
                                        <span wire:loading wire:target="buscar">…</span>
                                    </button>
                                </div>
                            </label>
                        </div>
                    @endif

                    <p class="inv-state" wire:loading wire:target="buscar">Buscando…</p>

                    @if ($resultados !== [])
                        <ul class="inv-list">
                            @foreach ($resultados as $item)
                                <li>
                                    <button type="button" class="inv-item" wire:click="abrirProduto({{ $item['id'] }})" wire:loading.attr="disabled" wire:target="abrirProduto">
                                        <strong>{{ $item['descricao'] }}</strong>
                                        <span class="inv-item__meta"><span>Cód. {{ $item['codigo'] }}</span><span>{{ $item['unidade'] }}</span></span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif (mb_strlen(trim($busca)) >= 2)
                        <p class="inv-empty" wire:loading.remove wire:target="buscar">Nenhum produto encontrado.</p>
                    @endif
                </div>

                <div class="inv-col">
                    <section class="inv-card inv-card--counts">
                        <h2 class="inv-card__title">Contagens no setor <span>{{ count($itens) }}</span></h2>
                        @if ($itens === [])
                            <p class="inv-empty">Nenhuma contagem neste setor. O que não for contado permanece como está.</p>
                        @else
                            <ul class="inv-list">
                                @foreach ($itens as $item)
                                    <li class="inv-conf {{ $loop->first ? 'inv-conf--latest' : '' }}">
                                        <strong>{{ $item['descricao'] }}</strong>
                                        <small>Cód. {{ $item['codigo'] }} · {{ $item['horario'] }}</small>
                                        <div class="inv-conf__nums">
                                            <span>Antes <b>{{ $item['saldo_antes'] }}</b></span>
                                            <span>Contado <b>{{ $item['contada'] }}</b></span>
                                            <span class="{{ $item['diferenca_valor'] < 0 ? 'is-neg' : ($item['diferenca_valor'] > 0 ? 'is-pos' : '') }}">Diferença <b>{{ $item['diferenca'] }}</b></span>
                                        </div>
                                        <em>Saldo do depósito {{ $item['saldo_depois'] }}{{ $item['movimento'] !== '' ? ' · movimento '.$item['movimento'] : '' }}</em>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </div>

            @if ($this->podeContar())
                <section class="inv-close">
                    <button type="button" class="inv-btn inv-btn--fechar" wire:click="fecharEtapa" wire:loading.attr="disabled" wire:target="fecharEtapa">
                        <span wire:loading.remove wire:target="fecharEtapa">
                            @include('filament.inventario.partials.icon', ['name' => 'lock'])
                            Fechar setor
                        </span>
                        <span wire:loading wire:target="fecharEtapa">Fechando…</span>
                    </button>
                </section>
            @endif
        @elseif ($etapa === 'produto' && $produto)
            <div class="inv-context">
                <button type="button" class="inv-back" wire:click="continuarEtapa">
                    @include('filament.inventario.partials.icon', ['name' => 'back'])
                    Voltar
                </button>
                <p class="inv-context__setor">Setor <strong>{{ $produto['etapa'] }}</strong></p>
            </div>

            <div class="inv-count" x-data="{ qtd: '', enviando: false }">
                <section class="inv-card inv-card--product">
                    <p class="inv-code">Cód. {{ $produto['codigo'] }}</p>
                    <h2 class="inv-product">{{ $produto['descricao'] }}</h2>
                    <p class="inv-unit">Saldo ao abrir {{ $produto['saldo'] }}</p>
                </section>
                <section class="inv-card">
                    <label class="inv-field inv-qty">
                        <span>Quantidade total contada no depósito</span>
                        <div class="inv-qty__control">
                            <input
                                type="text"
                                class="inv-input inv-input--qty"
                                x-model="qtd"
                                x-bind:disabled="enviando"
                                x-on:keydown.enter.prevent="if (enviando) return; enviando = true; $wire.salvarQuantidade(qtd).finally(() => enviando = false)"
                                inputmode="decimal"
                                autocomplete="off"
                                enterkeyhint="done"
                            >
                            <span class="inv-qty__unit">{{ $produto['unidade'] }}</span>
                        </div>
                    </label>
                    <p class="inv-hint">Ao salvar, o estoque é atualizado.</p>
                    @if ($this->podeContar())
                        <button
                            type="button"
                            class="inv-btn inv-btn--solid"
                            x-bind:disabled="enviando"
                            x-on:click="if (enviando) return; enviando = true; $wire.salvarQuantidade(qtd).finally(() => enviando = false)"
                        >
                            <span x-show="!enviando">
                                @include('filament.inventario.partials.icon', ['name' => 'check'])
                                Salvar contagem
                            </span>
                            <span x-show="enviando" x-cloak>Salvando…</span>
                        </button>
                    @endif
                    @if (! $this->podeAplicar())
                        <p class="inv-alert inv-alert--inline" role="status">Sua permissão conta, mas não aplica ajuste. Uma quantidade diferente do saldo será recusada.</p>
                    @endif
                </section>
            </div>
        @elseif ($etapa === 'historico')
            <div class="inv-context">
                <button type="button" class="inv-back" wire:click="irInicio">
                    @include('filament.inventario.partials.icon', ['name' => 'back'])
                    Voltar
                </button>
                <p class="inv-context__setor">Histórico</p>
            </div>

            <p class="inv-state" wire:loading wire:target="abrirHistorico">Carregando…</p>
            <div class="inv-grid inv-grid--history">
                <div class="inv-col">
                    @if ($historico === [])
                        <section class="inv-card">
                            <p class="inv-empty">Nenhum balanço encerrado neste depósito.</p>
                        </section>
                    @else
                        <ul class="inv-list">
                            @foreach ($historico as $contagem)
                                <li>
                                    <button type="button" class="inv-record {{ (int) $historicoAberto === (int) $contagem['id'] ? 'is-on' : '' }}" wire:click="abrirHistorico({{ $contagem['id'] }})" wire:loading.attr="disabled" wire:target="abrirHistorico">
                                        <span class="inv-record__date">{{ $contagem['data'] }}</span>
                                        <span class="inv-record__who">{{ $contagem['responsavel'] }}</span>
                                        <span class="inv-record__count">{{ $contagem['itens'] }} {{ (int) $contagem['itens'] === 1 ? 'registro' : 'registros' }}</span>
                                        <span class="inv-badge inv-badge--off">Encerrado</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="inv-col inv-detail-col">
                    <div wire:loading.remove wire:target="abrirHistorico">
                        @if (! $historicoAberto)
                            <section class="inv-card inv-placeholder">
                                <p class="inv-empty">Selecione um balanço para ver as contagens.</p>
                            </section>
                        @elseif ($historicoAberto)
                            @if ($historicoItens === [])
                                <section class="inv-card">
                                    <p class="inv-empty">Nenhuma contagem registrada</p>
                                </section>
                            @else
                                <div class="inv-detail">
                                    <div class="inv-detail__head" aria-hidden="true">
                                        <span>Produto</span>
                                        <span>Setor</span>
                                        <span>Responsável</span>
                                        <span>Contado</span>
                                        <span>Diferença</span>
                                        <span>Saldo</span>
                                    </div>
                                    @foreach ($historicoItens as $item)
                                        <article class="inv-detail__row">
                                            <div class="inv-detail__cell is-product">
                                                <span class="inv-detail__label">Produto</span>
                                                <strong>{{ $item['descricao'] }}</strong>
                                                <small>Cód. {{ $item['codigo'] }} · {{ $item['unidade'] }} · {{ $item['horario'] }}</small>
                                            </div>
                                            <div class="inv-detail__cell">
                                                <span class="inv-detail__label">Setor</span>
                                                {{ $item['setor'] }}
                                            </div>
                                            <div class="inv-detail__cell">
                                                <span class="inv-detail__label">Responsável</span>
                                                {{ $item['responsavel'] }}
                                            </div>
                                            <div class="inv-detail__cell">
                                                <span class="inv-detail__label">Contado</span>
                                                <b>{{ $item['contada'] }}</b>
                                            </div>
                                            <div class="inv-detail__cell {{ $item['diferenca_valor'] < 0 ? 'is-neg' : ($item['diferenca_valor'] > 0 ? 'is-pos' : '') }}">
                                                <span class="inv-detail__label">Diferença</span>
                                                <b>{{ $item['diferenca'] }}</b>
                                            </div>
                                            <div class="inv-detail__cell">
                                                <span class="inv-detail__label">Saldo</span>
                                                <b>{{ $item['saldo_depois'] }}</b>
                                                <small>{{ $item['movimento'] !== '' ? 'Movimento '.$item['movimento'] : 'Sem movimento de estoque' }}</small>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
