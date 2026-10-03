<?php

namespace App\Filament\Pages;

use App\Support\Erp\Dashboard\ErpDashboardData;
use App\Support\Erp\Dashboard\ErpDashboardScope;
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
use Livewire\Attributes\Computed;

class Dashboard extends BaseDashboard
{
    public string $dashboardVisao = ErpDashboardScope::VISAO_EMPRESA;

    public bool $dashboardHeavyReady = false;

    public bool $pixRenovacaoOpen = false;

    public bool $pixLoading = false;

    public string $pixQrDataUrl = '';

    public string $pixBrCode = '';

    public string $pixAmount = '';

    public string $pixDescription = '';

    public int $pixInvoiceId = 0;

    public string $pixMessage = '';

    public string $pixFeedback = '';

    public function mount(): void
    {
        ErpScreen::set('Principal');
        $this->dashboardVisao = ErpDashboardScope::VISAO_EMPRESA;
        $this->dashboardHeavyReady = false;
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

    /**
     * First paint: KPIs + metadados de visão.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function dashboardShell(): array
    {
        return ErpDashboardData::shell(
            empresaId: ErpContext::currentEmpresaId(),
            visao: $this->dashboardVisao,
        );
    }

    /**
     * Blocos pesados (carregados após wire:init).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function dashboardHeavy(): array
    {
        return ErpDashboardData::heavy(
            empresaId: ErpContext::currentEmpresaId(),
            visao: $this->dashboardVisao,
        );
    }

    /**
     * Compat: shell + heavy quando pronto; só shell no first paint.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function dashboardData(): array
    {
        $shell = $this->dashboardShell;

        if (! $this->dashboardHeavyReady) {
            return $shell;
        }

        return [
            ...$shell,
            ...$this->dashboardHeavy,
        ];
    }

    public function loadDashboardHeavy(): void
    {
        if (! ErpAccess::currentCan('dashboard.access')) {
            return;
        }

        $this->dashboardHeavyReady = true;
        unset($this->dashboardHeavy, $this->dashboardData);
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
        $this->dashboardHeavyReady = false;
        unset($this->dashboardShell, $this->dashboardHeavy, $this->dashboardData);
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

                unset($this->dashboardShell, $this->dashboardData);

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
                    ErpAccess::currentCan('dashboard.access')
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
