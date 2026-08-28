<?php

use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            foreach (EmpresaParametros::moduleEnableFields() as $field => $meta) {
                if (! Schema::hasColumn('empresas', $field)) {
                    $table->boolean($field)->default((bool) $meta['default']);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table): void {
            $columns = array_keys(EmpresaParametros::moduleEnableFields());
            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('empresas', $column),
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
