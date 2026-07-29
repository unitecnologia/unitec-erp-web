<?php

namespace App\Models;

use App\Support\Erp\Fiscal\IcmsAliquotaTabela;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IcmsAliquota extends Model
{
    protected $table = 'icms_aliquotas';

    protected $fillable = [
        'uf_origem',
        'uf_destino',
        'aliquota',
    ];

    protected function casts(): array
    {
        return [
            'aliquota' => 'decimal:2',
        ];
    }

    public static function rate(string $ufOrigem, string $ufDestino): ?float
    {
        $origem = strtoupper(trim($ufOrigem));
        $destino = strtoupper(trim($ufDestino));

        if ($origem === '' || $destino === '') {
            return null;
        }

        $row = static::query()
            ->where('uf_origem', $origem)
            ->where('uf_destino', $destino)
            ->value('aliquota');

        return $row !== null ? (float) $row : null;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public static function matriz(): array
    {
        $ufs = IcmsAliquotaTabela::ufs();
        $matriz = [];

        foreach ($ufs as $origem) {
            foreach ($ufs as $destino) {
                $matriz[$origem][$destino] = 0.0;
            }
        }

        static::query()
            ->orderBy('uf_origem')
            ->orderBy('uf_destino')
            ->get(['uf_origem', 'uf_destino', 'aliquota'])
            ->each(function (self $row) use (&$matriz): void {
                $origem = strtoupper((string) $row->uf_origem);
                $destino = strtoupper((string) $row->uf_destino);

                if (! isset($matriz[$origem][$destino])) {
                    return;
                }

                $matriz[$origem][$destino] = (float) $row->aliquota;
            });

        return $matriz;
    }

    /**
     * Grava a matriz padrão 2026 (substitui todos os registros).
     */
    public static function seedPadrao2026(): int
    {
        $now = now();
        $payload = [];

        foreach (IcmsAliquotaTabela::matrizPadrao() as $origem => $destinos) {
            foreach ($destinos as $destino => $aliquota) {
                $payload[] = [
                    'uf_origem' => $origem,
                    'uf_destino' => $destino,
                    'aliquota' => number_format($aliquota, 2, '.', ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($payload): void {
            static::query()->delete();

            foreach (array_chunk($payload, 200) as $chunk) {
                static::query()->insert($chunk);
            }
        });

        return count($payload);
    }
}
