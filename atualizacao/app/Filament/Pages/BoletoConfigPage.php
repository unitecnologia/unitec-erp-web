<?php

namespace App\Filament\Pages;

use App\Models\BoletoParametro;
use App\Models\Empresa;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class BoletoConfigPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $title = '';

    protected static ?string $slug = 'boleto-configuracao';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('boletos.access');
    }

    public function mount(): void
    {
        ErpScreen::set('Boleto — Configuração');

        $empresaId = (int) (Auth::user()?->empresa_id ?? Empresa::query()->value('id') ?? 0);

        if ($empresaId < 1) {
            return;
        }

        $params = BoletoParametro::forEmpresa($empresaId);
        $this->form = $params->only([
            'banco', 'layout', 'carteira', 'tipo_carteira', 'especie_docto', 'especie_moeda',
            'aceite', 'tipo_documento', 'cnab_versao', 'local_pagamento',
            'ben_agencia', 'ben_agencia_dv', 'ben_conta', 'ben_conta_dv', 'ben_convenio',
            'ben_modalidade', 'ben_cod_cedente', 'nosso_numero',
            'path_remessa', 'path_retorno',
            'homologacao', 'remover_acentuacao_remessa', 'webservice_indicador_pix',
            'webservice_client_id', 'webservice_client_secret', 'webservice_key_user',
        ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.boleto.config-screen'),
            ]);
    }

    public function save(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'boletos.update')) {
            return;
        }

        $empresaId = (int) (Auth::user()?->empresa_id ?? Empresa::query()->value('id') ?? 0);

        if ($empresaId < 1) {
            Notification::make()->title('Empresa não encontrada.')->danger()->send();

            return;
        }

        $params = BoletoParametro::forEmpresa($empresaId);
        $params->fill([
            'banco' => trim((string) ($this->form['banco'] ?? '')) ?: null,
            'layout' => trim((string) ($this->form['layout'] ?? '')) ?: null,
            'carteira' => trim((string) ($this->form['carteira'] ?? '')) ?: null,
            'tipo_carteira' => trim((string) ($this->form['tipo_carteira'] ?? '')) ?: null,
            'especie_docto' => trim((string) ($this->form['especie_docto'] ?? '')) ?: null,
            'especie_moeda' => trim((string) ($this->form['especie_moeda'] ?? '')) ?: null,
            'aceite' => trim((string) ($this->form['aceite'] ?? '')) ?: null,
            'tipo_documento' => trim((string) ($this->form['tipo_documento'] ?? '')) ?: null,
            'cnab_versao' => trim((string) ($this->form['cnab_versao'] ?? '')) ?: null,
            'local_pagamento' => trim((string) ($this->form['local_pagamento'] ?? '')) ?: null,
            'ben_agencia' => filled($this->form['ben_agencia'] ?? null) ? (int) $this->form['ben_agencia'] : null,
            'ben_agencia_dv' => filled($this->form['ben_agencia_dv'] ?? null) ? (int) $this->form['ben_agencia_dv'] : null,
            'ben_conta' => filled($this->form['ben_conta'] ?? null) ? (int) $this->form['ben_conta'] : null,
            'ben_conta_dv' => filled($this->form['ben_conta_dv'] ?? null) ? (int) $this->form['ben_conta_dv'] : null,
            'ben_convenio' => trim((string) ($this->form['ben_convenio'] ?? '')) ?: null,
            'ben_modalidade' => trim((string) ($this->form['ben_modalidade'] ?? '')) ?: null,
            'ben_cod_cedente' => filled($this->form['ben_cod_cedente'] ?? null) ? (int) $this->form['ben_cod_cedente'] : null,
            'nosso_numero' => trim((string) ($this->form['nosso_numero'] ?? '')) ?: null,
            'path_remessa' => trim((string) ($this->form['path_remessa'] ?? '')) ?: null,
            'path_retorno' => trim((string) ($this->form['path_retorno'] ?? '')) ?: null,
            'homologacao' => (bool) ($this->form['homologacao'] ?? false),
            'remover_acentuacao_remessa' => (bool) ($this->form['remover_acentuacao_remessa'] ?? false),
            'webservice_indicador_pix' => (bool) ($this->form['webservice_indicador_pix'] ?? false),
            'webservice_client_id' => trim((string) ($this->form['webservice_client_id'] ?? '')) ?: null,
            'webservice_client_secret' => trim((string) ($this->form['webservice_client_secret'] ?? '')) ?: null,
            'webservice_key_user' => trim((string) ($this->form['webservice_key_user'] ?? '')) ?: null,
        ]);
        $params->save();

        Notification::make()->title('Configuração de boleto salva.')->success()->send();
    }

    public function closeScreen(): void
    {
        $this->redirect(filament()->getHomeUrl());
    }
}
