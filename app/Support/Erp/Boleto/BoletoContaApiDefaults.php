<?php

namespace App\Support\Erp\Boleto;

use App\Models\BoletoContaApi;
use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;

/**
 * Defaults de homologação Unitec (manual Ailos / Sicredi).
 * Novos clientes já nascem prontos para testar; em produção o cliente troca as credenciais.
 */
final class BoletoContaApiDefaults
{
    /**
     * Garante Ailos + Sicredi de homologação para a empresa (não sobrescreve conta já configurada).
     */
    public static function ensureHomologContasForEmpresa(Empresa $empresa): void
    {
        if (! $empresa->exists) {
            return;
        }

        self::ensureContaRow((int) $empresa->id, EmpresaParametros::BOLETO_BANCO_AILOS, self::ailosHomologacao(), padraoSeNenhuma: true);
        self::ensureContaRow((int) $empresa->id, EmpresaParametros::BOLETO_BANCO_SICREDI, self::sicrediHomologacao(), padraoSeNenhuma: false);
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private static function ensureContaRow(int $empresaId, string $banco, array $defaults, bool $padraoSeNenhuma): void
    {
        $existing = BoletoContaApi::query()
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
            'pos_vencimento' => $defaults['pos_vencimento'] ?? EmpresaParametros::BOLETO_POS_VENCIMENTO_NENHUMA,
            'pix_hibrido' => (bool) ($defaults['pix_hibrido'] ?? false),
            'ativo' => true,
        ];

        if ($existing) {
            if (trim((string) ($existing->client_id ?? '')) !== '') {
                return;
            }

            $existing->fill($payload)->save();

            return;
        }

        $temPadrao = BoletoContaApi::query()
            ->where('empresa_id', $empresaId)
            ->where('padrao', true)
            ->exists();

        BoletoContaApi::query()->create(array_merge($payload, [
            'empresa_id' => $empresaId,
            'banco' => $banco,
            'padrao' => $padraoSeNenhuma && ! $temPadrao,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public static function forBanco(string $banco): array
    {
        $banco = preg_replace('/\D/', '', $banco) ?? '';

        return match ($banco) {
            EmpresaParametros::BOLETO_BANCO_SICREDI => self::sicrediHomologacao(),
            EmpresaParametros::BOLETO_BANCO_AILOS => self::ailosHomologacao(),
            default => self::base(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function ailosHomologacao(): array
    {
        return array_merge(self::base(), [
            'nome' => 'Ailos',
            'banco' => EmpresaParametros::BOLETO_BANCO_AILOS,
            'ambiente' => 'homologacao',
            'client_id' => '_4BfqhqA51q72rCI_oC4mjWjQ1ga',
            'client_secret' => 'lBieybBTY5iiwXGfI4ft4fPueIAa',
            'dev_app_key' => 'ca905590-e8da-029c-e053-0a2918728117',
            'agencia' => '9',
            'conta' => '99516470',
            'convenio' => '2',
            'carteira' => '1',
            'senha_api' => 'aaaaa11111@',
            'api_url' => 'https://apiendpointhml.ailos.coop.br',
            'callback_url' => EmpresaParametros::BOLETO_AILOS_AUTH_CALLBACK_URL,
            'especie_documento' => 'DM',
            'pix_hibrido' => true,
        ]);
    }

    /**
     * Sandbox oficial Sicredi (developers.sicredi.com.br) + Client ID/Secret da app Unitec.
     *
     * @return array<string, mixed>
     */
    public static function sicrediHomologacao(): array
    {
        return array_merge(self::base(), [
            'nome' => 'Sicredi',
            'banco' => EmpresaParametros::BOLETO_BANCO_SICREDI,
            'ambiente' => 'homologacao',
            // Client ID/Secret da app Unitec no portal (referência).
            // x-api-key = Access Token (Minhas Apps) — preencher em dev_app_key após liberação Sicredi.
            'client_id' => '1deded5c-457a-414d-a361-7a4393cf5abb',
            'client_secret' => '2aa11fc1-36cf-4fb4-ae20-8b59840f4833',
            'dev_app_key' => '',
            // Dados de teste do Sandbox (manual Sicredi).
            'agencia' => '6789',
            'agencia_dv' => '03',
            'beneficiario_codigo' => '12345',
            'senha_api' => 'teste123',
            'api_url' => 'https://api-parceiro.sicredi.com.br/sb',
            'especie_documento' => 'DM',
            'pix_hibrido' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function base(): array
    {
        return [
            'nome' => '',
            'banco' => EmpresaParametros::BOLETO_BANCO_AILOS,
            'ativo' => true,
            'padrao' => false,
            'ambiente' => 'homologacao',
            'convenio' => '',
            'carteira' => '',
            'agencia' => '',
            'agencia_dv' => '',
            'conta' => '',
            'conta_dv' => '',
            'beneficiario_codigo' => '',
            'client_id' => '',
            'client_secret' => '',
            'dev_app_key' => '',
            'senha_api' => '',
            'api_url' => '',
            'callback_url' => EmpresaParametros::BOLETO_AILOS_AUTH_CALLBACK_URL,
            'especie_documento' => 'DM',
            'instrucao1' => '',
            'instrucao2' => '',
            'juros_pct' => '',
            'multa_pct' => '',
            'desconto_pct' => '',
            'protesto_dias' => '',
            'pos_vencimento' => EmpresaParametros::BOLETO_POS_VENCIMENTO_NENHUMA,
            'pix_hibrido' => false,
        ];
    }
}
