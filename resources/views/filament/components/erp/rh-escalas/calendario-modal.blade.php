@if ($this->calendarioModalOpen)
    @php
        $meses = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
        $tituloMes = ($meses[$this->calendarioMes] ?? '').' / '.$this->calendarioAno;
        $dias = $this->calendarioDias;
        $offset = count($dias) > 0 ? (int) (\Carbon\Carbon::parse($dias[0]['data'])->dayOfWeek) : 0;
        $cells = array_merge(array_fill(0, $offset, null), $dias);
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }
        $semanas = array_chunk($cells, 7);
    @endphp
    <div class="erp-lookup-modal erp-rh-escala-calendario-modal" wire:keydown.escape.window="closeCalendarioModal">
        <div class="erp-lookup-modal__backdrop" wire:click="closeCalendarioModal"></div>
        <div class="erp-lookup-modal__window erp-rh-escala-calendario-modal__window" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Calendário — {{ $this->calendarioFuncionarioNome }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeCalendarioModal">✕</button>
            </div>
            <div class="erp-lookup-modal__body">
                <div class="erp-rh-escalas-cal__toolbar">
                    <button type="button" class="erp-rh-escalas-cal__nav" wire:click="calendarioMesAnterior" title="Mês anterior">‹</button>
                    <span class="erp-rh-escalas-cal__mes">{{ $tituloMes }}</span>
                    <button type="button" class="erp-rh-escalas-cal__nav" wire:click="calendarioProximoMes" title="Próximo mês">›</button>
                    <button type="button" class="erp-rh-escalas-cal__print" wire:click="imprimirCalendarioMensal">Imprimir mês</button>
                </div>
                <table class="erp-rh-escalas-cal__table">
                    <thead>
                        <tr>
                            @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $hd)
                                <th>{{ $hd }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($semanas as $semana)
                            <tr>
                                @foreach ($semana as $cel)
                                    <td class="erp-rh-escalas-cal__cell @if($cel) erp-rh-escalas-cal__cell--{{ $cel['tipo'] }} @if($cel['ajuste']) erp-rh-escalas-cal__cell--ajuste @endif @endif">
                                        @if ($cel)
                                            <span class="erp-rh-escalas-cal__dia-num">{{ $cel['dia'] }}</span>
                                            <span class="erp-rh-escalas-cal__dia-label">{{ $cel['label'] }}</span>
                                            @if (filled($cel['hora_inicio']))
                                                <span class="erp-rh-escalas-cal__hora">{{ $cel['hora_inicio'] }}–{{ $cel['hora_fim'] ?? '' }}</span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="erp-rh-escalas__hint">Ajustes pontuais aparecem destacados. 12×36 alterna a partir do início do vínculo.</p>
            </div>
        </div>
    </div>
@endif
