<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Import\FirebirdIsqlClient;
use App\Support\Erp\Import\FirebirdMigraService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class MigraFirebirdPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $title = '';

    protected static ?string $slug = 'migra-dados-fb';

    protected static bool $shouldRegisterNavigation = false;

    public string $database = '';

    public string $username = '';

    public string $password = 'masterkey';

    public string $host = '';

    public string $port = '3050';

    public bool $optEmpresa = true;

    public bool $optProdutos = true;

    public bool $optClientes = true;

    public bool $optAuxiliares = true;

    public bool $optContas = true;

    public bool $optFormas = true;

    public bool $optVendedores = true;

    public bool $optUsuarios = true;

    public bool $optContador = true;

    public bool $optTerminais = true;

    public bool $optContasPagar = true;

    public bool $optContasReceber = true;

    public bool $optCaixa = true;

    public bool $optPdvVendas = true;

    public bool $optPdvNfce = true;

    public bool $optNfes = true;

    public bool $optCompras = true;

    public bool $optNotasFornecedor = true;

    public bool $optPdvCaixaMovimentos = true;

    public bool $optPlanosContas = true;

    public bool $optContaPagarPagamentos = true;

    public bool $optUltimosPrecos = true;

    public bool $optVendasParametros = true;

    public bool $updateExisting = true;

    public string $statusMsg = '';

    public string $statusTipo = '';

    /** @var list<string> */
    public array $logLines = [];

    public bool $progressActive = false;

    public int $progressPct = 0;

    public string $progressLabel = '';

    public string $progressDetail = '';

    public bool $progressDryRun = false;

    /** @var array<string, array{created: int, updated: int, skipped: int}> */
    public array $progressStats = [];

    public function mount(): void
    {
        ErpScreen::set('Migra dados FB');

        $this->database = (string) config('firebird.database', '');
        $this->username = (string) config('firebird.username', 'SYSDBA');
        $pwd = trim((string) config('firebird.password', ''));
        $this->password = $pwd !== '' ? $pwd : 'masterkey';
        $this->host = (string) config('firebird.host', 'localhost');
        $this->port = (string) config('firebird.port', 3050);
    }

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('migra_firebird.access');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-comando-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.migra-firebird.screen'),
            ]);
    }

    public function testarConexao(): void
    {
        $this->statusMsg = '';
        $this->statusTipo = '';
        $this->logLines = [];

        try {
            $client = $this->makeClient();
            $client->ping();
            $tables = $client->listTables();

            $this->statusTipo = 'ok';
            $this->statusMsg = 'Conexão OK com o Firebird ('.count($tables).' tabelas).';
            $this->logLines = array_slice($tables, 0, 30);

            Notification::make()
                ->title('Conexão Firebird OK')
                ->body(count($tables).' tabelas encontradas.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * Inicia migração por etapas (barra de progresso via Alpine).
     *
     * @return array{ok: bool, dry_run: bool, steps: list<array{key: string, label: string}>, message?: string}
     */
    public function prepararMigracao(bool $dryRun = false): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $only = $this->selectedOnly();

        if ($only === []) {
            $this->fail('Marque ao menos um item para migrar.');

            return ['ok' => false, 'dry_run' => $dryRun, 'steps' => [], 'message' => 'Nenhum item marcado.'];
        }

        try {
            $this->makeClient()->ping();
        } catch (Throwable $e) {
            $this->fail($e->getMessage());

            return ['ok' => false, 'dry_run' => $dryRun, 'steps' => [], 'message' => $e->getMessage()];
        }

        $labels = $this->domainLabels();
        $migra = $this->makeMigraService($this->makeClient());
        $domains = $migra->expandDomains($only);

        $steps = [];
        foreach ($domains as $key) {
            $steps[] = [
                'key' => $key,
                'label' => $labels[$key] ?? strtoupper($key),
            ];
        }

        $this->progressActive = true;
        $this->progressPct = 0;
        $this->progressLabel = $dryRun ? 'Preparando simulação…' : 'Preparando migração…';
        $this->progressDetail = '0 / '.count($steps).' etapas';
        $this->progressDryRun = $dryRun;
        $this->progressStats = [];
        $this->statusMsg = '';
        $this->statusTipo = '';
        $this->logLines = [];

        return [
            'ok' => true,
            'dry_run' => $dryRun,
            'steps' => $steps,
        ];
    }

    /**
     * @return array{ok: bool, lines: list<string>, message?: string}
     */
    public function executarPasso(string $domain): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        $labels = $this->domainLabels();
        $label = $labels[$domain] ?? strtoupper($domain);
        $this->progressLabel = ($this->progressDryRun ? 'Simulando' : 'Migrando').": {$label}…";

        try {
            $only = $this->selectedOnly();
            $migra = $this->makeMigraService($this->makeClient());
            $result = $migra->migrateDomain($domain, $only, $this->updateExisting, $this->progressDryRun);

            foreach ($result as $bloco => $stats) {
                if (! is_array($stats)) {
                    continue;
                }
                $this->accumulateStats((string) $bloco, $stats);
            }

            return ['ok' => true, 'lines' => $this->formatStatsLines()];
        } catch (Throwable $e) {
            $this->progressActive = false;
            $this->fail($e->getMessage());

            return ['ok' => false, 'lines' => $this->logLines, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, done: bool, next_skip: int, fetched: int, lines: list<string>, message?: string}
     */
    public function executarLoteProdutos(int $skip = 0): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        $lote = (int) floor($skip / FirebirdMigraService::PRODUTO_BATCH) + 1;
        $this->progressLabel = ($this->progressDryRun ? 'Simulando' : 'Migrando').": Produtos (lote {$lote})…";

        try {
            $migra = $this->makeMigraService($this->makeClient());
            $part = $migra->migrateProdutosLote($skip, $this->updateExisting, $this->progressDryRun);
            $this->accumulateStats('produtos', $part);

            $processados = $part['next_skip'];
            if ($part['done'] && $part['fetched'] === 0 && $skip === 0) {
                $this->progressDetail = 'Produtos: nenhum registro';
            } else {
                $this->progressDetail = sprintf(
                    'Produtos: ~%d processados%s',
                    $part['done'] ? max(0, $processados - FirebirdMigraService::PRODUTO_BATCH + $part['fetched']) : $processados,
                    $part['done'] ? ' (concluído)' : '…',
                );
            }

            return [
                'ok' => true,
                'done' => (bool) $part['done'],
                'next_skip' => (int) $part['next_skip'],
                'fetched' => (int) $part['fetched'],
                'lines' => $this->formatStatsLines(),
            ];
        } catch (Throwable $e) {
            $this->progressActive = false;
            $this->fail($e->getMessage());

            return [
                'ok' => false,
                'done' => true,
                'next_skip' => $skip,
                'fetched' => 0,
                'lines' => $this->logLines,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, done: bool, next_skip: int, fetched: int, processed: int, total: int|null, lines: list<string>, message?: string}
     */
    public function executarLotePdvNfce(int $skip = 0): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        $lote = (int) floor($skip / FirebirdMigraService::PDV_NFCE_BATCH) + 1;
        $this->progressLabel = ($this->progressDryRun ? 'Simulando' : 'Migrando').": NFC-e PDV (lote {$lote})…";

        try {
            $migra = $this->makeMigraService($this->makeClient());
            $part = $migra->migratePdvNfceLote($skip, $this->updateExisting, $this->progressDryRun);
            $this->accumulateStats('pdv_nfce', $part);

            $processed = (int) ($part['processed'] ?? $skip + (int) ($part['fetched'] ?? 0));
            $total = $part['total'] ?? null;

            if ($part['done'] && (int) ($part['fetched'] ?? 0) === 0 && $skip === 0) {
                $this->progressDetail = 'NFC-e: nenhum registro';
            } elseif (is_int($total) && $total > 0) {
                $this->progressDetail = sprintf(
                    'NFC-e: %d / %d%s',
                    min($processed, $total),
                    $total,
                    $part['done'] ? ' (concluído)' : '…',
                );
            } else {
                $this->progressDetail = sprintf(
                    'NFC-e: %d processados%s',
                    $processed,
                    $part['done'] ? ' (concluído)' : '…',
                );
            }

            return [
                'ok' => true,
                'done' => (bool) $part['done'],
                'next_skip' => (int) $part['next_skip'],
                'fetched' => (int) $part['fetched'],
                'processed' => $processed,
                'total' => is_int($total) ? $total : null,
                'lines' => $this->formatStatsLines(),
            ];
        } catch (Throwable $e) {
            $this->progressActive = false;
            $this->fail($e->getMessage());

            return [
                'ok' => false,
                'done' => true,
                'next_skip' => $skip,
                'fetched' => 0,
                'processed' => $skip,
                'total' => null,
                'lines' => $this->logLines,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, done: bool, next_skip: int, fetched: int, processed: int, total: int|null, lines: list<string>, message?: string}
     */
    public function executarLoteNfes(int $skip = 0): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        $lote = (int) floor($skip / FirebirdMigraService::NFE_BATCH) + 1;
        $this->progressLabel = ($this->progressDryRun ? 'Simulando' : 'Migrando').": NF-e (lote {$lote})…";

        try {
            $migra = $this->makeMigraService($this->makeClient());
            $part = $migra->migrateNfeLote($skip, $this->updateExisting, $this->progressDryRun);
            $this->accumulateStats('nfes', $part);

            $processed = (int) ($part['processed'] ?? $skip + (int) ($part['fetched'] ?? 0));
            $total = $part['total'] ?? null;

            if ($part['done'] && (int) ($part['fetched'] ?? 0) === 0 && $skip === 0) {
                $this->progressDetail = 'NF-e: nenhum registro';
            } elseif (is_int($total) && $total > 0) {
                $this->progressDetail = sprintf(
                    'NF-e: %d / %d%s',
                    min($processed, $total),
                    $total,
                    $part['done'] ? ' (concluído)' : '…',
                );
            } else {
                $this->progressDetail = sprintf(
                    'NF-e: %d processados%s',
                    $processed,
                    $part['done'] ? ' (concluído)' : '…',
                );
            }

            return [
                'ok' => true,
                'done' => (bool) $part['done'],
                'next_skip' => (int) $part['next_skip'],
                'fetched' => (int) $part['fetched'],
                'processed' => $processed,
                'total' => is_int($total) ? $total : null,
                'lines' => $this->formatStatsLines(),
            ];
        } catch (Throwable $e) {
            $this->progressActive = false;
            $this->fail($e->getMessage());

            return [
                'ok' => false,
                'done' => true,
                'next_skip' => $skip,
                'fetched' => 0,
                'processed' => $skip,
                'total' => null,
                'lines' => $this->logLines,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function atualizarProgresso(int $pct, string $label, string $detail = ''): void
    {
        $this->progressPct = max(0, min(100, $pct));
        $this->progressLabel = $label;
        if ($detail !== '') {
            $this->progressDetail = $detail;
        }
    }

    public function finalizarMigracao(): void
    {
        $dryRun = $this->progressDryRun;
        $this->progressActive = false;
        $this->progressPct = 100;
        $this->progressLabel = $dryRun ? 'Simulação concluída' : 'Migração concluída';
        $this->progressDetail = '';
        $this->logLines = $this->formatStatsLines();
        $this->statusTipo = 'ok';
        $this->statusMsg = $dryRun
            ? 'Simulação concluída. Nada foi gravado.'
            : 'Migração concluída com sucesso.';

        Notification::make()
            ->title($dryRun ? 'Simulação OK' : 'Migração concluída')
            ->body(implode(' · ', $this->logLines) ?: 'Sem alterações.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    protected function domainLabels(): array
    {
        return [
            'contas' => 'Contas caixa',
            'empresa' => 'Empresa',
            'auxiliares' => 'Grupos / Marcas / Unidades',
            'produtos' => 'Produtos + estoque',
            'clientes' => 'Pessoas (clientes, fornecedores…)',
            'pessoas' => 'Pessoas (clientes, fornecedores…)',
            'formas' => 'Formas de pagamento',
            'vendedores' => 'Vendedores',
            'usuarios' => 'Usuários',
            'contador' => 'Contador',
            'terminais' => 'Terminais / PDV',
            'contas_pagar' => 'Contas a pagar',
            'conta_pagar_pagamentos' => 'Baixas contas a pagar',
            'contas_receber' => 'Contas a receber',
            'planos_contas' => 'Plano de contas',
            'caixa' => 'Caixa (lançamentos)',
            'ultimos_precos' => 'Últimos preços produtos',
            'vendas_parametros' => 'Parâmetros fiscais',
            'pdv_vendas' => 'Vendas PDV',
            'pdv_nfce' => 'NFC-e PDV',
            'nfes' => 'NF-e (modelo 55)',
            'compras' => 'Compras',
            'notas_fornecedor' => 'Notas de compra (DF-e)',
            'pdv_caixa_movimentos' => 'Movimentos caixa PDV',
        ];
    }

    /**
     * @param  array{created?: int, updated?: int, skipped?: int}  $stats
     */
    protected function accumulateStats(string $bloco, array $stats): void
    {
        $prev = $this->progressStats[$bloco] ?? ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $this->progressStats[$bloco] = [
            'created' => $prev['created'] + (int) ($stats['created'] ?? 0),
            'updated' => $prev['updated'] + (int) ($stats['updated'] ?? 0),
            'skipped' => $prev['skipped'] + (int) ($stats['skipped'] ?? 0),
        ];
        $this->logLines = $this->formatStatsLines();
    }

    /**
     * @return list<string>
     */
    protected function formatStatsLines(): array
    {
        $prefix = $this->progressDryRun ? '[simulação] ' : '';
        $lines = [];

        foreach ($this->progressStats as $bloco => $stats) {
            $lines[] = sprintf(
                '%s%s — criados: %d | atualizados: %d | ignorados: %d',
                $prefix,
                strtoupper((string) $bloco),
                (int) ($stats['created'] ?? 0),
                (int) ($stats['updated'] ?? 0),
                (int) ($stats['skipped'] ?? 0),
            );
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    protected function selectedOnly(): array
    {
        $only = [];

        if ($this->optEmpresa) {
            $only[] = 'empresa';
        }

        if ($this->optAuxiliares) {
            $only[] = 'auxiliares';
        }

        if ($this->optProdutos) {
            $only[] = 'produtos';
        }

        if ($this->optClientes) {
            // Padrão: todas as pessoas do FB (cliente, fornecedor, etc.), não só CLI='S'.
            $only[] = 'pessoas';
        }

        if ($this->optContas) {
            $only[] = 'contas';
        }

        if ($this->optFormas) {
            $only[] = 'formas';
        }

        if ($this->optVendedores) {
            $only[] = 'vendedores';
        }

        if ($this->optUsuarios) {
            $only[] = 'usuarios';
        }

        if ($this->optContador) {
            $only[] = 'contador';
        }

        if ($this->optTerminais) {
            $only[] = 'terminais';
        }

        if ($this->optContasPagar) {
            $only[] = 'contas_pagar';
        }

        if ($this->optContasReceber) {
            $only[] = 'contas_receber';
        }

        if ($this->optCaixa) {
            $only[] = 'caixa';
        }

        if ($this->optPdvVendas) {
            $only[] = 'pdv_vendas';
        }

        if ($this->optPdvNfce) {
            $only[] = 'pdv_nfce';
        }

        if ($this->optNfes) {
            $only[] = 'nfes';
        }

        if ($this->optCompras) {
            $only[] = 'compras';
        }

        if ($this->optNotasFornecedor) {
            $only[] = 'notas_fornecedor';
        }

        if ($this->optPdvCaixaMovimentos) {
            $only[] = 'pdv_caixa_movimentos';
        }

        if ($this->optPlanosContas) {
            $only[] = 'planos_contas';
        }

        if ($this->optContaPagarPagamentos) {
            $only[] = 'conta_pagar_pagamentos';
        }

        if ($this->optUltimosPrecos) {
            $only[] = 'ultimos_precos';
        }

        if ($this->optVendasParametros) {
            $only[] = 'vendas_parametros';
        }

        return $only;
    }

    protected function makeMigraService(FirebirdIsqlClient $client): FirebirdMigraService
    {
        return new FirebirdMigraService(
            $client,
            app(\App\Support\Erp\Import\FirebirdEmpresaImportService::class),
            app(\App\Support\Erp\Import\FirebirdProductImportService::class),
            app(\App\Support\Erp\Import\FirebirdPersonImportService::class),
            app(\App\Support\Erp\Import\FirebirdCadastrosImportService::class),
            app(\App\Support\Erp\Import\FirebirdContaPagarImportService::class),
            app(\App\Support\Erp\Import\FirebirdContaReceberImportService::class),
            app(\App\Support\Erp\Import\FirebirdCaixaLancamentoImportService::class),
            app(\App\Support\Erp\Import\FirebirdPdvVendaImportService::class),
            app(\App\Support\Erp\Import\FirebirdPdvNfceImportService::class),
            app(\App\Support\Erp\Import\FirebirdNfeImportService::class),
            app(\App\Support\Erp\Import\FirebirdPdvCaixaMovimentoImportService::class),
            app(\App\Support\Erp\Import\FirebirdPlanoContaImportService::class),
            app(\App\Support\Erp\Import\FirebirdContaPagarPagamentoImportService::class),
            app(\App\Support\Erp\Import\FirebirdProdUltimosPrecosImportService::class),
            app(\App\Support\Erp\Import\FirebirdVendasParametroImportService::class),
            app(\App\Support\Erp\Import\FirebirdCompraImportService::class),
            app(\App\Support\Erp\Import\FirebirdNotaFornecedorImportService::class),
        );
    }

    protected function makeClient(): FirebirdIsqlClient
    {
        $config = config('firebird', []);
        $config['database'] = trim($this->database) !== '' ? trim($this->database) : ($config['database'] ?? '');
        $config['username'] = trim($this->username) !== '' ? trim($this->username) : ($config['username'] ?? 'SYSDBA');
        $config['host'] = trim($this->host) !== '' ? trim($this->host) : ($config['host'] ?? 'localhost');
        $config['port'] = (int) (trim($this->port) !== '' ? $this->port : ($config['port'] ?? 3050));
        $config['password'] = (string) $this->password;
        $config['use_embedded'] = trim($this->host) === ''
            ? (bool) ($config['use_embedded'] ?? false)
            : false;

        if ($config['database'] === '') {
            throw new \RuntimeException('Informe o caminho do arquivo .fdb.');
        }

        return new FirebirdIsqlClient($config);
    }

    protected function fail(string $message): void
    {
        $this->progressActive = false;
        $this->statusTipo = 'erro';
        $this->statusMsg = $message;

        Notification::make()
            ->title('Falha na migração Firebird')
            ->body($message)
            ->danger()
            ->send();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }
}
