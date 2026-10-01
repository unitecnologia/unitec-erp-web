@php
    $record = $getRecord();
    $key = (string) (int) $record->getKey();
    $marcado = in_array($key, $this->selecionados, true);
@endphp

{{--
  Clique: Alpine pinta na hora + syncSelecionado (skipRender).
  Render PHP: ListCargas::table() recordClasses deriva erp-cargas-row--checked de $selecionados.
  pageshow: após F4/F6 (history.back), o morph pode tirar a classe e manter checked — reaplica.
--}}
<input
    type="checkbox"
    class="erp-cargas__check"
    value="{{ $key }}"
    @checked($marcado)
    wire:key="carga-sel-{{ $key }}-{{ $marcado ? '1' : '0' }}"
    x-data
    x-init="
        const paint = () => {
            const row = $el.closest('.fi-ta-row');
            if (row) {
                row.classList.toggle('erp-cargas-row--checked', $el.checked);
            }
        };
        paint();
        window.addEventListener('pageshow', () => {
            paint();
            queueMicrotask(paint);
            setTimeout(paint, 0);
            setTimeout(paint, 50);
        });
    "
    @click.stop="
        const row = $el.closest('.fi-ta-row');
        if (row) {
            row.classList.toggle('erp-cargas-row--checked', $el.checked);
        }
        $wire.syncSelecionado({{ (int) $record->getKey() }}, $el.checked);
    "
    aria-label="Selecionar carga {{ $record->numero }}"
>
