<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContadorCloudSyncLog extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'contador_cloud_sync_logs';

    protected $fillable = [
        'empresa_id',
        'tipo_documento',
        'evento',
        'chave',
        'referencia_type',
        'referencia_id',
        'status',
        'http_status',
        'error_message',
        'response_body',
        'payload_json',
        'attempts',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SENT => 'Enviado',
            self::STATUS_FAILED => 'Falha',
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_SKIPPED => 'Ignorado',
            default => $status,
        };
    }

    public static function tipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'nfe_saida' => 'NF-e Saída',
            'nfe_entrada' => 'NF-e Entrada',
            'nfce_saida' => 'NFC-e Saída',
            'compra_entrada' => 'Compra',
            'nota_fornecedor_entrada' => 'Nota Fornecedor',
            default => $tipo,
        };
    }

    public static function eventoLabel(string $evento): string
    {
        return match ($evento) {
            'autorizado' => 'Autorizado',
            'cancelado' => 'Cancelado',
            default => $evento,
        };
    }
}
