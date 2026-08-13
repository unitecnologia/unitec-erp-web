@if ($this->portalContadorLogModalOpen)
    @php
        $summary = $this->portalContadorLogSummary;
        $rows = $this->portalContadorLogRows;
    @endphp

    <div
        class="erp-lookup-modal erp-portal-contador-log-modal"
        wire:keydown.escape.window="closePortalContadorLogModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closePortalContadorLogModal"></div>

        <div
            class="erp-lookup-modal__window erp-portal-contador-log-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-portal-contador-log-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-portal-contador-log-title">Log — Portal do Contador</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closePortalContadorLogModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-portal-contador-log-modal__body">
                <div class="erp-portal-contador-log-modal__summary">
                    <span><strong>{{ $summary['total'] }}</strong> registro(s)</span>
                    <span class="erp-portal-contador-log-modal__badge erp-portal-contador-log-modal__badge--sent">{{ $summary['sent'] }} enviado(s)</span>
                    <span class="erp-portal-contador-log-modal__badge erp-portal-contador-log-modal__badge--failed">{{ $summary['failed'] }} falha(s)</span>
                    @if ($summary['pending'] > 0)
                        <span class="erp-portal-contador-log-modal__badge erp-portal-contador-log-modal__badge--pending">{{ $summary['pending'] }} pendente(s)</span>
                    @endif
                    @if ($summary['skipped'] > 0)
                        <span class="erp-portal-contador-log-modal__badge erp-portal-contador-log-modal__badge--skipped">{{ $summary['skipped'] }} ignorado(s)</span>
                    @endif
                    <button
                        type="button"
                        class="erp-pcad-form__btn erp-portal-contador-log-modal__refresh-btn"
                        wire:click="refreshPortalContadorLog"
                        wire:loading.attr="disabled"
                        wire:target="refreshPortalContadorLog"
                    >
                        <span wire:loading.remove wire:target="refreshPortalContadorLog">Atualizar</span>
                        <span wire:loading wire:target="refreshPortalContadorLog">Atualizando…</span>
                    </button>
                </div>

                <div class="erp-lookup-modal__grid-wrap erp-portal-contador-log-modal__grid-wrap">
                    <table class="erp-lookup-modal__grid erp-portal-contador-log-modal__grid">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Documento</th>
                                <th>Evento</th>
                                <th>Status</th>
                                <th>HTTP</th>
                                <th>Tent.</th>
                                <th>Chave</th>
                                <th>Erro</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>{{ $row['data_hora'] }}</td>
                                    <td>{{ $row['tipo'] }}</td>
                                    <td>{{ $row['evento'] }}</td>
                                    <td>
                                        <span @class([
                                            'erp-portal-contador-log-modal__status',
                                            'erp-portal-contador-log-modal__status--' . ($row['status_codigo'] ?? 'pending'),
                                        ])>{{ $row['status'] }}</span>
                                    </td>
                                    <td>{{ $row['http_status'] }}</td>
                                    <td>{{ $row['tentativas'] }}</td>
                                    <td class="erp-portal-contador-log-modal__chave">{{ $row['chave'] }}</td>
                                    <td class="erp-portal-contador-log-modal__erro">{{ $row['erro'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="erp-lookup-modal__empty">
                                        Nenhum envio registrado para esta empresa.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <p class="erp-portal-contador-log-modal__hint">
                    Exibindo os últimos 100 registros. Documentos com status <strong>Falha</strong> podem ser reenviados após corrigir URL, token ou ID da empresa na nuvem.
                </p>
            </div>
        </div>
    </div>
@endif
