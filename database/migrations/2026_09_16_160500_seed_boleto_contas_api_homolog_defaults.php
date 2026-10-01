<?php

use App\Support\Erp\Boleto\BoletoContaApiDefaults;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garante contas Ailos/Sicredi de homologação (padrão Unitec) em boleto_contas_api.
 * Não sobrescreve conta Sicredi que já tenha Client ID preenchido (cliente em produção).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boleto_contas_api') || ! Schema::hasTable('empresas')) {
            return;
        }

        $empresaIds = DB::table('empresas')->pluck('id');
        $now = now();

        foreach ($empresaIds as $empresaId) {
            $this->ensureConta((int) $empresaId, EmpresaParametros::BOLETO_BANCO_AILOS, BoletoContaApiDefaults::ailosHomologacao(), $now, padraoSeNenhuma: true);
            $this->ensureConta((int) $empresaId, EmpresaParametros::BOLETO_BANCO_SICREDI, BoletoContaApiDefaults::sicrediHomologacao(), $now, padraoSeNenhuma: false);
        }
    }

    public function down(): void
    {
        // Defaults de homologação não são revertidos automaticamente.
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private function ensureConta(int $empresaId, string $banco, array $defaults, $now, bool $padraoSeNenhuma): void
    {
        $existing = DB::table('boleto_contas_api')
            ->where('empresa_id', $empresaId)
            ->where('banco', $banco)
            ->orderByDesc('padrao')
            ->orderBy('id')
            ->first();

        $payload = [
            'nome' => $defaults['nome'] ?? null,
            'ambiente' => $defaults['ambiente'] ?? 'homologacao',
            'client_id' => $defaults['client_id'] ?? null,
            'client_secret' => $defaults['client_secret'] ?? null,
            'dev_app_key' => ($defaults['dev_app_key'] ?? '') !== '' ? $defaults['dev_app_key'] : null,
            'agencia' => $defaults['agencia'] ?? null,
            'agencia_dv' => $defaults['agencia_dv'] ?? null,
            'beneficiario_codigo' => $defaults['beneficiario_codigo'] ?? null,
            'conta' => $defaults['conta'] ?? null,
            'convenio' => $defaults['convenio'] ?? null,
            'carteira' => $defaults['carteira'] ?? null,
            'senha_api' => $defaults['senha_api'] ?? null,
            'api_url' => $defaults['api_url'] ?? null,
            'callback_url' => $defaults['callback_url'] ?? null,
            'especie_documento' => $defaults['especie_documento'] ?? 'DM',
            'pos_vencimento' => $defaults['pos_vencimento'] ?? 'nenhuma',
            'pix_hibrido' => (bool) ($defaults['pix_hibrido'] ?? false),
            'ativo' => true,
            'updated_at' => $now,
        ];

        if ($existing) {
            $clientId = trim((string) ($existing->client_id ?? ''));
            // Conta já configurada pelo cliente: não sobrescreve.
            if ($clientId !== '') {
                return;
            }

            DB::table('boleto_contas_api')->where('id', $existing->id)->update($payload);

            return;
        }

        $temPadrao = DB::table('boleto_contas_api')
            ->where('empresa_id', $empresaId)
            ->where('padrao', true)
            ->exists();

        DB::table('boleto_contas_api')->insert(array_merge($payload, [
            'empresa_id' => $empresaId,
            'banco' => $banco,
            'padrao' => $padraoSeNenhuma && ! $temPadrao,
            'created_at' => $now,
        ]));
    }
};
