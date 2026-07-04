<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sigla', 'descricao', 'ativo'])]
class Unidade extends Model
{
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
