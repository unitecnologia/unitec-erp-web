<?php

use App\Support\Erp\Ccg\CcgConsGtinEndpoints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colunas exclusivas da integração Cosmos/Bluesoft (URL, timeout, habilitar e Serper permanecem).
     *
     * @var list<string>
     */
    private array $cosmosColumns = [
        'param_api_servicos_usuario',
        'param_api_servicos_senha',
        'param_api_servicos_token',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        if (Schema::hasColumn('empresas', 'param_api_servicos_url')) {
            DB::table('empresas')
                ->where(function ($query): void {
                    $query->whereNull('param_api_servicos_url')
                        ->orWhere('param_api_servicos_url', '')
                        ->orWhere('param_api_servicos_url', 'like', '%bluesoft%')
                        ->orWhere('param_api_servicos_url', 'like', '%cosmos%');
                })
                ->update([
                    'param_api_servicos_url' => CcgConsGtinEndpoints::URL,
                ]);
        }

        Schema::table('empresas', function (Blueprint $table): void {
            $drop = array_values(array_filter(
                $this->cosmosColumns,
                fn (string $column): bool => Schema::hasColumn('empresas', $column),
            ));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table): void {
            foreach ($this->cosmosColumns as $column) {
                if (! Schema::hasColumn('empresas', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }
};
