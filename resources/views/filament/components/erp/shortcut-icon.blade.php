@php
    $shortcutImageFile = public_path($shortcut['image']);
    $shortcutImageVersion = is_file($shortcutImageFile)
        ? filemtime($shortcutImageFile)
        : \App\Support\Erp\ErpAssetVersion::bundle();
@endphp
<span class="erp-shortcut__icon">
    <img
        src="{{ asset($shortcut['image']) }}?v={{ $shortcutImageVersion }}"
        alt=""
        class="erp-shortcut__img"
        loading="lazy"
        decoding="async"
    />
</span>
