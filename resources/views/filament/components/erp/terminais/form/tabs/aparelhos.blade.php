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
            Confira o código no celular e use <kbd>F2</kbd> para autorizar ou <kbd>F4</kbd> para revogar.
            Aparelhos autorizados passam a contar no limite de telefones em Terminais.
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
                    <th>Plataforma</th>
                    <th>Solicitado</th>
                    <th>Situação</th>
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
                        <td>{{ $aparelho['platform'] ?: '—' }}</td>
                        <td>{{ $aparelho['registered_at'] ?: '—' }}</td>
                        <td>
                            <span @class([
                                'erp-terminais-aparelhos__badge',
                                'erp-terminais-aparelhos__badge--pendente' => $aparelho['situacao'] === 'Pendente',
                                'erp-terminais-aparelhos__badge--ativo' => $aparelho['situacao'] === 'Ativo',
                                'erp-terminais-aparelhos__badge--revogado' => $aparelho['situacao'] === 'Revogado',
                            ])>{{ $aparelho['situacao'] }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="erp-terminais-aparelhos__empty">
                            Nenhum aparelho nesta situação.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
