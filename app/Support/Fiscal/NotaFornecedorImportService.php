<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use App\Support\Erp\NotaFornecedor\NotaFornecedorItensSyncService;
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
            ->where('chave', $documento->chave)
            ->where(function ($query) use ($empresa): void {
                $query->where('empresa_id', $empresa->id)
                    ->orWhereNull('empresa_id');
            })
            ->orderByRaw('empresa_id IS NULL')
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
            $payload['xml'] = $this->preservarXmlCompleto($existente->xml, $payload['xml']);

            if ($existente->status === NotaFornecedor::STATUS_GEROU_COMPRAS) {
                $updateGerou = [
                    'nsu' => $payload['nsu'] ?? $existente->nsu,
                    'total' => $payload['total'],
                    'xml' => $payload['xml'],
                ];
                if ($existente->empresa_id === null) {
                    $updateGerou['empresa_id'] = $empresa->id;
                }
                $existente->update($updateGerou);
            } else {
                $existente->update($payload);
            }

            $nota = $existente->fresh() ?? $existente;
            $this->syncItens($nota);
            $this->dispararPortalContador($nota, $empresa, (string) ($nota->xml ?? ''), $syncImmediate);

            return ['nota' => $nota, 'criada' => false];
        }

        $nota = NotaFornecedor::query()->create([
            ...$payload,
            'status' => NotaFornecedor::STATUS_PENDENTE,
        ]);

        $this->syncItens($nota);
        $this->dispararPortalContador($nota, $empresa, $documento->xml, $syncImmediate);

        return ['nota' => $nota, 'criada' => true];
    }

    public function preservarXmlCompleto(?string $atual, ?string $novo): ?string
    {
        if ($this->xmlTemItens($atual) && ! $this->xmlTemItens($novo)) {
            return $atual;
        }

        if (filled($novo)) {
            return $novo;
        }

        return $atual;
    }

    private function xmlTemItens(?string $xml): bool
    {
        if ($xml === null || $xml === '') {
            return false;
        }

        return str_contains($xml, '<det') && (str_contains($xml, '<nfeProc') || str_contains($xml, '<infNFe'));
    }

    private function syncItens(NotaFornecedor $nota): void
    {
        if (blank($nota->xml)) {
            return;
        }

        (new NotaFornecedorItensSyncService())->sync($nota);
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
