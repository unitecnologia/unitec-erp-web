<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_venda_nfce')) {
            Schema::create('pdv_venda_nfce', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pdv_venda_id')->unique()->constrained('pdv_vendas')->cascadeOnDelete();
                $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
                $table->foreignId('nfe_id')->nullable()->constrained('nfes')->nullOnDelete();
                $table->string('operacao', 32);
                $table->string('modelo', 2)->default('65');
                $table->string('serie', 3)->default('1');
                $table->unsignedInteger('numero')->nullable();
                $table->string('cnf', 8)->nullable();
                $table->string('chave', 44)->nullable();
                $table->string('protocolo', 20)->nullable();
                $table->string('status', 20)->default('simulada');
                $table->unsignedTinyInteger('ambiente')->default(2);
                $table->string('tipo_emissao', 1)->default('1');
                $table->boolean('simulada')->default(true);
                $table->text('qr_code_conteudo')->nullable();
                $table->longText('xml')->nullable();
                $table->longText('xml_cancelamento')->nullable();
                $table->string('motivo_rejeicao')->nullable();
                $table->string('motivo_contingencia')->nullable();
                $table->timestamp('autorizada_em')->nullable();
                $table->timestamp('cancelada_em')->nullable();
                $table->timestamps();

                $table->index(['status', 'simulada']);
                $table->index('chave');
                $table->index('numero');
            });
        }

        if (Schema::hasTable('nfes') && ! Schema::hasColumn('nfes', 'pdv_venda_id')) {
            Schema::table('nfes', function (Blueprint $table): void {
                $table->foreignId('pdv_venda_id')
                    ->nullable()
                    ->after('venda_id')
                    ->constrained('pdv_vendas')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nfes') && Schema::hasColumn('nfes', 'pdv_venda_id')) {
            Schema::table('nfes', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pdv_venda_id');
            });
        }

        Schema::dropIfExists('pdv_venda_nfce');
    }
};
