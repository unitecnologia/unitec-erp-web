<?php

namespace Unitec\PdvUi\Support;

/** Cache-bust de assets estáticos sem depender do ERP. */
final class PdvUiAssets
{
    public static function version(string $publicPath): string
    {
        $full = public_path($publicPath);

        if (is_file($full)) {
            return (string) filemtime($full);
        }

        return (string) (config('unitec.versao') ?? config('pdv.versao') ?? '1');
    }
}
