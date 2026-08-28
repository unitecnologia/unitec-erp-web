<?php

namespace App\Console\Commands;

use App\Support\Erp\Import\FirebirdIsqlClient;
use App\Support\Erp\Import\FirebirdMigraService;
use Illuminate\Console\Command;
use Throwable;

class MigraFirebirdCommand extends Command
{
    protected $signature = 'erp:migra-firebird
                            {--only= : empresa,auxiliares,produtos,pessoas,clientes,contas,formas,vendedores,usuarios,contador,terminais,planos_contas,contas_pagar,conta_pagar_pagamentos,contas_receber,caixa,recibos,ultimos_precos,vendas_parametros,pdv_vendas,pdv_nfce,nfes,compras,notas_fornecedor,pdv_caixa_movimentos (csv)}
                            {--update : Atualiza registros já existentes (padrão: sim)}
                            {--no-update : Não atualiza existentes}
                            {--dry-run : Simula sem gravar}
                            {--ping : Só testa conexão e lista tabelas}';

    protected $description = 'Migra dados do Firebird legado (dados.fdb) para o ERP web';

    public function handle(FirebirdIsqlClient $client, FirebirdMigraService $migra): int
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        if (! config('firebird.enabled') && ! $this->option('ping')) {
            $this->warn('FB_ENABLED=false no .env — ativando só para este comando.');
        }

        try {
            $this->info('Firebird: '.$client->databaseTarget());
            $this->info('isql: '.$client->isqlPath());

            if (! $client->ping()) {
                $this->error('Não foi possível conectar no Firebird.');

                return self::FAILURE;
            }

            $this->info('Conexão OK.');

            if ($this->option('ping')) {
                $tables = $client->listTables();
                $this->line('Tabelas ('.count($tables).'):');
                foreach (array_slice($tables, 0, 40) as $table) {
                    $this->line('  - '.$table);
                }
                if (count($tables) > 40) {
                    $this->line('  …');
                }

                return self::SUCCESS;
            }

            $onlyRaw = trim((string) $this->option('only'));
            $only = $onlyRaw === ''
                ? [
                    'empresa',
                    'auxiliares',
                    'produtos',
                    'pessoas',
                    'contas',
                    'formas',
                    'vendedores',
                    'usuarios',
                    'contador',
                    'terminais',
                    'planos_contas',
                    'contas_pagar',
                    'conta_pagar_pagamentos',
                    'contas_receber',
                    'caixa',
                    'recibos',
                    'ultimos_precos',
                    'vendas_parametros',
                    'pdv_vendas',
                    'pdv_nfce',
                    'nfes',
                    'compras',
                    'notas_fornecedor',
                    'pdv_caixa_movimentos',
                ]
                : array_map('trim', explode(',', $onlyRaw));

            $update = ! $this->option('no-update');
            if ($this->option('update')) {
                $update = true;
            }

            $dryRun = (bool) $this->option('dry-run');
            $prefix = $dryRun ? '[dry-run] ' : '';

            $this->info($prefix.'Migrando: '.implode(', ', $only));

            $result = $migra->migrate($only, $update, $dryRun);

            foreach ($result as $bloco => $stats) {
                if (! is_array($stats)) {
                    continue;
                }

                $this->line(sprintf(
                    '%s%s — criados: %d | atualizados: %d | ignorados: %d%s',
                    $prefix,
                    strtoupper((string) $bloco),
                    (int) ($stats['created'] ?? 0),
                    (int) ($stats['updated'] ?? 0),
                    (int) ($stats['skipped'] ?? 0),
                    isset($stats['empresa_id']) && $stats['empresa_id']
                        ? ' | empresa_id='.$stats['empresa_id']
                        : '',
                ));
            }

            $this->info($prefix.'Concluído.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
