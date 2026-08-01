<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpScreen;
use App\Support\Erp\License\LicencaMachineInfo;
use App\Support\Erp\License\LicencaRemotaService;
use App\Support\Erp\License\LicencaSnapshot;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class LicencaSistemaPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $title = '';

    protected static ?string $slug = 'licenca-sistema';

    protected static bool $shouldRegisterNavigation = false;

    public string $aba = 'online';

    public string $status = '';

    public string $statusLabel = '';

    public string $nome = '';

    public string $cnpj = '';

    public string $cnpjMascarado = '';

    public string $validoAte = '';

    public string $mensagem = '';

    public string $pagamentoUrl = '';

    public string $portalUrl = '';

    public string $computador = '';

    public string $mac = '';

    public string $suporteEmail = '';

    public string $suporteWhatsapp = '';

    public string $suporteSite = '';

    public bool $verificando = false;

    public bool $jaConsultado = false;

    public function mount(LicencaRemotaService $licencas): void
    {
        ErpScreen::set('Licença do Sistema');

        $this->pagamentoUrl = $licencas->pagamentoUrl();
        $this->portalUrl = rtrim((string) config('unitec.licenca_api.base_url', ''), '/')
            ?: $this->pagamentoUrl;
        $this->suporteEmail = (string) config('unitec.licenca_suporte.email', 'sac@unitecnologiasc.com.br');
        $this->suporteWhatsapp = (string) config('unitec.licenca_suporte.whatsapp', '47996446859');
        $this->suporteSite = (string) config('unitec.licenca_suporte.site', '');
        $this->computador = LicencaMachineInfo::computador();
        $this->mac = LicencaMachineInfo::macAddress();

        $cnpj = $licencas->currentCnpj();
        $this->cnpj = $cnpj ?? '';
        $this->cnpjMascarado = $cnpj ? $this->maskCnpj($cnpj) : '—';

        // Carrega cache/sessão sem forçar HTTP; o usuário confirma com "Ativar Online".
        $this->applySnapshot($licencas->checkCurrentEmpresa(forceRefresh: false));
        $this->jaConsultado = true;
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
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-licenca-sistema-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.licenca.sistema-modal'),
            ]);
    }

    public function setAba(string $aba): void
    {
        if (! in_array($aba, ['online', 'offline'], true)) {
            return;
        }

        $this->aba = $aba;
    }

    public function ativarOnline(LicencaRemotaService $licencas): void
    {
        $this->verificando = true;

        try {
            $snapshot = $licencas->checkCurrentEmpresa(forceRefresh: true);
            $this->applySnapshot($snapshot);
            $this->jaConsultado = true;

            $title = match ($snapshot->status) {
                LicencaSnapshot::STATUS_ATIVO => 'Licença ativa no portal.',
                LicencaSnapshot::STATUS_BLOQUEADO => 'CNPJ cadastrado, porém bloqueado.',
                LicencaSnapshot::STATUS_NAO_ENCONTRADO => 'CNPJ não cadastrado no portal.',
                LicencaSnapshot::STATUS_SEM_CNPJ => 'Cadastre o CNPJ da empresa.',
                LicencaSnapshot::STATUS_DESABILITADO => 'Validação remota desabilitada.',
                default => 'Não foi possível consultar o portal.',
            };

            $notification = Notification::make()->title($title);

            if (in_array($snapshot->status, [
                LicencaSnapshot::STATUS_ATIVO,
                LicencaSnapshot::STATUS_DESABILITADO,
            ], true)) {
                $notification->success();
            } elseif (in_array($snapshot->status, [
                LicencaSnapshot::STATUS_BLOQUEADO,
                LicencaSnapshot::STATUS_NAO_ENCONTRADO,
                LicencaSnapshot::STATUS_SEM_CNPJ,
            ], true)) {
                $notification->warning();
            } else {
                $notification->danger();
            }

            $notification->send();
        } finally {
            $this->verificando = false;
        }
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }

    private function applySnapshot(LicencaSnapshot $snapshot): void
    {
        $this->status = $snapshot->status;
        $this->statusLabel = $this->labelForStatus($snapshot->status);
        $this->nome = (string) ($snapshot->nome ?? '');
        $this->mensagem = (string) ($snapshot->mensagem ?? '');

        $expires = $snapshot->expiresAt();
        $this->validoAte = $expires ? $expires->format('d/m/Y') : '';

        if ($this->validoAte === '' && filled($snapshot->validoAte)) {
            try {
                $this->validoAte = Carbon::parse($snapshot->validoAte)->format('d/m/Y');
            } catch (\Throwable) {
                $this->validoAte = (string) $snapshot->validoAte;
            }
        }
    }

    private function labelForStatus(string $status): string
    {
        return match ($status) {
            LicencaSnapshot::STATUS_ATIVO => 'Cadastrado e ativo',
            LicencaSnapshot::STATUS_BLOQUEADO => 'Cadastrado (bloqueado)',
            LicencaSnapshot::STATUS_NAO_ENCONTRADO => 'Não cadastrado no portal',
            LicencaSnapshot::STATUS_SEM_CNPJ => 'Sem CNPJ na empresa',
            LicencaSnapshot::STATUS_INDISPONIVEL => 'Portal indisponível',
            LicencaSnapshot::STATUS_DESABILITADO => 'Validação remota desligada',
            default => 'Desconhecido',
        };
    }

    private function maskCnpj(string $digits): string
    {
        $digits = preg_replace('/\D/', '', $digits) ?? '';

        if (strlen($digits) !== 14) {
            return $digits;
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12, 2);
    }
}
