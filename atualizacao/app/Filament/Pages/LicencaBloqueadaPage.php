<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpScreen;
use App\Support\Erp\License\LicencaPortalPagamentoService;
use App\Support\Erp\License\LicencaRemotaService;
use App\Support\Erp\License\LicencaSnapshot;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class LicencaBloqueadaPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $title = 'Licença bloqueada';

    protected static ?string $slug = 'licenca-bloqueada';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.licenca-bloqueada';

    public string $status = '';

    public string $nome = '';

    public string $validoAte = '';

    public string $mensagem = '';

    public string $pagamentoUrl = '';

    public string $cnpj = '';

    public string $feedback = '';

    public bool $mensalidadeVencida = false;

    public bool $pixLoading = false;

    public string $pixQrDataUrl = '';

    public string $pixBrCode = '';

    public string $pixAmount = '';

    public string $pixDescription = '';

    public int $pixInvoiceId = 0;

    public string $pixMessage = '';

    public function mount(LicencaRemotaService $licencas): void
    {
        ErpScreen::set('Licença');
        $this->pagamentoUrl = $licencas->pagamentoUrl();
        $this->cnpj = $licencas->currentCnpj() ?? '';

        $portal = $licencas->checkCurrentEmpresa();
        $snapshot = $licencas->applyMensalidadeExpiry($portal);
        $this->applySnapshot($snapshot, $licencas);

        // Libera a tela só se portal + mensalidade permitirem.
        if ($snapshot->isAllowed()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        if ($this->mensalidadeVencida) {
            $this->carregarPixMensalidade();
        }
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

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-licenca-bloqueada-page'];
    }

    /**
     * @return array<string, string>
     */
    public function getExtraBodyAttributes(): array
    {
        $attributes = parent::getExtraBodyAttributes();
        $attributes['class'] = trim(($attributes['class'] ?? '').' erp-licenca-bloqueada-body');

        return $attributes;
    }

    public function verificarNovamente(
        LicencaRemotaService $licencas,
        LicencaPortalPagamentoService $pagamentos,
    ): void {
        $this->feedback = '';

        if ($this->pixInvoiceId > 0 && filled($this->cnpj)) {
            $pago = $pagamentos->verificarPagamentoFatura($this->cnpj, $this->pixInvoiceId);
            if (($pago['ok'] ?? false) && ($pago['paid'] ?? false)) {
                $this->feedback = (string) ($pago['message'] ?? 'Pagamento confirmado.');
            }
        }

        $portal = $licencas->checkCurrentEmpresa(forceRefresh: true);
        $licencas->syncMensalidadeNoGate();
        $licencas->rememberLoginGate($portal);

        $snapshot = $licencas->applyMensalidadeExpiry($portal);
        $this->applySnapshot($snapshot, $licencas);

        if ($snapshot->isAllowed()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        if ($this->mensalidadeVencida) {
            $this->carregarPixMensalidade();
            $this->feedback = filled($this->feedback)
                ? $this->feedback
                : 'Mensalidade ainda vencida. Pague o Pix e verifique novamente.';
        } else {
            $this->limparPix();
            $this->feedback = 'Ainda bloqueado. Aguarde a liberação no gerenciador de licenças.';
        }
    }

    public function sair(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }

    private function applySnapshot(LicencaSnapshot $snapshot, LicencaRemotaService $licencas): void
    {
        $this->status = $snapshot->status;
        $this->nome = (string) ($snapshot->nome ?? '');
        $this->validoAte = (string) ($snapshot->validoAte ?? '');
        $this->mensagem = (string) ($snapshot->mensagem ?? '');
        $this->mensalidadeVencida = $licencas->mensalidadeVencida();
    }

    private function carregarPixMensalidade(): void
    {
        $this->pixLoading = true;
        $this->pixMessage = '';

        try {
            if ($this->cnpj === '') {
                $this->limparPix();
                $this->pixMessage = 'CNPJ não disponível para gerar o Pix.';

                return;
            }

            $pix = app(LicencaPortalPagamentoService::class)->carregarPixPendente($this->cnpj);

            if (! ($pix['ok'] ?? false)) {
                $this->limparPix();
                $this->pixMessage = (string) ($pix['message'] ?? 'Não foi possível carregar o Pix agora.');

                return;
            }

            $this->pixInvoiceId = (int) ($pix['invoice_id'] ?? 0);
            $this->pixAmount = $this->formatAmount((string) ($pix['amount'] ?? ''));
            $this->pixDescription = (string) ($pix['description'] ?? '');
            $this->pixBrCode = (string) ($pix['br_code'] ?? '');
            $this->pixQrDataUrl = (string) ($pix['qr_code_data_url'] ?? '');
            $this->pixMessage = '';

            if ($this->pixQrDataUrl === '' && $this->pixBrCode === '') {
                $this->pixMessage = 'Pix gerado sem QR. Use Abrir portal ou tente novamente.';
            }
        } finally {
            $this->pixLoading = false;
        }
    }

    private function limparPix(): void
    {
        $this->pixInvoiceId = 0;
        $this->pixAmount = '';
        $this->pixDescription = '';
        $this->pixBrCode = '';
        $this->pixQrDataUrl = '';
        $this->pixMessage = '';
        $this->pixLoading = false;
    }

    private function formatAmount(string $amount): string
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
}
