<?php

namespace App\Models;

use App\Support\Erp\ErpDataSyncVersion;
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
    'preco_venda_anterior',
    'qtd_atacado',
    'preco_atacado',
    'preco_especial',
    'estoque',
    'estoque_minimo',
    'peso_kg',
    'localizacao',
    'validade',
    'lote',
    'info_adicionais',
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
    'cst_cofins',
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
    'iva_cst',
    'cclass_trib',
    'cclass_trib_descricao',
    'aliq_ibs_uf',
    'aliq_cbs',
    'aliq_ibs_mun',
    'aliq_adrem_ibs',
    'aliq_adrem_cbs',
    'reducao_cbs',
    'reducao_ibs',
    'glp_pct',
    'gnn_pct',
    'gni_pct',
    'peso_liq',
    'anp_code',
    'issqn',
    'prefixo_balanca',
    'produto_pesado',
    'tem_info_nutricional',
    'nutri_porcao_qtd',
    'nutri_porcao_unidade',
    'nutri_medida_inteiro',
    'nutri_medida_fracao',
    'nutri_medida_tipo',
    'nutri_valor_energetico',
    'nutri_carboidratos',
    'nutri_proteinas',
    'nutri_gorduras_totais',
    'nutri_gorduras_saturadas',
    'nutri_gorduras_trans',
    'nutri_fibra',
    'nutri_sodio',
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
    protected static function booted(): void
    {
        static::saved(static function (): void {
            ErpDataSyncVersion::bump(ErpDataSyncVersion::CHANNEL_PRODUCTS);
        });

        static::deleted(static function (): void {
            ErpDataSyncVersion::bump(ErpDataSyncVersion::CHANNEL_PRODUCTS);
        });
    }

    protected function casts(): array
    {
        return [
            'preco_compra' => 'decimal:2',
            'pct_custos' => 'decimal:2',
            'preco_custo' => 'decimal:2',
            'pct_lucro' => 'decimal:2',
            'preco_venda' => 'decimal:2',
            'preco_venda_anterior' => 'decimal:2',
            'ult_compra' => 'decimal:2',
            'ult_compra_anterior' => 'decimal:2',
            'preco_custo_anterior' => 'decimal:2',
            'e_medio' => 'decimal:3',
            'qtd_atacado' => 'decimal:3',
            'preco_atacado' => 'decimal:2',
            'preco_especial' => 'decimal:2',
            'estoque' => 'decimal:3',
            'estoque_minimo' => 'decimal:3',
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
            'tem_info_nutricional' => 'boolean',
            'nutri_porcao_qtd' => 'integer',
            'nutri_medida_inteiro' => 'integer',
            'nutri_valor_energetico' => 'decimal:1',
            'nutri_carboidratos' => 'decimal:1',
            'nutri_proteinas' => 'decimal:1',
            'nutri_gorduras_totais' => 'decimal:1',
            'nutri_gorduras_saturadas' => 'decimal:1',
            'nutri_gorduras_trans' => 'decimal:1',
            'nutri_fibra' => 'decimal:1',
            'nutri_sodio' => 'decimal:1',
            'principio_ativo_id' => 'integer',
            'aliq_ibs_uf' => 'decimal:4',
            'aliq_cbs' => 'decimal:4',
            'aliq_ibs_mun' => 'decimal:4',
            'aliq_adrem_ibs' => 'decimal:4',
            'aliq_adrem_cbs' => 'decimal:4',
            'reducao_cbs' => 'decimal:4',
            'reducao_ibs' => 'decimal:4',
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

    public function empresaPrecos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductEmpresaPreco::class);
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
                ->get(['sigla', 'descricao']);

            if ($fromDb->isNotEmpty()) {
                return $fromDb
                    ->mapWithKeys(fn (Unidade $u): array => [
                        strtoupper(trim((string) $u->sigla)) => Unidade::normalizeDescricao(
                            (string) $u->sigla,
                            (string) $u->descricao
                        ),
                    ])
                    ->all();
            }
        } catch (\Throwable) {
            // Tabela ainda não migrada — usa fallback estático.
        }

        return Unidade::descricoesCanonicas();
    }

    public function validadeVencida(?\Carbon\CarbonInterface $reference = null): bool
    {
        if ($this->validade === null) {
            return false;
        }

        $reference ??= now()->startOfDay();

        return $this->validade->copy()->startOfDay()->lt($reference);
    }

    /**
     * Dias até a validade (negativo = vencido). Null se sem data.
     */
    public function validadeDiasRestantes(?\Carbon\CarbonInterface $reference = null): ?int
    {
        if ($this->validade === null) {
            return null;
        }

        $reference ??= now()->startOfDay();

        return (int) $reference->diffInDays($this->validade->copy()->startOfDay(), false);
    }

    /**
     * Faixa visual da validade na grid: ok|atencao|vencido.
     */
    public function validadeStatus(?\Carbon\CarbonInterface $reference = null): ?string
    {
        $dias = $this->validadeDiasRestantes($reference);

        if ($dias === null) {
            return null;
        }

        if ($dias < 0) {
            return 'vencido';
        }

        // Amarelo: 8 a 30 dias (≤7 também amarelo — sem lilás/laranja).
        if ($dias <= 30) {
            return 'atencao';
        }

        return 'ok';
    }

    public function validadeStatusLabel(?\Carbon\CarbonInterface $reference = null): string
    {
        $status = $this->validadeStatus($reference);
        $dias = $this->validadeDiasRestantes($reference);

        return match ($status) {
            'ok' => 'Mais de 30 dias',
            'atencao' => ($dias !== null && $dias <= 7) ? 'Até 7 dias' : 'Entre 8 e 30 dias',
            'vencido' => 'Vencido',
            default => '',
        };
    }

    /**
     * Produto com bloco nutricional preenchido para exportação de balança (MGV).
     */
    public function hasInfoNutricional(): bool
    {
        return (bool) ($this->tem_info_nutricional ?? false);
    }
}
