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

    public string $cnpj = '';

    public string $feedback = '';

    public string $diagnostico = '';

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
        $this->cnpj = $licencas->currentCnpj() ?? '';

        $persisted = $licencas->pullPersistedDiagnostico();
        if (is_array($persisted)) {
            $this->diagnostico = $this->formatDiagnosticoArray($persisted);
        }

        $portal = $licencas->checkCurrentEmpresa();
        $decision = $licencas->resolveAccess($portal);
        $snapshot = $licencas->applyMensalidadeExpiry($portal);
        $this->applySnapshot($snapshot, $licencas);

        // Libera a tela só se portal + mensalidade permitirem.
        if ($decision['allowed'] || $snapshot->isAllowed()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        if ($this->diagnostico === '') {
            $fetch = $licencas->lastApiFetch();
            if (is_array($fetch)) {
                $this->diagnostico = $this->formatDiagnosticoArray([
                    'at' => (string) ($fetch['at'] ?? now()->toDateTimeString()),
                    'http_status' => $fetch['http_status'] ?? null,
                    'status' => is_array($fetch['body'] ?? null) ? ($fetch['body']['status'] ?? null) : null,
                    'valido_ate' => is_array($fetch['body'] ?? null) ? ($fetch['body']['valido_ate'] ?? null) : null,
                    'vencimento' => is_array($fetch['body'] ?? null) ? ($fetch['body']['vencimento'] ?? null) : null,
                    'decision_field' => $decision['field'],
                    'decision_detail' => $decision['detail'],
                    'body' => $fetch['body'] ?? null,
                ]);
            }
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
        $this->diagnostico = '';

        if ($this->pixInvoiceId > 0 && filled($this->cnpj)) {
            $pago = $pagamentos->verificarPagamentoFatura($this->cnpj, $this->pixInvoiceId);
            if (($pago['ok'] ?? false) && ($pago['paid'] ?? false)) {
                $this->feedback = (string) ($pago['message'] ?? 'Pagamento confirmado.');
            }
        }

        // 1) Consulta portal  2) grava gate com status fresco  3) só então sincroniza mensalidade
        $portal = $licencas->checkCurrentEmpresa(forceRefresh: true);
        $licencas->rememberLoginGate($portal);
        $licencas->syncMensalidadeNoGate();
        $licencas->rememberLoginGate($portal);

        $decision = $licencas->resolveAccess($portal);
        $licencas->persistDiagnostico($decision);
        $this->diagnostico = $this->formatDiagnosticoArray([
            'at' => now()->toDateTimeString(),
            'http_status' => $licencas->lastApiFetch()['http_status'] ?? null,
            'status' => $licencas->lastApiPayload()['status'] ?? null,
            'valido_ate' => $licencas->lastApiPayload()['valido_ate'] ?? null,
            'vencimento' => $licencas->lastApiPayload()['vencimento'] ?? null,
            'decision_field' => $decision['field'],
            'decision_detail' => $decision['detail'],
            'body' => $licencas->lastApiPayload(),
        ]);

        $snapshot = $licencas->applyMensalidadeExpiry($portal);
        $this->applySnapshot($snapshot, $licencas);

        // status=ativo do portal libera mesmo com vencimento no passado (tolerância).
        if ($decision['allowed'] || $snapshot->isAllowed()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        if ($this->mensalidadeVencida) {
            $this->feedback = filled($this->feedback)
                ? $this->feedback
                : 'Mensalidade ainda vencida. Pague o Pix e verifique novamente.';
            $this->carregarPixMensalidade();
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

    /**
     * @param  array{at?: string, http_status?: int|null, status?: mixed, valido_ate?: mixed, vencimento?: mixed, decision_field?: string, decision_detail?: string, body?: array<string, mixed>|null}  $diag
     */
    private function formatDiagnosticoArray(array $diag): string
    {
        $corpo = array_key_exists('body', $diag)
            ? (json_encode($diag['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '(JSON inválido)')
            : '(sem corpo)';

        return implode("\n", [
            'Horário: '.((string) ($diag['at'] ?? now()->toDateTimeString())),
            'HTTP: '.((string) ($diag['http_status'] ?? '—')),
            'status: '.json_encode($diag['status'] ?? null, JSON_UNESCAPED_UNICODE),
            'valido_ate: '.json_encode($diag['valido_ate'] ?? null, JSON_UNESCAPED_UNICODE),
            'vencimento: '.json_encode($diag['vencimento'] ?? null, JSON_UNESCAPED_UNICODE),
            'Campo usado para bloquear: '.((string) ($diag['decision_field'] ?? '—'))
                .' ('.((string) ($diag['decision_detail'] ?? '')).')',
            'Corpo completo:',
            $corpo,
        ]);
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
                $this->pixMessage = 'Pix gerado sem QR. Tente verificar novamente.';
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
