<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            if (! Schema::hasColumn('people', 'email2')) {
                $table->string('email2')->nullable()->after('email');
            }

            if (! Schema::hasColumn('people', 'dia_pgto')) {
                $table->unsignedTinyInteger('dia_pgto')->nullable()->after('limite_credito');
            }

            if (! Schema::hasColumn('people', 'banco')) {
                $table->string('banco')->nullable()->after('observacoes');
            }

            if (! Schema::hasColumn('people', 'agencia')) {
                $table->string('agencia')->nullable()->after('banco');
            }

            if (! Schema::hasColumn('people', 'gerente')) {
                $table->string('gerente')->nullable()->after('agencia');
            }

            if (! Schema::hasColumn('people', 'fone_gerente')) {
                $table->string('fone_gerente')->nullable()->after('gerente');
            }

            if (! Schema::hasColumn('people', 'representante')) {
                $table->string('representante')->nullable()->after('fone_gerente');
            }

            if (! Schema::hasColumn('people', 'is_fabricante')) {
                $table->boolean('is_fabricante')->default(false)->after('is_parceiro');
            }

            if (! Schema::hasColumn('people', 'is_transportadora')) {
                $table->boolean('is_transportadora')->default(false)->after('is_fabricante');
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            foreach ([
                'email2',
                'dia_pgto',
                'banco',
                'agencia',
                'gerente',
                'fone_gerente',
                'representante',
                'is_fabricante',
                'is_transportadora',
            ] as $column) {
                if (Schema::hasColumn('people', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
