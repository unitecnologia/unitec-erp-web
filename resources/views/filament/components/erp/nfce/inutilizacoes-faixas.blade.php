@if ($this->statusFilter === \App\Models\PdvVendaNfce::TAB_INUTILIZADOS)
    @php
        $faixas = $this->nfceInutilizacoesFaixas;
    @endphp
    <section class="erp-nfce-inut" aria-labelledby="erp-nfce-inut-title">
        <header class="erp-nfce-inut__header">
            <h3 id="erp-nfce-inut-title" class="erp-nfce-inut__title">Faixas inutilizadas na SEFAZ</h3>
            <span class="erp-nfce-inut__hint">Inclui números que não chegaram a gerar NFC-e · período pela data da inutilização</span>
        </header>

        @if ($faixas === [])
            <p class="erp-nfce-inut__empty">Nenhuma faixa inutilizada no período.</p>
        @else
            <div class="erp-nfce-inut__scroll">
                <table class="erp-nfce-inut__table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th class="num">Série</th>
                            <th class="num">Faixa</th>
                            <th>Protocolo</th>
                            <th class="num">cStat</th>
                            <th>Ambiente</th>
                            <th>Usuário</th>
                            <th>Justificativa</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($faixas as $faixa)
                            @php
                                $ini = (int) $faixa['numero_inicial'];
                                $fim = (int) $faixa['numero_final'];
                            @endphp
                            <tr wire:key="nfce-inut-{{ $faixa['id'] }}">
                                <td>{{ $faixa['created_at'] ? \Illuminate\Support\Carbon::parse($faixa['created_at'])->format('d/m/Y H:i') : '—' }}</td>
                                <td class="num">{{ $faixa['serie'] }}</td>
                                <td class="num">{{ $ini === $fim ? $ini : $ini.' a '.$fim }}</td>
                                <td>{{ $faixa['protocolo'] ?: '—' }}</td>
                                <td class="num">{{ $faixa['status_codigo'] ?: '—' }}</td>
                                <td>{{ (string) $faixa['ambiente'] === '1' ? 'Produção' : 'Homologação' }}</td>
                                <td>{{ $faixa['usuario'] ?: '—' }}</td>
                                <td class="erp-nfce-inut__just" title="{{ $faixa['justificativa'] }}">{{ $faixa['justificativa'] ?: '—' }}</td>
                                <td class="erp-nfce-inut__acao">
                                    @if ($faixa['tem_xml'])
                                        <button
                                            type="button"
                                            class="erp-nfce-inut__xml"
                                            wire:click="baixarXmlInutilizacao({{ (int) $faixa['id'] }})"
                                            wire:loading.attr="disabled"
                                        >XML</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endif
