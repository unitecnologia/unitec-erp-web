<?php

namespace App\Console\Commands;

use App\Models\PixCobranca;
use App\Support\Pix\PixCobrancaService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Consulta cobranças Pix pendentes no provedor (Ailos/MP) e baixa quando CONCLUIDA/approved.
 */
final class PixConsultarPendentesCommand extends Command
{
    protected $signature = 'pix:consultar-pendentes {--limit=100 : Máximo de cobranças por execução}';

    protected $description = 'Consulta status de cobranças Pix pendentes e registra pagamento quando confirmado';

    public function handle(PixCobrancaService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $pendentes = PixCobranca::query()
            ->where('status', PixCobranca::STATUS_PENDENTE)
            ->whereNotNull('provider_ref')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $ok = 0;
        $pagos = 0;
        $erros = 0;

        foreach ($pendentes as $cobranca) {
            try {
                $antes = $cobranca->status;
                $atualizada = $service->atualizarStatus($cobranca);
                $ok++;
                if ($antes !== PixCobranca::STATUS_PAGO && $atualizada->isPago()) {
                    $pagos++;
                }
            } catch (Throwable $e) {
                $erros++;
                $this->warn('Pix #'.$cobranca->id.': '.$e->getMessage());
            }
        }

        $this->info("Consultadas={$ok} pagas={$pagos} erros={$erros}");

        return self::SUCCESS;
    }
}
