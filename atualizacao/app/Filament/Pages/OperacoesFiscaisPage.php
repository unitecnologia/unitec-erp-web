<?php

namespace App\Filament\Pages;

use App\Models\Cfop;
use App\Models\OperacaoFiscal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class OperacoesFiscaisPage extends Page
{
    protected static ?string $slug = 'operacoes-fiscais';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.operacoes-fiscais';

    /** @var array<string, string> */
    public array $form = [];

    public string $alert = '';

    public ?string $cfopLookupCampo = null;

    /** @var array<int, array{codigo: string, descricao: string}> */
    public array $cfopResultados = [];

    /** @var array<string, string> */
    private const OPERACOES = [
        'financeiro' => 'CFOP - Processamento Fiscal',
        'venda_mercadoria' => 'CFOP - Venda de mercadoria',
        'devolucao_vendas' => 'CFOP - Devolução de Vendas',
        'devolucao_compras' => 'CFOP - Devolução de Compras',
        'transferencias' => 'CFOP - Transferências',
        'outras_saidas' => 'CFOP - Outras saídas de estoque',
        'entrada_futura' => 'CFOP - Entrega Futura',
        'bonificacao' => 'CFOP - Bonificação',
        'saida_perda' => 'CFOP - Saída de estoque por perda',
    ];

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('cfops.access');
    }

    public function mount(): void
    {
        ErpScreen::set('CFOP - Operações fiscais');

        $empresaId = ErpContext::currentEmpresaId();
        if (! $empresaId) {
            return;
        }

        $operacoes = OperacaoFiscal::forEmpresa($empresaId);
        $this->form = ['mensagem' => (string) ($operacoes->mensagem ?? '')];

        foreach (array_keys(self::OPERACOES) as $operacao) {
            $this->form[$operacao.'_estadual'] = (string) ($operacoes->{'cfop_'.$operacao.'_estadual'} ?? '');
            $this->form[$operacao.'_interestadual'] = (string) ($operacoes->{'cfop_'.$operacao.'_interestadual'} ?? '');
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-os-form-page',
            'erp-operacoes-fiscais-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.pages.operacoes-fiscais'),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public function operacoes(): array
    {
        return self::OPERACOES;
    }

    public function abrirBuscaCfop(string $campo): void
    {
        if (! array_key_exists($campo, $this->form)) {
            return;
        }

        $this->cfopLookupCampo = $campo;
        $this->carregarResultadosCfop((string) ($this->form[$campo] ?? ''));
    }

    public function atualizarBuscaCfop(string $campo, string $busca): void
    {
        if ($this->cfopLookupCampo !== $campo) {
            return;
        }

        $this->carregarResultadosCfop($busca);
    }

    public function selecionarCfop(int $codigo): void
    {
        if ($this->cfopLookupCampo === null) {
            return;
        }

        $this->form[$this->cfopLookupCampo] = (string) $codigo;
        $this->fecharBuscaCfop();
    }

    public function fecharBuscaCfop(): void
    {
        $this->cfopLookupCampo = null;
        $this->cfopResultados = [];
    }

    public function salvar(): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        if (! $empresaId) {
            $this->alert = 'Empresa não identificada.';

            return;
        }

        $data = ['mensagem' => trim($this->form['mensagem'] ?? '') ?: null];
        $rules = ['form.mensagem' => ['nullable', 'string', 'max:1000']];
        $attributes = ['form.mensagem' => 'mensagem'];

        foreach (self::OPERACOES as $key => $label) {
            foreach (['estadual', 'interestadual'] as $escopo) {
                $formKey = $key.'_'.$escopo;
                $column = 'cfop_'.$formKey;
                $value = trim($this->form[$formKey] ?? '');

                $data[$column] = $value === '' ? null : (int) $value;
                $rules['form.'.$formKey] = ['nullable', 'integer', 'exists:cfops,codigo'];
                $attributes['form.'.$formKey] = $label.' '.$escopo;
            }
        }

        $this->validate($rules, [], $attributes);
        OperacaoFiscal::forEmpresa($empresaId)->update($data);
        $this->alert = 'OK: Operações fiscais gravadas.';
    }

    private function carregarResultadosCfop(string $busca): void
    {
        $term = trim($busca);
        $digits = preg_replace('/\D/', '', $term) ?: '';

        $this->cfopResultados = Cfop::query()
            ->ativos()
            ->when($term !== '', function ($query) use ($term, $digits): void {
                $query->where(function ($inner) use ($term, $digits): void {
                    $inner->where('descricao', 'like', '%'.$term.'%');

                    if ($digits !== '') {
                        $inner->orWhere('codigo', 'like', $digits.'%');
                    }
                });
            })
            ->orderBy('codigo')
            ->limit(12)
            ->get(['codigo', 'descricao'])
            ->map(fn (Cfop $cfop): array => [
                'codigo' => (string) $cfop->codigo,
                'descricao' => (string) $cfop->descricao,
            ])
            ->all();
    }
}
