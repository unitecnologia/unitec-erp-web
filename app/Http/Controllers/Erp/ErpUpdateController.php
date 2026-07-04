<?php



namespace App\Http\Controllers\Erp;



use App\Support\Erp\ErpUpdateProcessLauncher;

use App\Support\Erp\ErpUpdateService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;



class ErpUpdateController

{

    public function launch(Request $request): JsonResponse

    {

        abort_unless(Auth::check(), 403);



        ErpUpdateService::clearStaleLock();



        if (ErpUpdateService::isRunning()) {

            return response()->json([

                'message' => 'Já existe uma atualização em andamento.',

            ], 409);

        }



        ErpUpdateService::resetStatus();



        if (! ErpUpdateProcessLauncher::launch(base_path())) {

            ErpUpdateService::forceReset();



            return response()->json([

                'message' => 'Não foi possível iniciar o processo de atualização. Verifique storage/logs/erp-update-spawn.log e instalacao.log.',

            ], 500);

        }



        return response()->json([

            'message' => 'Atualização iniciada.',

        ]);

    }



    public function status(): JsonResponse

    {

        abort_unless(Auth::check(), 403);



        ErpUpdateService::clearStaleLock(300);



        return response()->json(ErpUpdateService::readStatus());

    }



    public function reset(Request $request): JsonResponse

    {

        abort_unless(Auth::check(), 403);



        ErpUpdateService::forceReset();



        return response()->json([

            'message' => 'Estado de atualização limpo.',

        ]);

    }

}


