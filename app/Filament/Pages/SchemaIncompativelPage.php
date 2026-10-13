<?php

namespace App\Filament\Pages;

use App\Support\Erp\Atualizacao\AtualizacaoPasta;
use App\Support\Erp\Database\PendingMigrationsGuard;
use App\Support\Erp\ErpScreen;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class SchemaIncompativelPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $title = 'Banco incompatível';

    protected static ?string $slug = 'schema-incompativel';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.schema-incompativel';

    public string $mensagem = '';

    public int $pending = 0;

    public bool $atualizacaoPendente = false;

    public string $versaoPendente = '';

    public function mount(PendingMigrationsGuard $guard): void
    {
        ErpScreen::set('Schema');

        $result = $this->resolveSchemaCheck($guard);

        if ($result['ok']) {
            $this->redirect(url('/admin'));

            return;
        }

        // Lock transitório: não afirmar pendências nem empurrar para update/reparo falso.
        if (($result['transient'] ?? false) === true) {
            $this->pending = 0;
            $this->mensagem = (string) ($result['message'] ?? 'Verificando compatibilidade do banco…');
            $this->atualizacaoPendente = false;
            $this->versaoPendente = '';

            return;
        }

        $this->pending = max(0, (int) $result['pending']);
        $this->mensagem = (string) ($result['message'] ?? $guard->repairGuidanceMessage($this->pending));
        $this->atualizacaoPendente = AtualizacaoPasta::ensurePendingReady();
        $this->versaoPendente = (string) (AtualizacaoPasta::pendingVersion() ?? '');
    }

    /**
     * @return array{ok: bool, pending: int, message: ?string, from_cache: bool, transient?: bool}
     */
    private function resolveSchemaCheck(PendingMigrationsGuard $guard): array
    {
        $result = $guard->check();

        for ($i = 0; $i < 6 && (($result['transient'] ?? false) === true); $i++) {
            usleep(200_000);
            $result = $guard->check(forceRefresh: true);
        }

        return $result;
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function irParaLogin(): void
    {
        // Com pacote e sem skip de licença: manter sessão para o prompt Sim/Não.
        // Caso contrário, sair evita loop login ↔ schema.
        $updateSkipped = (bool) session('erp_update_choice_skipped');
        if ($this->atualizacaoPendente && ! $updateSkipped) {
            session([
                'erp_awaiting_update_choice' => true,
                'erp_pending_update_version' => $this->versaoPendente,
            ]);
            $this->redirect(url('/admin/login'));

            return;
        }

        $this->sair();
    }

    public function sair(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect(url('/admin/login'));
    }
}
