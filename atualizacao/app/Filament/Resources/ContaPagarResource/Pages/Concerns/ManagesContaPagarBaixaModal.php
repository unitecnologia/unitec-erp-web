<?php

namespace App\Filament\Resources\ContaPagarResource\Pages\Concerns;

use App\Models\ContaPagar;
use App\Models\PlanoConta;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaPagarBaixaService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesContaPagarBaixaModal
{
    public bool $baixaModalOpen = false;

    /** @var list<int> */
    public array $baixaContaIds = [];

    public ?int $baixaFormaPagamentoId = null;

    public ?int $baixaPlanoContaId = null;

    public ?int $baixaCaixaContaId = null;

    public string $baixaResumoQtd = '0';

    public string $baixaResumoTotal = '0,00';

    /** @var array<string, string> */
    public array $baixaDados = [
        'fornecedor' => '',
        'documento' => '',
        'emissao' => '',
        'vencimento' => '',
        'valor' => '0,00',
        'juros_pago' => '0,00',
        'desconto_recebido' => '0,00',
        'valor_pago_acumulado' => '0,00',
        'valor_a_pagar_titulo' => '0,00',
    ];

    public string $baixaSaldo = '0,00';

    public string $baixaPercJuros = '0,00';

    public string $baixaJuros = '0,00';

    public string $baixaSaldoComJuros = '0,00';

    public string $baixaPercDesconto = '0,00';

    public string $baixaDesconto = '0,00';

    public string $baixaValorAPagar = '0,00';

    public string $baixaValorPago = '0,00';

    public string $baixaPagoEm = '';

    /** @var list<array{id: int, label: string, tipo: string|null, caixa_conta_id?: int|null}> */
    public array $baixaFormasOptions = [];

    /** @var list<array{id: int, label: string}> */
    public array $baixaPlanosOptions = [];

    /** @var list<array{id: int, label: string}> */
    public array $baixaCaixasOptions = [];

    public function baixarConta(): void
    {
        if (! $this->highlightedRecordIdOrNotify('baixar')) {
            return;
        }

        $conta = ContaPagar::query()
            ->with('fornecedor:id,nome_razao,apelido_fantasia')
            ->whereKey((int) $this->highlightedRecordId)
            ->first();

        if (! $conta || (float) $conta->saldo <= 0) {
            Notification::make()
                ->title('Nenhuma conta com saldo para baixar.')
                ->warning()
                ->send();

            return;
        }

        $service = app(ContaPagarBaixaService::class);
        $this->baixaFormasOptions = $service->formasDisponiveis();
        $this->baixaPlanosOptions = $service->planosDisponiveis();
        $this->baixaCaixasOptions = $service->caixasDisponiveis();

        if ($this->baixaFormasOptions === []) {
            Notification::make()
                ->title('Cadastre um meio de pagamento')
                ->body('Nenhuma forma de pagamento disponível para Contas a Pagar.')
                ->warning()
                ->send();

            return;
        }

        if ($this->baixaCaixasOptions === []) {
            Notification::make()
                ->title('Cadastre uma conta caixa')
                ->body('Nenhuma conta de destino disponível para a baixa.')
                ->warning()
                ->send();

            return;
        }

        $saldo = round((float) $conta->saldo, 2);
        $fornecedor = $conta->fornecedor;
        $fornecedorNome = trim((string) (
            $fornecedor?->apelido_fantasia
            ?: $fornecedor?->nome_razao
            ?: ''
        ));

        $this->baixaContaIds = [(int) $conta->id];
        $this->baixaResumoQtd = '1';
        $this->baixaResumoTotal = ErpMoney::formatBr($saldo);
        $this->baixaFormaPagamentoId = (int) ($this->baixaFormasOptions[0]['id'] ?? 0);
        $this->baixaPlanoContaId = $this->resolvePlanoContaPadraoFornecedores();
        $this->baixaCaixaContaId = (int) (
            $this->baixaFormasOptions[0]['caixa_conta_id']
            ?? $this->baixaCaixasOptions[0]['id']
            ?? 0
        ) ?: null;

        $this->baixaDados = [
            'fornecedor' => $fornecedorNome !== '' ? mb_strtoupper($fornecedorNome, 'UTF-8') : '—',
            'documento' => mb_strtoupper(trim((string) ($conta->documento ?: '—')), 'UTF-8'),
            'emissao' => optional($conta->emissao)->format('d/m/Y') ?: '—',
            'vencimento' => optional($conta->vencimento)->format('d/m/Y') ?: '—',
            'valor' => ErpMoney::formatBr((float) $conta->valor),
            'juros_pago' => ErpMoney::formatBr((float) $conta->juros),
            'desconto_recebido' => ErpMoney::formatBr((float) $conta->desconto),
            'valor_pago_acumulado' => ErpMoney::formatBr((float) $conta->valor_pago),
            'valor_a_pagar_titulo' => ErpMoney::formatBr($saldo),
        ];

        $this->baixaSaldo = ErpMoney::formatBr($saldo);
        $this->baixaPercJuros = '0,00';
        $this->baixaJuros = '0,00';
        $this->baixaSaldoComJuros = ErpMoney::formatBr($saldo);
        $this->baixaPercDesconto = '0,00';
        $this->baixaDesconto = '0,00';
        $this->baixaValorAPagar = ErpMoney::formatBr($saldo);
        $this->baixaValorPago = ErpMoney::formatBr($saldo);
        $this->baixaPagoEm = ErpTimezone::toLocal()->toDateString();
        $this->baixaModalOpen = true;
    }

    public function updatedBaixaFormaPagamentoId(mixed $value): void
    {
        $formaId = (int) $value;
        foreach ($this->baixaFormasOptions as $forma) {
            if ((int) $forma['id'] === $formaId && filled($forma['caixa_conta_id'] ?? null)) {
                $this->baixaCaixaContaId = (int) $forma['caixa_conta_id'];

                return;
            }
        }
    }

    public function updatedBaixaPercJuros(mixed $value): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercJuros = ErpMoney::formatBr($perc);
        $this->baixaJuros = ErpMoney::formatBr(round($saldo * ($perc / 100), 2));
        $this->recalcularBaixaTotais();
    }

    public function updatedBaixaJuros(mixed $value): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $juros = ErpMoney::parseBr($value);
        $this->baixaJuros = ErpMoney::formatBr($juros);
        $this->baixaPercJuros = $saldo > 0
            ? ErpMoney::formatBr(round(($juros / $saldo) * 100, 2))
            : '0,00';
        $this->recalcularBaixaTotais();
    }

    public function updatedBaixaPercDesconto(mixed $value): void
    {
        $base = ErpMoney::parseBr($this->baixaSaldoComJuros);
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercDesconto = ErpMoney::formatBr($perc);
        $this->baixaDesconto = ErpMoney::formatBr(round($base * ($perc / 100), 2));
        $this->recalcularBaixaTotais(preserveValorPago: false);
    }

    public function updatedBaixaDesconto(mixed $value): void
    {
        $base = ErpMoney::parseBr($this->baixaSaldoComJuros);
        $desconto = ErpMoney::parseBr($value);
        $this->baixaDesconto = ErpMoney::formatBr($desconto);
        $this->baixaPercDesconto = $base > 0
            ? ErpMoney::formatBr(round(($desconto / $base) * 100, 2))
            : '0,00';
        $this->recalcularBaixaTotais(preserveValorPago: false);
    }

    public function updatedBaixaValorPago(mixed $value): void
    {
        $this->baixaValorPago = ErpMoney::formatBr(ErpMoney::parseBr($value));
    }

    protected function recalcularBaixaTotais(bool $preserveValorPago = true): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $juros = ErpMoney::parseBr($this->baixaJuros);
        $desconto = ErpMoney::parseBr($this->baixaDesconto);
        $saldoComJuros = round($saldo + $juros, 2);
        $desconto = min($desconto, $saldoComJuros);
        $valorAPagar = round(max(0, $saldoComJuros - $desconto), 2);

        $this->baixaSaldoComJuros = ErpMoney::formatBr($saldoComJuros);
        $this->baixaDesconto = ErpMoney::formatBr($desconto);
        $this->baixaValorAPagar = ErpMoney::formatBr($valorAPagar);

        if (! $preserveValorPago) {
            $this->baixaValorPago = ErpMoney::formatBr($valorAPagar);
        }
    }

    public function closeBaixaModal(): void
    {
        $this->baixaModalOpen = false;
        $this->baixaContaIds = [];
        $this->baixaFormaPagamentoId = null;
        $this->baixaPlanoContaId = null;
        $this->baixaCaixaContaId = null;
        $this->baixaResumoQtd = '0';
        $this->baixaResumoTotal = '0,00';
        $this->baixaFormasOptions = [];
        $this->baixaPlanosOptions = [];
        $this->baixaCaixasOptions = [];
        $this->baixaDados = [
            'fornecedor' => '',
            'documento' => '',
            'emissao' => '',
            'vencimento' => '',
            'valor' => '0,00',
            'juros_pago' => '0,00',
            'desconto_recebido' => '0,00',
            'valor_pago_acumulado' => '0,00',
            'valor_a_pagar_titulo' => '0,00',
        ];
        $this->baixaSaldo = '0,00';
        $this->baixaPercJuros = '0,00';
        $this->baixaJuros = '0,00';
        $this->baixaSaldoComJuros = '0,00';
        $this->baixaPercDesconto = '0,00';
        $this->baixaDesconto = '0,00';
        $this->baixaValorAPagar = '0,00';
        $this->baixaValorPago = '0,00';
        $this->baixaPagoEm = '';
    }

    protected function resolvePlanoContaPadraoFornecedores(): ?int
    {
        $porCodigo = PlanoConta::query()
            ->where('codigo', 1)
            ->where('ativo', true)
            ->value('id');

        if ($porCodigo) {
            return (int) $porCodigo;
        }

        foreach ($this->baixaPlanosOptions as $plano) {
            $label = mb_strtoupper((string) ($plano['label'] ?? ''), 'UTF-8');
            $id = (int) ($plano['id'] ?? 0);

            if ($id > 0 && str_contains($label, 'FORNECEDOR')) {
                return $id;
            }
        }

        return (int) ($this->baixaPlanosOptions[0]['id'] ?? 0) ?: null;
    }

    public function confirmarBaixaConta(): void
    {
        if (! $this->baixaModalOpen || $this->baixaContaIds === []) {
            return;
        }

        if (! $this->baixaFormaPagamentoId) {
            Notification::make()
                ->title('Selecione o meio de pagamento.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->baixaCaixaContaId) {
            Notification::make()
                ->title('Selecione a conta de destino.')
                ->warning()
                ->send();

            return;
        }

        try {
            $resultado = app(ContaPagarBaixaService::class)
                ->baixarMuitas($this->baixaContaIds, (int) $this->baixaFormaPagamentoId, [
                    'plano_conta_id' => $this->baixaPlanoContaId,
                    'caixa_conta_id' => $this->baixaCaixaContaId,
                    'perc_juros' => ErpMoney::parseBr($this->baixaPercJuros),
                    'juros' => ErpMoney::parseBr($this->baixaJuros),
                    'perc_desconto' => ErpMoney::parseBr($this->baixaPercDesconto),
                    'desconto' => ErpMoney::parseBr($this->baixaDesconto),
                    'valor_pago' => ErpMoney::parseBr($this->baixaValorPago),
                    'pago_em' => $this->baixaPagoEm,
                ]);
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Não foi possível baixar a conta.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->closeBaixaModal();
        $this->clearListSelection();
        $this->situacaoFilter = 'pagas';
        $this->resetTable();

        if ($resultado['ok'] < 1) {
            Notification::make()
                ->title('Nenhuma conta foi baixada.')
                ->warning()
                ->send();

            return;
        }

        $qtd = $resultado['ok'];
        Notification::make()
            ->title($qtd === 1 ? 'Conta baixada.' : "{$qtd} contas baixadas.")
            ->body('Total pago: R$ '.ErpMoney::formatBr((float) $resultado['total']))
            ->success()
            ->send();
    }
}
