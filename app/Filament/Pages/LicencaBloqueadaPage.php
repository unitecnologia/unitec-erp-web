<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpScreen;
use App\Support\Erp\License\LicencaPortalPagamentoService;
use App\Support\Erp\License\LicencaRemotaService;
use App\Support\Erp\License\LicencaSnapshot;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class LicencaBloqueadaPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $title = 'Licença bloqueada';

    protected static ?string $slug = 'licenca-bloqueada';

    protected static bool $shouldRegisterNavigation = false;

    public string $status = '';

    public string $nome = '';

    public string $validoAte = '';

    public string $mensagem = '';

    public string $pagamentoUrl = '';

    public string $cnpj = '';

    public string $feedback = '';

    public bool $pixLoading = false;

    public bool $pixReady = false;

    public ?int $pixInvoiceId = null;

    public string $pixAmount = '';

    public string $pixDescription = '';

    public string $pixDueDate = '';

    public string $pixBrCode = '';

    public string $pixQrCodeDataUrl = '';

    public string $pixCopied = '';

    public int $pollTick = 0;

    public function mount(
        LicencaRemotaService $licencas,
        LicencaPortalPagamentoService $pagamentos,
    ): void {
        ErpScreen::set('Licença');
        $this->pagamentoUrl = $licencas->pagamentoUrl();
        $this->cnpj = $licencas->currentCnpj() ?? '';
        $this->applySnapshot($licencas->checkCurrentEmpresa());

        if ($this->status === LicencaSnapshot::STATUS_ATIVO
            || $this->status === LicencaSnapshot::STATUS_DESABILITADO
            || $this->status === LicencaSnapshot::STATUS_INDISPONIVEL) {
            $this->redirect(Filament::getUrl());

            return;
        }

        if ($this->status === LicencaSnapshot::STATUS_BLOQUEADO && filled($this->cnpj)) {
            $this->carregarPix($pagamentos);
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.licenca.bloqueada'),
            ]);
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-licenca-bloqueada-page'];
    }

    public function carregarPix(LicencaPortalPagamentoService $pagamentos): void
    {
        if (! filled($this->cnpj)) {
            return;
        }

        $this->pixLoading = true;
        $this->pixReady = false;
        $this->feedback = '';

        $result = $pagamentos->carregarPixPendente($this->cnpj);

        $this->pixLoading = false;

        if (! ($result['ok'] ?? false)) {
            $this->feedback = (string) ($result['message'] ?? 'Não foi possível carregar o Pix.');

            return;
        }

        $this->pixReady = true;
        $this->pixInvoiceId = isset($result['invoice_id']) ? (int) $result['invoice_id'] : null;
        $this->pixAmount = (string) ($result['amount'] ?? '');
        $this->pixDescription = (string) ($result['description'] ?? '');
        $this->pixDueDate = (string) ($result['due_date'] ?? '');
        $this->pixBrCode = (string) ($result['br_code'] ?? '');
        $this->pixQrCodeDataUrl = (string) ($result['qr_code_data_url'] ?? '');
    }

    public function pollPagamento(
        LicencaRemotaService $licencas,
        LicencaPortalPagamentoService $pagamentos,
    ): void {
        $this->pollTick++;

        // A cada ~30s confere Pix no portal (mais pesado: login + check).
        if ($this->pixInvoiceId && filled($this->cnpj) && ($this->pollTick % 2) === 0) {
            $check = $pagamentos->verificarPagamentoFatura($this->cnpj, $this->pixInvoiceId);

            if ($check['paid'] ?? false) {
                $this->feedback = 'Pagamento encontrado. Liberando sistema…';
            }
        }

        // GET leve no status da licença (sem cache) para liberar assim que o portal ativar.
        $snapshot = $licencas->checkCurrentEmpresa(forceRefresh: true);
        $this->applySnapshot($snapshot);

        if ($snapshot->isAllowed()) {
            $this->redirect(Filament::getUrl());
        }
    }

    public function verificarNovamente(
        LicencaRemotaService $licencas,
        LicencaPortalPagamentoService $pagamentos,
    ): void {
        $this->feedback = '';

        if ($this->pixInvoiceId && filled($this->cnpj)) {
            $check = $pagamentos->verificarPagamentoFatura($this->cnpj, $this->pixInvoiceId);

            if ($check['paid'] ?? false) {
                $this->feedback = 'Pagamento confirmado no portal. Validando licença…';
            }
        }

        $snapshot = $licencas->checkCurrentEmpresa(forceRefresh: true);
        $this->applySnapshot($snapshot);

        if ($snapshot->isAllowed()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        if ($this->feedback === '') {
            $this->feedback = 'Ainda bloqueado. Pague o Pix abaixo ou aguarde a confirmação automática.';
        }

        if (! $this->pixReady && filled($this->cnpj)) {
            $this->carregarPix($pagamentos);
        }
    }

    public function copiarPix(): void
    {
        $this->pixCopied = 'Código Pix copiado.';
    }

    public function sair(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }

    private function applySnapshot(LicencaSnapshot $snapshot): void
    {
        $this->status = $snapshot->status;
        $this->nome = (string) ($snapshot->nome ?? '');
        $this->validoAte = (string) ($snapshot->validoAte ?? '');
        $this->mensagem = (string) ($snapshot->mensagem ?? '');
    }
}
