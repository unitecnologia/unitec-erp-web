<?php

namespace App\Models;

use App\Support\Erp\ProductPhotoUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'codigo',
    'referencia',
    'codigo_barras',
    'codigo_barras_caixa',
    'descricao',
    'tipo_produto',
    'marca',
    'grupo',
    'ult_fornecedor_id',
    'unidade',
    'preco_compra',
    'ult_compra',
    'ult_compra_anterior',
    'pct_custos',
    'preco_custo',
    'preco_custo_anterior',
    'e_medio',
    'pct_lucro',
    'preco_venda',
    'preco_venda_prazo',
    'preco_venda_anterior',
    'qtd_atacado',
    'preco_atacado',
    'comissao_pct',
    'desconto_pct',
    'estoque',
    'estoque_minimo',
    'estoque_inicial',
    'peso_kg',
    'localizacao',
    'validade',
    'ncm',
    'ncm_descricao',
    'cest',
    'cfop_interno',
    'origem',
    'cst_icms',
    'csosn',
    'aliq_icms',
    'cfop_externo',
    'cst_externo',
    'csosn_externo',
    'aliq_icms_externo',
    'cst_entrada',
    'cst_saida',
    'aliq_pis',
    'aliq_cofins',
    'cst_ipi',
    'cod_enq_ipi',
    'aliq_ipi',
    'fcp_pct',
    'mva_pct',
    'mva_normal',
    'reducao_base_pct',
    'icms_diferido',
    'aliq_deson',
    'motivo_desoneracao',
    'tipo_tributacao',
    'tributacao_monofasica',
    'cod_beneficio',
    'glp_pct',
    'gnn_pct',
    'gni_pct',
    'peso_liq',
    'anp_code',
    'issqn',
    'prefixo_balanca',
    'produto_pesado',
    'ativo',
    'is_fiscal',
    'paga_comissao',
    'preco_variavel',
    'is_composicao',
    'is_servico',
    'is_grade',
    'usa_tab_preco',
    'is_combustivel',
    'usa_imei',
    'contr_est_grade',
    'mostrar_no_app',
    'is_restaurante',
    'tipo_restaurante',
    'menu_id',
    'tipo_alimento',
    'qtd_sabores',
    'valor_pequena',
    'valor_media',
    'valor_grande',
    'complemento',
    'tempo_espera',
    'is_remedio',
    'principio_ativo_id',
    'aplicacao',
    'foto_path',
    'promo_data_inicio',
    'promo_data_fim',
    'promo_preco_venda',
    'promo_preco_atacado',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'preco_compra' => 'decimal:2',
            'pct_custos' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'pct_lucro' => 'decimal:2',
            'preco_venda' => 'decimal:2',
            'preco_venda_prazo' => 'decimal:2',
            'preco_venda_anterior' => 'decimal:2',
            'ult_compra' => 'decimal:2',
            'ult_compra_anterior' => 'decimal:2',
            'preco_custo_anterior' => 'decimal:2',
            'e_medio' => 'decimal:3',
            'qtd_atacado' => 'decimal:3',
            'preco_atacado' => 'decimal:2',
            'comissao_pct' => 'decimal:2',
            'desconto_pct' => 'decimal:2',
            'estoque' => 'decimal:3',
            'estoque_minimo' => 'decimal:3',
            'estoque_inicial' => 'decimal:3',
            'peso_kg' => 'decimal:3',
            'origem' => 'integer',
            'aliq_icms' => 'decimal:2',
            'aliq_icms_externo' => 'decimal:2',
            'aliq_pis' => 'decimal:2',
            'aliq_cofins' => 'decimal:2',
            'aliq_ipi' => 'decimal:2',
            'fcp_pct' => 'decimal:2',
            'mva_pct' => 'decimal:2',
            'mva_normal' => 'decimal:4',
            'reducao_base_pct' => 'decimal:2',
            'icms_diferido' => 'decimal:4',
            'aliq_deson' => 'decimal:4',
            'motivo_desoneracao' => 'integer',
            'tributacao_monofasica' => 'boolean',
            'produto_pesado' => 'boolean',
            'principio_ativo_id' => 'integer',
            'glp_pct' => 'decimal:2',
            'gnn_pct' => 'decimal:2',
            'gni_pct' => 'decimal:2',
            'peso_liq' => 'decimal:3',
            'issqn' => 'decimal:2',
            'validade' => 'date',
            'promo_data_inicio' => 'date',
            'promo_data_fim' => 'date',
            'promo_preco_venda' => 'decimal:2',
            'promo_preco_atacado' => 'decimal:2',
            'ativo' => 'boolean',
            'is_fiscal' => 'boolean',
            'paga_comissao' => 'boolean',
            'preco_variavel' => 'boolean',
            'is_composicao' => 'boolean',
            'is_servico' => 'boolean',
            'is_grade' => 'boolean',
            'usa_tab_preco' => 'boolean',
            'is_combustivel' => 'boolean',
            'usa_imei' => 'boolean',
            'contr_est_grade' => 'boolean',
            'mostrar_no_app' => 'boolean',
            'is_restaurante' => 'boolean',
            'menu_id' => 'integer',
            'qtd_sabores' => 'integer',
            'valor_pequena' => 'decimal:4',
            'valor_media' => 'decimal:4',
            'valor_grande' => 'decimal:4',
            'is_remedio' => 'boolean',
            'tempo_espera' => 'integer',
        ];
    }

    public function ultFornecedor(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'ult_fornecedor_id');
    }

    public function seriais(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function grades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductGrade::class);
    }

    public function compositions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductComposition::class);
    }

    public function priceTableItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductPriceTableItem::class);
    }

    public function priceHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    public function imeis(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductImei::class);
    }

    public function estoqueReservas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EstoqueReserva::class);
    }

    public function fotoUrl(): ?string
    {
        if (blank($this->foto_path)) {
            return null;
        }

        return ProductPhotoUrl::forPath($this->foto_path);
    }

    /**
     * @return array<string, string>
     */
    public static function tiposProduto(): array
    {
        return [
            '00' => '00-MERCADORIA PARA REVENDA',
            '01' => '01-MATÉRIA PRIMA',
            '02' => '02-EMBALAGEM',
            '03' => '03-PRODUTO EM PROCESSO',
            '04' => '04-PRODUTO ACABADO',
            '05' => '05-SUBPRODUTO',
            '06' => '06-PRODUTO INTERMEDIÁRIO',
            '07' => '07-MATERIAL DE USO E CONSUMO',
            '08' => '08-ATIVO IMOBILIZADO',
            '09' => '09-SERVIÇOS',
            '10' => '10-OUTROS INSUMOS',
            '99' => '99-OUTRAS',
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

    /**
     * @return array<string, string>
     */
    public function scopeEstoqueCritico(Builder $query): Builder
    {
        return $query
            ->where('ativo', true)
            ->where('estoque_minimo', '>', 0)
            ->whereColumn('estoque', '<', 'estoque_minimo');
    }

    public static function unidades(): array
    {
        try {
            $fromDb = Unidade::query()
                ->where('ativo', true)
                ->orderBy('sigla')
                ->pluck('descricao', 'sigla')
                ->all();

            if ($fromDb !== []) {
                return $fromDb;
            }
        } catch (\Throwable) {
            // Tabela ainda não migrada — usa fallback estático.
        }

        return [
            'UN' => 'UNIDADE',
            'KG' => 'KG',
            'PC' => 'PC',
            'CX' => 'CX',
            'LT' => 'LT',
            'MT' => 'MT',
            'M2' => 'M2',
            'M3' => 'M3',
            'PAR' => 'PAR',
            'SC' => 'SC',
        ];
    }

    public function validadeVencida(?\Carbon\CarbonInterface $reference = null): bool
    {
        if ($this->validade === null) {
            return false;
        }

        $reference ??= now()->startOfDay();

        return $this->validade->copy()->startOfDay()->lt($reference);
    }
}
