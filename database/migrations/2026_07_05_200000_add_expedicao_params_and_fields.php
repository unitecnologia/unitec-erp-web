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
        Schema::table('empresas', function (Blueprint $table) {
            foreach (EmpresaParametros::expedicaoBooleanFields() as $field => $meta) {
                if (Schema::hasColumn('empresas', $field)) {
                    continue;
                }

                $table->boolean($field)->default((bool) $meta['default']);
            }
        });

        if (Schema::hasTable('entregas')) {
            Schema::table('entregas', function (Blueprint $table) {
                if (! Schema::hasColumn('entregas', 'usuario_expedicao_id')) {
                    $table->foreignId('usuario_expedicao_id')->nullable()->after('origem')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('entregas', 'expedido_em')) {
                    $table->timestamp('expedido_em')->nullable()->after('finalizado_em');
                }
            });

            DB::table('entregas')
                ->whereIn('status', [
                    'aguardando_separacao',
                    'em_separacao',
                    'separado',
                    'em_conferencia',
                    'conferido',
                    'em_montagem_carga',
                    'saiu_entrega',
                ])
                ->update(['status' => 'pendente']);

            DB::table('entregas')
                ->whereIn('status', ['entregue', 'finalizado'])
                ->update(['status' => 'expedido']);
        }

        if (Schema::hasTable('entrega_itens')) {
            Schema::table('entrega_itens', function (Blueprint $table) {
                if (! Schema::hasColumn('entrega_itens', 'codigo_barras')) {
                    $table->string('codigo_barras', 60)->nullable()->after('codigo');
                }

                if (! Schema::hasColumn('entrega_itens', 'quantidade_expedida')) {
                    $table->decimal('quantidade_expedida', 12, 3)->default(0)->after('quantidade_pedida');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('entrega_itens')) {
            Schema::table('entrega_itens', function (Blueprint $table) {
                if (Schema::hasColumn('entrega_itens', 'codigo_barras')) {
                    $table->dropColumn('codigo_barras');
                }

                if (Schema::hasColumn('entrega_itens', 'quantidade_expedida')) {
                    $table->dropColumn('quantidade_expedida');
                }
            });
        }

        if (Schema::hasTable('entregas')) {
            Schema::table('entregas', function (Blueprint $table) {
                if (Schema::hasColumn('entregas', 'usuario_expedicao_id')) {
                    $table->dropConstrainedForeignId('usuario_expedicao_id');
                }

                if (Schema::hasColumn('entregas', 'expedido_em')) {
                    $table->dropColumn('expedido_em');
                }
            });
        }

        Schema::table('empresas', function (Blueprint $table) {
            foreach (array_keys(EmpresaParametros::expedicaoBooleanFields()) as $field) {
                if (Schema::hasColumn('empresas', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
};
