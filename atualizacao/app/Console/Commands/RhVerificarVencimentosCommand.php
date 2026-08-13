<?php

namespace App\Console\Commands;

use App\Models\RhAnexo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RhVerificarVencimentosCommand extends Command
{
    protected $signature = 'rh:verificar-vencimentos {--dias=30 : Janela de dias à frente}';

    protected $description = 'Lista anexos de RH vencendo (documentos, EPI, exames, etc.)';

    public function handle(): int
    {
        if (! Schema::hasTable('rh_anexos')) {
            $this->warn('Tabela rh_anexos ainda não existe.');

            return self::SUCCESS;
        }

        $dias = max(1, (int) $this->option('dias'));
        $vencendo = RhAnexo::query()->vencendoEm($dias)->with('anexavel')->orderBy('valido_ate')->get();
        $vencidos = RhAnexo::query()->vencidos()->with('anexavel')->orderBy('valido_ate')->limit(100)->get();

        $this->info("Anexos vencendo em até {$dias} dia(s): {$vencendo->count()}");
        $this->info('Anexos já vencidos: '.$vencidos->count());

        Log::info('rh.verificar-vencimentos', [
            'dias' => $dias,
            'vencendo' => $vencendo->count(),
            'vencidos' => $vencidos->count(),
        ]);

        return self::SUCCESS;
    }
}
