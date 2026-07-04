<?php

namespace App\Models;

use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'codigo',
    'nome',
    'fantasia',
    'razao_social',
    'pessoa_tipo',
    'cidade',
    'cnpj',
    'ie',
    'im',
    'cnae',
    'regime_tributario',
    'cep',
    'endereco',
    'numero',
    'complemento',
    'bairro',
    'cidade_codigo',
    'uf',
    'pais_codigo',
    'pais',
    'email',
    'site',
    'telefone',
    'responsavel',
    'cnpj_representante',
    'tipo_atividade',
    'obs_fisco',
    'obs_carne',
    'obs_nfce',
    'msg_cobranca_whatsapp',
    'logo_path',
    'ativo',
])]
class Empresa extends Model
{
    public const PESSOA_FISICA = 'fisica';

    public const PESSOA_JURIDICA = 'juridica';

    public function __construct(array $attributes = [])
    {
        $this->fillable = array_values(array_unique([
            ...$this->fillable,
            ...EmpresaParametros::allFieldNames(),
        ]));

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        $casts = [
            'codigo' => 'integer',
            'ativo' => 'boolean',
        ];

        foreach (EmpresaParametros::numericFields() as $field => $meta) {
            if ($meta['type'] === 'integer') {
                $casts[$field] = 'integer';
            } elseif ($meta['type'] === 'decimal') {
                $casts[$field] = 'decimal:2';
            }
        }

        foreach (EmpresaParametros::permissionFields() as $field => $meta) {
            $casts[$field] = 'boolean';
        }

        foreach (EmpresaParametros::impostoFields() as $field => $meta) {
            if ($meta['type'] === 'decimal') {
                $casts[$field] = 'decimal:2';
            }
        }

        foreach (EmpresaParametros::difalFields() as $field => $meta) {
            if ($meta['type'] === 'decimal') {
                $casts[$field] = 'decimal:2';
            }
        }

        foreach ([
            ...EmpresaParametros::difalBooleanFields(),
            ...EmpresaParametros::pixBooleanFields(),
            ...EmpresaParametros::boletoBooleanFields(),
            ...EmpresaParametros::apiServicosBooleanFields(),
            ...EmpresaParametros::whatsAppBooleanFields(),
            ...EmpresaParametros::portalContadorBooleanFields(),
            ...EmpresaParametros::sistemaBooleanFields(),
        ] as $field => $meta) {
            $casts[$field] = 'boolean';
        }

        $casts['param_api_servicos_timeout'] = 'integer';
        $casts['param_whatsapp_timeout'] = 'integer';
        $casts['param_whatsapp_gateway_port'] = 'integer';
        $casts['param_whatsapp_limite_dia'] = 'integer';
        $casts['param_whatsapp_msgs_hoje'] = 'integer';
        $casts['param_whatsapp_msgs_data'] = 'date';
        $casts['param_portal_contador_timeout'] = 'integer';
        $casts['param_portal_contador_contador_id'] = 'integer';
        $casts['param_backup_intervalo_horas'] = 'integer';

        return $casts;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        $version = $this->updated_at?->timestamp ?? time();

        return asset('storage/' . $this->logo_path) . '?v=' . $version;
    }

    public static function nextCodigo(): int
    {
        $max = static::query()->max('codigo');

        return ((int) $max) + 1;
    }

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
            'normal' => 'NORMAL',
            'simples' => 'SIMPLES',
            'presumido' => 'PRESUMIDO',
            'real' => 'REAL',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposAtividade(): array
    {
        return [
            'informatica' => 'Informática',
            'loja_roupas' => 'Loja de Roupas',
            'materiais_construcao' => 'Materiais de Construção',
            'mercado_mercearia' => 'Mercado/Mercearia',
            'prestador_servicos' => 'Prestador de Serviços',
            'comercio_geral' => 'Comércio em Geral',
            'restaurante_lanchonete' => 'Restaurante/Lanchonete',
            'bazar_armarinhos' => 'Bazar/Armarinhos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ufs(): array
    {
        return Person::ufs();
    }
}
