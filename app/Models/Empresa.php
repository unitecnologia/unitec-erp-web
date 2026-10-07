<?php

namespace App\Models;

use App\Casts\SafeEncryptedString;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

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
    'obs_contribuinte',
    'msg_cobranca_whatsapp',
    'logo_path',
    'ativo',
    'configuracao_inicial_concluida',
    'param_monitor_vendas_imp_valor_liquido',
    'param_monitor_vendas_imp_sem_coluna_desconto',
])]
#[Hidden(['param_consulta_placa_token'])]
class Empresa extends Model
{
    public const PESSOA_FISICA = 'fisica';

    public const PESSOA_JURIDICA = 'juridica';

    public const TIPO_ATIVIDADE_PRESTADOR_SERVICOS = 'prestador_servicos';

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
            'configuracao_inicial_concluida' => 'boolean',
        ];

        foreach (EmpresaParametros::numericFields() as $field => $meta) {
            if ($meta['type'] === 'integer') {
                $casts[$field] = 'integer';
            } elseif ($meta['type'] === 'decimal') {
                $casts[$field] = 'decimal:2';
            }
        }

        foreach (array_keys(EmpresaParametros::planoContaFields()) as $field) {
            $casts[$field] = 'integer';
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
            ...EmpresaParametros::moduleEnableFields(),
            ...EmpresaParametros::difalBooleanFields(),
            ...EmpresaParametros::pixBooleanFields(),
            ...EmpresaParametros::boletoBooleanFields(),
            ...EmpresaParametros::apiServicosBooleanFields(),
            ...EmpresaParametros::consultaPlacaBooleanFields(),
            ...EmpresaParametros::acessoRemotoBooleanFields(),
            ...EmpresaParametros::licencaApiBooleanFields(),
            ...EmpresaParametros::whatsAppBooleanFields(),
            ...EmpresaParametros::portalContadorBooleanFields(),
            ...EmpresaParametros::mercadoLivreBooleanFields(),
            ...EmpresaParametros::ifoodBooleanFields(),
            ...EmpresaParametros::sistemaBooleanFields(),
        ] as $field => $meta) {
            $casts[$field] = 'boolean';
        }

        $casts['param_api_servicos_timeout'] = 'integer';
        $casts['param_consulta_placa_timeout'] = 'integer';
        $casts['param_consulta_placa_token'] = SafeEncryptedString::class;
        $casts['param_licenca_api_timeout'] = 'integer';
        $casts['param_whatsapp_timeout'] = 'integer';
        $casts['param_whatsapp_gateway_port'] = 'integer';
        $casts['param_whatsapp_limite_dia'] = 'integer';
        $casts['param_whatsapp_msgs_hoje'] = 'integer';
        $casts['param_whatsapp_msgs_data'] = 'date';
        $casts['param_portal_contador_timeout'] = 'integer';
        $casts['param_portal_contador_contador_id'] = 'integer';
        $casts['param_portal_contador_vinculado_em'] = 'datetime';
        $casts['param_meli_token_expires_at'] = 'datetime';
        $casts['param_meli_vinculado_em'] = 'datetime';
        $casts['param_backup_intervalo_horas'] = 'integer';
        $casts['param_balanca_etiqueta_modelo'] = 'integer';
        $casts['param_balanca_digitos'] = 'integer';
        $casts['param_ui_zoom'] = 'integer';
        $casts['param_ui_density'] = 'string';
        $casts['param_monitor_vendas_desconto_reais_item_modo'] = 'string';

        return $casts;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function boletoContasApi(): HasMany
    {
        return $this->hasMany(BoletoContaApi::class);
    }

    public function usuariosLiberados(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_user')->withTimestamps();
    }

    /**
     * Empresas autorizadas a emitir NF-e no Monitor em nome dos pedidos desta empresa (Matriz).
     * Configuração comercial — independente de empresa_user.
     */
    public function nfeEmitentes(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'empresa_nfe_emitente',
            'empresa_id',
            'emitente_empresa_id',
        )->withTimestamps();
    }

    /**
     * Empresas (Matrizes) que autorizaram esta empresa como emitente de NF-e no Monitor.
     */
    public function nfeEmitenteDe(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'empresa_nfe_emitente',
            'emitente_empresa_id',
            'empresa_id',
        )->withTimestamps();
    }

    public function estoques(): HasMany
    {
        return $this->hasMany(Estoque::class);
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
            'mei' => 'MEI (SIMEI)',
            'presumido' => 'PRESUMIDO',
            'real' => 'REAL',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function isPrestadorServicos(): bool
    {
        return ($this->tipo_atividade ?? '') === self::TIPO_ATIVIDADE_PRESTADOR_SERVICOS;
    }

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

    /**
     * Uma leitura de empresas.id = 1. Sem cache: o login consulta de novo a cada acesso.
     */
    public static function configuracaoInicialPendente(): bool
    {
        try {
            $empresa = static::query()->whereKey(1)->first(['id', 'configuracao_inicial_concluida']);
        } catch (QueryException $exception) {
            $message = $exception->getMessage();
            if (($exception->errorInfo[0] ?? '') === '42S22' || str_contains($message, 'configuracao_inicial_concluida')) {
                return false;
            }

            throw $exception;
        }

        return $empresa !== null && ! $empresa->configuracao_inicial_concluida;
    }
}
