<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes_fiscais', function (Blueprint $table): void {
            if (! Schema::hasColumn('operacoes_fiscais', 'cfop_entrega_futura_estadual')) {
                $table->unsignedInteger('cfop_entrega_futura_estadual')
                    ->nullable()
                    ->after('cfop_entrada_futura_interestadual');
            }

            if (! Schema::hasColumn('operacoes_fiscais', 'cfop_entrega_futura_interestadual')) {
                $table->unsignedInteger('cfop_entrega_futura_interestadual')
                    ->nullable()
                    ->after('cfop_entrega_futura_estadual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operacoes_fiscais', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('operacoes_fiscais', 'cfop_entrega_futura_estadual')
                    ? 'cfop_entrega_futura_estadual'
                    : null,
                Schema::hasColumn('operacoes_fiscais', 'cfop_entrega_futura_interestadual')
                    ? 'cfop_entrega_futura_interestadual'
                    : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
