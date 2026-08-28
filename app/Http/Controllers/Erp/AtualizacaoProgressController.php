<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Erp\Atualizacao\AtualizacaoApplyService;
use Illuminate\Http\JsonResponse;

class AtualizacaoProgressController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(AtualizacaoApplyService::readProgress(base_path()))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
