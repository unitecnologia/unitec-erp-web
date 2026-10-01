<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfseItem extends Model
{
    protected $table = 'nfse_itens';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nfse_id',
        'product_id',
        'ordem',
        'codigo',
        'descricao',
        'unidade',
        'quantidade',
        'valor',
        'total',
        'c_trib_nac',
        'c_nbs',
        'c_trib_mun',
        'c_ind_op',
        'obra_insc_imob_fisc',
        'obra_tipo',
        'obra_c_obra',
        'obra_c_cib',
        'obra_cep',
        'obra_logradouro',
        'obra_numero',
        'obra_complemento',
        'obra_bairro',
    ];

    public function nfse(): BelongsTo
    {
        return $this->belongsTo(Nfse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'quantidade' => 'decimal:3',
            'valor' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
