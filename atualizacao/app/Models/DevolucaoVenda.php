<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'codigo_legado',
    'numero',
    'situacao',
    'tipo_devolucao',
    'data',
    'hora',
    'venda_id',
    'venda_numero',
    'cliente_id',
    'cliente_nome',
    'vendedor_id',
    'usuario_id',
    'observacoes',
    'total',
])]
class DevolucaoVenda extends Model
{
    protected $table = 'devolucoes_venda';

    public const SITUACAO_ABERTA = 'aberta';

    public const SITUACAO_FINALIZADA = 'finalizada';

    public const SITUACAO_CANCELADA = 'cancelada';

    public const TIPO_PARCIAL = 'parcial';

    public const TIPO_TOTAL = 'total';

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            self::SITUACAO_ABERTA => 'Aberta',
            self::SITUACAO_FINALIZADA => 'Finalizada',
            self::SITUACAO_CANCELADA => 'Cancelada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            self::TIPO_PARCIAL => 'Parcial',
            self::TIPO_TOTAL => 'Total',
        ];
    }

    public function situacaoLabel(): string
    {
        return static::situacaoLabels()[$this->situacao] ?? mb_strtoupper((string) $this->situacao, 'UTF-8');
    }

    public function tipoLabel(): string
    {
        return static::tipoLabels()[$this->tipo_devolucao] ?? (string) ($this->tipo_devolucao ?: '—');
    }

    public function clienteNome(): string
    {
        if (filled($this->cliente_nome)) {
            return (string) $this->cliente_nome;
        }

        return (string) ($this->cliente?->nome_razao ?? '—');
    }

    public function horaExibicao(): ?string
    {
        if ($this->hora === null) {
            return null;
        }

        return substr((string) $this->hora, 0, 5);
    }

    public function isEditable(): bool
    {
        return $this->situacao === self::SITUACAO_ABERTA;
    }

    public static function nextNumero(): string
    {
        $max = static::query()
            ->pluck('numero')
            ->map(fn (?string $numero): int => (int) preg_replace('/\D/', '', (string) $numero))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public static function mapSituacaoLegado(?string $codigo): string
    {
        return match (mb_strtoupper(trim((string) $codigo), 'UTF-8')) {
            'A', '1' => self::SITUACAO_ABERTA,
            'F', '2' => self::SITUACAO_FINALIZADA,
            'C', '3', 'X' => self::SITUACAO_CANCELADA,
            default => self::SITUACAO_ABERTA,
        };
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(DevolucaoVendaItem::class, 'devolucao_venda_id');
    }

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'hora' => 'string',
            'total' => 'decimal:2',
        ];
    }
}
