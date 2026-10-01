<?php

use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boleto_contas_api')) {
            Schema::create('boleto_contas_api', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nome', 80)->nullable();
                $table->string('banco', 10)->index();
                $table->boolean('ativo')->default(true);
                $table->boolean('padrao')->default(false);
                $table->string('ambiente', 20)->default('homologacao');
                $table->string('convenio', 40)->nullable();
                $table->string('carteira', 20)->nullable();
                $table->string('agencia', 20)->nullable();
                $table->string('agencia_dv', 10)->nullable();
                $table->string('conta', 40)->nullable();
                $table->string('conta_dv', 10)->nullable();
                $table->string('beneficiario_codigo', 40)->nullable();
                $table->text('client_id')->nullable();
                $table->text('client_secret')->nullable();
                $table->text('dev_app_key')->nullable();
                $table->text('senha_api')->nullable();
                $table->string('api_url', 255)->nullable();
                $table->string('callback_url', 255)->nullable();
                $table->string('especie_documento', 20)->nullable();
                $table->string('instrucao1', 250)->nullable();
                $table->string('instrucao2', 250)->nullable();
                $table->string('juros_pct', 20)->nullable();
                $table->string('multa_pct', 20)->nullable();
                $table->string('desconto_pct', 20)->nullable();
                $table->string('protesto_dias', 10)->nullable();
                $table->string('pos_vencimento', 32)->default('nenhuma');
                $table->boolean('pix_hibrido')->default(false);
                $table->timestamps();

                $table->index(['empresa_id', 'ativo']);
            });
        }

        if (Schema::hasTable('boletos') && ! Schema::hasColumn('boletos', 'boleto_conta_api_id')) {
            Schema::table('boletos', function (Blueprint $table): void {
                $table->foreignId('boleto_conta_api_id')
                    ->nullable()
                    ->after('empresa_id')
                    ->constrained('boleto_contas_api')
                    ->nullOnDelete();
            });
        }

        $this->migrarParametrosLegados();
    }

    public function down(): void
    {
        if (Schema::hasTable('boletos') && Schema::hasColumn('boletos', 'boleto_conta_api_id')) {
            Schema::table('boletos', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('boleto_conta_api_id');
            });
        }

        Schema::dropIfExists('boleto_contas_api');
    }

    private function migrarParametrosLegados(): void
    {
        if (! Schema::hasTable('empresas') || ! Schema::hasTable('boleto_contas_api')) {
            return;
        }

        $cols = Schema::getColumnListing('empresas');
        if (! in_array('param_boleto_banco', $cols, true)) {
            return;
        }

        $rows = DB::table('empresas')
            ->whereNotNull('param_boleto_banco')
            ->where('param_boleto_banco', '!=', '')
            ->get(['id', 'param_boleto_banco', 'param_boleto_ambiente', 'param_boleto_convenio',
                'param_boleto_carteira', 'param_boleto_agencia', 'param_boleto_agencia_dv',
                'param_boleto_conta', 'param_boleto_conta_dv', 'param_boleto_beneficiario_codigo',
                'param_boleto_client_id', 'param_boleto_client_secret', 'param_boleto_dev_app_key',
                'param_boleto_senha_api', 'param_boleto_api_url', 'param_boleto_callback_url',
                'param_boleto_especie_documento', 'param_boleto_instrucao1', 'param_boleto_instrucao2',
                'param_boleto_juros_pct', 'param_boleto_multa_pct', 'param_boleto_desconto_pct',
                'param_boleto_protesto_dias', 'param_boleto_pos_vencimento', 'param_boleto_pix_hibrido',
            ]);

        foreach ($rows as $row) {
            $exists = DB::table('boleto_contas_api')->where('empresa_id', $row->id)->exists();
            if ($exists) {
                continue;
            }

            $banco = preg_replace('/\D/', '', (string) $row->param_boleto_banco) ?? '';
            if ($banco === '') {
                continue;
            }

            $nome = match ($banco) {
                EmpresaParametros::BOLETO_BANCO_AILOS => 'Ailos',
                EmpresaParametros::BOLETO_BANCO_SICREDI => 'Sicredi',
                default => 'Banco '.$banco,
            };

            DB::table('boleto_contas_api')->insert([
                'empresa_id' => $row->id,
                'nome' => $nome,
                'banco' => $banco,
                'ativo' => true,
                'padrao' => true,
                'ambiente' => $row->param_boleto_ambiente ?: 'homologacao',
                'convenio' => $row->param_boleto_convenio,
                'carteira' => $row->param_boleto_carteira,
                'agencia' => $row->param_boleto_agencia,
                'agencia_dv' => $row->param_boleto_agencia_dv,
                'conta' => $row->param_boleto_conta,
                'conta_dv' => $row->param_boleto_conta_dv,
                'beneficiario_codigo' => $row->param_boleto_beneficiario_codigo,
                'client_id' => $row->param_boleto_client_id,
                'client_secret' => $row->param_boleto_client_secret,
                'dev_app_key' => $row->param_boleto_dev_app_key,
                'senha_api' => $row->param_boleto_senha_api,
                'api_url' => $row->param_boleto_api_url,
                'callback_url' => $row->param_boleto_callback_url,
                'especie_documento' => $row->param_boleto_especie_documento ?: 'DM',
                'instrucao1' => $row->param_boleto_instrucao1,
                'instrucao2' => $row->param_boleto_instrucao2,
                'juros_pct' => $row->param_boleto_juros_pct,
                'multa_pct' => $row->param_boleto_multa_pct,
                'desconto_pct' => $row->param_boleto_desconto_pct,
                'protesto_dias' => $row->param_boleto_protesto_dias,
                'pos_vencimento' => $row->param_boleto_pos_vencimento ?: 'nenhuma',
                'pix_hibrido' => (bool) ($row->param_boleto_pix_hibrido ?? false),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
