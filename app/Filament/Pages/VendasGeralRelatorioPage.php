<?php

namespace App\Filament\Pages;

use App\Models\Venda;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\Tabular\Definitions\HistoricoVendasReport;
use App\Support\Erp\Reports\Tabular\ReportRegistry;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;

class VendasGeralRelatorioPage extends Page
{
    /** Tela de filtros migrada para VendasGeralFiltrosController (rota web estável). */
    protected static bool $isDiscovered = false;

    protected static ?string $slug = 'relatorio-vendas-geral-legacy';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.relatorio-vendas-geral';

    #[Url(as: 'de', except: '')]
    public string $de = '';

    #[Url(as: 'ate', except: '')]
    public string $ate = '';

    #[Url(as: 'cliente_q', except: '')]
    public string $cliente_q = '';

    #[Url(as: 'vendedor', except: 'todos')]
    public string $vendedor = 'todos';

    #[Url(as: 'cidade_q', except: '')]
    public string $cidade_q = '';

    #[Url(as: 'grupo', except: 'todos')]
    public string $grupo = 'todos';

    #[Url(as: 'caixa', except: 'todos')]
    public string $caixa = 'todos';

    #[Url(as: 'forma', except: 'todos')]
    public string $forma = 'todos';

    #[Url(as: 'usuario', except: 'todos')]
    public string $usuario = 'todos';

    #[Url(as: 'numero_q', except: '')]
    public string $numero_q = '';

    #[Url(as: 'origem', except: 'todos')]
    public string $origem = 'todos';

    #[Url(as: 'status', except: 'fechado')]
    public string $status = Venda::STATUS_FECHADO;

    #[Url(as: 'doc_tipo', except: 'todos')]
    public string $doc_tipo = 'todos';

    #[Url(as: 'exibir_produtos', except: false)]
    public bool $exibir_produtos = false;

    #[Url(as: 'mostrar_resumo', except: false)]
    public bool $mostrar_resumo = false;

    #[Url(as: 'apenas_resumo', except: false)]
    public bool $apenas_resumo = false;

    #[Url(as: 'exibir_formas', except: false)]
    public bool $exibir_formas = false;

    #[Url(as: 'exibir_grafico', except: false)]
    public bool $exibir_grafico = false;

    #[Url(as: 'exibir_pct_lucro', except: false)]
    public bool $exibir_pct_lucro = false;

    #[Url(as: 'agrupar', except: 'nenhum')]
    public string $agrupar = 'nenhum';

    public string $erroValidacao = '';

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('vendas.print');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        ErpScreen::set('Relatório de Vendas Geral');

        $hoje = ErpTimezone::toLocal();

        if ($this->de === '') {
            $this->de = $hoje->copy()->startOfMonth()->toDateString();
        }

        if ($this->ate === '') {
            $this->ate = $hoje->toDateString();
        }
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-relatorio-vendas-geral-page',
        ];
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
     * @return array<string, array<string, string>>
     */
    public function selectOptions(): array
    {
        $fields = (new HistoricoVendasReport)->filterFields();
        $out = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? '') !== 'select') {
                continue;
            }
            $out[(string) $field['key']] = $field['options'] ?? [];
        }

        return $out;
    }

    public function limparFiltros(): void
    {
        $hoje = ErpTimezone::toLocal();
        $this->de = $hoje->copy()->startOfMonth()->toDateString();
        $this->ate = $hoje->toDateString();
        $this->cliente_q = '';
        $this->vendedor = 'todos';
        $this->cidade_q = '';
        $this->grupo = 'todos';
        $this->caixa = 'todos';
        $this->forma = 'todos';
        $this->usuario = 'todos';
        $this->numero_q = '';
        $this->origem = 'todos';
        $this->status = Venda::STATUS_FECHADO;
        $this->doc_tipo = 'todos';
        $this->exibir_produtos = false;
        $this->mostrar_resumo = false;
        $this->apenas_resumo = false;
        $this->exibir_formas = false;
        $this->exibir_grafico = false;
        $this->exibir_pct_lucro = false;
        $this->agrupar = 'nenhum';
        $this->erroValidacao = '';
    }

    public function gerarRelatorio(): void
    {
        $this->erroValidacao = '';

        $validator = Validator::make([
            'de' => $this->de,
            'ate' => $this->ate,
            'agrupar' => $this->agrupar,
            'status' => $this->status,
        ], [
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after_or_equal:de'],
            'agrupar' => ['required', 'in:nenhum,cliente,cidade,vendedor,forma,dia'],
            'status' => ['required'],
        ], [
            'de.required' => 'Informe a data inicial.',
            'ate.required' => 'Informe a data final.',
            'ate.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ]);

        if ($validator->fails()) {
            $this->erroValidacao = (string) $validator->errors()->first();

            return;
        }

        $params = $this->queryParams(forGenerate: true);

        $this->redirect(
            ReportRegistry::route('historico-vendas').'?'.http_build_query($params),
            navigate: false,
        );
    }

    /**
     * @return array<string, scalar>
     */
    public function queryParams(bool $forGenerate = false): array
    {
        $params = [
            'de' => $this->de,
            'ate' => $this->ate,
            'cliente_q' => $this->cliente_q,
            'vendedor' => $this->vendedor,
            'cidade_q' => $this->cidade_q,
            'grupo' => $this->grupo,
            'caixa' => $this->caixa,
            'forma' => $this->forma,
            'usuario' => $this->usuario,
            'numero_q' => $this->numero_q,
            'origem' => $this->origem,
            'status' => $this->status,
            'doc_tipo' => $this->doc_tipo,
            'agrupar' => $this->agrupar,
            'return' => url('/admin/relatorio-vendas-geral'),
        ];

        if ($this->exibir_produtos) {
            $params['exibir_produtos'] = 1;
        }
        if ($this->mostrar_resumo) {
            $params['mostrar_resumo'] = 1;
        }
        if ($this->apenas_resumo) {
            $params['apenas_resumo'] = 1;
        }
        if ($this->exibir_formas) {
            $params['exibir_formas'] = 1;
        }
        if ($this->exibir_grafico) {
            $params['exibir_grafico'] = 1;
        }
        if ($this->exibir_pct_lucro) {
            $params['exibir_pct_lucro'] = 1;
        }
        if ($forGenerate) {
            $params['gerar'] = 1;
        }

        return array_filter(
            $params,
            static fn ($value): bool => $value !== '' && $value !== null,
        );
    }
}
