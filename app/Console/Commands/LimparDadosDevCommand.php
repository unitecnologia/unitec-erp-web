<?php

namespace App\Console\Commands;

use App\Models\Boleto;
use App\Models\Carga;
use App\Models\CargaEntrega;
use App\Models\CargaEntregaItem;
use App\Models\CargaPedido;
use App\Models\ClienteCreditoMovimentacao;
use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\DevolucaoCompra;
use App\Models\DevolucaoCompraItem;
use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Models\Person;
use App\Models\PixCobranca;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Limpeza pontual da base DEV (sem migrate:fresh).
 * Remove CR, compras/notas, clientes (exceto CF), empresas 2/3 e users (exceto USUARIO).
 */
class LimparDadosDevCommand extends Command
{
    protected $signature = 'erp:limpar-dados-dev
                            {--dry-run : Apenas conta o que seria apagado}
                            {--force : Executa sem confirmação interativa}';

    protected $description = 'Limpa dados operacionais do DEV (CR, clientes, compras, empresas 2/3, users extras)';

    /** @var array<string, int> */
    private array $counts = [];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Bloqueado: APP_ENV=production. Este comando so roda em desenvolvimento.');

            return self::FAILURE;
        }

        $dbName = (string) config('database.connections.'.config('database.default').'.database');
        $this->info("Banco efetivo: {$dbName} (driver=".config('database.default').')');
        $this->info('APP_ENV='.app()->environment());

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $preview = $this->collectPreview();
        foreach ($preview as $label => $count) {
            $this->line(sprintf('  %-40s %d', $label, $count));
        }

        if ($dryRun) {
            $this->warn('Dry-run: nada foi apagado.');

            return self::SUCCESS;
        }

        if (! $force) {
            if (! $this->confirm('Confirma limpeza DESTRUTIVA deste banco DEV?', false)) {
                $this->warn('Cancelado.');

                return self::SUCCESS;
            }
        }

        try {
            DB::transaction(function (): void {
                $cf = Person::resolveConsumidorFinal();
                if (! Person::isCodigoConsumidorFinal((string) $cf->codigo)) {
                    $cf->forceFill([
                        'codigo' => Person::CODIGO_CONSUMIDOR_FINAL,
                        'nome_razao' => 'CONSUMIDOR FINAL',
                        'is_cliente' => true,
                    ])->save();
                }
                $usuario = $this->ensureUsuarioPadrao();

                $this->purgeContasReceber();
                $this->purgeComprasENotas();
                $this->purgeCargasENfseRestrict();
                $this->purgeClientesExcetoConsumidor($cf->id);
                $this->purgeEmpresasDoisETres();
                $this->purgeUsersExcetoUsuario($usuario->id);

                // Garante vínculo do user padrão à empresa 1 quando existir.
                $empresa1 = Empresa::query()
                    ->where(function ($q): void {
                        $q->where('id', 1)->orWhere('codigo', 1);
                    })
                    ->orderBy('id')
                    ->first();
                if ($empresa1 && (int) $usuario->empresa_id !== (int) $empresa1->id) {
                    $usuario->forceFill(['empresa_id' => $empresa1->id])->save();
                }
            });
        } catch (Throwable $e) {
            $this->error('Falha na limpeza: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Limpeza concluida:');
        foreach ($this->counts as $label => $count) {
            $this->line(sprintf('  %-40s %d', $label, $count));
        }

        $this->assertPosLimpeza();

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function collectPreview(): array
    {
        $cfIds = $this->consumidorIds();
        $empresaIds = $this->empresaIdsDoisETres();

        return [
            'contas_receber' => ContaReceber::query()->count(),
            'boletos (com CR)' => Schema::hasTable('boletos')
                ? Boleto::query()->whereNotNull('conta_receber_id')->count()
                : 0,
            'pix_cobrancas (com CR)' => Schema::hasTable('pix_cobrancas')
                ? PixCobranca::query()->whereNotNull('conta_receber_id')->count()
                : 0,
            'compras' => Compra::query()->count(),
            'notas_fornecedores' => NotaFornecedor::query()->count(),
            'devolucoes_compra' => Schema::hasTable('devolucoes_compra')
                ? DevolucaoCompra::query()->count()
                : 0,
            'contas_pagar (com compra_id)' => ContaPagar::query()->whereNotNull('compra_id')->count(),
            'clientes (exceto CF)' => Person::query()
                ->where('is_cliente', true)
                ->when($cfIds !== [], fn ($q) => $q->whereNotIn('id', $cfIds))
                ->count(),
            'empresas 2/3' => $empresaIds === [] ? 0 : Empresa::query()->whereIn('id', $empresaIds)->count(),
            'users (exceto USUARIO)' => User::query()->where('name', '!=', 'USUARIO')->count(),
        ];
    }

    private function purgeContasReceber(): void
    {
        if (Schema::hasTable('boletos')) {
            $this->counts['boletos'] = Boleto::query()
                ->whereNotNull('conta_receber_id')
                ->delete();
        }

        if (Schema::hasTable('pix_cobrancas')) {
            $this->counts['pix_cobrancas'] = PixCobranca::query()
                ->whereNotNull('conta_receber_id')
                ->delete();
        }

        $this->counts['contas_receber'] = ContaReceber::query()->delete();
    }

    private function purgeComprasENotas(): void
    {
        if (Schema::hasTable('devolucao_compra_itens')) {
            $this->counts['devolucao_compra_itens'] = DevolucaoCompraItem::query()->delete();
        }
        if (Schema::hasTable('devolucoes_compra')) {
            $this->counts['devolucoes_compra'] = DevolucaoCompra::query()->delete();
        }

        // CP vinculada a compra (sem FK) — remove pagamentos via cascade do model/table.
        $cpIds = ContaPagar::query()->whereNotNull('compra_id')->pluck('id');
        if ($cpIds->isNotEmpty() && Schema::hasTable('conta_pagar_pagamentos')) {
            $this->counts['conta_pagar_pagamentos'] = DB::table('conta_pagar_pagamentos')
                ->whereIn('conta_pagar_id', $cpIds)
                ->delete();
        }
        $this->counts['contas_pagar_compra'] = ContaPagar::query()->whereNotNull('compra_id')->delete();

        $this->counts['notas_fornecedores'] = NotaFornecedor::query()->delete();
        $this->counts['compras'] = Compra::query()->delete();
    }

    private function purgeCargasENfseRestrict(): void
    {
        $empresaIds = $this->empresaIdsDoisETres();

        // carga_pedidos restringe delete de vendas (cascade de people) — limpa tudo.
        if (Schema::hasTable('carga_pedidos')) {
            $this->counts['carga_pedidos'] = CargaPedido::query()->delete();
        }
        if (Schema::hasTable('carga_entrega_itens')) {
            $this->counts['carga_entrega_itens'] = CargaEntregaItem::query()->delete();
        }
        if (Schema::hasTable('carga_entregas')) {
            $this->counts['carga_entregas'] = CargaEntrega::query()->delete();
        }
        if (Schema::hasTable('cargas')) {
            $q = Carga::query();
            if ($empresaIds !== []) {
                $q->whereIn('empresa_id', $empresaIds);
            }
            // Também remove cargas órfãs de emp 1 que bloqueariam vendas; plan pede só emp 2/3,
            // mas carga_pedidos já foi zerado. Ainda assim remove cargas das emp 2/3.
            $this->counts['cargas'] = ($empresaIds === [] ? Carga::query() : Carga::query()->whereIn('empresa_id', $empresaIds))->delete();
        }

        if (Schema::hasTable('nfse_itens') && Schema::hasTable('nfses') && $empresaIds !== []) {
            $nfseIds = DB::table('nfses')->whereIn('empresa_id', $empresaIds)->pluck('id');
            if ($nfseIds->isNotEmpty()) {
                $this->counts['nfse_itens'] = DB::table('nfse_itens')->whereIn('nfse_id', $nfseIds)->delete();
                $this->counts['nfses'] = DB::table('nfses')->whereIn('id', $nfseIds)->delete();
            }
        }
        if (Schema::hasTable('nfse_dps_sequencias') && $empresaIds !== []) {
            $this->counts['nfse_dps_sequencias'] = DB::table('nfse_dps_sequencias')
                ->whereIn('empresa_id', $empresaIds)
                ->delete();
        }
    }

    private function purgeClientesExcetoConsumidor(int $cfId): void
    {
        $cfIds = array_values(array_unique(array_filter([...$this->consumidorIds(), $cfId])));

        $clienteIds = Person::query()
            ->where('is_cliente', true)
            ->whereNotIn('id', $cfIds)
            ->pluck('id');

        if ($clienteIds->isEmpty()) {
            $this->counts['clientes'] = 0;

            return;
        }

        if (Schema::hasTable('cliente_credito_movimentacoes')) {
            $this->counts['cliente_credito_movimentacoes'] = ClienteCreditoMovimentacao::query()
                ->whereIn('cliente_id', $clienteIds)
                ->delete();
        }

        $this->counts['clientes'] = Person::query()->whereIn('id', $clienteIds)->delete();
    }

    private function purgeEmpresasDoisETres(): void
    {
        $ids = $this->empresaIdsDoisETres();
        if ($ids === []) {
            $this->counts['empresas_2_3'] = 0;

            return;
        }

        $this->counts['empresas_2_3'] = Empresa::query()->whereIn('id', $ids)->delete();
    }

    private function purgeUsersExcetoUsuario(int $usuarioId): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            $otherIds = User::query()->where('id', '!=', $usuarioId)->pluck('id');
            if ($otherIds->isNotEmpty()) {
                $this->counts['personal_access_tokens'] = DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $otherIds)
                    ->delete();
            }
        }

        $this->counts['users'] = User::query()->where('id', '!=', $usuarioId)->delete();
    }

    private function ensureUsuarioPadrao(): User
    {
        return User::query()->updateOrCreate(
            ['name' => 'USUARIO'],
            [
                'password' => Hash::make('01'),
                'senha' => '01',
                'is_admin' => true,
                'ativo' => true,
            ],
        );
    }

    /**
     * @return list<int>
     */
    private function consumidorIds(): array
    {
        return Person::query()
            ->where(function ($q): void {
                $q->whereIn('codigo', Person::codigosConsumidorFinal())
                    ->orWhereRaw('UPPER(TRIM(nome_razao)) = ?', ['CONSUMIDOR FINAL']);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function empresaIdsDoisETres(): array
    {
        return Empresa::query()
            ->where(function ($q): void {
                $q->whereIn('id', [2, 3])->orWhereIn('codigo', [2, 3]);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function assertPosLimpeza(): void
    {
        $cf = Person::resolveConsumidorFinal();
        $usuarios = User::query()->pluck('name')->all();
        $empresas = Empresa::query()->orderBy('id')->get(['id', 'codigo', 'nome', 'fantasia']);

        $this->newLine();
        $this->info('Pos-limpeza:');
        $this->line('  CF id='.$cf->id.' codigo='.$cf->codigo.' nome='.$cf->nome_razao);
        $this->line('  Users: '.implode(', ', $usuarios));
        $this->line('  Empresas: '.$empresas->map(fn ($e) => "#{$e->id}/cod{$e->codigo} ".($e->fantasia ?: $e->nome))->implode('; '));
        $this->line('  CR restantes: '.ContaReceber::query()->count());
        $this->line('  Compras restantes: '.Compra::query()->count());
        $this->line('  Notas fornecedor restantes: '.NotaFornecedor::query()->count());
        $this->line('  Clientes (is_cliente): '.Person::query()->where('is_cliente', true)->count());
    }
}
