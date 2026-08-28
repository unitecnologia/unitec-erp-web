<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'nome',
    'tipo',
    'situacao',
    'ativo',
    'sistema',
    'ultimo_usuario_id',
])]
class CaixaConta extends Model
{
    public const NOME_CAIXA_GERAL = 'CAIXA GERAL';

    public const TIPO_BANCO = 'BANCO';

    public const TIPO_PDV = 'PDV';

    /** @deprecated Use TIPO_PDV */
    public const TIPO_CAIXA = self::TIPO_PDV;

    public const TIPO_SUBCAIXA = 'SUBCAIXA';

    public const TIPO_COFRE = 'COFRE';

    public const SITUACAO_ABERTO = 'aberto';

    public const SITUACAO_FECHADO = 'fechado';

    protected $table = 'caixa_contas';

    /**
     * Garante a conta protegida do sistema (destino do fechamento/sangria do PDV).
     */
    public static function ensureCaixaGeral(): self
    {
        $conta = self::query()
            ->whereRaw('UPPER(nome) = ?', [self::NOME_CAIXA_GERAL])
            ->first();

        if ($conta) {
            $conta->fill([
                'tipo' => self::TIPO_SUBCAIXA,
                'situacao' => self::SITUACAO_ABERTO,
                'ativo' => true,
                'sistema' => true,
                'nome' => self::NOME_CAIXA_GERAL,
            ]);

            if ($conta->isDirty()) {
                $conta->save();
            }

            return $conta;
        }

        $codigo = 1;

        if (self::query()->where('codigo', 1)->exists()) {
            $codigo = (int) self::query()->max('codigo') + 1;
        }

        return self::query()->create([
            'codigo' => $codigo,
            'nome' => self::NOME_CAIXA_GERAL,
            'tipo' => self::TIPO_SUBCAIXA,
            'situacao' => self::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => true,
        ]);
    }

    public function isSistema(): bool
    {
        return (bool) $this->sistema
            || mb_strtoupper((string) $this->nome, 'UTF-8') === self::NOME_CAIXA_GERAL;
    }

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            self::TIPO_BANCO => 'BANCO',
            self::TIPO_PDV => 'PDV',
            self::TIPO_SUBCAIXA => 'SUBCAIXA',
            self::TIPO_COFRE => 'COFRE',
        ];
    }

    /**
     * @return list<string>
     */
    public static function tiposValidos(): array
    {
        return array_keys(self::tipoLabels());
    }

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            self::SITUACAO_ABERTO => 'ABERTO',
            self::SITUACAO_FECHADO => 'FECHADO',
        ];
    }

    public function tipoLabel(): string
    {
        $tipo = match ((string) $this->tipo) {
            'CAIXA', 'X' => self::TIPO_PDV,
            default => $this->tipo,
        };

        return self::tipoLabels()[$tipo] ?? mb_strtoupper((string) $tipo, 'UTF-8');
    }

    public function situacaoLabel(): string
    {
        return self::situacaoLabels()[$this->situacao] ?? mb_strtoupper((string) $this->situacao, 'UTF-8');
    }

    public function lancamentos(): HasMany
    {
        return $this->hasMany(CaixaLancamento::class);
    }

    public function ultimoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ultimo_usuario_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'caixa_conta_user')
            ->withPivot(['empresa_id', 'is_padrao'])
            ->withTimestamps();
    }

    /**
     * Contas liberáveis para usuário no PDV: só tipo PDV (legado CAIXA/X).
     * SUBCAIXA fica para destino de sangria/fechamento (ex.: CAIXA GERAL), não para operar PDV.
     */
    public function scopeAssignable($query)
    {
        return $query
            ->where('ativo', true)
            ->where(function ($builder): void {
                $builder
                    ->where('sistema', false)
                    ->orWhereNull('sistema');
            })
            ->whereRaw('UPPER(nome) <> ?', [self::NOME_CAIXA_GERAL])
            ->whereIn('tipo', [self::TIPO_PDV, 'CAIXA', 'X']);
    }

    /**
     * Garante ao menos uma conta operacional tipo PDV (promove SUBCAIXA não-sistema ou cria).
     */
    public static function ensurePdvOperacional(): self
    {
        // Legado comum: "CAIXA" cadastrado como SUBCAIXA — passa a PDV.
        self::query()
            ->where('ativo', true)
            ->where('tipo', self::TIPO_SUBCAIXA)
            ->where(function ($builder): void {
                $builder
                    ->where('sistema', false)
                    ->orWhereNull('sistema');
            })
            ->whereRaw('UPPER(TRIM(nome)) = ?', ['CAIXA'])
            ->update(['tipo' => self::TIPO_PDV]);

        $existente = self::query()
            ->where('ativo', true)
            ->whereIn('tipo', [self::TIPO_PDV, 'CAIXA', 'X'])
            ->whereRaw('UPPER(nome) <> ?', [self::NOME_CAIXA_GERAL])
            ->orderBy('codigo')
            ->first();

        if ($existente) {
            if ((string) $existente->tipo !== self::TIPO_PDV) {
                $existente->forceFill(['tipo' => self::TIPO_PDV])->save();
            }

            return $existente->fresh() ?? $existente;
        }

        $promover = self::query()
            ->where('ativo', true)
            ->where(function ($builder): void {
                $builder
                    ->where('sistema', false)
                    ->orWhereNull('sistema');
            })
            ->where('tipo', self::TIPO_SUBCAIXA)
            ->whereRaw('UPPER(nome) <> ?', [self::NOME_CAIXA_GERAL])
            ->orderBy('codigo')
            ->first();

        if ($promover) {
            $promover->forceFill(['tipo' => self::TIPO_PDV])->save();

            return $promover->fresh() ?? $promover;
        }

        $codigo = ((int) self::query()->max('codigo')) + 1;

        if ($codigo < 1) {
            $codigo = 1;
        }

        return self::query()->create([
            'codigo' => $codigo,
            'nome' => 'CAIXA PDV',
            'tipo' => self::TIPO_PDV,
            'situacao' => self::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
        ]);
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'sistema' => 'boolean',
        ];
    }
}
