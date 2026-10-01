@php
    $comDesc = ! filter_var($impSemColunaDesconto ?? true, FILTER_VALIDATE_BOOLEAN);
@endphp
<colgroup>
    <col class="col-codigo" style="width: 10%;">
    <col class="col-produto" style="width: {{ $comDesc ? '36%' : '44%' }};">
    <col class="col-un" style="width: 7%;">
    <col class="col-qtd" style="width: 11%;">
    <col class="col-unit" style="width: {{ $comDesc ? '11%' : '13%' }};">
    @if ($comDesc)
        <col class="col-desc" style="width: 11%;">
    @endif
    <col class="col-sub" style="width: {{ $comDesc ? '14%' : '15%' }};">
</colgroup>
