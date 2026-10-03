<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpScreen;
use App\Support\Erp\License\LicencaPortalParcelasService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class ParcelasPortalPage extends Page
{
    protected static ?string $slug = 'parcelas-portal';

    protected static ?string $title = 'Parcelas do Portal';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.parcelas-portal';

    public string $q = '';

    public string $status = '';

    public string $vencimentoDe = '';

    public string $vencimentoAte = '';

    public int $page = 1;

    public string $resultado = 'loading';

    public string $mensagem = '';

    /** @var list<array{cliente: string, cnpj: string, documento: string, vencimento: string, valor: string, situacao: string, situacao_tom: string, pagamento: string}> */
    public array $rows = [];

    public int $total = 0;

    public int $perPage = 50;

    public static function canAccess(): bool
    {
        return Auth::check() && LicencaPortalParcelasService::habilitada();
    }

    public function mount(LicencaPortalParcelasService $parcelas): void
    {
        abort_unless(static::canAccess(), 403);

        ErpScreen::set('Parcelas do Portal');
        $this->aplicar($parcelas->listar($this->filtros()));
    }

    public function filtrar(LicencaPortalParcelasService $parcelas): void
    {
        $this->page = 1;
        $this->consultar($parcelas);
    }

    public function limparFiltros(LicencaPortalParcelasService $parcelas): void
    {
        $this->q = '';
        $this->status = '';
        $this->vencimentoDe = '';
        $this->vencimentoAte = '';
        $this->page = 1;
        $this->consultar($parcelas);
    }

    public function irParaPagina(int $pagina, LicencaPortalParcelasService $parcelas): void
    {
        $this->page = max(1, $pagina);
        $this->consultar($parcelas);
    }

    public function consultar(LicencaPortalParcelasService $parcelas): void
    {
        if (! $this->periodoValido()) {
            $this->resultado = 'filtro';
            $this->mensagem = 'Informe o período de vencimento com a data inicial até a data final.';

            return;
        }

        $this->aplicar($parcelas->listar($this->filtros()));
    }

    public function ultimaPagina(): int
    {
        if ($this->perPage < 1) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->perPage));
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
            'erp-parcelas-portal-page',
        ];
    }

    /**
     * @param  array{ok: bool, resultado: string, message: string, rows: list<array{cliente: string, cnpj: string, documento: string, vencimento: string, valor: string, situacao: string, situacao_tom: string, pagamento: string}>, page: int, per_page: int, total: int}  $resposta
     */
    private function aplicar(array $resposta): void
    {
        $this->resultado = (string) $resposta['resultado'];
        $this->mensagem = (string) $resposta['message'];
        $this->rows = $resposta['rows'];
        $this->page = (int) $resposta['page'];
        $this->perPage = (int) $resposta['per_page'];
        $this->total = (int) $resposta['total'];
    }

    /**
     * @return array{page: int, status: string, q: string, vencimento_de: string, vencimento_ate: string}
     */
    private function filtros(): array
    {
        return [
            'page' => $this->page,
            'status' => $this->status,
            'q' => $this->q,
            'vencimento_de' => $this->vencimentoDe,
            'vencimento_ate' => $this->vencimentoAte,
        ];
    }

    private function periodoValido(): bool
    {
        if ($this->vencimentoDe === '' || $this->vencimentoAte === '') {
            return true;
        }

        return $this->vencimentoDe <= $this->vencimentoAte;
    }
}
