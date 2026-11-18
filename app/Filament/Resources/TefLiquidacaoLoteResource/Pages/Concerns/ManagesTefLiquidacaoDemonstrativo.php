<?php

namespace App\Filament\Resources\TefLiquidacaoLoteResource\Pages\Concerns;

use App\Models\Terminal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Tef\TefLiquidacaoDemonstrativoService;
use App\Support\Erp\Tef\TefProviderId;
use Filament\Notifications\Notification;

trait ManagesTefLiquidacaoDemonstrativo
{
    public bool $demoOpen = false;

    public string $demoPeriodoDe = '';

    public string $demoPeriodoAte = '';

    public string $demoSituacao = 'ativos';

    public string $demoTerminalId = '';

    public int $demoPage = 1;

    /** @var list<array<string, mixed>> */
    public array $demoRows = [];

    /** @var array<string, mixed> */
    public array $demoTotais = [];

    /** @var array<string, mixed> */
    public array $demoMeta = [];

    public int $demoTotal = 0;

    public int $demoLastPage = 1;

    public int $demoPerPage = TefLiquidacaoDemonstrativoService::PER_PAGE_DEFAULT;

    public bool $demoConsultado = false;

    public ?int $demoDetalheLoteId = null;

    /** @var array<string, mixed>|null */
    public ?array $demoDetalheLote = null;

    /** @var list<array<string, mixed>> */
    public array $demoDetalheItens = [];

    /** @var list<array{id: int, nome: string}> */
    public array $demoTerminais = [];

    public function abrirDemonstrativo(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.access')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $this->demoOpen = true;
        $this->demoConsultado = false;
        $this->demoRows = [];
        $this->demoTotais = [];
        $this->demoMeta = [
            'provedor_label' => TefProviderId::label(TefProviderId::SUPERTEF),
            'aviso' => 'Somente controle — sem lançamento no Livro Caixa, CR ou DRE.',
        ];
        $this->demoPage = 1;
        $this->demoDetalheLoteId = null;
        $this->demoDetalheLote = null;
        $this->demoDetalheItens = [];
        $this->carregarDemoTerminais();

        if ($this->demoPeriodoDe === '' && $this->demoPeriodoAte === '') {
            $this->demoPeriodoAte = now()->toDateString();
            $this->demoPeriodoDe = now()->startOfMonth()->toDateString();
        }
    }

    public function closeDemonstrativo(): void
    {
        $this->demoOpen = false;
        $this->demoRows = [];
        $this->demoDetalheLoteId = null;
        $this->demoDetalheLote = null;
        $this->demoDetalheItens = [];
        $this->demoConsultado = false;
    }

    public function consultarDemonstrativo(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.access')) {
            return;
        }

        $this->demoPage = 1;
        $this->demoDetalheLoteId = null;
        $this->demoDetalheLote = null;
        $this->demoDetalheItens = [];
        $this->carregarDemoPagina();
    }

    public function demoPaginaAnterior(): void
    {
        if ($this->demoPage <= 1) {
            return;
        }
        $this->demoPage--;
        $this->carregarDemoPagina();
    }

    public function demoPaginaProxima(): void
    {
        if ($this->demoPage >= $this->demoLastPage) {
            return;
        }
        $this->demoPage++;
        $this->carregarDemoPagina();
    }

    public function abrirDemoDetalhe(int $loteId): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.access') || $loteId <= 0) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $det = app(TefLiquidacaoDemonstrativoService::class)->detalhe($empresaId, $loteId);
        $this->demoDetalheLoteId = $loteId;
        $this->demoDetalheLote = $det['lote'];
        $this->demoDetalheItens = $det['itens'];
    }

    public function fecharDemoDetalhe(): void
    {
        $this->demoDetalheLoteId = null;
        $this->demoDetalheLote = null;
        $this->demoDetalheItens = [];
    }

    private function carregarDemoPagina(): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $result = app(TefLiquidacaoDemonstrativoService::class)->consultar($empresaId, [
            'periodo_de' => $this->demoPeriodoDe !== '' ? $this->demoPeriodoDe : null,
            'periodo_ate' => $this->demoPeriodoAte !== '' ? $this->demoPeriodoAte : null,
            'terminal_id' => $this->demoTerminalId !== '' ? (int) $this->demoTerminalId : null,
            'situacao' => $this->demoSituacao,
            'page' => $this->demoPage,
            'per_page' => $this->demoPerPage,
        ]);

        $this->demoRows = $result['rows'];
        $this->demoTotais = $result['totais'];
        $this->demoMeta = $result['meta'];
        $this->demoTotal = (int) $result['total'];
        $this->demoLastPage = (int) $result['last_page'];
        $this->demoPage = (int) $result['page'];
        $this->demoConsultado = true;
    }

    private function carregarDemoTerminais(): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $this->demoTerminais = Terminal::query()
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('ativo', true)
            ->orderBy('nome')
            ->limit(200)
            ->get(['id', 'nome'])
            ->map(fn (Terminal $t): array => [
                'id' => (int) $t->id,
                'nome' => (string) $t->nome,
            ])
            ->all();
    }

    protected function formatDemoMoney(?string $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
    }
}
