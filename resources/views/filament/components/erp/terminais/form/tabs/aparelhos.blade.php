@php
    $statusOptions = [
        'pendentes' => 'Pendentes',
        'ativos' => 'Ativos',
        'revogados' => 'Revogados',
        'todos' => 'Todos',
    ];
@endphp

<div class="erp-terminais-aparelhos">
    <div class="erp-terminais-aparelhos__toolbar">
        <label class="erp-terminais-aparelhos__filter">
            <span>Situação</span>
            <select wire:model.live="aparelhoStatusFilter">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <p class="erp-terminais-aparelhos__hint">
            Confira o código no celular e use <kbd>F2</kbd> para autorizar ou <kbd>F4</kbd> para excluir.
            Aparelhos autorizados (Força de Vendas / Vendas Internas / Unitec OS) ficam nesta aba.
            Telefones não aparecem na lista Dispositivo à esquerda. Excluir remove o aparelho e libera a vaga da licença.
            <strong>Autorizar Reset da Base</strong> apaga os dados locais do app Força de Vendas na próxima conexão.
        </p>
    </div>

    <div class="erp-terminais-aparelhos__table-wrap">
        <table class="erp-terminais-aparelhos__table">
            <thead>
                <tr>
                    <th>Aparelho</th>
                    <th>Origem</th>
                    <th>Código</th>
                    <th>Vendedor</th>
                    <th>Empresa</th>
                    <th>Plataforma</th>
                    <th>Versão</th>
                    <th>Solicitado</th>
                    <th>Situação</th>
                    <th>Reset da base</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->aparelhosPendentes as $aparelho)
                    <tr
                        wire:key="aparelho-{{ $aparelho['key'] }}"
                        wire:click="selectAparelho(@js($aparelho['key']))"
                        @class([
                            'erp-terminais-aparelhos__row',
                            'erp-terminais-aparelhos__row--selected' => $this->selectedAparelhoKey === $aparelho['key'],
                        ])
                    >
                        <td>{{ $aparelho['device_name'] }}</td>
                        <td>{{ $aparelho['origem_label'] }}</td>
                        <td class="erp-terminais-aparelhos__code">{{ $aparelho['pairing_code'] ?: '—' }}</td>
                        <td>{{ $aparelho['vendedor'] ?: '—' }}</td>
                        <td>{{ $aparelho['empresa'] ?: '—' }}</td>
                        <td>{{ $aparelho['platform'] ?: '—' }}</td>
                        <td>{{ $aparelho['app_version'] ?: '—' }}</td>
                        <td>{{ $aparelho['registered_at'] ?: '—' }}</td>
                        <td>
                            <span @class([
                                'erp-terminais-aparelhos__badge',
                                'erp-terminais-aparelhos__badge--pendente' => $aparelho['situacao'] === 'Pendente',
                                'erp-terminais-aparelhos__badge--ativo' => $aparelho['situacao'] === 'Ativo',
                                'erp-terminais-aparelhos__badge--revogado' => $aparelho['situacao'] === 'Revogado',
                            ])>{{ $aparelho['situacao'] }}</span>
                        </td>
                        <td title="{{ $aparelho['reset_title'] ?? '' }}">
                            @if (($aparelho['reset_status'] ?? null) === \App\Models\ForcaVendasDeviceReset::STATUS_PENDENTE)
                                <span class="erp-terminais-aparelhos__badge erp-terminais-aparelhos__badge--reset">{{ $aparelho['reset_label'] }}</span>
                            @elseif (filled($aparelho['reset_label'] ?? null))
                                <span class="erp-terminais-aparelhos__reset-done">{{ $aparelho['reset_label'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="erp-terminais-aparelhos__empty">
                            Nenhum aparelho nesta situação.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @php($resetConfirm = $this->resetAparelhoConfirm)
    @if ($resetConfirm !== null)
        <div class="erp-lookup-modal erp-fv-reset-modal" wire:keydown.escape="cancelarResetAparelho">
            <div class="erp-lookup-modal__backdrop" wire:click="cancelarResetAparelho"></div>

            <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-fv-reset-title">
                <div class="erp-lookup-modal__titlebar">
                    <span id="erp-fv-reset-title">Autorizar Reset da Base</span>
                    <button type="button" class="erp-lookup-modal__close" wire:click="cancelarResetAparelho" title="Fechar">✕</button>
                </div>

                <div class="erp-lookup-modal__body erp-fv-reset-modal__body">
                    <p class="erp-fv-reset-modal__alert">
                        Atenção: pedidos, orçamentos, clientes, visitas e demais dados que ainda
                        <strong>não foram sincronizados</strong> neste aparelho poderão ser
                        <strong>perdidos definitivamente</strong>.
                    </p>

                    <dl class="erp-fv-reset-modal__details">
                        <div><dt>Aparelho</dt><dd>{{ $resetConfirm['device_name'] }}</dd></div>
                        <div><dt>Vendedor vinculado</dt><dd>{{ $resetConfirm['vendedor'] ?: '—' }}</dd></div>
                        <div><dt>Visto por último</dt><dd>{{ $resetConfirm['last_seen_at'] ?: '—' }}</dd></div>
                        <div><dt>ID do aparelho</dt><dd class="erp-fv-reset-modal__uuid">{{ $resetConfirm['device_uuid'] }}</dd></div>
                    </dl>

                    <p>
                        Na próxima conexão com o servidor o app apaga toda a base local (catálogo, clientes,
                        pedidos, fila de envio, fotos e sessão) e volta para a tela de login.
                        O aparelho continua autorizado. Pedidos já gravados no ERP não são alterados.
                        Ao concluir, o vínculo com o vendedor é liberado: o próximo login válido
                        define o novo vendedor do aparelho. A autorização vale uma única vez.
                    </p>
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions">
                    <button type="button" wire:click="confirmarResetAparelho" class="erp-pcad-actions__btn erp-pcad-actions__btn--danger">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label">Sim, autorizar reset</span>
                    </button>
                    <button type="button" wire:click="cancelarResetAparelho" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Cancelar</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
