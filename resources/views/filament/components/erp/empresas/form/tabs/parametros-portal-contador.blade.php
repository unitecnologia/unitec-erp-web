@php
    use App\Models\Contador;
    use App\Support\Erp\EmpresaParametros;

    $booleans = EmpresaParametros::portalContadorClientBooleanFields();
    $contadores = Contador::query()->orderBy('nome')->pluck('nome', 'id');
    $status = $this->portalContadorStatusView();
    $conectado = $status['state'] === 'connected';

    $habilitar = $booleans['param_portal_contador_habilitar'] ?? null;
    unset($booleans['param_portal_contador_habilitar']);
@endphp

<div class="erp-empresas-api-servicos erp-empresas-portal-contador">
    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">Portal do Contador</h3>

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks">
            @if ($habilitar)
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="data.param_portal_contador_habilitar">
                    <span>{{ $habilitar['label'] }}</span>
                </label>
            @endif
        </div>

        <p class="erp-empresas-parametros__hint erp-empresas-api-servicos__status-msg">
            Ao habilitar, escolher o contador e <strong>salvar</strong> a empresa, o ERP vincula automaticamente
            ao portal na nuvem (sem autorização manual). O contador precisa ter nome, e-mail e <strong>CNPJ válido</strong> no cadastro (CPF não é aceito).
        </p>

        <div class="erp-empresas-parametros__field erp-empresas-api-servicos__field" style="max-width: 28rem; margin-bottom: 0.75rem;">
            <label class="erp-pcad-form__label" for="param-param_portal_contador_contador_id">Contador vinculado</label>
            <select
                id="param-param_portal_contador_contador_id"
                wire:model="data.param_portal_contador_contador_id"
                class="erp-pcad-form__select erp-pcad-form__select--md"
            >
                <option value="">— Selecione o contador —</option>
                @foreach ($contadores as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-portal-contador-vinculo-panel">
            <div class="erp-portal-contador-vinculo-panel__status">
                <span @class([
                    'erp-portal-contador-vinculo-panel__badge',
                    'erp-portal-contador-vinculo-panel__badge--connected' => $status['state'] === 'connected',
                    'erp-portal-contador-vinculo-panel__badge--pending' => $status['state'] === 'pending',
                    'erp-portal-contador-vinculo-panel__badge--error' => $status['state'] === 'error',
                    'erp-portal-contador-vinculo-panel__badge--disconnected' => $status['state'] === 'disabled',
                ])>{{ $status['label'] }}</span>
                <p>{{ $status['resumo'] }}</p>
                @if (filled($this->data['param_portal_contador_contador_nome_portal'] ?? null) && $conectado)
                    <p class="erp-portal-contador-vinculo-panel__meta">
                        Contador no portal: <strong>{{ $this->data['param_portal_contador_contador_nome_portal'] }}</strong>
                    </p>
                @endif
            </div>

            <div class="erp-portal-contador-vinculo-panel__actions">
                @if (! $conectado && ($this->data['param_portal_contador_habilitar'] ?? false))
                    <button
                        type="button"
                        class="erp-pcad-form__btn erp-portal-contador-vinculo-panel__btn-primary"
                        wire:click="tentarVincularPortalContadorNovamente"
                        wire:loading.attr="disabled"
                        wire:target="tentarVincularPortalContadorNovamente"
                    >
                        <span wire:loading.remove wire:target="tentarVincularPortalContadorNovamente">Tentar vincular novamente</span>
                        <span wire:loading wire:target="tentarVincularPortalContadorNovamente">Vinculando…</span>
                    </button>
                @endif

                @if ($conectado || filled($this->data['param_portal_contador_token'] ?? null))
                    <button
                        type="button"
                        class="erp-pcad-form__btn"
                        wire:click="desvincularPortalContador"
                        wire:confirm="Remover as credenciais do portal nesta empresa? O envio será desabilitado."
                    >Desvincular</button>
                @endif
            </div>
        </div>
    </section>

    <section class="erp-empresas-api-servicos__panel">
        <h3 class="erp-empresas-api-servicos__panel-title">O que enviar</h3>

        <div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline erp-empresas-api-servicos__checks erp-empresas-portal-contador__checks">
            @foreach ($booleans as $field => $meta)
                <label class="erp-pcad__check">
                    <input type="checkbox" wire:model="data.{{ $field }}">
                    <span>{{ $meta['label'] }}</span>
                </label>
            @endforeach
        </div>

        <div class="erp-empresas-api-servicos__provision-actions erp-empresas-portal-contador__actions">
            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="atualizarPortalContador"
                wire:loading.attr="disabled"
                wire:target="atualizarPortalContador"
            >
                <span wire:loading.remove wire:target="atualizarPortalContador">Reenviar falhas</span>
                <span wire:loading wire:target="atualizarPortalContador">Reenviando…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-form__btn erp-portal-contador-vinculo-panel__btn-primary"
                wire:click="pedirConfirmacaoEnviarMesPortalContador"
                wire:loading.attr="disabled"
                wire:target="pedirConfirmacaoEnviarMesPortalContador,confirmarEnviarMesPortalContador,enviarMesAtualPortalContador"
            >
                <span wire:loading.remove wire:target="pedirConfirmacaoEnviarMesPortalContador,confirmarEnviarMesPortalContador,enviarMesAtualPortalContador">Enviar mês atual e anterior</span>
                <span wire:loading wire:target="pedirConfirmacaoEnviarMesPortalContador,confirmarEnviarMesPortalContador,enviarMesAtualPortalContador">Enviando documentos…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="openPortalContadorLogModal"
                wire:loading.attr="disabled"
                wire:target="openPortalContadorLogModal"
            >
                <span wire:loading.remove wire:target="openPortalContadorLogModal">Histórico</span>
                <span wire:loading wire:target="openPortalContadorLogModal">Abrindo…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="testPortalContadorConnection"
                wire:loading.attr="disabled"
                wire:target="testPortalContadorConnection"
                title="Diagnóstico técnico"
            >
                <span wire:loading.remove wire:target="testPortalContadorConnection">Testar conexão</span>
                <span wire:loading wire:target="testPortalContadorConnection">Testando…</span>
            </button>
        </div>
    </section>
</div>

@if ($this->portalContadorEnviarMesConfirmOpen)
    @teleport('body')
        <div
            class="erp-empresas-atencao-overlay"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="erp-portal-enviar-mes-title"
            wire:keydown.escape.window="cancelarEnviarMesPortalContador"
        >
            <div
                class="erp-empresas-atencao-overlay__backdrop"
                wire:click="cancelarEnviarMesPortalContador"
            ></div>
            <div class="erp-empresas-atencao-overlay__box">
                <div class="erp-empresas-atencao-overlay__icon" aria-hidden="true">!</div>
                <h2 id="erp-portal-enviar-mes-title" class="erp-empresas-atencao-overlay__title">ATENÇÃO</h2>
                <p class="erp-empresas-atencao-overlay__msg">
                    Enviar ao portal NF-e, NFC-e (incl. contingência), NFS-e e XMLs de compra do mês atual e do mês anterior?
                </p>
                <div class="erp-empresas-atencao-overlay__actions">
                    <button
                        type="button"
                        class="erp-empresas-atencao-overlay__btn erp-empresas-atencao-overlay__btn--primary"
                        wire:click="confirmarEnviarMesPortalContador"
                        wire:loading.attr="disabled"
                        wire:target="confirmarEnviarMesPortalContador"
                    >
                        <span wire:loading.remove wire:target="confirmarEnviarMesPortalContador">Sim</span>
                        <span wire:loading wire:target="confirmarEnviarMesPortalContador">Enviando…</span>
                    </button>
                    <button
                        type="button"
                        class="erp-empresas-atencao-overlay__btn"
                        wire:click="cancelarEnviarMesPortalContador"
                        wire:loading.attr="disabled"
                        wire:target="confirmarEnviarMesPortalContador"
                    >Não</button>
                </div>
                <p class="erp-empresas-atencao-overlay__hint">Confirme para preparar e enviar os documentos fiscais.</p>
            </div>
        </div>
    @endteleport
@endif
