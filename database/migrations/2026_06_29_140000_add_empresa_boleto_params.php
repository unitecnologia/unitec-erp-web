<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos da aba "API Boleto" (Empresa > Parâmetros). Centraliza no ERP as
 * credenciais e parâmetros de cobrança registrada via API do banco, no mesmo
 * padrão usado para a API PIX (multi-banco, por empresa).
 */
return new class extends Migration
{
    /**
     * @var array<string, string> coluna => tipo (string|text|boolean)
     */
    private array $colunas = [
        'param_boleto_banco' => 'string',
        'param_boleto_ambiente' => 'string',
        'param_boleto_convenio' => 'string',
        'param_boleto_carteira' => 'string',
        'param_boleto_variacao_carteira' => 'string',
        'param_boleto_agencia' => 'string',
        'param_boleto_agencia_dv' => 'string',
        'param_boleto_conta' => 'string',
        'param_boleto_conta_dv' => 'string',
        'param_boleto_beneficiario_codigo' => 'string',
        'param_boleto_client_id' => 'text',
        'param_boleto_client_secret' => 'text',
        'param_boleto_dev_app_key' => 'text',
        'param_boleto_oauth_scope' => 'string',
        'param_boleto_api_url' => 'string',
        'param_boleto_certificado' => 'string',
        'param_boleto_certificado_senha' => 'string',
        'param_boleto_nosso_numero_inicial' => 'string',
        'param_boleto_especie_documento' => 'string',
        'param_boleto_local_pagamento' => 'string',
        'param_boleto_instrucao1' => 'string',
        'param_boleto_instrucao2' => 'string',
        'param_boleto_juros_pct' => 'string',
        'param_boleto_multa_pct' => 'string',
        'param_boleto_desconto_pct' => 'string',
        'param_boleto_carencia_dias' => 'string',
        'param_boleto_protesto_dias' => 'string',
        'param_boleto_baixa_dias' => 'string',
        'param_boleto_habilitar' => 'boolean',
        'param_boleto_registrar_automatico' => 'boolean',
        'param_boleto_pix_hibrido' => 'boolean',
        'param_boleto_protestar_automatico' => 'boolean',
    ];

    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach ($this->colunas as $coluna => $tipo) {
                if (Schema::hasColumn('empresas', $coluna)) {
                    continue;
                }

                match ($tipo) {
                    'boolean' => $table->boolean($coluna)->default(false),
                    'text' => $table->text($coluna)->nullable(),
                    default => $table->string($coluna)->nullable(),
                };
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            $existentes = array_filter(
                array_keys($this->colunas),
                fn (string $coluna): bool => Schema::hasColumn('empresas', $coluna),
            );

            if ($existentes !== []) {
                $table->dropColumn($existentes);
            }
        });
    }
};
