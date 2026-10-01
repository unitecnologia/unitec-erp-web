<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NfseDpsSequencia extends Model
{
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
     * Não usa MAX()+1 da tabela de notas.
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

        $proximo = ((int) $sequencia->ultimo_numero) + 1;
        $sequencia->ultimo_numero = $proximo;
        $sequencia->save();

        return $proximo;
    }

    protected function casts(): array
    {
        return [
            'ultimo_numero' => 'integer',
        ];
    }
}
