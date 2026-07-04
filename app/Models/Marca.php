<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'ativo'])]
class Marca extends Model
{
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
