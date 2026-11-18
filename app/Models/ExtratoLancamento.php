<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'empresa_id',
    'caixa_conta_id',
    'extrato_importacao_id',
    'data_movimento',
    'data_posted',
    'tipo',
    'valor',
    'historico',
    'memo',
    'payee',
    'check_num',
    'ref_num',
    'fitid',
    'fingerprint',
    'situacao',
    'ambiguo',
    'conciliado_em',
    'conciliado_por_user_id',
    'origem_tipo',
    'origem_id',
    'caixa_lancamento_id',
])]
class ExtratoLancamento extends Model
{
    public const TIPO_CREDITO = 'credito';

    public const TIPO_DEBITO = 'debito';

    public const TIPO_OUTRO = 'outro';

    public const SITUACAO_PENDENTE = 'pendente';

    public const SITUACAO_CONCILIADO = 'conciliado';

    public const SITUACAO_IGNORADO = 'ignorado';

    protected $table = 'extrato_lancamentos';

    protected function casts(): array
    {
        return [
            'data_movimento' => 'datetime',
            'data_posted' => 'datetime',
            'valor' => 'decimal:2',
            'ambiguo' => 'boolean',
            'conciliado_em' => 'datetime',
        ];
    }

    /**
     * Chave fraca (sem FITID) para contagem de ocorrência no arquivo.
     * Dois PIX iguais no mesmo dia geram a mesma chave e ocorrências 1 e 2.
     */
    public static function softIdentityKey(
        string $dataMovimento,
        string $tipo,
        string $valor,
        ?string $historico,
        ?string $memo,
        ?string $payee = null,
        ?string $checkNum = null,
        ?string $refNum = null,
    ): string {
        return implode('|', [
            substr(trim($dataMovimento), 0, 10),
            strtolower(trim($tipo)),
            number_format((float) $valor, 2, '.', ''),
            mb_strtoupper(trim((string) $historico), 'UTF-8'),
            mb_strtoupper(trim((string) $memo), 'UTF-8'),
            mb_strtoupper(trim((string) $payee), 'UTF-8'),
            mb_strtoupper(trim((string) $checkNum), 'UTF-8'),
            mb_strtoupper(trim((string) $refNum), 'UTF-8'),
        ]);
    }

    /**
     * Fingerprint estável anti-duplicidade (C2).
     * - Com FITID confiável: emp+conta+FITID (idempotente entre arquivos).
     * - Sem FITID: chave fraca + ocorrência no arquivo (1..N) — não colapsa 2 PIX iguais.
     */
    public static function buildFingerprint(
        ?string $fitid,
        string $dataMovimento,
        string $tipo,
        string $valor,
        ?string $historico,
        ?string $memo,
        int $ocorrencia = 1,
        ?string $payee = null,
        ?string $checkNum = null,
        ?string $refNum = null,
        int $empresaId = 0,
        int $caixaContaId = 0,
    ): string {
        $fitidNorm = strtoupper(trim((string) $fitid));
        if ($fitidNorm !== '') {
            return hash('sha256', implode('|', [
                'fitid',
                (string) $empresaId,
                (string) $caixaContaId,
                $fitidNorm,
            ]));
        }

        $soft = self::softIdentityKey(
            $dataMovimento,
            $tipo,
            $valor,
            $historico,
            $memo,
            $payee,
            $checkNum,
            $refNum,
        );

        return hash('sha256', implode('|', [
            'nofitid',
            (string) $empresaId,
            (string) $caixaContaId,
            $soft,
            'occ:'.max(1, $ocorrencia),
        ]));
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function caixaConta(): BelongsTo
    {
        return $this->belongsTo(CaixaConta::class, 'caixa_conta_id');
    }

    public function importacao(): BelongsTo
    {
        return $this->belongsTo(ExtratoImportacao::class, 'extrato_importacao_id');
    }

    public function conciliadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conciliado_por_user_id');
    }

    public function caixaLancamento(): BelongsTo
    {
        return $this->belongsTo(CaixaLancamento::class, 'caixa_lancamento_id');
    }

    /** Vínculos C5C.2 ativos (controle TEF × OFX). */
    public function vinculosTefAtivos(): HasMany
    {
        return $this->hasMany(TefLiquidacaoLoteExtrato::class, 'extrato_lancamento_id')
            ->where('status', TefLiquidacaoLoteExtrato::STATUS_ATIVO);
    }

    /** Grade: DD/MM/AAAA, e HH:MM só quando o OFX gravou relógio real. */
    public static function formatarDataMovimento(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00'
                ? $value->format('d/m/Y')
                : $value->format('d/m/Y H:i');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '—';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/', $raw, $m) !== 1) {
            return $raw;
        }

        $label = $m[3].'/'.$m[2].'/'.$m[1];
        $h = $m[4] ?? '00';
        $i = $m[5] ?? '00';
        $s = $m[6] ?? '00';
        if ($h === '00' && $i === '00' && ($s === '00' || $s === '')) {
            return $label;
        }

        return $label.' '.$h.':'.$i;
    }

    public function isPendente(): bool
    {
        return $this->situacao === self::SITUACAO_PENDENTE;
    }

    public function isConciliado(): bool
    {
        return $this->situacao === self::SITUACAO_CONCILIADO;
    }

    /** Crédito com vínculo TEF ativo — não disponível para C3. */
    public function temVinculoTefAtivo(): bool
    {
        $attrs = $this->getAttributes();
        if (array_key_exists('vinculos_tef_ativos_exists', $attrs)) {
            return (bool) $attrs['vinculos_tef_ativos_exists'];
        }

        if (! Schema::hasTable('tef_liquidacao_lote_extratos')) {
            return false;
        }

        return $this->vinculosTefAtivos()->exists();
    }
}
