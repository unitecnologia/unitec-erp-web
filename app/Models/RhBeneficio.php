<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RhBeneficio extends Model
{
    public const TIPO_ALIMENTACAO = 'alimentacao';

    public const TIPO_TRANSPORTE = 'transporte';

    public const TIPO_SAUDE = 'saude';

    public const TIPO_ODONTOLOGICO = 'odontologico';

    public const TIPO_OUTROS = 'outros';

    public const PER_MENSAL = 'mensal';

    public const PER_QUINZENAL = 'quinzenal';

    public const PER_ANUAL = 'anual';

    public const PER_UNICO = 'unico';

    protected $table = 'rh_beneficios';

    protected $fillable = [
        'codigo',
        'descricao',
        'tipo',
        'valor_padrao',
        'periodicidade',
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

    public function vinculos(): HasMany
    {
        return $this->hasMany(RhBeneficioVinculo::class, 'beneficio_id');
    }

    public function labelSelect(): string
    {
        return trim((string) $this->codigo.' — '.$this->descricao).' ('.$this->tipoLabel().')';
    }

    public function tipoLabel(): string
    {
        return match ($this->tipo) {
            self::TIPO_ALIMENTACAO => 'Alimentação',
            self::TIPO_TRANSPORTE => 'Transporte',
            self::TIPO_SAUDE => 'Saúde',
            self::TIPO_ODONTOLOGICO => 'Odontológico',
            self::TIPO_OUTROS => 'Outros',
            default => (string) $this->tipo,
        };
    }

    public function periodicidadeLabel(): string
    {
        return match ($this->periodicidade) {
            self::PER_MENSAL => 'Mensal',
            self::PER_QUINZENAL => 'Quinzenal',
            self::PER_ANUAL => 'Anual',
            self::PER_UNICO => 'Único',
            default => (string) $this->periodicidade,
        };
    }

    /**
     * @return list<string>
     */
    public static function tiposValidos(): array
    {
        return [
            self::TIPO_ALIMENTACAO,
            self::TIPO_TRANSPORTE,
            self::TIPO_SAUDE,
            self::TIPO_ODONTOLOGICO,
            self::TIPO_OUTROS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function periodicidadesValidas(): array
    {
        return [
            self::PER_MENSAL,
            self::PER_QUINZENAL,
            self::PER_ANUAL,
            self::PER_UNICO,
        ];
    }

    protected function casts(): array
    {
        return [
            'valor_padrao' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }
}
