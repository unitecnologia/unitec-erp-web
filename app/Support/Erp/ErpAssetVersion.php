<?php

namespace App\Support\Erp;

class ErpAssetVersion
{
    /**
     * Versão do bundle autenticado — filemtime dos CSS principais (cache bust quando shell muda).
     */
    public static function bundle(): string
    {
        static $version = null;

        if ($version === null) {
            $files = [
                public_path('css/erp-tokens.css'),
                public_path('css/erp-shell.css'),
                public_path('css/erp-home.css'),
                public_path('js/erp-shell.js'),
                public_path('js/erp-home-charts.js'),
            ];

            foreach (glob(public_path('css/erp-*.css')) ?: [] as $path) {
                $files[] = $path;
            }

            foreach (glob(public_path('js/erp-*.js')) ?: [] as $path) {
                $files[] = $path;
            }

            $mtime = 0;

            foreach ($files as $path) {
                if (file_exists($path)) {
                    $mtime = max($mtime, (int) filemtime($path));
                }
            }

            $shortcutDir = public_path('img/erp/shortcuts');
            if (is_dir($shortcutDir)) {
                foreach (glob($shortcutDir . DIRECTORY_SEPARATOR . '*.png') ?: [] as $icon) {
                    $mtime = max($mtime, (int) filemtime($icon));
                }
            }

            $version = $mtime > 0 ? (string) $mtime : (string) time();
        }

        return $version;
    }
}
