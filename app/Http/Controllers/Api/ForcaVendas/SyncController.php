<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Models\ForcaVendasDevice;
use App\Models\User;
use App\Support\ForcaVendas\ForcaVendasSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SyncController
{
    public function __construct(private readonly ForcaVendasSyncService $service)
    {
    }

    public function pull(Request $request): JsonResponse
    {
        $vendedorId = $request->user()?->vendedor_id;

        $signature = $this->service->pullSignature($vendedorId);
        $etag = '"'.$signature.'"';

        $this->touchDevice($request, pull: true);

        $ifNoneMatch = trim((string) $request->header('If-None-Match'), '"');

        if ($ifNoneMatch !== '' && hash_equals($signature, $ifNoneMatch)) {
            return response()->json(null, 304)->setEtag($signature);
        }

        $since = null;
        $sinceRaw = $request->query('since');

        if (is_string($sinceRaw) && $sinceRaw !== '') {
            try {
                $since = Carbon::parse($sinceRaw);
            } catch (\Throwable) {
                $since = null;
            }
        }

        $payload = $this->service->buildPull($since, $vendedorId);

        return response()->json($payload)->setEtag($signature);
    }

    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'customers' => ['nullable', 'array'],
            'customers.*.uuid' => ['required', 'string', 'max:100'],
            'customers.*.nome_razao' => ['required', 'string', 'max:255'],
            'orders' => ['nullable', 'array'],
            'orders.*.uuid' => ['required_with:orders', 'string', 'max:100'],
            'orders.*.cliente_id' => ['required_with:orders', 'integer'],
            'orders.*.itens' => ['required_with:orders', 'array', 'min:1'],
            'visitas_sem_venda' => ['nullable', 'array'],
            'visitas_sem_venda.*.uuid' => ['required', 'string', 'max:100'],
            'visitas_sem_venda.*.cliente_id' => ['required', 'integer'],
            'visitas_sem_venda.*.motivo' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $orders = (array) $request->input('orders', []);
        $visitas = (array) $request->input('visitas_sem_venda', []);
        $customers = (array) $request->input('customers', []);

        /** @var User $user */
        $user = $request->user();

        $this->touchDevice($request);

        $customerResults = $customers !== []
            ? $this->service->applyCustomersPush($customers, $user)
            : [];

        $results = $orders !== []
            ? $this->service->applyPush($orders, $user)
            : [];

        $visitaResults = $visitas !== []
            ? $this->service->applyVisitasPush($visitas, $user)
            : [];

        return response()->json([
            'customer_results' => $customerResults,
            'results' => $results,
            'visita_results' => $visitaResults,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function touchDevice(Request $request, bool $pull = false): void
    {
        $deviceUuid = (string) $request->header('X-FV-Device', '');

        if ($deviceUuid === '') {
            return;
        }

        $attrs = ['last_seen_at' => now()];

        if ($pull) {
            $attrs['last_pull_at'] = now();
        }

        ForcaVendasDevice::query()
            ->where('device_uuid', $deviceUuid)
            ->update($attrs);
    }
}
