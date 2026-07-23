<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'device_uuid',
    'user_id',
    'empresa_id',
    'tipo',
    'cliente_id',
    'vendedor_id',
    'orcamento_id',
    'venda_id',
    'total',
    'latitude',
    'longitude',
    'status',
    'situacao',
    'identificacao',
    'erro',
    'payload',
    'client_created_at',
    'received_at',
    'confirmed_at',
    'faturado_at',
    'canceled_at',
])]
class ForcaVendasOrder extends Model
{
    public const TIPO_PEDIDO = 'pedido';

    public const TIPO_ORCAMENTO = 'orcamento';

    public const STATUS_IMPORTADO = 'importado';

    public const STATUS_ERRO = 'erro';

    public const SITUACAO_PENDENTE = 'pendente';

    public const SITUACAO_FINANCEIRO = 'financeiro';

    public const SITUACAO_CONFIRMADO = 'confirmado';

    public const SITUACAO_FATURADO = 'faturado';

    public const SITUACAO_CANCELADO = 'cancelado';

    protected $table = 'forca_vendas_orders';

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'payload' => 'array',
            'client_created_at' => 'datetime',
            'received_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'faturado_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function situacaoLabels(): array
    {
        return [
            self::SITUACAO_PENDENTE => 'Pendente',
            self::SITUACAO_FINANCEIRO => 'Financeiro',
            self::SITUACAO_CONFIRMADO => 'Confirmado',
            self::SITUACAO_FATURADO => 'Faturado',
            self::SITUACAO_CANCELADO => 'Cancelado',
        ];
    }

    public function situacaoLabel(): string
    {
        return self::situacaoLabels()[$this->situacao] ?? 'Pendente';
    }

    /**
     * Cor (paleta Filament) usada no badge/linha da situação.
     */
    public function situacaoColor(): string
    {
        return match ($this->situacao) {
            self::SITUACAO_PENDENTE => 'gray',
            self::SITUACAO_FINANCEIRO => 'warning',
            self::SITUACAO_CONFIRMADO => 'info',
            self::SITUACAO_FATURADO => 'success',
            self::SITUACAO_CANCELADO => 'danger',
            default => 'gray',
        };
    }

    public function temRestricaoFinanceira(): bool
    {
        if ($this->situacao === self::SITUACAO_FINANCEIRO) {
            return true;
        }

        $payload = is_array($this->payload) ? $this->payload : [];

        return ! empty($payload['restricao_financeira']);
    }

    /**
     * Resumo estruturado para o modal de liberação (motivos + situação).
     *
     * @return array{motivos: list<string>, situacao: list<array{label: string, valor: string}>}
     */
    public function financeiroResumo(): array
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $money = static fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');

        $hasSnapshot = array_key_exists('credito_total_aberto', $payload)
            || array_key_exists('credito_limite', $payload)
            || ! empty($payload['credito_titulos_vencidos'])
            || ! empty($payload['credito_limite_excedido'])
            || ! empty($payload['credito_boleto_atrasado']);

        if (! $hasSnapshot) {
            $texto = trim((string) ($payload['credito_motivo'] ?? ''));

            return [
                'motivos' => [$texto !== '' ? $texto : 'Pendência financeira informada pelo aplicativo.'],
                'situacao' => [],
            ];
        }

        $motivos = [];
        if (! empty($payload['credito_titulos_vencidos'])) {
            $motivos[] = 'Títulos vencidos: '.$money((float) ($payload['credito_titulos_vencidos_saldo'] ?? 0));
        }
        if (! empty($payload['credito_boleto_atrasado'])) {
            $motivos[] = 'Boletos vencidos: '.$money((float) ($payload['credito_boleto_saldo'] ?? 0));
        }
        if (! empty($payload['credito_limite_excedido'])) {
            $motivos[] = 'Limite insuficiente / excedido';
        }
        if (! empty($payload['credito_cliente_em_debito']) && empty($payload['credito_titulos_vencidos'])) {
            $motivos[] = 'Cliente com débitos em aberto';
        }

        $limiteVal = (float) ($payload['credito_limite'] ?? 0);
        $situacao = [
            ['label' => 'Em aberto', 'valor' => $money((float) ($payload['credito_total_aberto'] ?? 0))],
        ];
        if (! empty($payload['credito_titulos_vencidos'])) {
            $situacao[] = [
                'label' => 'Vencidos',
                'valor' => $money((float) ($payload['credito_titulos_vencidos_saldo'] ?? 0)),
            ];
        }
        $situacao[] = [
            'label' => 'Limite',
            'valor' => $limiteVal > 0 ? $money($limiteVal) : 'não cadastrado',
        ];
        if ($limiteVal > 0) {
            $situacao[] = [
                'label' => 'Disponível',
                'valor' => $money((float) ($payload['credito_disponivel'] ?? 0)),
            ];
        }
        $situacao[] = [
            'label' => 'Pedido',
            'valor' => $money((float) ($payload['credito_total_pedido'] ?? $this->total)),
        ];
        $situacao[] = [
            'label' => 'Aberto após pedido',
            'valor' => $money((float) ($payload['credito_aberto_apos_pedido'] ?? 0)),
        ];
        if ($limiteVal > 0) {
            $situacao[] = [
                'label' => 'Disponível após',
                'valor' => $money((float) ($payload['credito_disponivel_apos_pedido'] ?? 0)),
            ];
        }

        return [
            'motivos' => $motivos,
            'situacao' => $situacao,
        ];
    }

    /**
     * Texto resumido da restrição financeira (vindo do app).
     */
    public function motivoFinanceiro(): string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        $motivo = trim((string) ($payload['credito_motivo'] ?? ''));
        if ($motivo !== '') {
            return $motivo;
        }

        $resumo = $this->financeiroResumo();
        $linhas = [];
        if ($resumo['motivos'] !== []) {
            $linhas[] = 'MOTIVOS:';
            foreach ($resumo['motivos'] as $m) {
                $linhas[] = '• '.$m;
            }
        }
        if ($resumo['situacao'] !== []) {
            if ($linhas !== []) {
                $linhas[] = '';
            }
            $linhas[] = 'SITUAÇÃO:';
            foreach ($resumo['situacao'] as $row) {
                $linhas[] = $row['label'].': '.$row['valor'];
            }
        }

        return $linhas !== []
            ? implode("\n", $linhas)
            : 'Pendência financeira informada pelo aplicativo.';
    }

    public function clienteNome(): string
    {
        $payload = $this->payload ?? [];

        return $this->cliente?->nome_razao
            ?? ($payload['cliente_nome'] ?? null)
            ?? ($this->cliente_id ? '#' . $this->cliente_id : '—');
    }

    /**
     * Data/hora em que a venda foi registrada no app (não a sincronização).
     */
    public function dataAberturaAt(): ?\Illuminate\Support\Carbon
    {
        return $this->client_created_at ?? $this->received_at;
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'cliente_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }
}
