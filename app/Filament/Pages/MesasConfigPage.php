<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Pdv\PdvMesaService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

class MesasConfigPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $title = '';

    protected static ?string $slug = 'mesas';

    protected static bool $shouldRegisterNavigation = false;

    public int $quantidade = PdvMesaService::QTD_PADRAO;

    /** @var array<string, string> numero (string) => nome exibido no formulário */
    public array $nomes = [];

    /** @var array<string, string> numero => livre|ocupada|atendimento */
    public array $status = [];

    public string $feedbackMsg = '';

    public string $feedbackTipo = 'ok';

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('empresa.access') && self::restauranteAtivo();
    }

    public function mount(): void
    {
        abort_unless(self::restauranteAtivo(), 403);

        ErpScreen::set('Mesas');
        $this->carregar();
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
            'erp-list-page',
            'erp-mesas-config-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.mesas.screen'),
            ]);
    }

    public function atualizarQuantidade(): void
    {
        $qtd = PdvMesaService::normalizarQuantidade($this->quantidade);
        $this->quantidade = $qtd;
        $this->reconstruirNomesAte($qtd);
        $this->refreshStatus();
        $this->feedbackMsg = '';
    }

    public function salvar(): void
    {
        $empresa = ErpContext::currentEmpresa();
        if ($empresa === null) {
            $this->feedback('Selecione uma empresa.', 'erro');

            return;
        }

        $this->quantidade = PdvMesaService::normalizarQuantidade($this->quantidade);
        $this->reconstruirNomesAte($this->quantidade);

        $resultado = PdvMesaService::make()->salvarConfiguracao(
            $empresa,
            $this->quantidade,
            $this->nomes
        );

        if (! ($resultado['ok'] ?? false)) {
            $this->feedback((string) ($resultado['erro'] ?? 'Não foi possível salvar.'), 'erro');
            $this->refreshStatus();

            return;
        }

        $empresa->refresh();
        $this->carregar();
        $this->feedback('Configuração de mesas gravada.', 'ok');

        Notification::make()
            ->title('Mesas atualizadas.')
            ->body($this->quantidade.' mesa(s) · nomes personalizados preservados.')
            ->success()
            ->send();
    }

    #[On('erp-escape')]
    public function handleEscape(): void
    {
        $this->redirect(filament()->getUrl());
    }

    public function dismissFeedback(): void
    {
        $this->feedbackMsg = '';
    }

    private function carregar(): void
    {
        $empresa = ErpContext::currentEmpresa();
        $qtd = PdvMesaService::quantidade($empresa);
        $this->quantidade = $qtd;

        if ($empresa !== null) {
            PdvMesaService::make()->garantirAte((int) $empresa->id, $qtd);
        }

        $mapa = $empresa !== null
            ? PdvMesaService::mapaRotulos((int) $empresa->id, $qtd)
            : [];

        $this->nomes = [];
        for ($n = 1; $n <= $qtd; $n++) {
            $this->nomes[(string) $n] = $mapa[$n] ?? PdvMesaService::rotuloPadrao($n);
        }

        $this->refreshStatus();
        $this->feedbackMsg = '';
    }

    private function reconstruirNomesAte(int $qtd): void
    {
        $atuais = $this->nomes;
        $empresa = ErpContext::currentEmpresa();
        $doBanco = $empresa !== null
            ? PdvMesaService::mapaRotulos((int) $empresa->id, $qtd)
            : [];
        $novos = [];

        for ($n = 1; $n <= $qtd; $n++) {
            $key = (string) $n;
            // Mantém o que já está no formulário; ao reexibir mesa (aumentar qtd), volta o nome do banco.
            if (array_key_exists($key, $atuais)) {
                $existente = trim((string) $atuais[$key]);
                $novos[$key] = $existente !== '' ? $existente : PdvMesaService::rotuloPadrao($n);
            } else {
                $novos[$key] = $doBanco[$n] ?? PdvMesaService::rotuloPadrao($n);
            }
        }

        $this->nomes = $novos;
    }

    private function refreshStatus(): void
    {
        $empresa = ErpContext::currentEmpresa();
        $this->status = [];

        if ($empresa === null) {
            return;
        }

        $empresaId = (int) $empresa->id;
        $agora = now()->format('Y-m-d H:i:s');
        $linhas = \App\Models\PdvMesa::query()
            ->toBase()
            ->where('empresa_id', $empresaId)
            ->where('numero', '<=', max(1, $this->quantidade))
            ->get(['numero', 'qtd_itens', 'reservado_ate']);

        foreach ($linhas as $row) {
            $n = (string) (int) $row->numero;
            $ocupada = (int) $row->qtd_itens > 0;
            $atendimento = $row->reservado_ate !== null && (string) $row->reservado_ate > $agora;
            $this->status[$n] = match (true) {
                $ocupada => 'ocupada',
                $atendimento => 'atendimento',
                default => 'livre',
            };
        }
    }

    private function feedback(string $msg, string $tipo): void
    {
        $this->feedbackMsg = $msg;
        $this->feedbackTipo = $tipo;
    }

    private static function restauranteAtivo(): bool
    {
        return ErpContext::currentEmpresa()?->tipo_atividade === 'restaurante_lanchonete';
    }
}
