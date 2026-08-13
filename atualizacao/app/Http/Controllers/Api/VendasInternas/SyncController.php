<?php

namespace App\Http\Controllers\Api\VendasInternas;

use App\Models\User;
use App\Models\VendasInternasDevice;
use App\Support\VendasInternas\VendasInternasSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SyncController
{
    public function __construct(private readonly VendasInternasSyncService $service)
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
            'orders' => ['nullable', 'array'],
            'orders.*.uuid' => ['required_with:orders', 'string', 'max:100'],
            'orders.*.cliente_id' => ['required_with:orders', 'integer'],
            'orders.*.itens' => ['required_with:orders', 'array', 'min:1'],
        ]);

        $orders = (array) $request->input('orders', []);

        /** @var User $user */
        $user = $request->user();

        $this->touchDevice($request);

        $results = $orders !== []
            ? $this->service->applyPush($orders, $user)
            : [];

        return response()->json([
            'results' => $results,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function touchDevice(Request $request, bool $pull = false): void
    {
        $deviceUuid = (string) $request->header('X-VI-Device', '');

        if ($deviceUuid === '') {
            return;
        }

        $attrs = ['last_seen_at' => now()];

        if ($pull) {
            $attrs['last_pull_at'] = now();
        }

        VendasInternasDevice::query()
            ->where('device_uuid', $deviceUuid)
            ->update($attrs);
    }
}
