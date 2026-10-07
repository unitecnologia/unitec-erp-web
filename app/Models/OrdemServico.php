<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'empresa_id', 'codigo_legado', 'app_local_uuid', 'device_uuid', 'numero', 'situacao',
    'data_inicio', 'hora_inicio', 'previsao_entrega',
    'data_termino', 'hora_termino', 'data_entrega', 'hora_entrega', 'data_emissao',
    'proxima_revisao', 'avisar_revisao',
    'cliente_id', 'orcamento_id', 'atendente_id', 'usuario_id', 'produto_id',
    'documento', 'nome', 'fone1', 'fone2', 'endereco', 'bairro', 'cidade', 'uf',
    'numero_serie', 'descricao', 'descricao2', 'modelo', 'marca', 'ano', 'placa', 'km',
    'modelo_veiculo', 'categoria_veiculo', 'marca_veiculo', 'ano_veiculo', 'cor_veiculo',
    'placa_veiculo', 'combustivel_veiculo', 'chassi_veiculo',
    'tipo_servico', 'nome_time', 'quantidade', 'tipo_tecido_legado',
    'problema', 'observacoes', 'laudo',
    'subtotal', 'subtotal_pecas', 'subtotal_servicos',
    'vl_desc_pecas', 'vl_desc_servicos', 'desc_perc_pecas', 'desc_perc_servicos',
    'total_servicos', 'total_produtos', 'total_geral', 'faturamento_pagamentos',
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

    public function horaTerminoExibicao(): ?string
    {
        if ($this->hora_termino === null) {
            return null;
        }

        return substr((string) $this->hora_termino, 0, 5);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->situacao, [self::SITUACAO_ABERTA, self::SITUACAO_ANDAMENTO], true);
    }

    public function aguardandoFaturamento(): bool
    {
        return $this->situacao === self::SITUACAO_ABERTA && $this->data_termino !== null;
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

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class, 'orcamento_id');
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

    public function nfse(): HasOne
    {
        return $this->hasOne(Nfse::class, 'ordem_servico_id')->latestOfMany();
    }

    public function nfseAutorizada(): HasOne
    {
        return $this->hasOne(Nfse::class, 'ordem_servico_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('status', Nfse::STATUS_AUTORIZADA));
    }

    /**
     * Serviço desta OS importado numa NFS-e autorizada cujo cabeçalho aponta para outra OS do mesmo cliente.
     */
    public function nfseItemAutorizado(): HasOne
    {
        return $this->hasOne(NfseItem::class, 'ordem_servico_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereHas(
                'nfse',
                fn ($nfse) => $nfse->where('status', Nfse::STATUS_AUTORIZADA),
            ));
    }

    /**
     * Desconto de itens + desconto geral (peças e serviços). `vl_desc_*` não serve aqui:
     * no web guarda só o desconto geral, no app guarda a soma dos itens.
     */
    public function descontoTotalLista(): float
    {
        $bruto = (float) ($this->subtotal_pecas ?? 0) + (float) ($this->subtotal_servicos ?? 0);
        $liquido = (float) ($this->total_produtos ?? 0) + (float) ($this->total_servicos ?? 0);

        return max(0.0, round($bruto - $liquido, 2));
    }

    /**
     * Só NFS-e autorizada: nota aberta (só DPS), cancelada ou substituída não aparece na lista.
     */
    public function nfseNumeroLista(): string
    {
        $numero = trim((string) ($this->nfseAutorizadaVinculada()?->numero_nfse ?? ''));

        return $numero !== '' ? $numero : '—';
    }

    public function possuiNfseAutorizada(): bool
    {
        return $this->nfseAutorizadaVinculada() !== null;
    }

    /** NFS-e autorizada pelo cabeçalho (esta OS) ou por serviço desta OS importado em NFS-e de outra OS. */
    public function nfseAutorizadaVinculada(): ?Nfse
    {
        $nfse = $this->relationLoaded('nfseAutorizada') ? $this->nfseAutorizada : $this->nfseAutorizada()->first();

        if ($nfse === null) {
            $item = $this->relationLoaded('nfseItemAutorizado') ? $this->nfseItemAutorizado : $this->nfseItemAutorizado()->first();
            $nfse = $item?->nfse;
        }

        return $nfse;
    }

    /** NF-e das peças com validade fiscal: bloqueia nova emissão (F10) e é reaberta em vez de duplicada. */
    public const NFE_PECAS_EMITIDA_STATUS = [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA, Nfe::STATUS_DUPLICIDADE];

    public function nfePecasEmitida(): HasOne
    {
        return $this->hasOne(Nfe::class, 'ordem_servico_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereIn('status', self::NFE_PECAS_EMITIDA_STATUS));
    }

    public function possuiNfePecasEmitida(): bool
    {
        $nfe = $this->relationLoaded('nfePecasEmitida') ? $this->nfePecasEmitida : $this->nfePecasEmitida()->first();

        return $nfe !== null;
    }

    public function nfeAutorizada(): HasOne
    {
        return $this->hasOne(Nfe::class, 'ordem_servico_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('status', Nfe::STATUS_TRANSMITIDA));
    }

    /**
     * Só NF-e autorizada (peças da OS).
     */
    public function nfeNumeroLista(): string
    {
        $nfe = $this->relationLoaded('nfeAutorizada') ? $this->nfeAutorizada : $this->nfeAutorizada()->first();
        $numero = ltrim(trim((string) ($nfe?->numero ?? '')), '0');

        return $numero !== '' ? $numero : '—';
    }

    /**
     * NFC-e ainda não tem vínculo com a OS (PDV "F4 — Importar Ordem de Serviço" pendente).
     */
    public function nfceNumeroLista(): string
    {
        return '—';
    }

    /**
     * Resumo dos meios de pagamento gravados no faturamento (lista).
     */
    public function meioPagamentoLista(): string
    {
        $raw = $this->faturamento_pagamentos;

        if (! is_array($raw) || $raw === []) {
            return '—';
        }

        $formas = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $forma = mb_strtoupper(trim((string) ($item['forma'] ?? '')), 'UTF-8');

            if ($forma === '' || in_array($forma, $formas, true)) {
                continue;
            }

            $formas[] = $forma;
        }

        return $formas === [] ? '—' : implode(' + ', $formas);
    }

    public function enviouEmail(): bool
    {
        $status = mb_strtoupper(trim((string) ($this->envio_whats_status ?? '')), 'UTF-8');

        return $status === 'E'
            || $status === 'E/W'
            || str_contains($status, 'EMAIL')
            || str_contains($status, 'E-MAIL');
    }

    public function enviouWhatsApp(): bool
    {
        $status = mb_strtoupper(trim((string) ($this->envio_whats_status ?? '')), 'UTF-8');

        if ($status === '' || $status === 'E') {
            return false;
        }

        if ($status === 'W' || $status === 'E/W') {
            return true;
        }

        // Legado / outros status preenchidos = WhatsApp (exceto só e-mail).
        return ! str_contains($status, 'EMAIL') && ! str_contains($status, 'E-MAIL');
    }

    /**
     * Resumo de envio para a grade: E | W | E/W | —.
     */
    public function envioListaLabel(): string
    {
        $email = $this->enviouEmail();
        $whats = $this->enviouWhatsApp();

        if ($email && $whats) {
            return 'E/W';
        }

        if ($email) {
            return 'E';
        }

        if ($whats) {
            return 'W';
        }

        return '—';
    }

    public function registrarEnvioEmail(): void
    {
        $this->forceFill([
            'envio_whats_status' => $this->enviouWhatsApp() ? 'E/W' : 'E',
        ])->save();
    }

    public function registrarEnvioWhatsApp(?string $numero = null): void
    {
        $payload = [
            'envio_whats_status' => $this->enviouEmail() ? 'E/W' : 'W',
        ];

        $fone = trim((string) $numero);

        if ($fone !== '') {
            $payload['numero_whatsapp'] = $fone;
        }

        $this->forceFill($payload)->save();
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
            'faturamento_pagamentos' => 'array',
        ];
    }
}
