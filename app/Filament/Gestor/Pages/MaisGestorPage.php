<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Models\GestorPushSubscription;
use App\Support\Gestor\GestorAprovacaoService;
use App\Support\Gestor\GestorPushService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class MaisGestorPage extends Page
{
    use InteractsWithGestorShell;

    protected static ?string $slug = 'mais';

    protected static ?string $title = 'Mais';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.gestor.mais';

    public int $aprovacoesPendentes = 0;

    public bool $pushAtivo = false;

    public bool $pushDisponivel = false;

    public static function canAccess(): bool
    {
        return static::canAccessGestor();
    }

    public function mount(): void
    {
        $this->mountGestorShell();
        $this->aprovacoesPendentes = app(GestorAprovacaoService::class)->countPendencias();
        $push = app(GestorPushService::class);
        $this->pushDisponivel = $push->isConfigured();
        $user = Auth::user();
        $this->pushAtivo = $user !== null
            && GestorPushSubscription::query()->where('user_id', $user->id)->exists();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function produtosUrl(): string
    {
        return ProdutosGestorPage::getUrl(panel: 'gestor');
    }

    public function aprovacoesUrl(): string
    {
        return AprovacoesGestorPage::getUrl(panel: 'gestor');
    }

    public function vapidPublicKey(): ?string
    {
        return app(GestorPushService::class)->publicKey();
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    public function salvarPushSubscription(array $subscription): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        try {
            app(GestorPushService::class)->subscribe($user, $subscription);
            $this->pushAtivo = true;
            Notification::make()->title('Notificações ativadas')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Falha ao ativar')->body($e->getMessage())->danger()->send();
        }
    }

    public function desativarPush(?string $endpoint = null): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        app(GestorPushService::class)->unsubscribe($user, $endpoint);
        $this->pushAtivo = GestorPushSubscription::query()->where('user_id', $user->id)->exists();
        Notification::make()->title('Notificações desativadas')->success()->send();
    }

    public function testarPush(): void
    {
        $n = app(GestorPushService::class)->notifyTest();

        if ($n > 0) {
            Notification::make()->title('Push de teste enviado')->success()->send();
        } else {
            Notification::make()
                ->title('Não enviado')
                ->body('Ative as notificações neste aparelho primeiro (e use HTTPS ou localhost).')
                ->warning()
                ->send();
        }
    }
}
