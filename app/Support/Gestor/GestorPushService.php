<?php

namespace App\Support\Gestor;

use App\Models\ForcaVendasDevice;
use App\Models\ForcaVendasOrder;
use App\Models\GestorPushSubscription;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\Dashboard\ErpDashboardGauges;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

final class GestorPushService
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        $key = config('services.webpush.public_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * @param  array{endpoint?: string, keys?: array{p256dh?: string, auth?: string}, contentEncoding?: string}  $subscription
     */
    public function subscribe(User $user, array $subscription, ?int $empresaId = null): GestorPushSubscription
    {
        $endpoint = (string) ($subscription['endpoint'] ?? '');

        if ($endpoint === '') {
            throw new \InvalidArgumentException('Endpoint de push inválido.');
        }

        $keys = $subscription['keys'] ?? [];

        return GestorPushSubscription::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
            ],
            [
                'empresa_id' => $empresaId ?: (int) (session('erp_empresa_id') ?? $user->empresa_id ?? 0) ?: null,
                'public_key' => (string) ($keys['p256dh'] ?? ''),
                'auth_token' => (string) ($keys['auth'] ?? ''),
                'content_encoding' => (string) ($subscription['contentEncoding'] ?? 'aesgcm'),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );
    }

    public function unsubscribe(User $user, ?string $endpoint = null): int
    {
        $q = GestorPushSubscription::query()->where('user_id', $user->id);

        if (filled($endpoint)) {
            $q->where('endpoint', $endpoint);
        }

        return (int) $q->delete();
    }

    public function notifyPedidoPendente(ForcaVendasOrder $order): void
    {
        if ($order->tipo !== ForcaVendasOrder::TIPO_PEDIDO) {
            return;
        }

        $this->sendToGestores(
            title: 'Pedido aguardando aprovação',
            body: 'Pedido #'.$order->id.' · '.$order->clienteNome().' · R$ '.number_format((float) $order->total, 2, ',', '.'),
            url: '/gestor/aprovacoes',
            tag: 'pedido-'.$order->id,
            empresaId: (int) ($order->empresa_id ?? 0),
        );
    }

    public function notifyAparelhoPendente(ForcaVendasDevice $device): void
    {
        $this->sendToGestores(
            title: 'Aparelho aguardando autorização',
            body: ($device->device_name ?: 'Aparelho').' · '.($device->platform ?: 'mobile'),
            url: '/gestor/aprovacoes',
            tag: 'aparelho-fv-'.$device->id,
            empresaId: (int) ($device->empresa_id ?? 0),
        );
    }

    public function notifyTest(?User $user = null): int
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return 0;
        }

        return $this->sendToUser(
            $user,
            'Unitec Executivo',
            'Notificações ativas neste aparelho.',
            '/gestor',
            'gestor-teste'
        );
    }

    /**
     * Alertas periódicos: estoque baixo, contas vencidas, meta atingida.
     */
    public function dispararAlertasDiarios(): array
    {
        $stats = [
            'estoque' => 0,
            'receber' => 0,
            'meta' => 0,
        ];

        $hoje = now()->toDateString();

        try {
            $estoqueBaixo = (int) Product::query()->estoqueCritico()->count();
            if ($estoqueBaixo > 0 && $this->oncePerDay('estoque', $hoje)) {
                $stats['estoque'] = $this->sendToGestores(
                    title: 'Estoque baixo',
                    body: $estoqueBaixo.' produto(s) abaixo do mínimo.',
                    url: '/gestor/estoque',
                    tag: 'estoque-'.$hoje,
                );
            }
        } catch (Throwable $e) {
            Log::warning('gestor.push.estoque', ['error' => $e->getMessage()]);
        }

        try {
            $svc = app(GestorExecutivoService::class);
            $snap = $svc->snapshot();
            $receber = (float) ($snap['receber_vencido'] ?? 0);
            if ($receber > 0 && $this->oncePerDay('receber', $hoje)) {
                $stats['receber'] = $this->sendToGestores(
                    title: 'Contas vencidas',
                    body: 'A receber vencido: R$ '.number_format($receber, 2, ',', '.'),
                    url: '/gestor/financeiro',
                    tag: 'receber-'.$hoje,
                );
            }
        } catch (Throwable $e) {
            Log::warning('gestor.push.receber', ['error' => $e->getMessage()]);
        }

        try {
            $empresaId = app(GestorExecutivoService::class)->empresaId();
            $metas = ErpDashboardGauges::buildVendedores($empresaId > 0 ? $empresaId : null);
            foreach ($metas as $meta) {
                $pct = (float) ($meta['percent'] ?? 0);
                if ($pct < 100) {
                    continue;
                }
                $key = 'meta-'.($meta['key'] ?? md5((string) ($meta['label'] ?? '')));
                if (! $this->oncePerDay($key, $hoje)) {
                    continue;
                }
                $stats['meta'] += $this->sendToGestores(
                    title: 'Meta atingida',
                    body: ($meta['full_name'] ?? $meta['label'] ?? 'Vendedor').' em '.number_format($pct, 0).'% da meta.',
                    url: '/gestor/vendas',
                    tag: $key.'-'.$hoje,
                    empresaId: $empresaId,
                );
            }
        } catch (Throwable $e) {
            Log::warning('gestor.push.meta', ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    /**
     * @return int Quantidade de envios tentados
     */
    public function sendToGestores(
        string $title,
        string $body,
        string $url = '/gestor',
        string $tag = 'gestor',
        ?int $empresaId = null,
    ): int {
        if (! $this->isConfigured()) {
            return 0;
        }

        $q = GestorPushSubscription::query()->with('user');

        if ($empresaId && $empresaId > 0) {
            $q->where(function ($builder) use ($empresaId): void {
                $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            });
        }

        $sent = 0;

        foreach ($q->get() as $sub) {
            $user = $sub->user;
            if (! $user || ! $user->ativo) {
                continue;
            }

            $sent += $this->sendSubscription($sub, $title, $body, $url, $tag) ? 1 : 0;
        }

        return $sent;
    }

    public function sendToUser(User $user, string $title, string $body, string $url = '/gestor', string $tag = 'gestor'): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $sent = 0;

        foreach (GestorPushSubscription::query()->where('user_id', $user->id)->get() as $sub) {
            $sent += $this->sendSubscription($sub, $title, $body, $url, $tag) ? 1 : 0;
        }

        return $sent;
    }

    private function sendSubscription(
        GestorPushSubscription $sub,
        string $title,
        string $body,
        string $url,
        string $tag,
    ): bool {
        try {
            $webPush = $this->client();
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
            ]);

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'tag' => $tag,
                'icon' => '/pwa-gestor/icons/icon-192.png',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $webPush->queueNotification($subscription, $payload ?: '{}');

            foreach ($webPush->flush() as $report) {
                if (! $report->isSuccess()) {
                    $code = $report->getResponse()?->getStatusCode();
                    if (in_array($code, [404, 410], true)) {
                        $sub->delete();
                    }
                    Log::info('gestor.push.fail', [
                        'endpoint' => $sub->endpoint,
                        'reason' => $report->getReason(),
                        'code' => $code,
                    ]);

                    return false;
                }
            }

            $sub->forceFill(['last_used_at' => now()])->save();

            return true;
        } catch (Throwable $e) {
            Log::warning('gestor.push.error', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => (string) config('services.webpush.subject', 'mailto:suporte@unitecnologia.com.br'),
                'publicKey' => (string) config('services.webpush.public_key'),
                'privateKey' => (string) config('services.webpush.private_key'),
            ],
        ]);
    }

    private function oncePerDay(string $key, string $date): bool
    {
        return Cache::add('gestor_push_once:'.$key.':'.$date, 1, now()->endOfDay());
    }
}
