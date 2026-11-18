<?php

namespace App\Models;

use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id',
    'terminal_id',
    'provedor',
    'acquirer_cnpj',
    'caixa_conta_id',
    'ativo',
    'updated_by_user_id',
])]
class TefLiquidacaoDestino extends Model
{
    protected $table = 'tef_liquidacao_destinos';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! filled($model->provedor)) {
                $model->provedor = TefProviderId::SUPERTEF;
            } else {
                $model->provedor = self::normalizeProvedor((string) $model->provedor);
            }
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('provedor')) {
                $model->provedor = self::normalizeProvedor((string) $model->provedor);
            }
        });
    }

    public static function normalizeProvedor(?string $value): string
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === TefProviderId::SCOPE) {
            return TefProviderId::SCOPE;
        }

        return TefProviderId::SUPERTEF;
    }

    /**
     * Provedores que a UI de Destinos TEF pode cadastrar agora.
     * Scope permanece no domínio, mas não é operacionalmente autorizado nesta etapa.
     *
     * @return array<string, string> value => label
     */
    public static function provedoresAutorizadosParaUi(): array
    {
        // Scope existe em TefProviderId, mas não é operacional nesta etapa (não ativar).
        // SuperTEF é o único provedor autorizável na UI de destinos.
        return [
            TefProviderId::SUPERTEF => TefProviderId::label(TefProviderId::SUPERTEF),
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    public function caixaConta(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'caixa_conta_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** Normaliza CNPJ para só dígitos (até 14) ou string vazia. */
    public static function normalizeAcquirerCnpj(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return substr($digits, 0, 14);
    }
}
