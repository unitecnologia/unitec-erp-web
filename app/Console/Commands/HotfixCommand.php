<?php

namespace App\Console\Commands;

use App\Support\Erp\Hotfix\HotfixService;
use Illuminate\Console\Command;

class HotfixCommand extends Command
{
    protected $signature = 'unitec:hotfix
        {--agendado : Execução do agendador: pacote negado pelo portal só é reconsultado após 1 hora}
        {--reverter : Reverte o último hotfix aplicado}
        {--verificar= : (interno) JSON com classes a carregar após aplicar}';

    protected $description = 'Verifica e aplica o hotfix autorizado pelo portal para a versão instalada (sem alterar a versão)';

    public function handle(HotfixService $service): int
    {
        @set_time_limit(0);

        $verificar = (string) ($this->option('verificar') ?? '');

        if ($verificar !== '') {
            $classes = json_decode((string) @file_get_contents($verificar), true);

            return is_array($classes) && HotfixService::verificarClasses(array_values(array_map('strval', $classes)))
                ? self::SUCCESS
                : self::FAILURE;
        }

        $resultado = $this->option('reverter')
            ? $service->reverterUltimo()
            : $service->executar(agendado: (bool) $this->option('agendado'));

        $this->line('['.$resultado['resultado'].'] '.$resultado['mensagem']);

        return in_array($resultado['resultado'], ['erro', 'falhou'], true) ? self::FAILURE : self::SUCCESS;
    }
}
