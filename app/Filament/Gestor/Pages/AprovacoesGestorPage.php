<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Support\Erp\ErpAccess;
use App\Support\Gestor\GestorAprovacaoService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class AprovacoesGestorPage extends Page
{
    use InteractsWithGestorShell;

    protected static ?string $slug = 'aprovacoes';

    protected static ?string $title = 'Aprovações';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.gestor.aprovacoes';

    /** @var array{total: int, pedidos: list<array<string, mixed>>, aparelhos: list<array<string, mixed>>} */
    public array $pendencias = [
        'total' => 0,
        'pedidos' => [],
        'aparelhos' => [],
    ];

    public ?int $pedidoSelecionadoId = null;

    public static function canAccess(): bool
    {
        return static::canAccessGestor();
    }

    public function mount(): void
    {
        $this->mountGestorShell();
        $this->refreshPendencias();
    }

    public function refreshPendencias(): void
    {
        $this->pendencias = app(GestorAprovacaoService::class)->pendencias();

        if ($this->pedidoSelecionadoId !== null) {
            $aindaExiste = collect($this->pendencias['pedidos'] ?? [])
                ->contains(fn (array $p): bool => (int) ($p['id'] ?? 0) === $this->pedidoSelecionadoId);

            if (! $aindaExiste) {
                $this->pedidoSelecionadoId = null;
            }
        }
    }

    public function abrirPedido(int $id): void
    {
        $this->pedidoSelecionadoId = $id;
    }

    public function fecharPedido(): void
    {
        $this->pedidoSelecionadoId = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pedidoSelecionado(): ?array
    {
        if ($this->pedidoSelecionadoId === null) {
            return null;
        }

        foreach ($this->pendencias['pedidos'] ?? [] as $pedido) {
            if ((int) ($pedido['id'] ?? 0) === $this->pedidoSelecionadoId) {
                return $pedido;
            }
        }

        return null;
    }

    public function aprovarPedido(int $id): void
    {
        if (! $this->podeAprovarPedidos()) {
            $this->nega();

            return;
        }

        try {
            app(GestorAprovacaoService::class)->aprovarPedido($id);
            Notification::make()->title('Pedido liberado')->body('Voltou como Pendente (Enviado no app).')->success()->send();
            $this->pedidoSelecionadoId = null;
        } catch (\Throwable $e) {
            Notification::make()->title('Não foi possível liberar')->body($e->getMessage())->danger()->send();
        }

        $this->refreshPendencias();
    }

    public function rejeitarPedido(int $id): void
    {
        if (! $this->podeAprovarPedidos()) {
            $this->nega();

            return;
        }

        try {
            app(GestorAprovacaoService::class)->rejeitarPedido($id);
            Notification::make()->title('Pedido negado')->body('Pedido cancelado.')->success()->send();
            $this->pedidoSelecionadoId = null;
        } catch (\Throwable $e) {
            Notification::make()->title('Não foi possível negar')->body($e->getMessage())->danger()->send();
        }

        $this->refreshPendencias();
    }

    public function aprovarAparelho(string $origem, int $id): void
    {
        if (! $this->podeAprovarAparelhos($origem)) {
            $this->nega();

            return;
        }

        try {
            app(GestorAprovacaoService::class)->aprovarAparelho($origem, $id);
            Notification::make()->title('Aparelho autorizado')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Não foi possível autorizar')->body($e->getMessage())->danger()->send();
        }

        $this->refreshPendencias();
    }

    public function rejeitarAparelho(string $origem, int $id): void
    {
        if (! $this->podeRejeitarAparelhos($origem)) {
            $this->nega();

            return;
        }

        try {
            app(GestorAprovacaoService::class)->rejeitarAparelho($origem, $id);
            Notification::make()->title('Aparelho rejeitado')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Não foi possível rejeitar')->body($e->getMessage())->danger()->send();
        }

        $this->refreshPendencias();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function podeAprovarPedidos(): bool
    {
        $user = Auth::user();

        return $user !== null && (
            (bool) $user->is_admin
            || (bool) $user->is_supervisor
            || ErpAccess::can($user, 'forca_vendas.access')
        );
    }

    public function podeAprovarAparelhos(string $origem): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ($user->is_admin || $user->is_supervisor) {
            return true;
        }

        return $origem === 'vi'
            ? ErpAccess::can($user, 'vendas_internas.config')
            : ErpAccess::can($user, 'forca_vendas.config');
    }

    public function podeRejeitarAparelhos(string $origem): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ($user->is_admin || $user->is_supervisor) {
            return true;
        }

        return $origem === 'vi'
            ? ErpAccess::can($user, 'vendas_internas.delete')
            : ErpAccess::can($user, 'forca_vendas.delete');
    }

    private function nega(): void
    {
        Notification::make()
            ->title('Sem permissão')
            ->body('Seu usuário não pode executar esta ação.')
            ->danger()
            ->send();
    }
}
