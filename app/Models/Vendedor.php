<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'codigo',
    'nome',
    'ativo',
    'comissao_av',
    'comissao_ap',
    'empresa_id',
    'cargo',
    'cpf',
    'rg',
    'pis_pasep',
    'data_nascimento',
    'cep',
    'logradouro',
    'endereco',
    'numero',
    'bairro',
    'complemento',
    'cidade_codigo',
    'cidade_nome',
    'uf',
    'telefone',
    'whatsapp',
    'email',
    'ctps',
    'admissao',
    'demissao',
    'tipo_salario',
    'salario',
    'inss',
    'estoque',
    'usar_agendamento',
    'setor_vendas',
    'tabela_venda_id',
    'ganha_comissao_todas_vendas',
    'mobile_meta_venda',
    'setor_servicos',
    'comissao_servico',
    'ganha_comissao_todos_servicos',
    'efetua_venda',
    'motorista',
    'ajudante',
    'observacoes',
])]
class Vendedor extends Model
{
    protected $table = 'vendedores';

    public static function nextCodigo(): string
    {
        $max = static::query()
            ->pluck('codigo')
            ->map(fn (string $codigo): int => (int) preg_replace('/\D/', '', $codigo))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'comissao_av' => 'decimal:2',
            'comissao_ap' => 'decimal:2',
            'comissao_servico' => 'decimal:2',
            'salario' => 'decimal:2',
            'mobile_meta_venda' => 'decimal:2',
            'data_nascimento' => 'date',
            'admissao' => 'date',
            'demissao' => 'date',
            'usar_agendamento' => 'boolean',
            'setor_vendas' => 'boolean',
            'setor_servicos' => 'boolean',
            'ganha_comissao_todas_vendas' => 'boolean',
            'ganha_comissao_todos_servicos' => 'boolean',
            'efetua_venda' => 'boolean',
            'motorista' => 'boolean',
            'ajudante' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_vendedor')->withTimestamps();
    }

    /**
     * Números (códigos) das empresas do colaborador, ex.: "1,2,3".
     */
    public function empresasNumeros(): string
    {
        return $this->empresas
            ->sortBy(fn (Empresa $empresa): int => (int) preg_replace('/\D/', '', (string) $empresa->codigo))
            ->pluck('codigo')
            ->filter(fn ($codigo): bool => filled($codigo))
            ->implode(',');
    }

    public function tabelaVenda(): BelongsTo
    {
        return $this->belongsTo(PriceTable::class, 'tabela_venda_id');
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(User::class, 'vendedor_id');
    }
}
