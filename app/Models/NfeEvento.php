<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfeEvento extends Model
{
    public const TIPO_CRIADA = 'criada';

    public const TIPO_EDITADA = 'editada';

    public const TIPO_TRANSMITIDA = 'transmitida';

    public const TIPO_CANCELADA = 'cancelada';

    public const TIPO_INUTILIZADA = 'inutilizada';

    public const TIPO_CARTA_CORRECAO = 'carta_correcao';

    public const TIPO_WHATSAPP = 'whatsapp';

    public const TIPO_EMAIL = 'email';

    public const TIPO_BOLETO = 'boleto';

    public const TIPO_IMPRESSAO = 'impressao';

    protected $table = 'nfe_eventos';

    public $timestamps = false;

    protected $fillable = [
        'nfe_id',
        'user_id',
        'tipo',
        'titulo',
        'descricao',
        'destinatario',
        'referencia_tipo',
        'referencia_id',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            self::TIPO_CRIADA => 'Criação',
            self::TIPO_EDITADA => 'Edição',
            self::TIPO_TRANSMITIDA => 'Transmissão',
            self::TIPO_CANCELADA => 'Cancelamento',
            self::TIPO_INUTILIZADA => 'Inutilização',
            self::TIPO_CARTA_CORRECAO => 'Carta de Correção',
            self::TIPO_WHATSAPP => 'WhatsApp',
            self::TIPO_EMAIL => 'E-mail',
            self::TIPO_BOLETO => 'Boleto',
            self::TIPO_IMPRESSAO => 'Impressão',
        ];
    }

    public function nfe(): BelongsTo
    {
        return $this->belongsTo(Nfe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
