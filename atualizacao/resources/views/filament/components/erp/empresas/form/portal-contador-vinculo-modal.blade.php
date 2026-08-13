@if ($this->portalContadorVinculoModalOpen)
    <div
        class="erp-lookup-modal erp-portal-contador-vinculo-modal"
        wire:keydown.escape.window="closePortalContadorVinculoModal"
        @if ($this->portalContadorVinculoStatus === 'pending') wire:poll.3s="pollPortalContadorVinculo" @endif
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closePortalContadorVinculoModal"></div>

        <div
            class="erp-lookup-modal__window erp-portal-contador-vinculo-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-portal-contador-vinculo-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-portal-contador-vinculo-title">Conectar ao Portal do Contador</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closePortalContadorVinculoModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-portal-contador-vinculo-modal__body">
                <p class="erp-portal-contador-vinculo-modal__intro">
                    Os dados da empresa serão enviados automaticamente para o portal.
                    O contador só precisa entrar no portal e clicar em <strong>Autorizar</strong>.
                </p>

                <div class="erp-portal-contador-vinculo-modal__empresa">
                    <div><span>CNPJ</span><strong>{{ $this->data['cnpj'] ?? '—' }}</strong></div>
                    <div><span>Razão social</span><strong>{{ $this->data['razao_social'] ?? $this->data['nome'] ?? '—' }}</strong></div>
                    <div><span>Fantasia</span><strong>{{ $this->data['fantasia'] ?? $this->data['nome'] ?? '—' }}</strong></div>
                </div>

                @if ($this->portalContadorVinculoCodigo !== '')
                    <div class="erp-portal-contador-vinculo-modal__code-box">
                        <span class="erp-portal-contador-vinculo-modal__code-label">Código de autorização</span>
                        <strong class="erp-portal-contador-vinculo-modal__code">{{ $this->portalContadorVinculoCodigo }}</strong>
                    </div>
                @endif

                <p @class([
                    'erp-portal-contador-vinculo-modal__status',
                    'erp-portal-contador-vinculo-modal__status--' . ($this->portalContadorVinculoStatus ?: 'pending'),
                ])>{{ $this->portalContadorVinculoMessage }}</p>

                <div class="erp-portal-contador-vinculo-modal__actions">
                    @if ($this->portalContadorVinculoAuthorizeUrl !== '')
                        <a
                            href="{{ $this->portalContadorVinculoAuthorizeUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="erp-pcad-form__btn erp-portal-contador-vinculo-modal__open-link"
                        >Abrir portal para autorizar</a>
                    @endif

                    @if ($this->portalContadorVinculoStatus === 'pending')
                        <button
                            type="button"
                            class="erp-pcad-form__btn"
                            wire:click="pollPortalContadorVinculo"
                        >Verificar agora</button>
                    @endif

                    @if ($this->portalContadorVinculoStatus === 'authorized')
                        <button
                            type="button"
                            class="erp-pcad-form__btn"
                            wire:click="closePortalContadorVinculoModal"
                        >Concluir</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
