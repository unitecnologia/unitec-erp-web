@php
    use App\Support\Erp\EmpresaParametros;
    use App\Support\Erp\WhatsApp\WhatsAppConfig;
    use App\Support\Erp\WhatsApp\WhatsAppPhone;

    $booleans = EmpresaParametros::whatsAppBooleanFields();
    $habilitar = $booleans['param_whatsapp_habilitar'] ?? null;
    unset($booleans['param_whatsapp_habilitar']);

    $status = WhatsAppConfig::normalizeStatus($this->data['param_whatsapp_status'] ?? WhatsAppConfig::STATUS_DESCONECTADO);
    $statusLabel = WhatsAppConfig::statusLabels()[$status] ?? $status;
    $numero = WhatsAppPhone::formatDisplay($this->data['param_whatsapp_numero'] ?? '');
    $limiteDia = (int) ($this->data['param_whatsapp_limite_dia'] ?? 100);
    $msgsHoje = (int) ($this->data['param_whatsapp_msgs_hoje'] ?? 0);
    $gatewayPort = (int) ($this->data['param_whatsapp_gateway_port'] ?? 8091);
    $timeout = (int) ($this->data['param_whatsapp_timeout'] ?? 30);
    $empresaSalva = isset($this->record) && filled($this->record?->getKey());
    $aguardandoQr = $status === WhatsAppConfig::STATUS_AGUARDANDO_QR;
    $pollAtivo = $empresaSalva
        && $aguardandoQr
        && ($this->activeParametrosSubTab ?? '') === 'whatsapp';
@endphp

<div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
    @if ($habilitar)
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="data.param_whatsapp_habilitar">
            <span>{{ $habilitar['label'] }}</span>
        </label>
    @endif
</div>

<p class="erp-empresas-parametros__hint">
    Conexão WhatsApp embarcada no ERP (Baileys). Use para envio de orçamentos e cobranças.
    NF-e continua preferencialmente por e-mail.
</p>

<div class="erp-empresas-parametros__section-title">Conexão</div>

<div class="erp-whatsapp-panel" @if ($pollAtivo) wire:poll.3s="refreshWhatsAppStatus" @endif>
    <div class="erp-whatsapp-panel__status-row">
        <div class="erp-whatsapp-panel__status">
            <span class="erp-whatsapp-panel__status-label">Status:</span>
            <span @class([
                'erp-whatsapp-panel__status-value',
                'erp-whatsapp-panel__status-value--connected' => $status === WhatsAppConfig::STATUS_CONECTADO,
                'erp-whatsapp-panel__status-value--waiting' => $status === WhatsAppConfig::STATUS_AGUARDANDO_QR,
                'erp-whatsapp-panel__status-value--error' => $status === WhatsAppConfig::STATUS_ERRO,
            ])>{{ $statusLabel }}</span>
        </div>

        @if ($numero !== '')
            <div class="erp-whatsapp-panel__status">
                <span class="erp-whatsapp-panel__status-label">Número:</span>
                <span class="erp-whatsapp-panel__status-value erp-whatsapp-panel__status-value--connected">{{ $numero }}</span>
            </div>
        @endif
    </div>

    @if ($this->whatsAppQr)
        <div class="erp-whatsapp-panel__qr-wrap">
            <img src="{{ $this->whatsAppQr }}" alt="QR Code WhatsApp" class="erp-whatsapp-panel__qr">
            <p class="erp-empresas-parametros__hint">Abra o WhatsApp no celular → Aparelhos conectados → Conectar aparelho.</p>
        </div>
    @endif

    <div class="erp-empresas-parametros__actions erp-whatsapp-panel__actions">
        @if ($empresaSalva)
            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="startWhatsAppConnection"
                wire:loading.attr="disabled"
                wire:target="startWhatsAppConnection,refreshWhatsAppStatus,disconnectWhatsApp"
                @disabled($status === WhatsAppConfig::STATUS_CONECTADO)
            >
                <span wire:loading.remove wire:target="startWhatsAppConnection">Conectar / Gerar QR</span>
                <span wire:loading wire:target="startWhatsAppConnection">Iniciando…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="refreshWhatsAppStatus"
                wire:loading.attr="disabled"
                wire:target="startWhatsAppConnection,refreshWhatsAppStatus,disconnectWhatsApp"
            >
                <span wire:loading.remove wire:target="refreshWhatsAppStatus">Atualizar status</span>
                <span wire:loading wire:target="refreshWhatsAppStatus">Atualizando…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-form__btn erp-whatsapp-panel__btn-danger"
                wire:click="disconnectWhatsApp"
                wire:loading.attr="disabled"
                wire:target="startWhatsAppConnection,refreshWhatsAppStatus,disconnectWhatsApp"
                @disabled($status === WhatsAppConfig::STATUS_DESCONECTADO)
            >
                <span wire:loading.remove wire:target="disconnectWhatsApp">Desconectar</span>
                <span wire:loading wire:target="disconnectWhatsApp">Desconectando…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-form__btn"
                wire:click="testWhatsAppConnection"
                wire:loading.attr="disabled"
                wire:target="testWhatsAppConnection"
            >
                <span wire:loading.remove wire:target="testWhatsAppConnection">Testar conexão</span>
                <span wire:loading wire:target="testWhatsAppConnection">Testando…</span>
            </button>
        @else
            <p class="erp-empresas-parametros__hint">Salve a empresa antes de conectar o WhatsApp.</p>
        @endif
    </div>
</div>

<div class="erp-empresas-parametros__section-title">Serviço interno</div>

<div class="erp-empresas-parametros__form-grid erp-empresas-parametros__form-grid--wide">
    <div class="erp-empresas-parametros__field erp-empresas-parametros__field--compact">
        <label class="erp-pcad-form__label" for="param-param_whatsapp_gateway_port">Porta do serviço interno</label>
        <input
            id="param-param_whatsapp_gateway_port"
            type="number"
            min="1024"
            max="65535"
            wire:model="data.param_whatsapp_gateway_port"
            class="erp-pcad-form__input erp-pcad-form__input--xs"
        >
    </div>

    <div class="erp-empresas-parametros__field erp-empresas-parametros__field--compact">
        <label class="erp-pcad-form__label" for="param-param_whatsapp_timeout">Timeout (segundos)</label>
        <input
            id="param-param_whatsapp_timeout"
            type="number"
            min="1"
            max="300"
            wire:model="data.param_whatsapp_timeout"
            class="erp-pcad-form__input erp-pcad-form__input--xs"
        >
    </div>

    <div class="erp-empresas-parametros__field erp-empresas-parametros__field--compact">
        <label class="erp-pcad-form__label" for="param-param_whatsapp_limite_dia">Limite de mensagens/dia</label>
        <input
            id="param-param_whatsapp_limite_dia"
            type="number"
            min="1"
            max="10000"
            wire:model="data.param_whatsapp_limite_dia"
            class="erp-pcad-form__input erp-pcad-form__input--xs"
        >
    </div>
</div>

<p class="erp-empresas-parametros__hint">
    Enviadas hoje: <strong>{{ $msgsHoje }}</strong> de <strong>{{ $limiteDia }}</strong>.
    Porta padrão: {{ $gatewayPort }} (localhost).
</p>

<div class="erp-empresas-parametros__section-title">Tipos de envio</div>

<div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
    @foreach ($booleans as $field => $meta)
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="data.{{ $field }}">
            <span>{{ $meta['label'] }}</span>
        </label>
    @endforeach
</div>

<p class="erp-empresas-parametros__hint">
    As mensagens de cobrança usam o texto da aba <strong>Mensagem de Cobrança (WhatsApp)</strong> deste cadastro.
</p>
