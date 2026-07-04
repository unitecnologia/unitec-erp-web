<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'pairing_secret',
    'pairing_required',
])]
class ForcaVendasSetting extends Model
{
    protected $table = 'forca_vendas_settings';

    protected function casts(): array
    {
        return [
            'pairing_required' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first();

        if ($setting === null) {
            $setting = static::query()->create([
                'pairing_secret' => Str::random(40),
                'pairing_required' => true,
            ]);
        }

        return $setting;
    }

    public function rotateSecret(): self
    {
        $this->update(['pairing_secret' => Str::random(40)]);

        return $this;
    }
}
