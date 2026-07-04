@php
    $cssPath = public_path('css/erp-shell.css');
@endphp

@if (file_exists($cssPath))
    <style id="erp-shell-styles">{!! file_get_contents($cssPath) !!}</style>
@endif
