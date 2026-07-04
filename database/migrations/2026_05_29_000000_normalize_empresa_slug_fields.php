<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $slugFields = [
        'tipo_atividade',
        'pessoa_tipo',
        'regime_tributario',
        'logo_path',
    ];

    public function up(): void
    {
        foreach (DB::table('empresas')->orderBy('id')->get() as $empresa) {
            $updates = [];

            foreach ($this->slugFields as $field) {
                $value = $empresa->{$field} ?? null;

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $normalized = mb_strtolower(trim($value), 'UTF-8');

                if ($normalized !== $value) {
                    $updates[$field] = $normalized;
                }
            }

            if ($updates !== []) {
                DB::table('empresas')->where('id', $empresa->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Irreversível — valores originais em maiúsculas não são restaurados.
    }
};
