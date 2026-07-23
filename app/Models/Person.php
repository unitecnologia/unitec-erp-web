<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'pessoa_tipo',
    'nome_razao',
    'apelido_fantasia',
    'cpf_cnpj',
    'rg_ie',
    'cep',
    'endereco',
    'numero',
    'complemento',
    'bairro',
    'cidade_codigo',
    'cidade_nome',
    'uf',
    'email',
    'email2',
    'fone1',
    'fone2',
    'celular1',
    'celular2',
    'whatsapp',
    'regime_tributario',
    'tipo_recebimento',
    'tipo_contribuinte',
    'nome_mae',
    'nome_pai',
    'data_nascimento',
    'limite_credito',
    'dia_pgto',
    'forma_pagamento_id',
    'tabela_prazo_id',
    'price_table_id',
    'vendedor_fv_id',
    'vendedor_loja_id',
    'estado_civil',
    'sexo',
    'salario',
    'data_admissao',
    'data_demissao',
    'observacoes',
    'banco',
    'agencia',
    'gerente',
    'fone_gerente',
    'is_atendente',
    'is_tecnico',
    'foto_path',
    'is_cliente',
    'is_fornecedor',
    'is_funcionario',
    'is_administradora',
    'is_parceiro',
    'is_fabricante',
    'is_transportadora',
    'is_ccf_spc',
    'ativo',
])]
class Person extends Model
{
    public const PESSOA_FISICA = 'fisica';

    public const PESSOA_JURIDICA = 'juridica';

    /**
     * @return array<string, string>
     */
    public static function pessoaTipos(): array
    {
        return [
            self::PESSOA_FISICA => 'FÍSICA',
            self::PESSOA_JURIDICA => 'JURÍDICA',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function regimesTributarios(): array
    {
        return [
            'simples' => 'SIMPLES',
            'presumido' => 'PRESUMIDO',
            'real' => 'REAL',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposContribuinte(): array
    {
        return [
            'nao_contribuinte' => 'NÃO CONTRIBUINTE',
            'contribuinte' => 'CONTRIBUINTE',
            'isento' => 'ISENTO',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposRecebimento(): array
    {
        return [
            '' => '',
            'dinheiro' => 'DINHEIRO',
            'cartao' => 'CARTÃO',
            'boleto' => 'BOLETO',
            'pix' => 'PIX',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function estadosCivis(): array
    {
        return [
            'solteiro' => 'Solteiro(a)',
            'casado' => 'Casado(a)',
            'divorciado' => 'Divorciado(a)',
            'viuvo' => 'Viúvo(a)',
            'uniao_estavel' => 'União estável',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sexos(): array
    {
        return [
            'masculino' => 'Masculino',
            'feminino' => 'Feminino',
            'outro' => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ufs(): array
    {
        return [
            'AC' => 'AC', 'AL' => 'AL', 'AP' => 'AP', 'AM' => 'AM', 'BA' => 'BA',
            'CE' => 'CE', 'DF' => 'DF', 'ES' => 'ES', 'GO' => 'GO', 'MA' => 'MA',
            'MT' => 'MT', 'MS' => 'MS', 'MG' => 'MG', 'PA' => 'PA', 'PB' => 'PB',
            'PR' => 'PR', 'PE' => 'PE', 'PI' => 'PI', 'RJ' => 'RJ', 'RN' => 'RN',
            'RS' => 'RS', 'RO' => 'RO', 'RR' => 'RR', 'SC' => 'SC', 'SP' => 'SP',
            'SE' => 'SE', 'TO' => 'TO',
        ];
    }

    public static function nextCodigo(): string
    {
        $max = static::query()
            ->pluck('codigo')
            ->map(fn (string $codigo): int => (int) preg_replace('/\D/', '', $codigo))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PersonContact::class)->orderByDesc('contato_em');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function tabelaPrazo(): BelongsTo
    {
        return $this->belongsTo(TabelaPrazo::class, 'tabela_prazo_id');
    }

    public function priceTable(): BelongsTo
    {
        return $this->belongsTo(PriceTable::class, 'price_table_id');
    }

    public function vendedorFv(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_fv_id');
    }

    public function vendedorLoja(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_loja_id');
    }

    public function visitaDias(): HasMany
    {
        return $this->hasMany(PersonVisitaDia::class)->orderBy('dia_semana')->orderBy('ordem');
    }

    public function isPessoaFisica(): bool
    {
        if ($this->pessoa_tipo === self::PESSOA_FISICA) {
            return true;
        }

        $digits = preg_replace('/\D/', '', (string) ($this->cpf_cnpj ?? '')) ?? '';

        return strlen($digits) === 11;
    }

    public function isConsumidorFinalPadrao(): bool
    {
        if ($this->isPessoaFisica()) {
            return true;
        }

        return strtolower((string) ($this->tipo_contribuinte ?? 'nao_contribuinte')) === 'nao_contribuinte';
    }

    public function getEnderecoListaAttribute(): string
    {
        $partes = array_filter([
            $this->endereco,
            $this->numero ? 'nº ' . $this->numero : null,
            $this->bairro,
            $this->cidade_nome,
            $this->uf,
        ]);

        if ($partes !== []) {
            return implode(', ', $partes);
        }

        return (string) ($this->endereco ?? '');
    }

    public function getFotoUrlAttribute(): ?string
    {
        if (blank($this->foto_path)) {
            return null;
        }

        return asset('storage/' . $this->foto_path);
    }

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'data_admissao' => 'date',
            'data_demissao' => 'date',
            'limite_credito' => 'decimal:2',
            'salario' => 'decimal:2',
            'is_atendente' => 'boolean',
            'is_tecnico' => 'boolean',
            'is_cliente' => 'boolean',
            'is_fornecedor' => 'boolean',
            'is_funcionario' => 'boolean',
            'is_administradora' => 'boolean',
            'is_parceiro' => 'boolean',
            'is_fabricante' => 'boolean',
            'is_transportadora' => 'boolean',
            'is_ccf_spc' => 'boolean',
            'dia_pgto' => 'integer',
            'forma_pagamento_id' => 'integer',
            'tabela_prazo_id' => 'integer',
            'price_table_id' => 'integer',
            'vendedor_fv_id' => 'integer',
            'vendedor_loja_id' => 'integer',
            'ativo' => 'boolean',
        ];
    }
}
