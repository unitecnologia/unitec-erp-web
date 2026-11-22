<?php

namespace App\Console\Commands;

use App\Support\Erp\Nfce\NfceScvInconsistenteDesvinculoService;
use Illuminate\Console\Command;

/**
 * DEV: arquiva NFC-e inconsistentes importadas do SCV e libera o pedido/venda para nova emissão manual.
 * Não gera nem transmite NFC-e.
 */
final class NfceDesvincularScvInconsistenteCommand extends Command
{
    protected $signature = 'nfce:desvincular-scv-inconsistente
                            {--empresa=1 : ID da empresa}
                            {--serie=1 : Série da NFC-e}
                            {--numeros=16362,16408,16440,16553,16635,16714 : Números separados por vírgula}
                            {--dry-run : Só analisa e grava relatório (padrão se --apply não for passado)}
                            {--apply : Executa desvinculação elegível em transação}
                            {--autorizado-responsavel : Libera inconsistentes SCV sem cStat SEFAZ quando o responsável confirma não transmissão}
                            {--reverter= : Caminho do relatório JSON (modo=apply) para restaurar vínculos}';

    protected $description = 'Arquiva NFC-e SCV inconsistente e remove vínculo fiscal que impede reemissão (DEV)';

    public function handle(NfceScvInconsistenteDesvinculoService $service): int
    {
        if (app()->environment('production')) {
            $this->error('Bloqueado: APP_ENV=production.');

            return self::FAILURE;
        }

        $reverter = trim((string) $this->option('reverter'));

        if ($reverter !== '') {
            if (! is_file($reverter)) {
                $this->error('Arquivo de relatório não encontrado: '.$reverter);

                return self::FAILURE;
            }

            $restaurados = $service->reverterDoRelatorio($reverter);
            $this->info('Revertidos: '.count($restaurados));
            foreach ($restaurados as $r) {
                $this->line(json_encode($r, JSON_UNESCAPED_UNICODE));
            }

            return self::SUCCESS;
        }

        $empresaId = (int) $this->option('empresa');
        $serie = (string) $this->option('serie');
        $numeros = array_values(array_filter(array_map(
            static fn (string $n): int => (int) trim($n),
            explode(',', (string) $this->option('numeros')),
        )));

        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');
        $autorizado = (bool) $this->option('autorizado-responsavel');

        if ($apply && (bool) $this->option('dry-run')) {
            $this->warn('--dry-run prevalece sobre --apply.');
            $dryRun = true;
        }

        $this->info('Banco: '.config('database.connections.'.config('database.default').'.database'));
        $this->info('APP_ENV='.app()->environment());
        $this->info('Modo: '.($dryRun ? 'dry-run' : 'APPLY'));
        $this->info('Autorização responsável: '.($autorizado ? 'sim' : 'não'));
        $this->info('Empresa '.$empresaId.' série '.$serie.' números: '.implode(', ', $numeros));

        $resultado = $service->executar($empresaId, $serie, $numeros, $dryRun, $autorizado);

        $this->newLine();
        $this->info('Elegíveis: '.count($resultado['elegiveis'] ?? []));
        foreach ($resultado['elegiveis'] ?? [] as $e) {
            $this->line(sprintf(
                '  OK  nº %d  nfce_id=%d  pdv=%d  venda=%s  pedido=%s  — %s',
                $e['numero'],
                $e['nfce_id'],
                $e['pdv_venda_id'],
                $e['venda_id'] ?? '—',
                $e['pedido_numero'] ?? ($e['pedido_id'] ?? '—'),
                $e['motivo_elegibilidade'],
            ));
        }

        if (($resultado['bloqueados'] ?? []) !== []) {
            $this->newLine();
            $this->warn('Bloqueados (não desvinculados): '.count($resultado['bloqueados']));
            foreach ($resultado['bloqueados'] as $b) {
                $this->line(sprintf('  !!  nº %d — %s', $b['numero'], $b['motivo_elegibilidade']));
            }
        }

        if (($resultado['nao_encontrados'] ?? []) !== []) {
            $this->newLine();
            $this->error('Não encontrados: '.implode(', ', $resultado['nao_encontrados']));
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info('Aplicados: '.count($resultado['aplicados']));
            foreach ($resultado['aplicados'] as $a) {
                $antes = $a['antes'];
                $depois = $a['depois'];
                $this->line(sprintf(
                    '  nº %d: pdv %d → arquivo %d | status %s → %s | venda_com_nfce=%s',
                    $antes['numero'],
                    $antes['pdv_venda_id'],
                    $depois['pdv_venda_id'],
                    $antes['status'],
                    $depois['status'],
                    $depois['venda_ainda_com_nfce'] ? 'sim' : 'nao',
                ));
            }
        }

        $this->newLine();
        $this->info('Relatório: '.$resultado['relatorio']);

        return (($resultado['nao_encontrados'] ?? []) === [] && ($dryRun || ($resultado['bloqueados'] ?? []) === []))
            || count($resultado['aplicados'] ?? []) > 0
                ? self::SUCCESS
                : self::FAILURE;
    }
}
