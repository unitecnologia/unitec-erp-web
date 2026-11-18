<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\RhAnexo;
use App\Models\RhExame;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhAnexoService;
use App\Support\Rh\RhExameService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RhAnexoDownloadController extends Controller
{
    public function __invoke(RhAnexo $anexo, RhAnexoService $service): BinaryFileResponse
    {
        if (! $this->canAccessCategoria((string) $anexo->categoria)) {
            abort(403);
        }

        $path = $service->absolutePath($anexo);

        if ($path === null || ! is_file($path)) {
            abort(404);
        }

        if ($anexo->categoria === RhAnexo::CAT_EXAME) {
            $this->auditExameAccess($anexo);
        }

        $nome = $this->downloadName($anexo);

        return response()->file($path, [
            'Content-Type' => $anexo->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function canAccessCategoria(string $categoria): bool
    {
        if ($categoria === RhAnexo::CAT_DOCUMENTO) {
            return ErpAccess::currentCan('rh.documentos.access')
                || ErpAccess::currentCan('rh.funcionarios.access');
        }

        if ($categoria === RhAnexo::CAT_EPI) {
            return ErpAccess::currentCan('rh.epis.access')
                || ErpAccess::currentCan('rh.funcionarios.access');
        }

        if ($categoria === RhAnexo::CAT_UNIFORME) {
            return ErpAccess::currentCan('rh.uniformes.access')
                || ErpAccess::currentCan('rh.funcionarios.access');
        }

        if ($categoria === RhAnexo::CAT_EXAME) {
            return ErpAccess::currentCan('rh.exames.access');
        }

        if ($categoria === RhAnexo::CAT_FERIAS) {
            return ErpAccess::currentCan('rh.ferias.access');
        }

        if ($categoria === RhAnexo::CAT_HOLERITE) {
            // Informação salarial: só quem tem permissão específica de holerites.
            return ErpAccess::currentCan('rh.holerites.access');
        }

        if ($categoria === RhAnexo::CAT_OCORRENCIA) {
            return ErpAccess::currentCan('rh.ocorrencias.access');
        }

        if ($categoria === RhAnexo::CAT_TREINAMENTO) {
            return ErpAccess::currentCan('rh.treinamentos.access')
                || ErpAccess::currentCan('rh.funcionarios.access');
        }

        return ErpAccess::currentCan('rh.funcionarios.access')
            || ErpAccess::currentCan('rh.dashboard.access');
    }

    private function auditExameAccess(RhAnexo $anexo): void
    {
        if ($anexo->anexavel_type !== (new RhExame)->getMorphClass()) {
            return;
        }

        $exame = RhExame::query()->find($anexo->anexavel_id);
        if (! $exame) {
            return;
        }

        app(RhExameService::class)->registrarAcessoAnexo($exame, $anexo);
    }

    private function downloadName(RhAnexo $anexo): string
    {
        $base = preg_replace('/[^\w\-]+/u', '_', (string) $anexo->titulo) ?: 'anexo';
        $ext = pathinfo((string) $anexo->caminho, PATHINFO_EXTENSION);

        return $ext !== '' ? $base.'.'.$ext : $base;
    }
}
