<?php

namespace Unitec\PdvUi\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Unitec\PdvUi\PdvUiServiceProvider;

/**
 * Copia os assets canônicos do pacote (dist/css, dist/js) para o public/ do app
 * atual. Rode no ERP e no PDV offline (build/deploy) para manter os dois com o
 * MESMO CSS/JS do PDV. Fonte única = pacote; cada app só publica.
 */
class PublishPdvUiAssetsCommand extends Command
{
    protected $signature = 'pdv-ui:publish {--force : Sobrescreve arquivos existentes}';

    protected $description = 'Publica o CSS/JS compartilhado do PDV (pacote unitec/pdv-ui) no public/ do app atual';

    public function handle(): int
    {
        $pairs = [
            PdvUiServiceProvider::distPath('css') => public_path('css'),
            PdvUiServiceProvider::distPath('js') => public_path('js'),
        ];

        $copiados = 0;

        foreach ($pairs as $origem => $destino) {
            if (! File::isDirectory($origem)) {
                continue;
            }

            File::ensureDirectoryExists($destino);

            foreach (File::files($origem) as $arquivo) {
                $alvo = $destino.DIRECTORY_SEPARATOR.$arquivo->getFilename();
                File::copy($arquivo->getPathname(), $alvo);
                $this->line('  publicado: '.$arquivo->getFilename());
                $copiados++;
            }
        }

        $this->info("Assets do PDV publicados ({$copiados} arquivo(s)).");

        return self::SUCCESS;
    }
}
