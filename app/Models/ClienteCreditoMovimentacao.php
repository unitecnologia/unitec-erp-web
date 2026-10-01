<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Extrato append-only do crédito do cliente. O saldo não é editável:
 * créditos gerados − créditos utilizados − estornos.
 */
class ClienteCreditoMovimentacao extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cliente_credito_movimentacoes';

    public const TIPO_CREDITO = 'credito';

    public const TIPO_DEBITO = 'debito';

    /** Legado: consumo gravado antes do tipo oficial `debito`. */
    public const TIPO_USO = 'debito';

    public const ORIGEM_MANUAL = 'manual';

    public const ORIGEM_DEVOLUCAO = 'devolucao_venda';

    public const ORIGEM_PDV = 'pdv_venda';

    /** Nome da forma já cadastrada (tipo_movimento = credito_cliente). Não criar outra. */
    public const FORMA_PDV = 'CRÉDITO DO CLIENTE';

    /**
     * @return array<string, string>
     */
    public static function tiposLabels(): array
    {
        return [
            self::TIPO_CREDITO => 'Crédito',
            self::TIPO_DEBITO => 'Utilizado',
        ];
    }

    public static function tipoLabel(string $tipo, bool $estorno = false): string
    {
        if ($estorno) {
            return 'Estorno';
        }

        return match ($tipo) {
            self::TIPO_CREDITO => 'Crédito',
            self::TIPO_DEBITO, 'uso' => 'Utilizado',
            'estorno' => 'Estorno',
            default => self::tiposLabels()[$tipo] ?? $tipo,
        };
    }

    public static function isDebito(string $tipo): bool
    {
        return in_array($tipo, [self::TIPO_DEBITO, 'uso'], true);
    }

    /**
     * Código de barras do vale futuro. Só aceita o identificador público (VT000000123), nunca o id do banco.
     */
    public static function codigoBarras(?string $codigoPublico): ?string
    {
        $codigo = strtoupper(trim((string) $codigoPublico));

        if (preg_match('/^VT\d{9}$/', $codigo) !== 1) {
            return null;
        }

        return $codigo;
    }

    public static function isFormaPdv(string $forma): bool
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');
        $forma = str_replace(['É', 'Ê', 'Á', 'Ã'], ['E', 'E', 'A', 'A'], $forma);

        return $forma === 'CREDITO DO CLIENTE' || str_contains($forma, 'CREDITO DO CLIENTE');
    }

    public static function origemLabel(?string $origemTipo, ?string $origemNumero, ?string $observacao = null): string
    {
        $numero = trim((string) ($origemNumero ?? ''));
        $tipo = (string) ($origemTipo ?? '');

        $prefix = match ($tipo) {
            self::ORIGEM_DEVOLUCAO => 'Troca Venda',
            self::ORIGEM_PDV => 'Venda PDV',
            self::ORIGEM_MANUAL => 'Lançamento manual',
            default => '',
        };

        if ($prefix === '') {
            $obs = trim((string) ($observacao ?? ''));

            return $obs !== '' ? $obs : '—';
        }

        return $numero !== '' ? $prefix.' '.$numero : $prefix;
    }

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'data_movimentacao',
        'tipo',
        'valor',
        'sinal',
        'saldo_anterior',
        'saldo_atual',
        'origem_tipo',
        'origem_id',
        'origem_numero',
        'codigo_publico',
        'estorna_id',
        'usuario_id',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'data_movimentacao' => 'datetime',
            'valor' => 'decimal:2',
            'sinal' => 'integer',
            'saldo_anterior' => 'decimal:2',
            'saldo_atual' => 'decimal:2',
            'origem_id' => 'integer',
            'estorna_id' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function estorna(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estorna_id');
    }

    public function efeito(): float
    {
        return round(((int) $this->sinal) * (float) $this->valor, 2);
    }
}
