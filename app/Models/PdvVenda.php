<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PdvVenda extends Model
{
    protected $table = 'pdv_vendas';

    protected $fillable = [
        'pdv_caixa_sessao_id',
        'user_id',
        'orcamento_id',
        'venda_id',
        'person_id',
        'cpf_nota',
        'vendedor_id',
        'vendedor_nome',
        'numero',
        'subtotal',
        'desconto',
        'acrescimo',
        'total',
        'forma_pagamento',
        'fiscal',
        'nfce_operacao',
        'observacoes',
        'troco',
        'dinheiro',
        'situacao',
        'fechado_em',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'desconto' => 'decimal:2',
            'acrescimo' => 'decimal:2',
            'total' => 'decimal:2',
            'troco' => 'decimal:2',
            'dinheiro' => 'decimal:2',
            'fiscal' => 'boolean',
            'fechado_em' => 'datetime',
        ];
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(PdvCaixaSessao::class, 'pdv_caixa_sessao_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PdvVendaItem::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(PdvVendaPagamento::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function nfce(): HasOne
    {
        return $this->hasOne(PdvVendaNfce::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }

    public static function nextNumero(int $sessaoId): int
    {
        $max = static::query()
            ->where('pdv_caixa_sessao_id', $sessaoId)
            ->max('numero');

        return ((int) $max) + 1;
    }
}
