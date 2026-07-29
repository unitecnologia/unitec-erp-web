@php
    use App\Support\Erp\ErpSystemConfig;

    $fontSizePx = ErpSystemConfig::uiFontSizePx();
@endphp
<meta name="erp-ui-font-size" content="{{ $fontSizePx }}">
<style id="erp-ui-density">
    html {
        font-size: {{ $fontSizePx }}px;
    }
</style>
<script>
    document.documentElement.dataset.erpFontSize = @json((string) $fontSizePx);
</script>
