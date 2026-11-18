<?php

namespace App\Filament\Pages;

use App\Models\PlanoConta;
use App\Support\Erp\Dre\DreDemonstrativoGerencialService;
use App\Support\Erp\Dre\DreDespesasGerenciaisService;
use App\Support\Erp\Dre\DreEmpresaParametrosService;
use App\Support\Erp\Dre\DrePlanoClassificacaoCatalog;
use App\Support\Erp\Dre\DrePlanoClassificacaoPadraoService;
use App\Support\Erp\Dre\DreReceitasGerenciaisService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Schema;

/**
 * D2.2–D2.8 — DRE Gerencial V1 (receitas, CMV histórico, despesas, tributos informativos).
 * Impostos destacados não deduzem receita. Resultado operacional é gerencial, não definitivo.
 */
class DreGerencialPage extends Page
{
    protected static ?string $slug = 'dre-gerencial';

    protected static ?string $routePath = 'dre-gerencial';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.dre-gerencial';

    public string $periodoDe = '';

    public string $periodoAte = '';

    public bool $gerado = false;

    /** @var array<string, mixed> */
    public array $resultado = [];

    public string $erro = '';

    public bool $showClassificar = false;

    /** @var list<array{id: int, codigo: int|string, descricao: string, dc: string, classificacao: string}> */
    public array $planosClassificar = [];

    public bool $showPreviaPadrao = false;

    /** @var list<array<string, mixed>> */
    public array $previaPadrao = [];

    /** @var array{aplicar: int, preservados: int, ignorados: int, total_ativos: int} */
    public array $previaPadraoResumo = [
        'aplicar' => 0,
        'preservados' => 0,
        'ignorados' => 0,
        'total_ativos' => 0,
    ];

    public bool $showParametros = false;

    public bool $paramExibirTributos = false;

    public bool $paramCalcularEstimativaSimples = false;

    public string $paramAliquotaSimplesPct = '';

    public bool $paramPermiteEstimativaSimples = false;

    public string $paramRegimeLabel = '';

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('dre_gerencial.access');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        ErpScreen::set('DRE Gerencial');

        $hoje = ErpTimezone::toLocal();
        if ($this->periodoDe === '') {
            $this->periodoDe = $hoje->copy()->startOfMonth()->toDateString();
        }
        if ($this->periodoAte === '') {
            $this->periodoAte = $hoje->toDateString();
        }

        // Zero cálculo no mount — só defaults de período.
        $this->gerado = false;
        $this->resultado = [];
        $this->erro = '';
        $this->showClassificar = false;
        $this->planosClassificar = [];
        $this->showPreviaPadrao = false;
        $this->previaPadrao = [];
        $this->previaPadraoResumo = [
            'aplicar' => 0,
            'preservados' => 0,
            'ignorados' => 0,
            'total_ativos' => 0,
        ];
        $this->showParametros = false;
        $this->paramExibirTributos = false;
        $this->paramCalcularEstimativaSimples = false;
        $this->paramAliquotaSimplesPct = '';
        $this->paramPermiteEstimativaSimples = false;
        $this->paramRegimeLabel = '';
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-rh-treinamentos-page',
            'erp-dre-gerencial-page',
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

    public function gerar(): void
    {
        if (! static::canAccess()) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            $this->erro = 'Selecione a empresa ativa.';
            $this->gerado = false;
            $this->resultado = [];

            return;
        }

        $de = $this->normalizarDataIso($this->periodoDe);
        $ate = $this->normalizarDataIso($this->periodoAte);
        if ($de === null || $ate === null || $de > $ate) {
            $this->erro = 'Informe um período válido (de ≤ até).';
            $this->gerado = false;
            $this->resultado = [];

            return;
        }

        $this->periodoDe = $de;
        $this->periodoAte = $ate;

        $this->erro = '';
        try {
            $t0 = microtime(true);
            $receitas = app(DreReceitasGerenciaisService::class)->calcular($empresaId, $de, $ate);
            $despesas = app(DreDespesasGerenciaisService::class)->calcular($empresaId, $de, $ate);
            $demonstrativo = app(DreDemonstrativoGerencialService::class)->montar($receitas, $despesas);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $this->resultado = array_merge($receitas, [
                'despesas' => $despesas,
                'demonstrativo' => $demonstrativo,
                'tempo_geracao_ms' => $ms,
            ]);
            $this->gerado = true;
        } catch (\Throwable $e) {
            report($e);
            $this->erro = 'Não foi possível gerar a DRE. Tente novamente ou ajuste o período.';
            $this->gerado = false;
            $this->resultado = [];
        }
    }

    public function abrirClassificar(): void
    {
        if (! static::canAccess()) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            Notification::make()->title('Selecione a empresa ativa.')->warning()->send();

            return;
        }

        if (! Schema::hasTable('planos_contas')) {
            Notification::make()->title('Plano de contas indisponível.')->warning()->send();

            return;
        }

        $mapa = app(DreDespesasGerenciaisService::class)->mapaClassificacao($empresaId);
        $this->planosClassificar = PlanoConta::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao', 'dc'])
            ->map(static function (PlanoConta $p) use ($mapa): array {
                $id = (int) $p->id;

                return [
                    'id' => $id,
                    'codigo' => $p->codigo,
                    'descricao' => (string) $p->descricao,
                    'dc' => (string) ($p->dc ?? ''),
                    'classificacao' => $mapa[$id] ?? DrePlanoClassificacaoCatalog::NAO_CLASSIFICADO,
                ];
            })
            ->values()
            ->all();

        $this->showClassificar = true;
    }

    public function fecharClassificar(): void
    {
        $this->showClassificar = false;
        $this->planosClassificar = [];
        $this->showPreviaPadrao = false;
        $this->previaPadrao = [];
        $this->previaPadraoResumo = [
            'aplicar' => 0,
            'preservados' => 0,
            'ignorados' => 0,
            'total_ativos' => 0,
        ];
    }

    public function abrirPreviaPadrao(): void
    {
        if (! static::canAccess()) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            Notification::make()->title('Selecione a empresa ativa.')->warning()->send();

            return;
        }

        $detalhe = app(DrePlanoClassificacaoPadraoService::class)->preverDetalhado($empresaId);
        $this->previaPadrao = $detalhe['itens'];
        $this->previaPadraoResumo = $detalhe['resumo'];
        // Sempre abre a prévia (z-index acima do modal de classificação).
        // Com zero elegíveis, a própria janela explica o motivo — não some em silêncio.
        $this->showPreviaPadrao = true;
    }

    public function cancelarPreviaPadrao(): void
    {
        $this->showPreviaPadrao = false;
        $this->previaPadrao = [];
        $this->previaPadraoResumo = [
            'aplicar' => 0,
            'preservados' => 0,
            'ignorados' => 0,
            'total_ativos' => 0,
        ];
    }

    public function confirmarAplicarPadrao(): void
    {
        if (! static::canAccess()) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        if ($this->previaPadrao === []) {
            $this->cancelarPreviaPadrao();

            return;
        }

        $res = app(DrePlanoClassificacaoPadraoService::class)->aplicar($empresaId);
        $this->showPreviaPadrao = false;
        $this->previaPadrao = [];
        $this->previaPadraoResumo = [
            'aplicar' => 0,
            'preservados' => 0,
            'ignorados' => 0,
            'total_ativos' => 0,
        ];

        Notification::make()
            ->title('Padrão aplicado.')
            ->body($res['aplicados'].' plano(s) classificado(s). Classificações existentes preservadas.')
            ->success()
            ->send();

        // Recarrega a grade do modal.
        $this->abrirClassificar();

        if ($this->gerado) {
            $this->gerar();
        }
    }

    public function abrirParametros(): void
    {
        if (! static::canAccess()) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            Notification::make()->title('Selecione a empresa ativa.')->warning()->send();

            return;
        }

        $p = app(DreEmpresaParametrosService::class)->obter($empresaId);
        $this->paramExibirTributos = (bool) $p['exibir_tributos'];
        $this->paramCalcularEstimativaSimples = (bool) $p['calcular_estimativa_simples'];
        $this->paramPermiteEstimativaSimples = (bool) $p['permite_estimativa_simples'];
        $aliq = $p['aliquota_simples_pct'];
        $this->paramAliquotaSimplesPct = $aliq === null || $aliq === ''
            ? ''
            : rtrim(rtrim(number_format((float) $aliq, 4, ',', ''), '0'), ',');
        $this->paramRegimeLabel = 'CRT '.(int) $p['crt'].' ('.$p['regime'].')';
        $this->showParametros = true;
    }

    public function fecharParametros(): void
    {
        $this->showParametros = false;
    }

    public function salvarParametros(): void
    {
        if (! static::canAccess()) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        app(DreEmpresaParametrosService::class)->salvar(
            $empresaId,
            (bool) $this->paramExibirTributos,
            (bool) $this->paramCalcularEstimativaSimples,
            trim($this->paramAliquotaSimplesPct) === '' ? null : $this->paramAliquotaSimplesPct,
        );

        $this->showParametros = false;
        Notification::make()->title('Parâmetros da DRE salvos.')->success()->send();

        if ($this->gerado) {
            $this->gerar();
        }
    }

    public function salvarClassificacaoPlano(int $planoId, string $classificacao): void
    {
        if (! static::canAccess()) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0 || $planoId <= 0) {
            return;
        }

        app(DreDespesasGerenciaisService::class)->salvarClassificacao($empresaId, $planoId, $classificacao);

        foreach ($this->planosClassificar as $i => $row) {
            if ((int) ($row['id'] ?? 0) === $planoId) {
                $this->planosClassificar[$i]['classificacao'] = DrePlanoClassificacaoCatalog::normalize($classificacao);
                break;
            }
        }

        // Recarrega DRE se já gerada (agregação sob demanda, sem mount).
        if ($this->gerado) {
            $this->gerar();
        }
    }

    /**
     * @return array<string, string>
     */
    public function opcoesClassificacao(): array
    {
        return DrePlanoClassificacaoCatalog::labels();
    }

    /** Aceita Y-m-d (wire) ou d/m/Y (digitação); serviço sempre recebe ISO. */
    private function normalizarDataIso(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    public function onEscape(): void
    {
        if ($this->showPreviaPadrao) {
            $this->cancelarPreviaPadrao();

            return;
        }

        if ($this->showParametros) {
            $this->fecharParametros();

            return;
        }

        if ($this->showClassificar) {
            $this->fecharClassificar();

            return;
        }

        $this->closeScreen();
    }

    public function closeScreen(): void
    {
        $this->redirect(filament()->getUrl());
    }

    public function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }
}
