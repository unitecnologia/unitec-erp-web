<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminais', function (Blueprint $table): void {
            if (! Schema::hasColumn('terminais', 'categoria_licenca')) {
                $table->string('categoria_licenca', 20)->nullable()->after('ativo');
            }

            if (! Schema::hasColumn('terminais', 'origens_dispositivo')) {
                $table->json('origens_dispositivo')->nullable()->after('categoria_licenca');
            }

            if (! Schema::hasColumn('terminais', 'device_uuid')) {
                $table->string('device_uuid', 120)->nullable()->after('origens_dispositivo');
            }

            if (! Schema::hasColumn('terminais', 'device_name')) {
                $table->string('device_name', 120)->nullable()->after('device_uuid');
            }

            if (! Schema::hasColumn('terminais', 'device_platform')) {
                $table->string('device_platform', 60)->nullable()->after('device_name');
            }

            if (! Schema::hasColumn('terminais', 'device_registered_at')) {
                $table->timestamp('device_registered_at')->nullable()->after('device_platform');
            }

            if (! Schema::hasColumn('terminais', 'device_last_seen_at')) {
                $table->timestamp('device_last_seen_at')->nullable()->after('device_registered_at');
            }
        });

        DB::table('terminais')
            ->whereNull('categoria_licenca')
            ->update(['categoria_licenca' => 'computador']);

        $this->backfillApprovedMobileDevices('forca_vendas_devices', 'forca_vendas');
        $this->backfillApprovedMobileDevices('vendas_internas_devices', 'vendas_internas');

        Schema::table('terminais', function (Blueprint $table): void {
            $table->index(['empresa_id', 'categoria_licenca', 'ativo'], 'terminais_licenca_categoria_ativo_idx');
            $table->unique(['empresa_id', 'device_uuid'], 'terminais_empresa_device_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('terminais', function (Blueprint $table): void {
            $table->dropUnique('terminais_empresa_device_uuid_unique');
            $table->dropIndex('terminais_licenca_categoria_ativo_idx');
            $table->dropColumn([
                'categoria_licenca',
                'origens_dispositivo',
                'device_uuid',
                'device_name',
                'device_platform',
                'device_registered_at',
                'device_last_seen_at',
            ]);
        });
    }

    private function backfillApprovedMobileDevices(string $table, string $origin): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where('status', 'aprovado')
            ->whereNull('revoked_at')
            ->whereNotNull('empresa_id')
            ->whereNotNull('device_uuid')
            ->orderBy('id')
            ->each(function (object $device) use ($origin): void {
                $uuid = trim((string) $device->device_uuid);

                if ($uuid === '') {
                    return;
                }

                $existing = DB::table('terminais')
                    ->where('empresa_id', $device->empresa_id)
                    ->where('device_uuid', $uuid)
                    ->first();

                if ($existing) {
                    $origins = json_decode((string) ($existing->origens_dispositivo ?? '[]'), true) ?: [];
                    $origins = array_values(array_unique([...$origins, $origin]));

                    DB::table('terminais')
                        ->where('id', $existing->id)
                        ->update([
                            'origens_dispositivo' => json_encode($origins, JSON_THROW_ON_ERROR),
                            'device_last_seen_at' => $device->last_seen_at ?? now(),
                            'updated_at' => now(),
                        ]);

                    return;
                }

                DB::table('terminais')->insert([
                    'empresa_id' => $device->empresa_id,
                    'nome' => 'MOBILE-'.strtoupper(substr(hash('sha256', $uuid), 0, 10)),
                    'eh_caixa' => false,
                    'pdv' => false,
                    'imprime' => false,
                    'ativo' => true,
                    'categoria_licenca' => 'telefone',
                    'origens_dispositivo' => json_encode([$origin], JSON_THROW_ON_ERROR),
                    'device_uuid' => $uuid,
                    'device_name' => $device->device_name,
                    'device_platform' => $device->platform,
                    'device_registered_at' => $device->registered_at ?? now(),
                    'device_last_seen_at' => $device->last_seen_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
};
