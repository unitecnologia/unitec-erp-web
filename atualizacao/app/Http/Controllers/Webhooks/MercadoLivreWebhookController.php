<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMeliOrderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoLivreWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $topic = trim((string) $request->input('topic', ''));
        $resource = trim((string) $request->input('resource', ''));
        $userId = $request->input('user_id');

        Log::info('meli.webhook.received', [
            'topic' => $topic,
            'resource' => $resource,
            'user_id' => $userId,
        ]);

        if ($topic === '' || $resource === '' || $userId === null || $userId === '') {
            return response()->json(['ok' => true]);
        }

        if (! in_array($topic, ['orders', 'shipments'], true)) {
            return response()->json(['ok' => true]);
        }

        try {
            ProcessMeliOrderNotification::dispatch($topic, $resource, $userId);
        } catch (\Throwable $e) {
            Log::error('meli.webhook.dispatch_failed', [
                'topic' => $topic,
                'resource' => $resource,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
