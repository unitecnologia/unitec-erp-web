<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'conta_receber_id', 'person_id', 'nosso_numero', 'numero_documento', 'linha_digitavel',
    'emissao', 'vencimento', 'processamento', 'valor', 'valor_juros', 'valor_desconto', 'valor_abatimento',
    'percentual_multa', 'data_juros', 'data_desconto', 'data_abatimento', 'data_protesto',
    'sacado_nome', 'sacado_documento', 'sacado_logradouro', 'sacado_numero', 'sacado_bairro',
    'sacado_cidade', 'sacado_uf', 'sacado_cep', 'instrucao1', 'instrucao2', 'path_pdf', 'status', 'codigo_legado',
])]
class Boleto extends Model
{
    public const STATUS_ABERTO = 'A';

    public const STATUS_PAGO = 'P';

    public const STATUS_CANCELADO = 'C';

    public const STATUS_BAIXADO = 'B';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function contaReceber(): BelongsTo
    {
        return $this->belongsTo(ContaReceber::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAGO => 'Pago',
            self::STATUS_CANCELADO => 'Cancelado',
            self::STATUS_BAIXADO => 'Baixado',
            default => 'Aberto',
        };
    }

    protected function casts(): array
    {
        return [
            'emissao' => 'date',
            'vencimento' => 'date',
            'processamento' => 'date',
            'data_juros' => 'date',
            'data_desconto' => 'date',
            'data_abatimento' => 'date',
            'data_protesto' => 'date',
            'valor' => 'decimal:2',
            'valor_juros' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_abatimento' => 'decimal:2',
            'percentual_multa' => 'decimal:4',
        ];
    }
}
