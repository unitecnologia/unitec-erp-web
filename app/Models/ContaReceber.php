<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'numero',
    'emissao',
    'historico',
    'documento',
    'cliente_id',
    'vencimento',
    'valor',
    'desconto',
    'juros',
    'juros_diario_pct',
    'carencia_juros_dias',
    'multa_pct',
    'multa',
    'valor_recebido',
    'recebido_em',
    'saldo',
    'forma',
    'numero_cheque',
    'cartao_nsu',
    'cartao_autorizacao',
    'cartao_maquininha',
    'cartao_bandeira',
    'cartao_parcela',
    'plano_conta_id',
    'pdv_venda_id',
])]
class ContaReceber extends Model
{
    public const FORMA_CARTEIRA = 'carteira';

    public const FORMA_CHEQUE = 'cheque';

    public const FORMA_CARTAO = 'cartao';

    public const FORMA_BOLETO = 'boleto';

    public const FORMA_PIX = 'pix';

    protected $table = 'contas_receber';

    /**
     * @return array<string, string>
     */
    public static function formaLabels(): array
    {
        return [
            self::FORMA_CARTEIRA => 'Carteira',
            'dinheiro' => 'Dinheiro',
            self::FORMA_CHEQUE => 'Cheques',
            self::FORMA_CARTAO => 'Cartão',
            self::FORMA_BOLETO => 'Boleto',
            self::FORMA_PIX => 'Pix',
            'deposito' => 'Depósito',
        ];
    }

    public function isFormaBoleto(): bool
    {
        $forma = mb_strtolower(trim((string) ($this->forma ?? '')), 'UTF-8');

        return $forma === self::FORMA_BOLETO
            || str_contains(mb_strtoupper((string) ($this->forma ?? ''), 'UTF-8'), 'BOLETO');
    }

    public function formaLabel(): string
    {
        $forma = mb_strtolower(trim((string) ($this->forma ?? '')), 'UTF-8');

        if ($forma === '') {
            return self::formaLabels()[self::FORMA_CARTEIRA];
        }

        return self::formaLabels()[$forma] ?? (string) $this->forma;
    }

    public static function nextNumero(): string
    {
        $max = static::query()
            ->pluck('numero')
            ->map(fn (string $numero): int => (int) preg_replace('/\D/', '', $numero))
            ->max();

        return str_pad((string) (($max ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    public static function calcularSaldo(float $valor, float $desconto, float $juros, float $valorRecebido, float $multa = 0): float
    {
        return round(max(0, $valor - $desconto + $juros + $multa - $valorRecebido), 2);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class, 'plano_conta_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(ContaReceberPagamento::class, 'conta_receber_id');
    }

    protected function casts(): array
    {
        return [
            'emissao' => 'date',
            'vencimento' => 'date',
            'recebido_em' => 'date',
            'valor' => 'decimal:2',
            'desconto' => 'decimal:2',
            'juros' => 'decimal:2',
            'juros_diario_pct' => 'decimal:4',
            'carencia_juros_dias' => 'integer',
            'multa_pct' => 'decimal:4',
            'multa' => 'decimal:2',
            'valor_recebido' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ContaReceber $conta): void {
            $conta->saldo = self::calcularSaldo(
                (float) $conta->valor,
                (float) $conta->desconto,
                (float) $conta->juros,
                (float) $conta->valor_recebido,
                (float) $conta->multa,
            );
        });
    }
}
