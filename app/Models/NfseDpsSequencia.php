<?php

namespace App\Models;

use App\Support\Erp\Nfse\NfseDpsNumeracaoRecusada;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NfseDpsSequencia extends Model
{
    /** TSSerieDPS: 1 a 4 dígitos, ou 5 dígitos de 00000 a 89999. */
    public const SERIE_PATTERN = '/^(?:[0-9]{1,4}|[0-8][0-9]{4})$/';

    public const NUMERO_MAX = 999999999999999;

    protected $table = 'nfse_dps_sequencias';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'empresa_id',
        'serie_dps',
        'ultimo_numero',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Próximo Nº DPS da série, com trava da linha de sequência.
     * Só pode ser chamado dentro da transaction da gravação.
     * A sequência configurada manda; se já houver DPS nessa série, o número fica acima da maior gravada.
     */
    public static function proximo(int $empresaId, string $serie): int
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('A sequência DPS só pode ser consumida dentro de uma transação.');
        }

        $agora = now();

        static::query()->insertOrIgnore([
            'empresa_id' => $empresaId,
            'serie_dps' => $serie,
            'ultimo_numero' => 0,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $sequencia = static::query()
            ->where('empresa_id', $empresaId)
            ->where('serie_dps', $serie)
            ->lockForUpdate()
            ->first();

        if ($sequencia === null) {
            throw new RuntimeException('Não foi possível reservar o número DPS.');
        }

        $maxUsado = (int) (Nfse::query()
            ->where('empresa_id', $empresaId)
            ->where('serie_dps', $serie)
            ->max('numero_dps') ?? 0);

        $proximo = max(((int) $sequencia->ultimo_numero) + 1, $maxUsado + 1);
        $sequencia->ultimo_numero = $proximo;
        $sequencia->save();

        return $proximo;
    }

    public static function serieEmUso(int $empresaId): string
    {
        $serie = trim((string) Empresa::query()->whereKey($empresaId)->value('nfse_serie_dps'));

        return $serie !== '' ? $serie : Nfse::SERIE_DPS;
    }

    public static function proximoConfigurado(int $empresaId, string $serie): int
    {
        $ultimo = static::query()
            ->where('empresa_id', $empresaId)
            ->where('serie_dps', $serie)
            ->value('ultimo_numero');

        return ((int) $ultimo) + 1;
    }

    /**
     * Grava a série em uso e o próximo número, sem alterar DPS já gravadas.
     * Recusa número menor ou igual ao maior já usado na série.
     */
    public static function configurar(int $empresaId, string $serie, int $proximo): void
    {
        if ($proximo < 1 || $proximo > self::NUMERO_MAX) {
            throw new NfseDpsNumeracaoRecusada('Próximo Nº DPS inválido.');
        }

        DB::transaction(function () use ($empresaId, $serie, $proximo): void {
            $agora = now();

            static::query()->insertOrIgnore([
                'empresa_id' => $empresaId,
                'serie_dps' => $serie,
                'ultimo_numero' => 0,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);

            $sequencia = static::query()
                ->where('empresa_id', $empresaId)
                ->where('serie_dps', $serie)
                ->lockForUpdate()
                ->first();

            if ($sequencia === null) {
                throw new NfseDpsNumeracaoRecusada('Não foi possível gravar a numeração da DPS.');
            }

            $maxUsado = (int) (Nfse::query()
                ->where('empresa_id', $empresaId)
                ->where('serie_dps', $serie)
                ->orderByDesc('numero_dps')
                ->lockForUpdate()
                ->value('numero_dps') ?? 0);

            if ($proximo <= $maxUsado) {
                throw new NfseDpsNumeracaoRecusada(self::mensagemReuso($serie, $maxUsado));
            }

            $sequencia->ultimo_numero = $proximo - 1;
            $sequencia->save();

            Empresa::query()->whereKey($empresaId)->update([
                'nfse_serie_dps' => $serie,
            ]);
        });
    }

    public static function recusaProximo(int $empresaId, string $serie, int $proximo): ?string
    {
        $maxUsado = (int) (Nfse::query()
            ->where('empresa_id', $empresaId)
            ->where('serie_dps', $serie)
            ->max('numero_dps') ?? 0);

        if ($proximo <= $maxUsado) {
            return self::mensagemReuso($serie, $maxUsado);
        }

        return null;
    }

    public static function mensagemReuso(string $serie, int $maxUsado): string
    {
        return "O próximo Nº DPS precisa ser maior que {$maxUsado}, já usado na série {$serie}.";
    }

    protected function casts(): array
    {
        return [
            'ultimo_numero' => 'integer',
        ];
    }
}
