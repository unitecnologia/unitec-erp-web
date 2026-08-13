<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'name')) {
            $duplicates = DB::table('users')
                ->select('name', DB::raw('COUNT(*) as total'))
                ->groupBy('name')
                ->having('total', '>', 1)
                ->pluck('name');

            foreach ($duplicates as $name) {
                $ids = DB::table('users')->where('name', $name)->orderBy('id')->pluck('id');
                $first = true;
                foreach ($ids as $id) {
                    if ($first) {
                        $first = false;
                        continue;
                    }
                    DB::table('users')->where('id', $id)->update([
                        'name' => $name.' #'.$id,
                    ]);
                }
            }

            try {
                Schema::table('users', function (Blueprint $table): void {
                    $table->unique('name');
                });
            } catch (\Throwable) {
                // Índice já existe.
            }
        }

        if (Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('email_verified_at');
            });
        }

        if (Schema::hasColumn('users', 'email')) {
            try {
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropUnique(['email']);
                });
            } catch (\Throwable) {
                // Índice com nome diferente ou já removido.
            }

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('email');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }
        });

        if (Schema::hasColumn('users', 'name') && Schema::hasColumn('users', 'email')) {
            foreach (DB::table('users')->orderBy('id')->get() as $user) {
                if (filled($user->email ?? null)) {
                    continue;
                }

                $slug = strtolower((string) preg_replace('/\s+/', '.', (string) $user->name)) ?: 'user';
                DB::table('users')->where('id', $user->id)->update([
                    'email' => $slug.$user->id.'@local.invalid',
                ]);
            }

            try {
                Schema::table('users', function (Blueprint $table): void {
                    $table->unique('email');
                });
            } catch (\Throwable) {
                // Já existe.
            }
        }
    }
};
