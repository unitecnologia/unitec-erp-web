<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'param_ui_density')) {
            return;
        }

        $rows = DB::table('empresas')->select('id', 'param_ui_density')->get();

        foreach ($rows as $row) {
            $raw = strtolower(trim((string) ($row->param_ui_density ?? '')));
            $px = match ($raw) {
                'compact', 'compacto' => 14,
                'large', 'grande' => 18,
                'normal', '' => 16,
                default => (preg_match('/^\d{2}$/', $raw) === 1 && (int) $raw >= 12 && (int) $raw <= 24)
                    ? (int) $raw
                    : 16,
            };

            DB::table('empresas')->where('id', $row->id)->update([
                'param_ui_density' => (string) $px,
            ]);
        }
    }

    public function down(): void
    {
        // Mantém valores numéricos; rollback semântico não é necessário.
    }
};
