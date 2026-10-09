<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdvMesa extends Model
{
    public const SITUACAO_ATENDIMENTO = 0;

    public const SITUACAO_AGUARDANDO_FECHAMENTO = 1;

    protected $table = 'pdv_mesas';

    protected $fillable = [
        'empresa_id',
        'numero',
        'qtd_itens',
        'total',
        'situacao',
        'parcial_em',
        'itens',
        'aberta_em',
        'reserva_token',
        'reservado_user_id',
        'reservado_terminal_id',
        'reservado_nome',
        'reservado_ate',
        'ultima_pdv_venda_id',
    ];

    protected $hidden = [
        'reserva_token',
    ];

    protected function casts(): array
    {
        return [
            'empresa_id' => 'integer',
            'numero' => 'integer',
            'qtd_itens' => 'integer',
            'total' => 'decimal:2',
            'situacao' => 'integer',
            'parcial_em' => 'datetime',
            'aberta_em' => 'datetime',
            'reservado_ate' => 'datetime',
        ];
    }

    public function rotulo(): string
    {
        return 'Mesa '.str_pad((string) $this->numero, 2, '0', STR_PAD_LEFT);
    }

    public function aguardandoFechamento(): bool
    {
        return (int) $this->situacao === self::SITUACAO_AGUARDANDO_FECHAMENTO;
    }
}
