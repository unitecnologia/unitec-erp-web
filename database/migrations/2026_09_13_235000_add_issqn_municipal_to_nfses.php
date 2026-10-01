<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            if (! Schema::hasColumn('nfses', 'trib_issqn')) {
                $table->string('trib_issqn', 1)->default('1')->after('municipio_prestacao_uf');
            }

            if (! Schema::hasColumn('nfses', 'tp_ret_issqn')) {
                $table->string('tp_ret_issqn', 1)->default('1')->after('trib_issqn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfses', function (Blueprint $table): void {
            if (Schema::hasColumn('nfses', 'tp_ret_issqn')) {
                $table->dropColumn('tp_ret_issqn');
            }

            if (Schema::hasColumn('nfses', 'trib_issqn')) {
                $table->dropColumn('trib_issqn');
            }
        });
    }
};
