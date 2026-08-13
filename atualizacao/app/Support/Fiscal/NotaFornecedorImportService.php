<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use Illuminate\Support\Carbon;
use Unitec\FiscalEngine\Dto\DfeResumoNfe;

final class NotaFornecedorImportService
{
    /**
     * @return array{nota: NotaFornecedor, criada: bool}
     */
    public function importarResumo(DfeResumoNfe $documento, Empresa $empresa, bool $syncImmediate = true): array
    {
        if (blank($documento->chave)) {
            throw new \InvalidArgumentException('Documento sem chave de acesso.');
        }

        $existente = NotaFornecedor::query()
            ->where('empresa_id', $empresa->id)
            ->where('chave', $documento->chave)
            ->first();

        $dataEntrada = $documento->dataRecebimento
            ? Carbon::instance($documento->dataRecebimento)
            : now();

        $payload = [
            'empresa_id' => $empresa->id,
            'data_entrada' => $dataEntrada->toDateString(),
            'data_emissao' => Carbon::instance($documento->dataEmissao)->toDateString(),
            'numero' => $documento->numero !== '' ? $documento->numero : $this->numeroFromChave($documento->chave),
            'chave' => $documento->chave,
            'cnpj' => $documento->cnpj,
            'nome' => mb_strtoupper($documento->nome, 'UTF-8'),
            'nsu' => $documento->nsu !== '' ? self::formatNsuCurto($documento->nsu) : null,
            'total' => $documento->total,
            'xml' => $documento->xml !== '' ? $documento->xml : null,
        ];

        if ($existente) {
            if ($existente->status === NotaFornecedor::STATUS_GEROU_COMPRAS) {
                $existente->update([
                    'nsu' => $payload['nsu'] ?? $existente->nsu,
                    'total' => $payload['total'],
                    'xml' => $payload['xml'] ?? $existente->xml,
                ]);

                $nota = $existente->fresh() ?? $existente;
                $this->dispararPortalContador($nota, $empresa, $documento->xml, $syncImmediate);

                return ['nota' => $nota, 'criada' => false];
            }

            $existente->update($payload);

            $nota = $existente->fresh() ?? $existente;
            $this->dispararPortalContador($nota, $empresa, $documento->xml, $syncImmediate);

            return ['nota' => $nota, 'criada' => false];
        }

        $nota = NotaFornecedor::query()->create([
            ...$payload,
            'status' => NotaFornecedor::STATUS_PENDENTE,
        ]);

        $this->dispararPortalContador($nota, $empresa, $documento->xml, $syncImmediate);

        return ['nota' => $nota, 'criada' => true];
    }

    private function dispararPortalContador(
        NotaFornecedor $nota,
        Empresa $empresa,
        string $xml,
        bool $immediate = true,
    ): void {
        (new ContadorCloudPortalHookService())->onNotaFornecedorImportada(
            $nota,
            $empresa,
            $xml !== '' ? $xml : null,
            $immediate,
        );
    }

    private function numeroFromChave(string $chave): string
    {
        $digits = preg_replace('/\D/', '', $chave) ?? '';

        if (strlen($digits) !== 44) {
            return '';
        }

        $numero = ltrim(substr($digits, 25, 9), '0');

        return $numero !== '' ? $numero : '0';
    }

    private static function formatNsuCurto(string $nsu): string
    {
        $digits = preg_replace('/\D/', '', $nsu) ?? '';

        if ($digits === '') {
            return $nsu;
        }

        $trimmed = ltrim($digits, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }
}
