<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpProfile extends Model
{
    protected $fillable = [
        'nome',
        'descricao',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(ErpProfilePermission::class);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return $this->permissions()
            ->orderBy('permission_key')
            ->pluck('permission_key')
            ->all();
    }
}
