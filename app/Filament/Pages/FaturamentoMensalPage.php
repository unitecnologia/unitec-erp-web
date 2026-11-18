<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithErpSpaRedirect;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\FaturamentoMensalService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class FaturamentoMensalPage extends Page
{
    use InteractsWithErpSpaRedirect;

    protected static ?string $slug = 'faturamento-mensal';

    protected static ?string $routePath = 'faturamento-mensal';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.faturamento-mensal';

    public int $ano = 0;

    /** '' = todos os meses; '1'..'12' = mês específico */
    public string $mes = '';

    public bool $gerado = false;

    /** @var array<string, mixed> */
    public array $resultado = [];

    public string $erro = '';

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('vendas.print');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        ErpScreen::set('Faturamento Mensal');

        if ($this->ano <= 0) {
            $this->ano = (int) ErpTimezone::toLocal()->year;
        }

        $this->gerado = false;
        $this->resultado = [];
        $this->erro = '';

        // Uma consulta agrupada é barata — gera o ano corrente ao abrir.
        $this->gerar();
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-list-page',
            'erp-rh-treinamentos-page',
            'erp-faturamento-mensal-page',
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function empresaLabel(): string
    {
        $empresa = ErpContext::currentEmpresa();

        if (! $empresa) {
            return '—';
        }

        return trim((string) ($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social ?: ('#'.$empresa->id)));
    }

    /**
     * @return list<int>
     */
    public function anosOptions(): array
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        return app(FaturamentoMensalService::class)->anosDisponiveis($empresaId);
    }

    /**
     * @return array<string, string>
     */
    public function mesesOptions(): array
    {
        return [
            '' => 'Todos os meses',
            '1' => 'Janeiro',
            '2' => 'Fevereiro',
            '3' => 'Março',
            '4' => 'Abril',
            '5' => 'Maio',
            '6' => 'Junho',
            '7' => 'Julho',
            '8' => 'Agosto',
            '9' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro',
        ];
    }

    public function gerar(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->erro = '';
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($empresaId <= 0) {
            $this->gerado = false;
            $this->resultado = [];
            $this->erro = 'Selecione a empresa logada para gerar o faturamento.';

            return;
        }

        $ano = (int) $this->ano;
        if ($ano < 2000 || $ano > 2100) {
            $this->erro = 'Ano inválido.';
            $this->gerado = false;
            $this->resultado = [];

            return;
        }

        $mesRaw = trim((string) $this->mes);
        $mes = $mesRaw === '' ? null : (int) $mesRaw;
        if ($mes !== null && ($mes < 1 || $mes > 12)) {
            $this->erro = 'Mês inválido.';
            $this->gerado = false;
            $this->resultado = [];

            return;
        }

        $this->resultado = app(FaturamentoMensalService::class)->calcular($empresaId, $ano, $mes);
        $this->gerado = true;
    }

    public function updatedAno(): void
    {
        $this->gerar();
    }

    public function updatedMes(): void
    {
        $this->gerar();
    }

    public function onEscape(): void
    {
        $this->sair();
    }

    public function sair(): void
    {
        ErpScreen::set('Principal');
        $this->redirectErpAdmin(filament()->getUrl());
    }
}
