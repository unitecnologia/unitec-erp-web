<?php

namespace App\Support\Erp\Audit;

use App\Models\ErpOperacaoLog;
use App\Models\User;
use App\Support\Erp\ErpSchema;
use Illuminate\Support\Facades\Auth;

final class ErpOperacaoLogService
{
    /**
     * @param  array<string, mixed>|null  $detalhes
     */
    public function registrar(
        string $operacao,
        string $resumo,
        string $resultado = 'ok',
        ?string $origem = null,
        ?string $documentoTipo = null,
        ?int $documentoId = null,
        ?string $documentoNumero = null,
        ?array $detalhes = null,
        ?int $empresaId = null,
        ?User $user = null,
    ): void {
        // ErpSchema memoiza no processo — evita information_schema a cada gravação.
        if (! ErpSchema::hasTable('erp_operacao_logs')) {
            return;
        }

        $user ??= Auth::user();
        $empresaId ??= session('erp_empresa_id') ? (int) session('erp_empresa_id') : null;

        ErpOperacaoLog::query()->create([
            'ocorrido_em' => now(),
            'user_id' => $user?->id,
            'user_nome' => $user?->name,
            'empresa_id' => $empresaId,
            'operacao' => mb_strtoupper($operacao, 'UTF-8'),
            'origem' => $origem,
            'documento_tipo' => $documentoTipo,
            'documento_id' => $documentoId,
            'documento_numero' => $documentoNumero,
            'resultado' => $resultado,
            'resumo' => $resumo,
            'detalhes' => $detalhes,
        ]);
    }
}
