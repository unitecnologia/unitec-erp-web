<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class RhFuncionario extends Model
{
    protected $table = 'rh_funcionarios';

    protected $fillable = [
        'codigo',
        'nome',
        'cpf',
        'rg',
        'pis_pasep',
        'ctps',
        'inss',
        'data_nascimento',
        'telefone',
        'whatsapp',
        'email',
        'cep',
        'logradouro',
        'endereco',
        'numero',
        'bairro',
        'complemento',
        'cidade_codigo',
        'cidade_nome',
        'uf',
        'cargo_id',
        'departamento_id',
        'tipo_salario',
        'salario',
        'data_admissao',
        'data_demissao',
        'foto_path',
        'user_id',
        'vendedor_id',
        'ativo',
    ];

    public static function nextCodigo(): string
    {
        $max = static::query()
            ->pluck('codigo')
            ->map(fn (string $codigo): int => (int) preg_replace('/\D/', '', $codigo))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(RhCargo::class, 'cargo_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(RhDepartamento::class, 'departamento_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function anexos(): MorphMany
    {
        return $this->morphMany(RhAnexo::class, 'anexavel');
    }

    public function fotoUrl(): ?string
    {
        if (! filled($this->foto_path)) {
            return null;
        }

        return Storage::disk('public')->url((string) $this->foto_path);
    }

    public function emFeriasHoje(): bool
    {
        return false;
    }

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'data_admissao' => 'date',
            'data_demissao' => 'date',
            'salario' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }
}
