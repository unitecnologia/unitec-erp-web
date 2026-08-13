<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id', 'codigo_legado', 'numero', 'situacao',
    'data_inicio', 'hora_inicio', 'previsao_entrega',
    'data_termino', 'hora_termino', 'data_entrega', 'hora_entrega', 'data_emissao',
    'proxima_revisao', 'avisar_revisao',
    'cliente_id', 'atendente_id', 'usuario_id', 'produto_id',
    'documento', 'nome', 'fone1', 'fone2', 'endereco', 'bairro', 'cidade', 'uf',
    'numero_serie', 'descricao', 'descricao2', 'modelo', 'marca', 'ano', 'placa', 'km',
    'modelo_veiculo', 'categoria_veiculo', 'marca_veiculo', 'ano_veiculo', 'cor_veiculo',
    'placa_veiculo', 'combustivel_veiculo', 'chassi_veiculo',
    'tipo_servico', 'nome_time', 'quantidade', 'tipo_tecido_legado',
    'problema', 'observacoes', 'laudo',
    'subtotal', 'subtotal_pecas', 'subtotal_servicos',
    'vl_desc_pecas', 'vl_desc_servicos', 'desc_perc_pecas', 'desc_perc_servicos',
    'total_servicos', 'total_produtos', 'total_geral',
    'envio_whats_status', 'path_pdf_whats', 'numero_whatsapp',
])]
class OrdemServico extends Model
{
    protected $table = 'ordens_servico';

    public const SITUACAO_ABERTA = 'aberta';

    public const SITUACAO_ANDAMENTO = 'andamento';

    public const SITUACAO_FINALIZADA = 'finalizada';

    public const SITUACAO_ENTREGUE = 'entregue';

    public const SITUACAO_CANCELADA = 'cancelada';

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            self::SITUACAO_ABERTA => 'Aberta',
            self::SITUACAO_ANDAMENTO => 'Em andamento',
            self::SITUACAO_FINALIZADA => 'Finalizada',
            self::SITUACAO_ENTREGUE => 'Entregue',
            self::SITUACAO_CANCELADA => 'Cancelada',
        ];
    }

    public function situacaoLabel(): string
    {
        return static::situacaoLabels()[$this->situacao] ?? mb_strtoupper((string) $this->situacao, 'UTF-8');
    }

    public function clienteNome(): string
    {
        if (filled($this->nome)) {
            return (string) $this->nome;
        }

        return (string) ($this->cliente?->nome_razao ?? '—');
    }

    public function horaInicioExibicao(): ?string
    {
        if ($this->hora_inicio === null) {
            return null;
        }

        return substr((string) $this->hora_inicio, 0, 5);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->situacao, [self::SITUACAO_ABERTA, self::SITUACAO_ANDAMENTO], true);
    }

    public static function nextNumero(): string
    {
        $max = static::query()
            ->pluck('numero')
            ->map(fn (?string $numero): int => (int) preg_replace('/\D/', '', (string) $numero))
            ->max();

        return (string) (($max ?? 0) + 1);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'atendente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'produto_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrdemServicoItem::class, 'ordem_servico_id');
    }

    public function imagens(): HasMany
    {
        return $this->hasMany(OrdemServicoImagem::class, 'ordem_servico_id');
    }

    /**
     * Mapeia situacao char(1) do Firebird para o web.
     */
    public static function mapSituacaoLegado(?string $codigo): string
    {
        return match (mb_strtoupper(trim((string) $codigo), 'UTF-8')) {
            'A', '1' => self::SITUACAO_ABERTA,
            'M', '2' => self::SITUACAO_ANDAMENTO,
            'F', '3' => self::SITUACAO_FINALIZADA,
            'E', '4' => self::SITUACAO_ENTREGUE,
            'C', '5', 'X' => self::SITUACAO_CANCELADA,
            default => self::SITUACAO_ABERTA,
        };
    }

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'hora_inicio' => 'string',
            'previsao_entrega' => 'datetime',
            'data_termino' => 'date',
            'hora_termino' => 'string',
            'data_entrega' => 'date',
            'hora_entrega' => 'string',
            'data_emissao' => 'date',
            'proxima_revisao' => 'date',
            'avisar_revisao' => 'boolean',
            'quantidade' => 'decimal:3',
            'subtotal' => 'decimal:2',
            'subtotal_pecas' => 'decimal:2',
            'subtotal_servicos' => 'decimal:2',
            'vl_desc_pecas' => 'decimal:2',
            'vl_desc_servicos' => 'decimal:2',
            'desc_perc_pecas' => 'decimal:4',
            'desc_perc_servicos' => 'decimal:4',
            'total_servicos' => 'decimal:2',
            'total_produtos' => 'decimal:2',
            'total_geral' => 'decimal:2',
        ];
    }
}
