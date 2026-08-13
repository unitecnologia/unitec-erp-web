<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo N:N entre usuários e empresas (lojas liberadas).
 * empresa_id no users continua como empresa padrão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa_user')) {
            Schema::create('empresa_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'empresa_id']);
            });
        }

        if (Schema::hasColumn('users', 'empresa_id')) {
            User::query()
                ->whereNotNull('empresa_id')
                ->get(['id', 'empresa_id'])
                ->each(function (User $user): void {
                    DB::table('empresa_user')->updateOrInsert(
                        [
                            'user_id' => $user->id,
                            'empresa_id' => $user->empresa_id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_user');
    }
};
