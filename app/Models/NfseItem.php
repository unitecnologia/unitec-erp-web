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
        'ordem_servico_id',
        'product_id',
        'ordem',
        'codigo',
        'descricao',
        'servico_prestado',
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

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Descrição que vai para o XML e para a impressão: serviço + serviço prestado da linha.
     */
    public function descricaoComServicoPrestado(): string
    {
        $descricao = trim((string) $this->descricao);
        $prestado = trim(preg_replace('/\s+/u', ' ', (string) $this->servico_prestado) ?? '');

        if ($prestado === '') {
            return $descricao;
        }

        return $descricao !== '' ? $descricao.': '.$prestado : $prestado;
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
