<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->unsignedBigInteger('forma_pagamento_id')->nullable()->after('dia_pgto');
            $table->index('forma_pagamento_id');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropIndex(['forma_pagamento_id']);
            $table->dropColumn('forma_pagamento_id');
        });
    }
};
