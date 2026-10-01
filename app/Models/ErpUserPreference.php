<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpUserPreference extends Model
{
    public const KEY_MONITOR_VENDAS_SITUACAO_FILTER = 'monitor_vendas.situacao_filter';

    protected $table = 'erp_user_preferences';

    protected $fillable = [
        'user_id',
        'empresa_id',
        'key',
        'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function getValue(int $userId, int $empresaId, string $key, ?string $default = null): ?string
    {
        if ($userId <= 0 || $empresaId <= 0 || $key === '') {
            return $default;
        }

        $value = static::query()
            ->where('user_id', $userId)
            ->where('empresa_id', $empresaId)
            ->where('key', $key)
            ->value('value');

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public static function putValue(int $userId, int $empresaId, string $key, string $value): void
    {
        if ($userId <= 0 || $empresaId <= 0 || $key === '') {
            return;
        }

        static::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'empresa_id' => $empresaId,
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }
}
