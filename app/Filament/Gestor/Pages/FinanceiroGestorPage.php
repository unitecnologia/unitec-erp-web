<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\ContaPagarBaixaService;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Gestor\GestorFinanceiroService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class FinanceiroGestorPage extends Page
{
    use InteractsWithGestorShell;

    protected static ?string $slug = 'financeiro';

    protected static ?string $title = 'Financeiro';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.gestor.financeiro';

    /** @var array<string, mixed> */
    public array $fin = [];

    public ?string $detalheTipo = null;

    /** @var list<array<string, mixed>> */
    public array $detalheItens = [];

    public bool $pagamentoModalOpen = false;

    public bool $saudeDetalheOpen = false;

    /** @var 'pagar'|'receber' */
    public string $baixaModo = 'pagar';

    public ?int $pagamentoContaId = null;

    public ?int $pagamentoFormaId = null;

    public string $pagamentoResumoPessoa = '';

    public string $pagamentoResumoValor = '';

    /** @var list<array{id: int, label: string, tipo: string|null}> */
    public array $pagamentoFormas = [];

    public static function canAccess(): bool
    {
        return static::canAccessGestor();
    }

    public function mount(): void
    {
        $this->mountGestorShell();
        $this->refreshFinanceiro();
    }

    public function refreshFinanceiro(): void
    {
        $this->fin = app(GestorFinanceiroService::class)->build();
        if ($this->detalheTipo) {
            $this->detalheItens = app(GestorFinanceiroService::class)->detalheTitulos($this->detalheTipo);
        }
    }

    public function abrirDetalhe(string $tipo): void
    {
        $tipos = ['receber_hoje', 'pagar_hoje', 'receber_vencido', 'pagar_vencido', 'proximos_receber', 'inadimplencia', 'acima_limite'];

        if (! in_array($tipo, $tipos, true)) {
            return;
        }

        try {
            $this->detalheTipo = $tipo;
            $this->detalheItens = app(GestorFinanceiroService::class)->detalheTitulos($tipo);
            $this->fecharPagamentoModal();
        } catch (\Throwable $e) {
            report($e);
            $this->detalheTipo = $tipo;
            $this->detalheItens = [];

            Notification::make()
                ->title('Não foi possível carregar os títulos')
                ->body('Tente atualizar a tela e abrir de novo.')
                ->danger()
                ->send();
        }
    }

    public function fecharDetalhe(): void
    {
        $this->detalheTipo = null;
        $this->detalheItens = [];
        $this->fecharPagamentoModal();
    }

    public function abrirSaudeDetalhe(): void
    {
        $this->saudeDetalheOpen = true;
        $this->fecharPagamentoModal();
    }

    public function fecharSaudeDetalhe(): void
    {
        $this->saudeDetalheOpen = false;
    }

    public function podePagarTitulos(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return (bool) $user->is_admin
            || (bool) $user->is_supervisor
            || ErpAccess::can($user, 'contas_pagar.baixa')
            || ErpAccess::can($user, 'contas_pagar.update')
            || ErpAccess::can($user, 'contas_pagar.access');
    }

    public function podeReceberTitulos(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return (bool) $user->is_admin
            || (bool) $user->is_supervisor
            || ErpAccess::can($user, 'contas_receber.baixa')
            || ErpAccess::can($user, 'contas_receber.update')
            || ErpAccess::can($user, 'contas_receber.access');
    }

    public function abrirPagamento(int $contaId): void
    {
        $this->abrirBaixaModal($contaId, 'pagar');
    }

    public function abrirRecebimento(int $contaId): void
    {
        $this->abrirBaixaModal($contaId, 'receber');
    }

    /**
     * @param  'pagar'|'receber'  $modo
     */
    private function abrirBaixaModal(int $contaId, string $modo): void
    {
        $isReceber = $modo === 'receber';

        if ($isReceber ? ! $this->podeReceberTitulos() : ! $this->podePagarTitulos()) {
            Notification::make()
                ->title($isReceber ? 'Sem permissão para receber títulos.' : 'Sem permissão para pagar títulos.')
                ->warning()
                ->send();

            return;
        }

        $item = collect($this->detalheItens)->firstWhere('id', $contaId);
        $flag = $isReceber ? 'pode_receber' : 'pode_pagar';

        if (! $item || empty($item[$flag])) {
            Notification::make()
                ->title($isReceber ? 'Título inválido para recebimento.' : 'Título inválido para pagamento.')
                ->warning()
                ->send();

            return;
        }

        $formas = $isReceber
            ? app(ContaReceberBaixaService::class)->formasDisponiveis()
            : app(ContaPagarBaixaService::class)->formasDisponiveis();

        if ($formas === []) {
            Notification::make()
                ->title('Cadastre um meio de pagamento')
                ->body($isReceber
                    ? 'Nenhuma forma de pagamento disponível para Contas a Receber.'
                    : 'Nenhuma forma de pagamento ativa encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->baixaModo = $modo;
        $this->pagamentoContaId = $contaId;
        $this->pagamentoResumoPessoa = (string) ($item['pessoa'] ?? '—');
        $this->pagamentoResumoValor = ErpMoney::formatBr((float) ($item['valor'] ?? 0));
        $this->pagamentoFormas = $formas;
        $this->pagamentoFormaId = (int) ($formas[0]['id'] ?? 0);
        $this->pagamentoModalOpen = true;
    }

    public function fecharPagamentoModal(): void
    {
        $this->pagamentoModalOpen = false;
        $this->baixaModo = 'pagar';
        $this->pagamentoContaId = null;
        $this->pagamentoFormaId = null;
        $this->pagamentoResumoPessoa = '';
        $this->pagamentoResumoValor = '';
        $this->pagamentoFormas = [];
    }

    public function confirmarPagamento(): void
    {
        if (! $this->pagamentoModalOpen || ! $this->pagamentoContaId) {
            return;
        }

        $isReceber = $this->baixaModo === 'receber';

        if ($isReceber ? ! $this->podeReceberTitulos() : ! $this->podePagarTitulos()) {
            Notification::make()
                ->title($isReceber ? 'Sem permissão para receber títulos.' : 'Sem permissão para pagar títulos.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->pagamentoFormaId) {
            Notification::make()
                ->title('Selecione o meio de pagamento.')
                ->warning()
                ->send();

            return;
        }

        try {
            $resultado = $isReceber
                ? app(ContaReceberBaixaService::class)->baixarMuitas(
                    [(int) $this->pagamentoContaId],
                    (int) $this->pagamentoFormaId,
                )
                : app(ContaPagarBaixaService::class)->baixarUma(
                    (int) $this->pagamentoContaId,
                    (int) $this->pagamentoFormaId,
                );
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title($isReceber ? 'Não foi possível registrar o recebimento.' : 'Não foi possível registrar o pagamento.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->fecharPagamentoModal();
        $this->refreshFinanceiro();

        if ($resultado['ok'] < 1) {
            Notification::make()
                ->title($isReceber ? 'Nenhum título foi recebido.' : 'Nenhum título foi pago.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title($isReceber ? 'Recebimento registrado.' : 'Pagamento registrado.')
            ->body(($isReceber ? 'Total recebido: R$ ' : 'Total pago: R$ ').ErpMoney::formatBr((float) $resultado['total']))
            ->success()
            ->send();
    }

    public function detalheTitulo(): string
    {
        return match ($this->detalheTipo) {
            'receber_hoje' => 'Receber hoje',
            'pagar_hoje' => 'Pagar hoje',
            'receber_vencido' => 'Receber vencido',
            'pagar_vencido' => 'Pagar vencido',
            'proximos_receber' => 'Próximos a receber',
            'inadimplencia' => 'Inadimplência',
            'acima_limite' => 'Acima do limite',
            default => 'Títulos',
        };
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
