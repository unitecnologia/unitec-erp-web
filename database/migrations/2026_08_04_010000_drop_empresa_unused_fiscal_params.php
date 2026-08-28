<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $columns = [
        'param_fiscal_enviar_email_nfe',
        'param_fiscal_usar_credito_icms',
        'param_fiscal_recolhe_fcp',
    ];

    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('empresas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            if (! Schema::hasColumn('empresas', 'param_fiscal_enviar_email_nfe')) {
                $table->boolean('param_fiscal_enviar_email_nfe')->default(true);
            }

            if (! Schema::hasColumn('empresas', 'param_fiscal_usar_credito_icms')) {
                $table->boolean('param_fiscal_usar_credito_icms')->default(true);
            }

            if (! Schema::hasColumn('empresas', 'param_fiscal_recolhe_fcp')) {
                $table->boolean('param_fiscal_recolhe_fcp')->default(true);
            }
        });
    }
};
