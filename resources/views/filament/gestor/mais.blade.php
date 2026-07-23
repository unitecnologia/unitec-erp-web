<x-filament-panels::page>
    <div class="gestor-shell" data-theme="{{ $this->gestorTema }}">
        <div class="gestor-shell__inner">
            @include('filament.gestor.partials.top', [
                'title' => 'Mais',
                'subtitle' => trim($this->usuarioNome().' · '.$this->empresaNome()),
                'eyebrow' => 'Atalhos',
            ])

            <nav class="gestor-menu" aria-label="Atalhos">
                <a class="gestor-menu__item" href="{{ $this->aprovacoesUrl() }}" wire:navigate>
                    <span class="gestor-menu__icon" data-icon="aprov"></span>
                    <span>
                        <strong>Aprovações</strong>
                        <small>
                            @if ($aprovacoesPendentes > 0)
                                {{ $aprovacoesPendentes }} pendência(s)
                            @else
                                Pedidos e aparelhos
                            @endif
                        </small>
                    </span>
                    @if ($aprovacoesPendentes > 0)
                        <span class="gestor-menu__badge">{{ $aprovacoesPendentes }}</span>
                    @endif
                </a>

                <a class="gestor-menu__item" href="{{ $this->produtosUrl() }}" wire:navigate>
                    <span class="gestor-menu__icon" data-icon="box"></span>
                    <span>
                        <strong>Produtos</strong>
                        <small>Preço, nome e estoque</small>
                    </span>
                </a>

                @if ($pushDisponivel)
                    <button type="button" class="gestor-menu__item" onclick="window.UnitecGestorPush && window.UnitecGestorPush.toggle($wire)">
                        <span class="gestor-menu__icon" data-icon="bell"></span>
                        <span>
                            <strong>{{ $pushAtivo ? 'Notificações ativas' : 'Ativar notificações' }}</strong>
                            <small>{{ $pushAtivo ? 'Toque para desativar neste aparelho' : 'Push de pedidos, estoque e metas' }}</small>
                        </span>
                    </button>
                    @if ($pushAtivo)
                        <button type="button" class="gestor-menu__item" wire:click="testarPush">
                            <span class="gestor-menu__icon" data-icon="bell"></span>
                            <span>
                                <strong>Testar push</strong>
                                <small>Envia uma notificação de teste</small>
                            </span>
                        </button>
                    @endif
                @else
                    <div class="gestor-menu__item" style="opacity:.75;cursor:default">
                        <span class="gestor-menu__icon" data-icon="bell"></span>
                        <span>
                            <strong>Notificações</strong>
                            <small>Configure VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY no .env</small>
                        </span>
                    </div>
                @endif

                <button type="button" class="gestor-menu__item" wire:click="toggleTema">
                    <span class="gestor-menu__icon" data-icon="theme"></span>
                    <span>
                        <strong>Tema {{ $this->gestorTema === 'dark' ? 'claro' : 'escuro' }}</strong>
                        <small>Alternar aparência</small>
                    </span>
                </button>
                <button type="button" class="gestor-menu__item gestor-menu__item--danger" wire:click="logoutGestor">
                    <span class="gestor-menu__icon" data-icon="logout"></span>
                    <span>
                        <strong>Sair</strong>
                        <small>Encerrar sessão</small>
                    </span>
                </button>
            </nav>

            <section class="gestor-roadmap">
                <h2>Roadmap do Executivo</h2>
                <ol>
                    <li class="is-done"><strong>Fase 1</strong> — Dashboard, financeiro, vendas, estoque e produtos</li>
                    <li class="is-done"><strong>Fase 2</strong> — PWA instalável + cache offline</li>
                    <li class="is-done"><strong>Fase 3</strong> — Central de aprovações</li>
                    <li class="is-done"><strong>Fase 4</strong> — Push (pedido, estoque, limite, contas, meta)</li>
                </ol>
            </section>
        </div>
        @include('filament.gestor.partials.bottom-nav')
    </div>
</x-filament-panels::page>
