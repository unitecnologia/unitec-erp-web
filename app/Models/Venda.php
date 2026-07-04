<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Attributes\Fillable;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;



#[Fillable([

    'numero',

    'data',

    'hora',

    'cliente_id',

    'vendedor_id',

    'vendedor_nome',

    'total',

    'forma_pagamento',

    'status',

    'tipo',

    'plataforma',

])]

class Venda extends Model

{

    public const STATUS_ABERTO = 'aberto';



    public const STATUS_GRAVADO = 'gravado';



    public const STATUS_FECHADO = 'fechado';



    public const STATUS_CANCELADO = 'cancelado';



    public const TIPO_PEDIDO = 'pedido';



    public const TIPO_CUPOM = 'cupom';

    public const PLATAFORMA_PDV = 'pdv';

    public const PLATAFORMA_ERP = 'erp';

    public const PLATAFORMA_MOBILE = 'mobile';

    /**

     * @return array<string, string>

     */

    public static function statusLabels(): array

    {

        return [

            self::STATUS_ABERTO => 'Aberto',

            self::STATUS_GRAVADO => 'Gravado',

            self::STATUS_FECHADO => 'Fechado',

            self::STATUS_CANCELADO => 'Cancelado',

        ];

    }



    /**

     * @return array<string, string>

     */

    public static function tipoLabels(): array

    {

        return [

            self::TIPO_PEDIDO => 'Pedido',

            self::TIPO_CUPOM => 'Cupom',

        ];

    }

    /**
     * @return array<string, string>
     */
    public static function plataformaLabels(): array
    {
        return [
            self::PLATAFORMA_PDV => 'PDV',
            self::PLATAFORMA_ERP => 'ERP',
            self::PLATAFORMA_MOBILE => 'Mobile',
        ];
    }

    /**
     * Plataforma de origem da venda, inferida pelo vínculo com PDV / Força de Vendas
     * quando o campo gravado estiver ausente ou incorreto (ex.: backfill antigo).
     */
    public function plataformaEfetiva(): string
    {
        if ($this->temOrigemPdv()) {
            return self::PLATAFORMA_PDV;
        }

        if ($this->temOrigemMobile()) {
            return self::PLATAFORMA_MOBILE;
        }

        $plataforma = $this->plataforma;

        if ($plataforma !== null && $plataforma !== '') {
            return (string) $plataforma;
        }

        return self::PLATAFORMA_ERP;
    }

    public function plataformaLabel(): string
    {
        $plataforma = $this->plataformaEfetiva();

        return self::plataformaLabels()[$plataforma]
            ?? mb_strtoupper($plataforma, 'UTF-8');
    }

    public function temOrigemPdv(): bool
    {
        if ($this->relationLoaded('pdvVenda')) {
            return $this->pdvVenda !== null;
        }

        return $this->pdvVenda()->exists();
    }

    public function temOrigemMobile(): bool
    {
        if ($this->relationLoaded('forcaVendasOrder')) {
            return $this->forcaVendasOrder !== null;
        }

        return $this->forcaVendasOrder()->exists();
    }

    public static function nextNumero(): string
    {
        return app(\App\Support\Erp\VendaNumeroService::class)->proximo();
    }



    public function cliente(): BelongsTo

    {

        return $this->belongsTo(Person::class, 'cliente_id');

    }



    public function vendedor(): BelongsTo

    {

        return $this->belongsTo(Vendedor::class, 'vendedor_id');

    }

    public function vendedorNome(): string
    {
        return $this->vendedor_nome
            ?: ($this->vendedor?->nome ?? 'LOJA');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function pdvVenda(): HasOne
    {
        return $this->hasOne(PdvVenda::class, 'venda_id');
    }

    public function forcaVendasOrder(): HasOne
    {
        return $this->hasOne(ForcaVendasOrder::class, 'venda_id');
    }

    protected function casts(): array

    {

        return [

            'data' => 'date',

            'total' => 'decimal:2',

        ];

    }

}

