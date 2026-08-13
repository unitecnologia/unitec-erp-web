<?php

namespace App\Support\Erp\Import;

use App\Models\Cfop;
use Throwable;

/**
 * Sincroniza a tabela CFOP do Firebird para o ERP web (opcional / manual).
 * A tela /admin/cfops usa a base web (seed); este sync não deve rodar no mount.
 */
final class CfopFirebirdSync
{
    public function __construct(
        private readonly FirebirdIsqlClient $fb = new FirebirdIsqlClient,
        private readonly FirebirdCadastrosImportService $importer = new FirebirdCadastrosImportService,
    ) {
    }

    /**
     * Importa do Firebird se a base local estiver vazia (ou força com $force).
     * Não lança exceção — retorna message em falha.
     *
     * @return array{created: int, updated: int, skipped: int, imported: bool, message?: string}
     */
    public function ensureImported(bool $force = false): array
    {
        if (! $force && Cfop::query()->exists()) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'imported' => false];
        }

        if (! (bool) config('firebird.enabled', false)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'imported' => false,
                'message' => 'Firebird desabilitado (FB_ENABLED=false).',
            ];
        }

        try {
            $rows = $this->fb->query('SELECT CODIGO, DESCRICAO, TIPO, MOV_ES, OPERACAO, ATIVO FROM CFOP ORDER BY CODIGO');
            $stats = $this->importer->importCfops($rows, updateExisting: true, dryRun: false);

            return [...$stats, 'imported' => true];
        } catch (Throwable $e) {
            report($e);

            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'imported' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
