<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O WhatsApp do cadastro de pessoas passou a ser o fone1.
 * Copia o número antigo só quando o fone1 ainda está vazio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('people') || ! Schema::hasColumn('people', 'whatsapp') || ! Schema::hasColumn('people', 'fone1')) {
            return;
        }

        DB::table('people')
            ->where(function ($query): void {
                $query->whereNull('fone1')->orWhere('fone1', '');
            })
            ->whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->update([
                'fone1' => DB::raw('whatsapp'),
            ]);
    }

    public function down(): void
    {
    }
};
