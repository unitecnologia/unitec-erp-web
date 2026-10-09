<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pdv_mesas', 'situacao')) {
            return;
        }

        Schema::table('pdv_mesas', function (Blueprint $table): void {
            // 0 = em atendimento, 1 = aguardando fechamento (pré-conta impressa)
            $table->unsignedTinyInteger('situacao')->default(0)->after('total');
            $table->timestamp('parcial_em')->nullable()->after('situacao');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pdv_mesas', 'situacao')) {
            return;
        }

        Schema::table('pdv_mesas', function (Blueprint $table): void {
            $table->dropColumn(['situacao', 'parcial_em']);
        });
    }
};
