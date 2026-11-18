<?php

namespace Database\Seeders;

use App\Models\BoletoContaApi;
use App\Models\Empresa;
use App\Models\ErpProfile;
use App\Models\OperacaoFiscal;
use App\Models\PlanoConta;
use App\Models\User;
use App\Models\VendasParametro;
use App\Support\Erp\EmpresaVendaProntaBootstrap;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpPermissionCatalog;
use App\Support\Fiscal\FiscalOperationDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class UnitecInitialSeeder extends Seeder
{
    /**
     * Códigos padrão do plano de contas (mesmos da migration / incluir-planos-contas).
     *
     * @var array<string, array{codigo: int, dc: string}>
     */
    public const PLANO_CONTA_PARAM_DEFAULTS = [
        'param_plano_conta_venda_id' => ['codigo' => 101, 'dc' => 'C'],
        'param_plano_conta_compra_id' => ['codigo' => 201, 'dc' => 'D'],
        'param_plano_conta_taxa_cartao_id' => ['codigo' => 209, 'dc' => 'D'],
    ];

    public function run(): void
    {
        // Dump oficial já traz USUARIO + empresa provisória; este seeder é o fallback.
        $this->call(ProductAuxiliarySeeder::class);
        $this->call(FiscalTabelasPadraoSeeder::class);

        User::query()->updateOrCreate(
            ['name' => 'USUARIO'],
            [
                'password' => Hash::make('01'),
                'senha' => '01',
                'empresa_id' => null,
                'is_admin' => true,
                'ativo' => true,
            ],
        );

        $adminProfile = ErpProfile::query()->updateOrCreate(
            ['nome' => 'ADMINISTRADOR'],
            [
                'descricao' => 'Acesso total (modelo)',
                'is_system' => true,
            ],
        );

        ErpAccess::syncProfilePermissions($adminProfile, ErpPermissionCatalog::allKeys());

        ErpProfile::query()->updateOrCreate(
            ['nome' => 'CAIXA'],
            [
                'descricao' => 'Operador de caixa e vendas',
                'is_system' => true,
            ],
        );

        $operacionalKeys = [
            'pdv.access',
            'pdv.print',
            'vendas.access',
            'vendas.print',
            'pessoas.access',
            'produtos.access',
        ];

        $caixaProfile = ErpProfile::query()->where('nome', 'CAIXA')->first();

        if ($caixaProfile) {
            ErpAccess::syncProfilePermissions($caixaProfile, $operacionalKeys);
        }

        // Operador de loja/produção (sem admin): mesmas chaves do CAIXA, perfil distinto.
        ErpProfile::query()->updateOrCreate(
            ['nome' => 'OPERADOR'],
            [
                'descricao' => 'Operador operacional (PDV/vendas) sem acesso administrativo',
                'is_system' => true,
            ],
        );

        $operadorProfile = ErpProfile::query()->where('nome', 'OPERADOR')->first();
        if ($operadorProfile) {
            ErpAccess::syncProfilePermissions($operadorProfile, $operacionalKeys);
        }

        if (is_file(database_path('data/instalador/empresa_unitec.json'))) {
            self::seedEmpresaInstalador();
        }
    }

    /**
     * Empresa Unitec pré-preenchida (todas as abas + relacionados) a partir do snapshot.
     */
    public static function seedEmpresaInstalador(): Empresa
    {
        $payload = self::loadEmpresaInstaladorPayload();
        $empresaData = self::filterColumns('empresas', (array) ($payload['empresa'] ?? []));

        if ($empresaData === []) {
            throw new \RuntimeException('Snapshot do instalador sem dados de empresa.');
        }

        $empresaData = self::sanitizeEmpresaPlanoContaIds($empresaData);

        $logoRelative = self::installEmpresaLogo($empresaData);
        if ($logoRelative !== null) {
            $empresaData['logo_path'] = $logoRelative;
        }

        $empresa = Empresa::query()->updateOrCreate(
            ['id' => 1],
            $empresaData,
        );

        self::seedVendasParametros($empresa, $payload['vendas_parametros'] ?? null);
        self::seedBoletoContasApi($empresa, $payload['boleto_contas_api'] ?? []);
        self::seedOperacoesFiscais($empresa, $payload['operacoes_fiscais'] ?? null);

        $user = User::query()->where('name', 'USUARIO')->first();

        if ($user) {
            $user->forceFill(['empresa_id' => $empresa->id])->save();

            if (! $user->empresas()->whereKey($empresa->id)->exists()) {
                $user->empresas()->attach($empresa->id);
            }
        }

        EmpresaVendaProntaBootstrap::forEmpresa($empresa, $user);

        return $empresa->fresh();
    }

    /**
     * Alias usado por DemoDatabaseSeeder.
     */
    public static function seedDemoEmpresa(): Empresa
    {
        return self::seedEmpresaInstalador();
    }

    /**
     * Resolve FKs de plano pelos códigos oficiais; null se o plano ainda não existir.
     * Evita falha de FK quando o fallback seed roda sem planos_contas populado.
     *
     * @param  array<string, mixed>  $empresaData
     * @return array<string, mixed>
     */
    public static function sanitizeEmpresaPlanoContaIds(array $empresaData): array
    {
        if (! Schema::hasTable('planos_contas')) {
            foreach (array_keys(self::PLANO_CONTA_PARAM_DEFAULTS) as $column) {
                unset($empresaData[$column]);
            }

            return $empresaData;
        }

        foreach (self::PLANO_CONTA_PARAM_DEFAULTS as $column => $match) {
            if (! Schema::hasColumn('empresas', $column)) {
                unset($empresaData[$column]);

                continue;
            }

            $resolvedId = PlanoConta::query()
                ->where('codigo', $match['codigo'])
                ->where('dc', $match['dc'])
                ->where('ativo', true)
                ->value('id');

            if ($resolvedId) {
                $empresaData[$column] = (int) $resolvedId;

                continue;
            }

            $candidateId = (int) ($empresaData[$column] ?? 0);
            if ($candidateId > 0 && PlanoConta::query()->whereKey($candidateId)->exists()) {
                $empresaData[$column] = $candidateId;

                continue;
            }

            $empresaData[$column] = null;
        }

        return $empresaData;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadEmpresaInstaladorPayload(): array
    {
        $path = database_path('data/instalador/empresa_unitec.json');

        if (! is_file($path)) {
            throw new \RuntimeException('Arquivo de snapshot ausente: '.$path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Snapshot do instalador inválido: '.$path);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private static function filterColumns(string $table, array $attrs): array
    {
        if ($attrs === [] || ! Schema::hasTable($table)) {
            return [];
        }

        $columns = array_flip(Schema::getColumnListing($table));
        $filtered = [];

        foreach ($attrs as $key => $value) {
            if (! is_string($key) || ! isset($columns[$key])) {
                continue;
            }

            if (in_array($key, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $empresaData
     */
    private static function installEmpresaLogo(array &$empresaData): ?string
    {
        $fixtureDir = database_path('data/instalador/logo');
        if (! is_dir($fixtureDir)) {
            return null;
        }

        $files = glob($fixtureDir.'/empresa_logo.*') ?: [];
        $source = $files[0] ?? null;
        if (! is_string($source) || ! is_file($source)) {
            return null;
        }

        $ext = pathinfo($source, PATHINFO_EXTENSION) ?: 'png';
        $relative = 'empresa-logos/unitec-instalador.'.$ext;

        try {
            Storage::disk('public')->put($relative, (string) file_get_contents($source));
        } catch (\Throwable) {
            $destDir = storage_path('app/public/empresa-logos');
            if (! is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }
            copy($source, $destDir.'/unitec-instalador.'.$ext);
        }

        return $relative;
    }

    /**
     * @param  array<string, mixed>|null  $attrs
     */
    private static function seedVendasParametros(Empresa $empresa, ?array $attrs): void
    {
        if (! Schema::hasTable('vendas_parametros')) {
            return;
        }

        $row = VendasParametro::forEmpresa((int) $empresa->id);

        if (! is_array($attrs) || $attrs === []) {
            return;
        }

        $filtered = self::filterColumns('vendas_parametros', $attrs);
        unset($filtered['empresa_id']);

        if ($filtered === []) {
            return;
        }

        // certificado_pfx no snapshot já está criptografado (cast encrypted).
        // Gravamos via query para não criptografar de novo.
        $certificadoPfx = $filtered['certificado_pfx'] ?? null;
        unset($filtered['certificado_pfx']);

        if ($filtered !== []) {
            $row->forceFill($filtered)->save();
        }

        if (is_string($certificadoPfx) && $certificadoPfx !== '') {
            \Illuminate\Support\Facades\DB::table('vendas_parametros')
                ->where('empresa_id', $empresa->id)
                ->update(['certificado_pfx' => $certificadoPfx]);
        }

        self::installCertificadoPfx($empresa, (string) ($filtered['caminho_certificado'] ?? $attrs['caminho_certificado'] ?? ''));
    }

    private static function installCertificadoPfx(Empresa $empresa, string $caminho): void
    {
        if ($caminho === '') {
            return;
        }

        $fixture = database_path('data/instalador/certificados/certificado.pfx');
        if (! is_file($fixture)) {
            return;
        }

        $dest = storage_path('app/private/'.$caminho);
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (! is_file($dest)) {
            copy($fixture, $dest);
        }
    }

    /**
     * @param  list<array<string, mixed>>|mixed  $contas
     */
    private static function seedBoletoContasApi(Empresa $empresa, mixed $contas): void
    {
        if (! Schema::hasTable('boleto_contas_api') || ! is_array($contas) || $contas === []) {
            return;
        }

        if (BoletoContaApi::query()->where('empresa_id', $empresa->id)->exists()) {
            return;
        }

        foreach ($contas as $conta) {
            if (! is_array($conta)) {
                continue;
            }

            $filtered = self::filterColumns('boleto_contas_api', $conta);
            if ($filtered === []) {
                continue;
            }

            BoletoContaApi::query()->create([
                ...$filtered,
                'empresa_id' => $empresa->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $attrs
     */
    private static function seedOperacoesFiscais(Empresa $empresa, ?array $attrs): void
    {
        if (! Schema::hasTable('operacoes_fiscais')) {
            return;
        }

        $defaults = is_array($attrs) && $attrs !== []
            ? self::filterColumns('operacoes_fiscais', $attrs)
            : FiscalOperationDefaults::attributesForCreate();

        unset($defaults['empresa_id']);

        OperacaoFiscal::query()->firstOrCreate(
            ['empresa_id' => $empresa->id],
            $defaults !== [] ? $defaults : FiscalOperationDefaults::attributesForCreate(),
        );
    }
}
