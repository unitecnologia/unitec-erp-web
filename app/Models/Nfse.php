<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nfse extends Model
{
    public const SERIE_DPS = '1';

    public const STATUS_ABERTA = 'aberta';

    public const STATUS_AUTORIZADA = 'autorizada';

    public const STATUS_TRANSMITIDA = 'transmitida';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS_SUBSTITUIDA = 'substituida';

    public const STATUS_REJEITADA = 'rejeitada';

    public const STATUS_CONTINGENCIA = 'contingencia';

    public const TRIB_ISSQN_TRIBUTAVEL = '1';

    public const TP_RET_ISSQN_NAO_RETIDO = '1';

    protected $table = 'nfses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'empresa_id',
        'ordem_servico_id',
        'tomador_id',
        'tomador_nome',
        'tomador_cpf_cnpj',
        'tomador_telefone',
        'tomador_endereco',
        'tomador_numero',
        'tomador_bairro',
        'tomador_cep',
        'tomador_cidade',
        'tomador_uf',
        'tomador_cidade_codigo',
        'tomador_email',
        'competencia',
        'data_emissao',
        'municipio_incidencia',
        'municipio_prestacao_codigo',
        'municipio_prestacao_nome',
        'municipio_prestacao_uf',
        'trib_issqn',
        'tp_ret_issqn',
        'aliquota_iss',
        'serie_dps',
        'numero_dps',
        'numero_nfse',
        'chave',
        'id_dps',
        'chave_acesso',
        'tipo_ambiente',
        'versao_aplicativo',
        'data_hora_processamento',
        'xml_nfse',
        'xml_dps',
        'alertas',
        'transmitindo_em',
        'protocolo',
        'valor_servicos',
        'desconto',
        'iss',
        'total',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    public static function tributacoesIssqn(): array
    {
        return [
            '1' => 'Operação tributável',
            '2' => 'Imunidade',
            '3' => 'Exportação',
            '4' => 'Não incidência',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function retencoesIssqn(): array
    {
        return [
            '1' => 'Não retido',
            '2' => 'Retido pelo tomador',
            '3' => 'Retido pelo intermediário',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_ABERTA => 'Aberta',
            self::STATUS_AUTORIZADA => 'Autorizada',
            self::STATUS_TRANSMITIDA => 'Transmitida',
            self::STATUS_CANCELADA => 'Cancelada',
            self::STATUS_SUBSTITUIDA => 'Substituída',
            self::STATUS_REJEITADA => 'Rejeitada',
            self::STATUS_CONTINGENCIA => 'Contingência',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function tomador(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'tomador_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NfseItem::class)->orderBy('ordem');
    }

    protected function casts(): array
    {
        return [
            'competencia' => 'date',
            'data_emissao' => 'date',
            'numero_dps' => 'integer',
            'valor_servicos' => 'decimal:2',
            'desconto' => 'decimal:2',
            'iss' => 'decimal:2',
            'aliquota_iss' => 'decimal:2',
            'total' => 'decimal:2',
            'alertas' => 'array',
            'transmitindo_em' => 'datetime',
        ];
    }
}
