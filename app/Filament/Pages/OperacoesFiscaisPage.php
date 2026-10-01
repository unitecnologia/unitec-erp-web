<?php

namespace App\Filament\Pages;

use App\Models\Cfop;
use App\Models\OperacaoFiscal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Fiscal\FiscalOperationDefaults;
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

    /** @var array<int, string> codigo => descricao */
    public array $cfopDescricoes = [];

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

        foreach (array_keys(FiscalOperationDefaults::operacoes()) as $operacao) {
            foreach (['estadual', 'interestadual'] as $escopo) {
                $formKey = $operacao.'_'.$escopo;
                $salvo = $operacoes->cfopSalvo($operacao, $escopo);
                $efetivo = $salvo ?? FiscalOperationDefaults::defaultCfop($operacao, $escopo);
                $this->form[$formKey] = $efetivo ? (string) $efetivo : '';
            }
        }

        $this->carregarDescricoesCfop();
        $this->formatarCamposCfop();
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
     * @return array<string, array{
     *     label: string,
     *     estadual: ?int,
     *     interestadual: ?int,
     *     interestadual_aplicavel: bool
     * }>
     */
    public function operacoes(): array
    {
        return FiscalOperationDefaults::operacoes();
    }

    public function isPadraoUnitec(string $operacao, string $escopo): bool
    {
        $default = FiscalOperationDefaults::defaultCfop($operacao, $escopo);
        if ($default === null) {
            return false;
        }

        $atual = $this->extrairCodigoCfop((string) ($this->form[$operacao.'_'.$escopo] ?? ''));

        return $atual === $default;
    }

    public function abrirBuscaCfop(string $campo): void
    {
        if (! array_key_exists($campo, $this->form)) {
            return;
        }

        if ($this->campoInterestadualDesabilitado($campo)) {
            return;
        }

        // Ao focar, deixa só o código para facilitar a pesquisa.
        $codigo = $this->extrairCodigoCfop((string) ($this->form[$campo] ?? ''));
        $this->form[$campo] = $codigo ? (string) $codigo : '';

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

        if ($this->campoInterestadualDesabilitado($this->cfopLookupCampo)) {
            return;
        }

        $descricao = $this->descricaoCfop($codigo);
        $this->cfopDescricoes[$codigo] = $descricao;
        $this->form[$this->cfopLookupCampo] = $this->formatarCfop($codigo, $descricao);
        $this->fecharBuscaCfop();
    }

    public function fecharBuscaCfop(): void
    {
        $this->cfopLookupCampo = null;
        $this->cfopResultados = [];
    }

    public function reformatarCampoCfop(string $campo): void
    {
        if (! array_key_exists($campo, $this->form) || $this->campoInterestadualDesabilitado($campo)) {
            return;
        }

        if ($this->cfopLookupCampo === $campo) {
            $this->fecharBuscaCfop();
        }

        $codigo = $this->extrairCodigoCfop((string) ($this->form[$campo] ?? ''));
        if ($codigo === null) {
            $this->form[$campo] = '';

            return;
        }

        $this->form[$campo] = $this->formatarCfop($codigo, $this->descricaoCfop($codigo));
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

        foreach (FiscalOperationDefaults::operacoes() as $key => $meta) {
            foreach (['estadual', 'interestadual'] as $escopo) {
                $formKey = $key.'_'.$escopo;
                $column = FiscalOperationDefaults::column($key, $escopo);
                $label = $meta['label'].' '.($escopo === 'estadual' ? 'dentro do estado' : 'fora do estado');

                if ($escopo === 'interestadual' && ! ($meta['interestadual_aplicavel'] ?? true)) {
                    $data[$column] = null;
                    $this->form[$formKey] = '';

                    continue;
                }

                $codigo = $this->extrairCodigoCfop((string) ($this->form[$formKey] ?? ''));
                $data[$column] = $codigo;
                $this->form[$formKey] = $codigo ? (string) $codigo : '';
                $rules['form.'.$formKey] = ['nullable', 'integer', 'exists:cfops,codigo'];
                $attributes['form.'.$formKey] = $label;
            }
        }

        $this->validate($rules, [], $attributes);
        OperacaoFiscal::forEmpresa($empresaId)->update($data);

        $this->carregarDescricoesCfop();
        $this->formatarCamposCfop();
        $this->alert = 'OK: Operações fiscais gravadas.';
    }

    public function restaurarPadroes(): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        if (! $empresaId) {
            $this->alert = 'Empresa não identificada.';

            return;
        }

        $operacoes = OperacaoFiscal::forEmpresa($empresaId);
        $operacoes->restaurarPadroesUnitec();

        foreach (array_keys(FiscalOperationDefaults::operacoes()) as $operacao) {
            foreach (['estadual', 'interestadual'] as $escopo) {
                $default = FiscalOperationDefaults::defaultCfop($operacao, $escopo);
                $this->form[$operacao.'_'.$escopo] = $default ? (string) $default : '';
            }
        }

        $this->carregarDescricoesCfop();
        $this->formatarCamposCfop();
        $this->fecharBuscaCfop();
        $this->alert = 'OK: CFOPs padrão do Unitec ERP restaurados.';
    }

    private function campoInterestadualDesabilitado(string $campo): bool
    {
        if (! str_ends_with($campo, '_interestadual')) {
            return false;
        }

        $operacao = substr($campo, 0, -strlen('_interestadual'));

        return ! FiscalOperationDefaults::interestadualAplicavel($operacao);
    }

    private function carregarResultadosCfop(string $busca): void
    {
        $term = trim($busca);
        $digits = preg_replace('/\D/', '', $term) ?: '';

        // Inclui CFOPs inativos do catálogo: vários padrões oficiais (ex.: 5117, 5922)
        // podem estar marcados como inativos e ainda assim são válidos nesta tela.
        $this->cfopResultados = Cfop::query()
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

    private function carregarDescricoesCfop(): void
    {
        $codigos = [];

        foreach (array_keys(FiscalOperationDefaults::operacoes()) as $operacao) {
            foreach (['estadual', 'interestadual'] as $escopo) {
                $codigo = $this->extrairCodigoCfop((string) ($this->form[$operacao.'_'.$escopo] ?? ''));
                if ($codigo !== null) {
                    $codigos[$codigo] = $codigo;
                }

                $default = FiscalOperationDefaults::defaultCfop($operacao, $escopo);
                if ($default !== null) {
                    $codigos[$default] = $default;
                }
            }
        }

        if ($codigos === []) {
            $this->cfopDescricoes = [];

            return;
        }

        $this->cfopDescricoes = Cfop::query()
            ->whereIn('codigo', array_values($codigos))
            ->pluck('descricao', 'codigo')
            ->map(fn ($descricao): string => (string) $descricao)
            ->all();
    }

    private function formatarCamposCfop(): void
    {
        foreach (array_keys(FiscalOperationDefaults::operacoes()) as $operacao) {
            foreach (['estadual', 'interestadual'] as $escopo) {
                if ($escopo === 'interestadual' && ! FiscalOperationDefaults::interestadualAplicavel($operacao)) {
                    $this->form[$operacao.'_'.$escopo] = '';

                    continue;
                }

                $codigo = $this->extrairCodigoCfop((string) ($this->form[$operacao.'_'.$escopo] ?? ''));
                if ($codigo === null) {
                    $this->form[$operacao.'_'.$escopo] = '';

                    continue;
                }

                $this->form[$operacao.'_'.$escopo] = $this->formatarCfop(
                    $codigo,
                    $this->descricaoCfop($codigo)
                );
            }
        }
    }

    private function formatarCfop(int $codigo, string $descricao): string
    {
        $descricao = trim($descricao);

        return $descricao !== ''
            ? $codigo.' — '.$descricao
            : (string) $codigo;
    }

    private function descricaoCfop(int $codigo): string
    {
        if (isset($this->cfopDescricoes[$codigo])) {
            return (string) $this->cfopDescricoes[$codigo];
        }

        $descricao = (string) (Cfop::query()->where('codigo', $codigo)->value('descricao') ?? '');
        if ($descricao !== '') {
            $this->cfopDescricoes[$codigo] = $descricao;
        }

        return $descricao;
    }

    private function extrairCodigoCfop(string $value): ?int
    {
        $digits = preg_replace('/\D/', '', trim($value)) ?: '';
        if ($digits === '') {
            return null;
        }

        // CFOP tem 4 dígitos; se o usuário colar texto com mais dígitos, pega os 4 primeiros.
        $codigo = (int) substr($digits, 0, 4);

        return $codigo > 0 ? $codigo : null;
    }
}
