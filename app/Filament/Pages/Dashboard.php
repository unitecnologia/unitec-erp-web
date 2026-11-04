<?php

namespace App\Filament\Pages;

use App\Support\Erp\Dashboard\ErpDashboardData;
use App\Support\Erp\Dashboard\ErpDashboardScope;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\License\LicencaPortalPagamentoService;
use App\Support\Erp\License\LicencaRemotaService;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Throwable;

class Dashboard extends BaseDashboard
{
    public string $dashboardVisao = ErpDashboardScope::VISAO_EMPRESA;

    /** True quando o bloco pesado foi carregado com sucesso (revelação única/coordenada). */
    public bool $dashboardHeavyReady = false;

    /** True quando a carga única do bloco pesado falhou (permite retry). */
    public bool $dashboardHeavyError = false;

    /**
     * Shell (KPIs + metadados) persistido entre requests Livewire — evita recalcular KPIs no heavy.
     *
     * @var array<string, mixed>|null
     */
    public ?array $cachedShell = null;

    /**
     * Payload único do bloco pesado (gauges, gráficos, listas, alertas).
     *
     * @var array<string, mixed>|null
     */
    public ?array $cachedHeavy = null;

    public bool $pixRenovacaoOpen = false;

    public bool $pixLoading = false;

    public string $pixQrDataUrl = '';

    public string $pixBrCode = '';

    public string $pixAmount = '';

    public string $pixDescription = '';

    public int $pixInvoiceId = 0;

    public string $pixMessage = '';

    public string $pixFeedback = '';

    public bool $licencaAvisoOpen = false;

    public string $licencaAvisoTitle = '';

    /** @var list<string> */
    public array $licencaAvisoLines = [];

    public function mount(): void
    {
        ErpScreen::set('Principal');
        $this->dashboardVisao = ErpDashboardScope::VISAO_EMPRESA;
        $this->resetHeavyState(preserveShell: false);
        $this->ensureCachedShell();
        $this->avisarLicencaNaAbertura();
    }

    /**
     * Uma vez por sessão: modal laranja central se a mensalidade vence em breve ou já venceu (carência).
     */
    private function avisarLicencaNaAbertura(): void
    {
        if (session('erp.licenca.aviso_startup_shown')) {
            return;
        }

        try {
            $aviso = app(LicencaRemotaService::class)->avisoStartup();
        } catch (Throwable) {
            return;
        }

        if ($aviso === null) {
            return;
        }

        session(['erp.licenca.aviso_startup_shown' => true]);

        $this->licencaAvisoTitle = (string) ($aviso['title'] ?? 'Aviso de licença');
        $this->licencaAvisoLines = filled($aviso['body'] ?? null)
            ? [(string) $aviso['body']]
            : ['Regularize o pagamento para evitar interrupção do sistema.'];
        $this->licencaAvisoOpen = true;
    }

    public function fecharLicencaAviso(): void
    {
        $this->licencaAvisoOpen = false;
    }

    public function renovarLicencaDoAviso(
        LicencaRemotaService $licencas,
        LicencaPortalPagamentoService $pagamentos,
    ): void {
        $this->licencaAvisoOpen = false;
        $this->abrirRenovacaoPix($licencas, $pagamentos);
    }

    #[Computed]
    public function accessibleEmpresaCount(): int
    {
        return count(ErpContext::accessibleEmpresaIds());
    }

    #[Computed]
    public function showDashboardVisaoToggle(): bool
    {
        return $this->accessibleEmpresaCount >= 2;
    }

    public function dashboardInicialAtivo(): bool
    {
        return ErpAccess::currentCan('dashboard.access')
            && EmpresaParametros::mostrarDashboardInicial();
    }

    /**
     * KPIs (shell) + bloco pesado quando pronto — sem recalcular KPIs no heavy.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function dashboardData(): array
    {
        if (! $this->dashboardInicialAtivo()) {
            return [];
        }

        $shell = $this->ensureCachedShell();
        unset($shell['_seed']);

        if (! $this->dashboardHeavyReady || ! is_array($this->cachedHeavy)) {
            return $shell;
        }

        return [
            ...$shell,
            ...$this->cachedHeavy,
        ];
    }

    /**
     * Carga única do bloco pesado (1 request Livewire). Revelação coordenada na UI.
     */
    public function loadDashboardHeavy(): void
    {
        if (! $this->dashboardInicialAtivo()) {
            return;
        }

        if ($this->dashboardHeavyReady && is_array($this->cachedHeavy)) {
            return;
        }

        $this->dashboardHeavyError = false;

        try {
            $shell = $this->ensureCachedShell();
            $seed = is_array($shell['_seed'] ?? null) ? $shell['_seed'] : null;

            $this->cachedHeavy = ErpDashboardData::heavy(
                empresaId: ErpContext::currentEmpresaId(),
                visao: $this->dashboardVisao,
                seed: $seed,
            );
            $this->dashboardHeavyReady = true;
            $this->dashboardHeavyError = false;
            unset($this->dashboardData);
            $this->dispatch('erp-dash-refresh');
            $this->js(
                'window.__erpDashHeavyDone=true;'
                .'window.dispatchEvent(new CustomEvent("erp-dash-heavy-done"));'
                .'window.dispatchEvent(new CustomEvent("erp-dash-waves-done"));'
            );
        } catch (Throwable $e) {
            Log::warning('Dashboard heavy falhou', ['message' => $e->getMessage()]);
            $this->cachedHeavy = null;
            $this->dashboardHeavyReady = false;
            $this->dashboardHeavyError = true;
            unset($this->dashboardData);
        }
    }

    public function setDashboardVisao(string $visao): void
    {
        if (! ErpAccess::currentCan('dashboard.access')) {
            return;
        }

        if (! in_array($visao, [ErpDashboardScope::VISAO_EMPRESA, ErpDashboardScope::VISAO_GRUPO], true)) {
            return;
        }

        if ($visao === ErpDashboardScope::VISAO_GRUPO && $this->accessibleEmpresaCount < 2) {
            return;
        }

        $this->dashboardVisao = $visao;
        $this->resetHeavyState(preserveShell: false);
        $this->ensureCachedShell();
        unset($this->dashboardData);
        $this->dispatch('erp-dash-refresh');
    }

    public function abrirRenovacaoPix(LicencaRemotaService $licencas, LicencaPortalPagamentoService $pagamentos): void
    {
        if (! ErpAccess::currentCan('dashboard.access')) {
            return;
        }

        $this->pixRenovacaoOpen = true;
        $this->pixFeedback = '';
        $this->pixLoading = true;
        $this->pixMessage = '';
        $this->pixQrDataUrl = '';
        $this->pixBrCode = '';
        $this->pixAmount = '';
        $this->pixDescription = '';
        $this->pixInvoiceId = 0;

        try {
            $cnpj = $licencas->currentCnpj() ?? '';

            if ($cnpj === '') {
                $this->pixMessage = 'CNPJ não disponível para gerar o Pix.';

                return;
            }

            $pix = $pagamentos->carregarPixPendente($cnpj);

            if (! ($pix['ok'] ?? false)) {
                $this->pixMessage = (string) ($pix['message'] ?? 'Não foi possível carregar o Pix agora.');

                return;
            }

            $this->pixInvoiceId = (int) ($pix['invoice_id'] ?? 0);
            $this->pixAmount = $this->formatPixAmount((string) ($pix['amount'] ?? ''));
            $this->pixDescription = (string) ($pix['description'] ?? '');
            $this->pixBrCode = (string) ($pix['br_code'] ?? '');
            $this->pixQrDataUrl = (string) ($pix['qr_code_data_url'] ?? '');

            if ($this->pixQrDataUrl === '' && $this->pixBrCode === '') {
                $this->pixMessage = 'Pix gerado sem QR. Tente atualizar.';
            }
        } finally {
            $this->pixLoading = false;
        }
    }

    public function fecharRenovacaoPix(): void
    {
        $this->pixRenovacaoOpen = false;
        $this->pixLoading = false;
        $this->pixQrDataUrl = '';
        $this->pixBrCode = '';
        $this->pixAmount = '';
        $this->pixDescription = '';
        $this->pixInvoiceId = 0;
        $this->pixMessage = '';
        $this->pixFeedback = '';
    }

    public function verificarPagamentoRenovacao(
        LicencaRemotaService $licencas,
        LicencaPortalPagamentoService $pagamentos,
    ): void {
        if (! ErpAccess::currentCan('dashboard.access')) {
            return;
        }

        $this->pixFeedback = '';
        $cnpj = $licencas->currentCnpj() ?? '';

        if ($this->pixInvoiceId > 0 && $cnpj !== '') {
            $pago = $pagamentos->verificarPagamentoFatura($cnpj, $this->pixInvoiceId);

            if (($pago['ok'] ?? false) && ($pago['paid'] ?? false)) {
                $this->pixFeedback = (string) ($pago['message'] ?? 'Pagamento confirmado.');

                $portal = $licencas->checkCurrentEmpresa(forceRefresh: true);
                $licencas->syncMensalidadeNoGate();
                $licencas->rememberLoginGate($portal);

                $this->cachedShell = null;
                $this->ensureCachedShell();
                unset($this->dashboardData);

                Notification::make()
                    ->title('Pagamento confirmado')
                    ->success()
                    ->send();

                return;
            }

            $this->pixFeedback = (string) ($pago['message'] ?? 'Pagamento ainda não confirmado.');
        } else {
            $this->pixFeedback = 'Não há fatura Pix para verificar.';
        }
    }

    private function formatPixAmount(string $amount): string
    {
        $amount = trim($amount);

        if ($amount === '') {
            return '';
        }

        if (preg_match('/^\d+([.,]\d{1,2})?$/', $amount) === 1) {
            $normalized = str_replace(',', '.', $amount);
            $value = (float) $normalized;

            return 'R$ '.number_format($value, 2, ',', '.');
        }

        return $amount;
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureCachedShell(): array
    {
        if (! $this->dashboardInicialAtivo()) {
            $this->cachedShell = [];

            return [];
        }

        if (is_array($this->cachedShell) && $this->cachedShell !== []) {
            return $this->cachedShell;
        }

        $this->cachedShell = ErpDashboardData::shell(
            empresaId: ErpContext::currentEmpresaId(),
            visao: $this->dashboardVisao,
        );

        return $this->cachedShell;
    }

    private function resetHeavyState(bool $preserveShell): void
    {
        if (! $preserveShell) {
            $this->cachedShell = null;
        }

        $this->dashboardHeavyReady = false;
        $this->dashboardHeavyError = false;
        $this->cachedHeavy = null;
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make(
                    $this->dashboardInicialAtivo()
                        ? 'filament.components.erp.home.screen'
                        : 'filament.components.erp.home.logo'
                ),
            ]);
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-home-page'];
    }
}
