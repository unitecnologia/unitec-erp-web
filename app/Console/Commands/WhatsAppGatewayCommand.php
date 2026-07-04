<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Support\Erp\WhatsApp\WhatsAppGatewayManager;
use Illuminate\Console\Command;

class WhatsAppGatewayCommand extends Command
{
    protected $signature = 'erp:whatsapp-gateway {--empresa= : ID da empresa para gerar config inicial} {--config-only : Apenas grava gateway-config.json}';

    protected $description = 'Inicia o gateway WhatsApp interno (Baileys) em primeiro plano';

    public function handle(WhatsAppGatewayManager $manager): int
    {
        $empresaId = $this->option('empresa');

        if ($empresaId !== null && $empresaId !== '') {
            $empresa = Empresa::query()->find($empresaId);

            if (! $empresa) {
                $this->error('Empresa não encontrada.');

                return self::FAILURE;
            }

            $manager->writeRuntimeConfig($empresa);
            $this->info('Configuração do gateway gravada em storage/app/whatsapp/gateway-config.json');
        } elseif (! is_file($manager->configPath())) {
            $empresa = Empresa::query()->orderBy('id')->first();

            if ($empresa) {
                $manager->writeRuntimeConfig($empresa);
                $this->info('Configuração inicial gerada a partir da empresa #' . $empresa->id);
            } else {
                $this->warn('Nenhuma empresa cadastrada. O gateway exige gateway-config.json.');
            }
        }

        if ($this->option('config-only')) {
            return self::SUCCESS;
        }

        $gatewayRoot = $manager->gatewayRoot();
        $index = $gatewayRoot . DIRECTORY_SEPARATOR . 'index.js';

        if (! is_file($index)) {
            $this->error('Gateway não encontrado em services/erp-whatsapp-gateway');

            return self::FAILURE;
        }

        if (! is_file($gatewayRoot . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . '@whiskeysockets' . DIRECTORY_SEPARATOR . 'baileys' . DIRECTORY_SEPARATOR . 'package.json')) {
            $this->error('Dependências não instaladas. Rode: cd services/erp-whatsapp-gateway && npm install');

            return self::FAILURE;
        }

        $nodeExecutable = $manager->nodeExecutable();

        if ($nodeExecutable === null) {
            $this->error('Node.js não encontrado. Rode dev-windows.ps1 ou instale Node 20+.');

            return self::FAILURE;
        }

        $this->info('Iniciando gateway WhatsApp (Ctrl+C para encerrar)...');

        $command = [$nodeExecutable, 'index.js'];

        if ($nodeExecutable === 'node') {
            $command = ['node', 'index.js'];
        }

        $process = proc_open(
            implode(' ', array_map(static fn (string $part): string => str_contains($part, ' ') ? '"' . $part . '"' : $part, $command)),
            [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ],
            $pipes,
            $gatewayRoot,
        );

        if (! is_resource($process)) {
            $this->error('Não foi possível iniciar o processo Node.');

            return self::FAILURE;
        }

        $exitCode = proc_close($process);

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
