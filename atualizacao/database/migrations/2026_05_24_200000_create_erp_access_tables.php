<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('erp_profiles')) {
            Schema::create('erp_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 80)->unique();
                $table->string('descricao', 255)->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('erp_profile_permissions')) {
            Schema::create('erp_profile_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('erp_profile_id')->constrained('erp_profiles')->cascadeOnDelete();
                $table->string('permission_key', 80);
                $table->timestamps();

                $table->unique(['erp_profile_id', 'permission_key'], 'unitec_erp_prof_perm_uq');
            });
        }

        if (! Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin')->default(false)->after('empresa_id');
            });
        }

        if (! Schema::hasColumn('users', 'ativo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('ativo')->default(true)->after('is_admin');
            });
        }

        if (! Schema::hasColumn('users', 'erp_profile_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('erp_profile_id')
                    ->nullable()
                    ->after('ativo')
                    ->constrained('erp_profiles')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('user_permissions')) {
            Schema::create('user_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('permission_key', 80);
                $table->timestamps();

                $table->unique(['user_id', 'permission_key'], 'unitec_user_perm_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');

        if (Schema::hasColumn('users', 'erp_profile_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('erp_profile_id');
            });
        }

        if (Schema::hasColumn('users', 'is_admin') || Schema::hasColumn('users', 'ativo')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    Schema::hasColumn('users', 'is_admin') ? 'is_admin' : null,
                    Schema::hasColumn('users', 'ativo') ? 'ativo' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('erp_profile_permissions');
        Schema::dropIfExists('erp_profiles');
    }
};
